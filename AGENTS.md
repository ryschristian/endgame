# Endgame — Agent Instructions

## Purpose
Endgame is a Laravel + Vue/Inertia application for project management, tickets, configurable workflows, Kanban, and team collaboration. GitHub/GitLab integrations and coding-agent automation are later phases.

**Primary goal:** Write code that a junior developer can read, debug, and maintain. Choose clear, conventional solutions over clever abstractions.

## Tech stack
- Backend: Laravel 13, PHP, MySQL.
- Frontend: Vue 3, TypeScript, Composition API, Inertia.js 3, Tailwind CSS 4, shadcn-vue.
- Testing: Pest (backend), Vitest (frontend), Playwright (critical user journeys).
- Architecture: one Laravel + Inertia modular monolith. No separate API or microservices unless a future approved requirement needs them.

## What to read
- For product behavior or schema changes, read `docs/SDD.md` and the relevant approved phase/process specification, **if present**.
- For PHP, controllers, models, authorization, or database changes, read `BACKEND.md`.
- For Vue, Inertia, and styling changes, read `FRONTEND.md`.
- Inspect the actual nearby code and tests before editing. For tiny fixes, read only what is relevant; do not scan every document.
- If a referenced file does not exist, report that fact rather than inventing its contents.
- Research and future-phase documentation are not permission to implement future features.

## Work on one task at a time
1. Identify the requested behavior, its owning module, and affected users/data.
2. Inspect nearby implementations and follow existing conventions.
3. Make the smallest complete change that satisfies the requirement.
4. Add or update focused tests; run available relevant checks.
5. Summarize what changed, what was tested, and any unresolved issue.

Do not start unrelated features, redesign modules, add packages, or refactor unrelated files. A new pattern or dependency requires a specific need; explain why before introducing it.

## Code simplicity
- Use descriptive names and short, focused functions. Prefer early returns to deep nesting.
- Avoid duplicate business rules. Extract logic when it becomes difficult to understand or is meaningfully reused.
- Do not introduce repositories, DTOs, service interfaces, generic base classes, event buses, or composable wrappers by default.
- Keep files and folder structures shallow. Do not create directories for hypothetical features.
- Comments should explain a non-obvious *why*, not repeat what the code does.
- Follow the repository's existing formatting, naming, and package-manager conventions.

## Backend boundaries
- Simple CRUD: `route → controller → model` is fine.
- Use a focused service when a business operation coordinates multiple models, significant rules, transactions, or integrations.
- Validate inputs and enforce authorization in Laravel, never only in Vue.
- Use policies/gates and project-scoped queries; reject cross-project relationships.
- Ticket status changes must use an authorized transition operation, not an arbitrary ticket update.
- For multi-row writes, use a transaction and preserve activity/audit history. Handle concurrent updates where needed.
- No destructive database or migration-reset commands without explicit approval and target verification.
- Each Laravel module supplies its form/filter choices, configurable fields and labels, defaults, and allowed selections as small, authorized Inertia props. Validate submitted choices again on the server.

## Frontend boundaries
- Use Vue 3 `<script setup lang="ts">`, typed props/emits, and Inertia page props/forms.
- One Inertia page per route; keep feature-specific tables, forms, and dialogs close to their feature.
- Prefer `computed` for derived state; use watchers for genuine side effects.
- Prefer Inertia `<Form>` / `useForm` for server submissions; do not introduce redundant API fetching or global state by default.
- Frontend permission checks control visibility/UX only; Laravel is the authority.
- Hide controls for unavailable permissions; disable them when permitted but temporarily inapplicable.
- Reuse existing shadcn-vue primitives before creating custom components.
- Vue forms and filters render typed, module-provided options/labels instead of hardcoding database-driven choices. Keep layout and static presentation text in Vue; avoid a generic form-rendering engine.

## Core workflow integrity
- A project owns its tickets, permissions, workflows, and other scoped records.
- Persist workflow state changes and their actor/history; do not infer state from Kanban column position.
- Respect configured transitions, resolution/reopen rules, and future approval gates.
- A board action may request a transition but must not bypass backend validation.
- Keep human actions and future agent runs distinguishable in the audit trail.
- Do not implement agent execution, GitHub/GitLab automation, or AI provider integrations until their phase is explicitly approved.

## Tests and completion
- Backend: cover authorization, validation, project isolation, relationships, and ticket transition edge cases.
- Frontend: test meaningful interactive behavior and forms; add Playwright tests for critical end-to-end flows when appropriate.
- Use factories for readable, realistic test data. Keep seed data required for the app separate from optional demos.
- Run affected tests, formatting, and type checks using scripts already present; do not invent commands.
- Never claim tests passed unless you ran them. State any checks that could not run.

## Definition of done
- Requirement works with appropriate permissions and validation.
- Existing behavior remains intact; errors and edge cases are handled.
- Code is readable without needing an elaborate architecture explanation.
- Relevant tests pass, or failures/limitations are reported.
- The change is scoped and does not implement unapproved future features.
