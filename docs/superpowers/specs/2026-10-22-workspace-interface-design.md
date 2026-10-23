# Team workspace interface

Approved by the user's 2026-10-22 request and three visual references. This updates the P02 interface and supersedes its configurable-project-timezone requirement.

- Use Geist Sans, a warm neutral sidebar, white content, fine borders, compact controls, and a muted rose primary color.
- Present authorized projects in a responsive card grid with a list alternative. Use actual descriptions, membership counts/previews, archive state, and update dates; do not fabricate ticket completion or workflow data.
- The sidebar contains only implemented destinations: Projects, account administration when permitted, archived projects, individual authorized projects, and account settings/sign-out. The top bar provides working project search and project creation for authorized users.
- Keep overview/member/role navigation and current permission checks. Adapt the project form to a compact panel/dialog consistent with the reference; retain its dedicated create route.
- Use the team's single `Asia/Manila` timezone for projects and displayed team dates. Remove timezone choices from forms and page props. Laravel supplies the fixed timezone, defaults it when omitted, and rejects submitted alternatives. Keep storage/application timestamps in UTC.
- Normalize existing project timezone fields through an additive migration with before/after audit metadata and a revision increment. Do not run this migration against the application database during implementation.
- Reuse current Vue/Inertia/Tailwind/shadcn-vue/Reka dependencies. Self-host the official Geist variable WOFF2 with its license. Add no packages or future-phase features.

Implementation order: test timezone defaults/tampering and shared visibility; implement backend contract and migration; implement typography/shell/cards/forms; verify PHP/Vue/type/build/browser checks and inspect desktop/mobile screenshots.
