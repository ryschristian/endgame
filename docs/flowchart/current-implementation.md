# Current implementation process flow

**Last updated:** 2026-10-22  
**Implemented scope:** P01 accounts and authentication; P02 projects, members, roles, permissions, and invitations.

The diagrams below reflect the implemented server rules. Vue/Inertia screens can hide unavailable controls, but Laravel policies and transactional service checks decide every protected operation.

## 1. End-to-end access path

```mermaid
flowchart TD
    Start([Start]) --> Bootstrap{"Has the first\nSystem Admin been created?"}

    Bootstrap -- No --> BootstrapCommand["Run app:bootstrap-admin\nwith hidden password prompt"]
    BootstrapCommand --> FirstAdmin["Create verified, active\nSystem Admin"]:::success
    Bootstrap -- Yes --> Identity{"Does the person already\nhave an active account?"}

    FirstAdmin --> Identity
    Identity -- No --> AccountInvite["System Admin sends\nP01 account invitation"]
    AccountInvite --> AccountAccept["Recipient opens 48-hour\nsingle-use link and sets password"]
    AccountAccept --> NewAccount["Create verified, active\nordinary account"]:::success
    Identity -- Yes --> Login["Sign in"]
    NewAccount --> Login

    Login --> Active{"Account active and\nauth_version current?"}
    Active -- No --> Denied["Reject session or sign-in\nSuspended accounts have no access"]:::failure
    Active -- Yes --> ProjectList["Show only readable projects"]

    ProjectList --> ProjectAccess{"System Admin or active member\nwith project.view?"}
    ProjectAccess -- No --> ProjectDenied["Return not found / deny access"]:::failure
    ProjectAccess -- Yes --> ProjectPage["Project overview and only\nthe authorized actions"]:::success

    classDef success fill:#e8f5ee,stroke:#23825b,color:#173e2e;
    classDef failure fill:#fbeaec,stroke:#bd3656,color:#641b2b;
```

## 2. Account administration and security lifecycle (P01)

```mermaid
flowchart TD
    Admin["Active System Admin"] --> AccountAction{"Choose account action"}

    AccountAction --> Invite["Invite / resend / revoke\naccount invitation"]
    Invite --> InviteToken["Create or rotate 48-hour token\nStore token hash only"]
    InviteToken --> InviteAudit["Write invitation audit event\nin the same transaction"]
    InviteAudit --> Mail{"Safe mail delivery succeeds?"}
    Mail -- Yes --> InviteSent["Invitation is available\nfor acceptance"]:::success
    Mail -- No --> InviteSaved["Keep saved invitation\nShow explicit resend path"]:::failure

    AccountAction --> Status["Activate or suspend account"]
    Status --> LastAdmin{"Would this remove the final\nactive System Admin?"}
    LastAdmin -- Yes --> PreserveAdmin["Reject change"]:::failure
    LastAdmin -- No --> ProjectManagerCheck{"For suspension: would it remove\nthe final usable manager\nfrom any project?"}
    ProjectManagerCheck -- Yes --> PreserveManager["Reject suspension and identify\naffected projects to administrator"]:::failure
    ProjectManagerCheck -- No --> RevokeSessions["Update account state\nIncrement auth_version\nRevoke sessions, remember access\nand reset tokens when required"]
    RevokeSessions --> StatusAudit["Write account audit event\ninside the transaction"]
    StatusAudit --> StatusDone["Account state changed"]:::success

    AccountAction --> AdminRole["Grant or revoke\nSystem Admin authority"]
    AdminRole --> PasswordConfirm{"Current password confirmed\nand another active admin remains\nwhen needed?"}
    PasswordConfirm -- No --> AdminRoleDenied["Reject change"]:::failure
    PasswordConfirm -- Yes --> AdminRoleDone["Update authority and audit"]:::success

    classDef success fill:#e8f5ee,stroke:#23825b,color:#173e2e;
    classDef failure fill:#fbeaec,stroke:#bd3656,color:#641b2b;
```

## 3. Project creation, visibility, and lifecycle (P02)

```mermaid
flowchart TD
    Request["Create project request"] --> Creator{"Actor is an active\nSystem Admin?"}
    Creator -- No --> CreateDenied["Reject creation"]:::failure
    Creator -- Yes --> ProjectInput{"Valid immutable key, name,\nand active initial-manager email?"}
    ProjectInput -- No --> CreateInvalid["Return field validation errors"]:::failure
    ProjectInput -- Yes --> CreateTransaction["One transaction:\ncreate project in Asia/Manila\ncreate manager membership\ncreate Project Manager role\ngrant all six P02 permissions\nassign role and write audit"]
    CreateTransaction --> NewProject["Project ready with one\nusable project manager"]:::success

    NewProject --> ProjectOperation{"Authorized action"}
    ProjectOperation --> Settings["Update name / description"]
    ProjectOperation --> Archive["Archive project"]
    ProjectOperation --> Restore["Restore project"]

    Settings --> SettingsRules{"project.update, project active,\nrevision current?"}
    SettingsRules -- Yes --> SaveSettings["Save settings, increment revision, audit"]:::success
    SettingsRules -- No --> SettingsDenied["Reject unauthorized, archived,\nor stale write"]:::failure

    Archive --> ArchiveRules{"project.archive and\nrevision current?"}
    ArchiveRules -- Yes --> Archived["Set archived_at, increment revision, audit"]:::success
    ArchiveRules -- No --> ArchiveDenied["Reject action"]:::failure

    Archived --> ArchivedRead["Authorized readers can still view\nproject, members, and history"]
    ArchivedRead --> BusinessWrite["Settings, role, member, and\ninvitation business write"]
    BusinessWrite --> BlockArchived["Reject until restored"]:::failure

    Restore --> RestoreRules{"project.restore and\nrevision current?"}
    RestoreRules -- Yes --> Restored["Clear archived_at, increment revision, audit"]:::success
    RestoreRules -- No --> RestoreDenied["Reject action"]:::failure

    classDef success fill:#e8f5ee,stroke:#23825b,color:#173e2e;
    classDef failure fill:#fbeaec,stroke:#bd3656,color:#641b2b;
```

### Fixed timezone rule

```mermaid
flowchart LR
    Client["Project create or settings form"] --> NoPicker["No timezone picker is rendered"]
    NoPicker --> Server["Laravel uses Asia/Manila"]
    Submitted{"Client submits another\ntimezone value?"}
    Submitted -- Yes --> Reject["Reject validation"]:::failure
    Submitted -- No --> Store["Store project timezone\nas Asia/Manila"]:::success
    Existing["Existing project with older timezone"] --> Migration["Standardization migration"] --> Audit["Store previous value in audit\nincrement revision"] --> Store

    classDef success fill:#e8f5ee,stroke:#23825b,color:#173e2e;
    classDef failure fill:#fbeaec,stroke:#bd3656,color:#641b2b;
```

Application timestamps remain stored in UTC; project-facing dates are displayed in `Asia/Manila`.

## 4. Project permissions, roles, and membership changes

```mermaid
flowchart TD
    Actor["Active authenticated actor"] --> ReadCheck{"Active System Admin?"}
    ReadCheck -- Yes --> AdminAccess["All P02 project capabilities\nsubject to archive rule"]:::success
    ReadCheck -- No --> Membership{"Active project membership?"}
    Membership -- No --> NoProjectAccess["No project access"]:::failure
    Membership -- Yes --> RoleUnion["Union permissions from all\nassigned roles in this project"]
    RoleUnion --> Capability{"Required permission present?"}
    Capability -- No --> CapabilityDenied["Hide UI control and reject\nserver request"]:::failure
    Capability -- Yes --> ProtectedAction["Allow requested protected action"]:::success

    ProtectedAction --> ChangeType{"Role or member change?"}
    ChangeType -- No --> Complete["Perform authorized operation"]:::success
    ChangeType -- Yes --> GrantCeiling{"For non-admin: every proposed\nand retained target permission\nis within actor's effective set?"}
    GrantCeiling -- No --> CeilingDenied["Reject privilege escalation"]:::failure
    GrantCeiling -- Yes --> LastManager{"Would the change leave no active\nmember with all six P02 permissions?"}
    LastManager -- Yes --> ManagerDenied["Reject final-manager removal\nor downgrade"]:::failure
    LastManager -- No --> AccessTransaction["Lock account control row, then\nproject records; recheck actor,\nrevision, permissions, and state"]
    AccessTransaction --> AccessAudit["Apply change, increment revision,\nand write audit atomically"]:::success

    classDef success fill:#e8f5ee,stroke:#23825b,color:#173e2e;
    classDef failure fill:#fbeaec,stroke:#bd3656,color:#641b2b;
```

The six implemented P02 permissions are `project.view`, `project.update`, `project.members.manage`, `project.roles.manage`, `project.archive`, and `project.restore`. Membership and a role name alone grant nothing.

## 5. Direct addition, removal, and project invitation lifecycle

```mermaid
flowchart TD
    Manager["Actor with project.members.manage\nor active System Admin"] --> Target["Enter exact email for an\nexisting active application account"]
    Target --> Eligible{"Target exists, is active, and\nproject is not archived?"}
    Eligible -- No --> TargetRejected["Show same eligibility error\nfor unknown or suspended target"]:::failure
    Eligible -- Yes --> Method{"Add now or invite?"}

    Method -- Add now --> AddRoles["Choose zero or more\nproject-owned roles"]
    AddRoles --> AddCeiling{"Roles stay within actor's\ngrant ceiling and target\nis not already active member?"}
    AddCeiling -- No --> AddRejected["Reject request"]:::failure
    AddCeiling -- Yes --> AddTransaction["Create or reinstate membership\nassign selected roles\nrevoke pending invitation\nincrement revision and audit"]
    AddTransaction --> MemberAdded["Member gains selected access"]:::success

    Method -- Invite --> InviteRoles["Choose proposed\nproject-owned roles"]
    InviteRoles --> InviteChecks{"No active membership; roles valid;\nactor may grant them?"}
    InviteChecks -- No --> ProjectInviteRejected["Reject request"]:::failure
    InviteChecks -- Yes --> ProjectInviteTransaction["Create or rotate 48-hour invitation\nstore token hash and role selections\nwrite audit"]
    ProjectInviteTransaction --> ProjectMail{"Safe mail delivery succeeds?"}
    ProjectMail -- Yes --> PendingInvite["Pending invitation"]:::success
    ProjectMail -- No --> PendingWithError["Keep invitation; manager may resend"]:::failure

    PendingInvite --> LinkOpen["Recipient opens invitation link"]
    LinkOpen --> SignedIn{"Signed in as the intended\nactive account?"}
    SignedIn -- No --> SignIn["Sign in, then return only to\nthe exact local invitation URL"]
    SignIn --> SignedIn
    SignedIn -- Yes --> AcceptChecks{"Token unexpired and unused; project\nactive; no active membership; current\nauthorizer may still grant roles?"}
    AcceptChecks -- No --> AcceptRejected["Leave unavailable invitation\nunconsumed when authorization fails"]:::failure
    AcceptChecks -- Yes --> AcceptTransaction["Lock records; create/reinstate member\nassign approved roles; mark accepted\nincrement revision and audit"]
    AcceptTransaction --> Accepted["Membership is active"]:::success

    MemberAdded --> Remove["Authorized removal"]
    Accepted --> Remove
    Remove --> RemoveChecks{"Grant ceiling and last-manager\nprotection still satisfied?"}
    RemoveChecks -- No --> RemoveRejected["Reject removal"]:::failure
    RemoveChecks -- Yes --> Removed["Set removed_at; detach roles;\nrevoke pending invitations; audit"]:::success

    classDef success fill:#e8f5ee,stroke:#23825b,color:#173e2e;
    classDef failure fill:#fbeaec,stroke:#bd3656,color:#641b2b;
```

## 6. Write integrity and audit boundary

```mermaid
sequenceDiagram
    participant Browser as Vue / Inertia form
    participant Controller as Laravel controller
    participant Service as Project or Account service
    participant Lock as Database lock order
    participant DB as Project/account tables
    participant Audit as audit_events
    participant Mail as Mail notification

    Browser->>Controller: protected write + CSRF token
    Controller->>Service: validated input and policy context
    Service->>Lock: lock account_administration first
    Service->>Lock: lock affected projects in ID order
    Service->>DB: reload actor, target, roles, revision, archive state
    Service->>Service: recheck policy, grant ceiling, and invariants
    alt any rule fails
        Service-->>Controller: validation / authorization failure
        Controller-->>Browser: preserve form and show error
    else every rule passes
        Service->>DB: apply business change
        Service->>Audit: write matching audit event
        Service->>DB: commit both changes together
        opt an invitation was issued or resent
            Service->>Mail: send only after commit
            Mail-->>Service: delivery success or safe delivery failure
        end
        Service-->>Controller: success result
        Controller-->>Browser: redirect with feedback
    end
```

No raw invitation token, token hash, password, session payload, or mail transport exception is placed in audit metadata or Inertia page props.

## 7. Implementation and operator status

```mermaid
flowchart TD
    Code["P01 and P02 source code\nand migrations are complete"]:::success
    Tests["Automated verification complete:\n118 Pest tests / 647 assertions\n11 Vitest tests / 9 browser journeys\n8 MariaDB concurrency races"]:::success
    UI["Workspace design complete:\nGeist Sans, responsive navigation,\nproject grid/list, create dialog"]:::success
    Code --> Tests --> UI

    UI --> DB{"Application database migrated\non the intended MySQL server?"}
    DB -- No --> Setup["Operator action required:\nverify target and backup\nconfigure database sessions and SMTP\nrun php artisan migrate"]:::pending
    DB -- Yes --> Bootstrap{"New installation has\na System Admin?"}
    Bootstrap -- No --> BootstrapAdmin["Run php artisan app:bootstrap-admin"]:::pending
    Bootstrap -- Yes --> Ready["Ready for P01/P02 team use"]:::success
    BootstrapAdmin --> Ready

    Ready --> Future["P03 ticket management requires\nits own approved design"]:::future

    classDef success fill:#e8f5ee,stroke:#23825b,color:#173e2e;
    classDef pending fill:#fff4da,stroke:#ba7b00,color:#604000;
    classDef future fill:#f5f3f0,stroke:#aaa39a,color:#655f58,stroke-dasharray: 5 4;
```

The isolated test migrations and retained MariaDB test schemas do not migrate the application database. Live SMTP delivery and execution on an actual MySQL server remain operator verification steps.
