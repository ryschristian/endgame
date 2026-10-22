# Backend Coding Conventions — Endgame

## 1. Principles
Use Laravel conventions and the simplest maintainable design. Keep behavior close to where it belongs.

- Simple CRUD: **Route → Controller → Model → Database**.
- Complex business operation: **Route → Controller → focused Service → Model → Database**.
- A service is justified by multi-model workflows, complex transactions, significant reusable business rules, or external integration—not by CRUD alone.
- Avoid repository/DTO/action/interface layers unless they solve an actual, identified problem.
- Prefer Laravel built-ins over custom infrastructure.

## 2. Routes, controllers, and validation
- Use conventional named routes and resource controllers where they fit.
- Controllers authenticate/authorize, validate input, coordinate work, and return Inertia pages or redirects.
- Simple one-off input rules may use controller validation. Use a Form Request when rules or authorization are complex, reused, or make the controller difficult to read.
- Send only required, authorized data to Inertia page props; paginate large tables.
- Keep controller methods short enough to follow without jumping through unnecessary layers.
- Never trust client-supplied project ownership, protected status/resolution fields, or permission grants.

## 3. Module-owned form and selection data
- Each business module owns the **data contract** its forms and filters need: available select options, available input fields and required/read-only flags when configurable, field labels, defaults, allowed actions, and validation rules.
- Prepare these values on the Laravel side and pass them as **small, clearly named Inertia page props** (for example, `workTypes`, `priorities`, `assignees`, `labels`, `fieldLabels`, `fieldStates`, `defaults`). Keep this preparation near the module's controller or focused query/helper; do not build a generic form-schema engine.
- Dropdown options should use a consistent, simple shape such as `{ value, label }` where useful. Provide stable IDs/keys for submitted values and human-readable labels for display.
- Query only options the current user is allowed to select, restricted to the active project and record state. Do not send full models or unrelated columns merely to populate a select.
- When modules have configurable fields or labels, the backend supplies their metadata; do not duplicate those rules or option lists in Vue. Static UI copy that is not business-configurable may stay in the frontend.
- For large or dependent selects, use a small authorized search endpoint or an Inertia partial reload **only when needed**; do not preload thousands of options.
- **The submitted option must still be validated on the server** against current membership, project, status, and allowed choices. Displayed options are not authorization or a substitute for validation.
- Keep data contracts explicit and module-specific. Do not introduce a generic metadata framework, repositories, or services solely to pass dropdown data.

## 4. Models and queries
- Use Eloquent for relationships, casts, scopes, and small model-specific behavior.
- Keep multi-step business workflows out of model observers/accessors.
- Use eager loading to avoid N+1 queries, but avoid loading unused relationships.
- Keep filtering and sorting allowlisted and scoped before pagination/counts.
- Use validated attributes for mass assignment; explicitly protect sensitive columns.
- Prefer expressive Query Builder/Eloquent code; use raw SQL only when it adds real value.

## 5. Authorization and project scope
- Enforce authentication, active account state, permissions, and active project membership on the server.
- Use Laravel policies for model actions and gates for non-model capabilities where appropriate.
- Treat role names as labels; use stable permission keys to grant capabilities.
- System Admin authority must be explicit and audited; never rely on a magic role string scattered across controllers.
- Restrict query visibility and relationship assignments to the correct project. Do not leak other projects through search, counts, route model binding, or exports.
- Preserve the last usable System Admin / project manager according to the approved specification.

## 6. Tickets and workflow rules
- Ticket creation, key allocation, status transitions, assignment, resolution, and reopening follow approved business processes.
- A generic ticket edit must not directly bypass transition rules or modify protected lifecycle fields.
- Validate the actor, ticket workflow version, allowed transition, required inputs, and permission at the time of the operation.
- Keep status, priority, and resolution separate; record important before/after changes and actor identity.
- Use transactions for ticket numbering, multi-row state changes, and history writes that must be atomic.
- Use a lock or optimistic version check for concurrent updates that could lose data or violate workflow rules.
- A Kanban move calls the same authorized transition operation as other UI actions.

## 7. Database and migrations
- Use MySQL InnoDB/utf8mb4, correct foreign keys, unique constraints, indexes, and appropriate column types.
- Preserve same-project integrity in database constraints where practical and in server-side validation.
- Prefer additive forward migrations once a migration has been shared, deployed, or applied to a non-disposable database.
- You may revise an original create-table migration only when it has not become part of any database history that needs preservation; explicitly check this first.
- **Never** run `migrate:fresh`, truncate, reset, or destructive commands without explicit authorization and verifying the target connection.
- Update models, factories, seeders, and relevant tests when the schema changes.


Rules:
- Keep migration history clean and understandable.
- Prefer updating the original `create_*` migration for schema changes when the database is disposable.
- Avoid unnecessary `update_*`, `alter_*`, or `revise_*` migrations during this stage.
- Merge obsolete development-only patch migrations into the original table creation migration when safe.
- Update affected models, factories, and seeders.
- Maintain valid foreign-key creation order.
- Preserve appropriate constraints and indexes.

## 8. Factories and seeders
- Factories describe realistic test cases and valid relationships.
- Seeders should be repeatable where practical and separate required system data (e.g. permission keys) from optional local sample data.
- Do not seed random records just to satisfy a count.
- Protect real users and established credentials from seeder overwrites.

## 9. Jobs and integrations
- Use Laravel jobs for work that is slow, retryable, or external to the request lifecycle when needed.
- Dispatch only after the transaction commits when the job needs newly written data.
- Make retryable operations idempotent where duplicate side effects matter.
- Keep credentials out of the repository, browser props, events, and application logs.
- GitHub/GitLab and coding-agent integrations belong to later approved phases.

## 10. Naming, style, and structure
- Use descriptive Laravel/PHP naming, focused methods, early returns, and framework conventions.
- Prefer one clear service for a meaningful workflow to many tiny services/actions.
- Avoid excessive folders, premature interfaces, magic helpers, and needless comments.
- Format consistently with the project's PHP formatter (Laravel Pint when configured).
- Do not change unrelated behavior or files.

## 11. Testing and safety
- Use Pest and focused feature tests for requests, policies, project boundaries, and workflow behavior.
- Test success, unauthorized access, invalid input, concurrency-sensitive paths, and important failure cases.
- Before changes, inspect relevant routes, controllers, models, migrations, policies, services, and tests—not the entire repo.
- Follow `AGENTS.md` and `docs/SDD.md` when they exist; resolve specification conflicts before changing contracts.
- Report the changed behavior, tests run, and any remaining risks.

**Rule: readable and correct beats abstract and clever.**
