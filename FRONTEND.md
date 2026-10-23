# Frontend Coding Conventions — Endgame

## 1. Stack and approach
- Vue 3, TypeScript, Composition API, `<script setup lang="ts">`, Inertia.js 3.
- Tailwind CSS 4 and existing shadcn-vue components.
- Favor predictable, accessible UI and code a junior developer can navigate.
- Use Inertia for Laravel-backed pages and forms; do not add Vue Router, a separate API layer, or a data-fetching library without a concrete need.

## 2. Simple, feature-first structure
Keep folder nesting shallow. The example below is a convention, not a reason to move working code unnecessarily:

```text
resources/js/
├── pages/
│   ├── dashboard/
│   │   ├── index.vue
│   │   └── _components/
│   │       ├── stats-cards.vue
│   │       └── recent-activity.vue
│   │
│   ├── tickets/
│   │   ├── index.vue
│   │   ├── create.vue
│   │   ├── edit.vue
│   │   ├── show.vue
│   │   │
│   │   └── _components/
│   │       ├── ticket-table.vue
│   │       ├── ticket-form.vue
│   │       ├── ticket-filters.vue
│   │       └── ticket-status-badge.vue
│   │
│   └── settings/
│       ├── profile.vue
│       └── _components/
│           └── profile-form.vue
│
├── features/                 # Optional, when needed
│   └── tickets/
│       ├── use-ticket-filters.ts
│       └── types.ts
│
├── components/
│   ├── ui/                   # shadcn-vue
│   ├── page-header.vue
│   ├── confirm-dialog.vue
│   └── empty-state.vue
│
├── layouts/
├── composables/
├── lib/
└── types/
```

- Keep feature-specific components, composables, and types close to the feature.
- Place only genuinely reused UI or logic in shared folders.
- Avoid `components/`, `hooks/`, `utils/`, `services/` subtrees inside every feature without a need.
- Prefer **kebab-case filenames** throughout the app (`ticket-form.vue`, `use-ticket-filters.ts`). Keep imported Vue component tags consistently PascalCase.
- Follow the actual configured Inertia page resolver and existing conventions.


## SHADCN
- install and utilize shadcn's different components  (calendars, combobox for dropdowns, dropdown menu, range calendar), maximize drawers and dialogs for forms to avoid too much pages.

## 3. Components and composables
- Pages receive typed Inertia props and compose the page; they should not contain large tables, complex forms, and multiple dialogs all in one file.
- Extract components when they have a meaningful responsibility or become difficult to read. Do not split every small template section.
- Prefer `ref`/`reactive` for local state and `computed` for derived values.
- Use `watch`/`watchEffect` for genuine synchronization or side effects, not values that can simply be computed.
- Use composables for reusable reactive behavior, not for every click handler.
- Define meaningful props and events with TypeScript. Avoid `any` unless justified.
- Keep event/data flow obvious; avoid unnecessary global state stores and event buses.

## 4. Inertia pages and forms
- Let Laravel own routes, validation, authorization, and database data.
- Use Inertia page props and `<Form>` or `useForm` for ordinary submissions.
- Show field errors, submit/loading states, and clear success/error feedback.
- Use Inertia partial reloads or state preservation when helpful, not as default complexity.
- Avoid duplicating server props in local state unless editing requires a local copy.
- Do not invent a parallel REST/API architecture for routine Inertia pages.

## 5. Module-provided fields, labels, and options
- Treat each **Laravel business module as the source of truth** for business-specific form and filter data: dropdown choices, selection lists, ticket labels/tags, configurable input fields and their required/read-only states, labels, initial values, and allowed transitions.
- Receive this data through **typed Inertia page props** and pass it to the relevant feature forms, selects, and filters. Frontend components primarily **display the supplied data, manage user input, and submit selected values**.
- Do not hardcode work types, priorities, project members, ticket labels, workflow statuses, or other database/configuration-driven options inside Vue components. Do not reproduce backend business rules or maintain duplicate option catalogs in TypeScript.
- Use the provided option label for display and stable `value`/ID for submission. Display backend validation errors next to the corresponding field.
- Keep **presentation-only details** in Vue: component layout, placeholders, helper text, formatting, and truly static UI labels. Configurable business labels and choices come from the module.
- If an option list depends on another input or is too large to preload, request only the needed authorized data through the project's established Inertia pattern or a focused endpoint. Avoid unnecessary API calls and state duplication.
- Build explicit, readable forms for each feature. **Do not invent a generic dynamic form renderer** just because the backend supplies field metadata.
- A hidden or disabled choice is only UI behavior: Laravel must revalidate submitted choices and authorization.

## 6. Permissions and workflow UI
- Backend permission checks are mandatory. Frontend checks are presentation only.
- Hide an action the user does not have permission to perform.
- Disable an action if permission exists but the action is unavailable in the current state; explain why when useful.
- Kanban drag/drop requests a backend-validated ticket transition. Show a meaningful rejection/conflict state and restore the card if denied.
- Do not derive business authority or completion from a client-only status/column label.

## 7. UI quality and accessibility
- Reuse shadcn-vue primitives and existing design tokens before writing custom controls.
- Use clear labels, predictable actions, helpful empty states, and consistent loading/error states.
- Forms need visible labels, validation messages, and keyboard-usable controls.
- Use semantic HTML and sensible focus behavior for dialogs, menus, and tables.
- Prefer one obvious primary action per view; avoid icon-only controls without accessible names.
- Use Tailwind utilities consistently; avoid custom CSS for patterns already covered by primitives.
IMPORTANT NOTE:
Avoid unnecessary secondary labels and descriptions. Let the layout do the explaining.
Please use the latest shadcn design

## 8. Tables, search, and performance
- For potentially large ticket lists, use server pagination/filtering/sorting.
- Keep table columns and filter definitions near their feature.
- Avoid needless network requests, repeated heavy calculations, and duplicate reactive state.
- Prefer straightforward solutions before introducing a table engine, global store, or caching library.
- Ensure UI reflects server-confirmed state after saves and transitions.
- install tanstack table for tables make it simple and maintanable codes as much as possible when coding tables

## 9. Testing and changes
- Use Vitest for meaningful component and composable behavior; Playwright for essential user journeys as appropriate.
- Test forms, validation feedback, permissions UX, and workflow transitions where they matter.
- Run configured format, type-check, and relevant test scripts; report unavailable checks.
- Keep changes scoped; inspect related pages, components, props, and tests first.
- Follow `AGENTS.md` and relevant `docs/SDD.md`/phase specifications when they exist.


**Rule: simple components, clear data flow, and few levels of folders.**



