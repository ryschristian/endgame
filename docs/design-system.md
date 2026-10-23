# Gr8r Studio design-system review

Reviewed on **2026-10-22** for Endgame. Reference: [Gr8r Studio](https://gr8r-taskmanagement.vercel.app/).

## Evidence and scope

This is a review of the reference application's public design assets and the three desktop screenshots supplied in this conversation. The public HTML identifies the application as “Gr8r Studio” and describes it as “a calm, structured project management workspace.”

Exact values below were read from the deployed [stylesheet](https://gr8r-taskmanagement.vercel.app/assets/index-BAaun9PB.css). Component structures, preference persistence, and keyboard handlers were inspected in the deployed [JavaScript bundle](https://gr8r-taskmanagement.vercel.app/assets/index-BhWzXQlV.js). Asset filenames are deployment-specific and may change.

No browser session was available during this review. Desktop appearance is supported by the supplied screenshots; mobile layouts, dark mode, animations, and keyboard behavior are documented from source declarations, without live interaction verification. A CSS declaration is not proof that a control works or is accessible in practice.

The comparison with Endgame uses `resources/css/app.css`, `resources/js/layouts/account-layout.vue`, and `resources/js/pages/projects/_components/project-card.vue` (relocated from `features/projects` on 2026-10-23). This document does not change application behavior or approve new product modules.

The later project workspace request uses the newly supplied white/indigo screenshots for Overview and ticket views. Those pages apply local indigo action/focus tokens while preserving the shared shell. See [workspace implementation](project-workspace-implementation.md) for the working views and browser verification; the original asset review below remains historical evidence.

## 1. Visual direction

The interface is a dense workspace for repeated daily use. Small typography and compact controls keep attention on project and task content. Warm gray surfaces soften the chrome; white cards and panels establish the primary content plane. Thin borders do most of the separating. Shadows distinguish floating layers rather than making every section look elevated.

Use these principles when translating the reference:

- Keep one clear primary action per page or dialog.
- Separate workspace navigation, project navigation, view controls, and content into distinct horizontal or vertical bands.
- Use neutral surfaces for structure and accent color for action, selection, and focus.
- Use small semantic colors alongside text or recognizable symbols.
- Keep headings short and metadata quiet. Avoid decorative explanatory text.
- Preserve consistent density across cards, menus, and forms.

## 2. Color tokens

### Light surfaces and text

| Reference token | Value | Intended role |
|---|---|---|
| `--bg` | `#fbfaf8` | App background |
| `--bg-side` | `#f4f3f0` | Sidebar |
| `--surface` | `#ffffff` | Cards, inputs, top bar, floating panels |
| `--surface-2` | `#f6f5f2` | Hover and secondary surfaces |
| `--surface-3` | `#eeede9` | Pressed controls and segmented-control track |
| `--sunken` | `#f7f6f3` | Board columns |
| `--border` | `#e9e7e2` | Normal component border |
| `--border-strong` | `#dad7d0` | Input and emphasized border |
| `--divider` | `#f0eeea` | Quiet internal separator |
| `--text` | `#1d1c1a` | Main content |
| `--text-2` | `#57544e` | Secondary text |
| `--text-3` | `#6b6760` | Metadata and helper text |
| `--text-4` | `#8c8880` | Lowest-emphasis icons and details |
| `--overlay` | `rgba(24, 22, 19, .28)` | Modal backdrop |

### Accent system

The default accent is **indigo**, not rose. The reference switches accent pairs through `data-accent` on the root element. The supplied screenshots show the rose variant.

| Accent | Light | Dark |
|---|---|---|
| Indigo (default) | `#4b5bd6` | `#8e9af3` |
| Blue | `#2f6cd4` | `#74a7f0` |
| Violet | `#7348cc` | `#ae93f0` |
| Teal | `#1a7f7a` | `#5cc4ba` |
| Rose | `#b93d68` | `#ec8dae` |
| Graphite | `#34332f` | `#d9d6cf` |

`--accent-soft` mixes the active accent at 10% with transparent; `--accent-line` uses 38%. Light-mode text on the accent is white. Dark-mode text on the accent is `#12131c`.

Endgame currently uses a fixed rose primary of `#b53964`. Keeping a single team accent follows the user's existing direction; adding a theme/accent preference screen would be a separate feature decision.

### Semantic colors

| Meaning or category | Light value |
|---|---|
| Success / green | `#25754b` |
| Warning / amber | `#8f5e0e` |
| Error / red | `#b23c30` |
| Blue | `#3570c4` |
| Violet | `#7652c2` |
| Teal | `#1e8580` |
| Rose category | `#bc4270` |
| Orange | `#c0612b` |
| Neutral category | `#8a867e` |

The rose category token and the selectable rose accent have different values and purposes. Keep action color separate from category color.

The reference's workflow colors are Backlog `#a29e96`, To Do `#7c7870`, In Progress `#c88a14`, Review `#7652c2`, and Done `#2e8456`. These describe the reference, not Endgame's implemented workflow catalog. Endgame must receive future workflow labels and choices from Laravel.

### Dark theme

Dark styling supports both `prefers-color-scheme: dark` and an explicit `data-theme="dark"`; an explicit light selection overrides the system preference.

| Token | Dark value |
|---|---|
| Background / sidebar | `#1a1a19` / `#151514` |
| Surface / surface 2 / surface 3 | `#1d1d1c` / `#252524` / `#2d2d2b` |
| Sunken | `#181817` |
| Border / strong border | `#2c2b29` / `#3a3936` |
| Text / secondary / metadata | `#eceae5` / `#b5b2ab` / `#9a968f` |
| Overlay | `rgba(0, 0, 0, .55)` |

Dark semantic colors become lighter: green `#5cba86`, amber `#d8a443`, red `#e57d70`, blue `#72a5ea`, violet `#a98deb`, teal `#58c0b6`, rose `#e68aab`, and orange `#e3925f`.

## 3. Typography

The reference HTML requests **Geist** at weights 400, 500, and 600, and **Geist Mono** at 400 through Google Fonts. Some CSS declares weight 450, so its actual rendering depends on the delivered font faces. Endgame already self-hosts the Geist Sans variable font; preserve that delivery approach.

| Token | Size | Typical role |
|---|---|---|
| `--fs-2xs` | 11px | Keys, counts, section captions |
| `--fs-xs` | 12px | Metadata, small buttons, badges |
| `--fs-sm` | 12.5px | Labels, tabs, compact controls |
| `--fs` | 13.5px | Default body and navigation |
| `--fs-md` | 14.5px | Dialog titles |
| `--fs-lg` | 16px | Intermediate emphasis |
| `--fs-xl` | 19px | Larger headings; mobile page title |
| `--fs-2xl` | 23px | Desktop page title |

Body line-height is 1.45, with font smoothing and `ss01`/`cv11` font features. Page titles use weight 600, line-height 1.2, and tracking `-.022em`. Project descriptions use 12.5px text at 1.45 line-height with a two-line clamp. Task titles use 13.5px at 1.4 line-height. Monospace text is used for task keys and keyboard hints; numerical metadata supports tabular figures.

Use the smallest sizes for secondary metadata only. Evaluate readability at browser zoom and on ordinary laptop displays before copying the density everywhere.

## 4. Spacing, geometry, and elevation

| Foundation | Reference scale |
|---|---|
| Spacing | 4, 8, 12, 16, 20, 24, 32, 40, 48px |
| Corner radii | 4px, 6px, 8px, 10px, pill 999px |
| Control heights | 22px, 26px, 30px, 36px |
| Sidebar row | 30px; compact preference 26px |
| Data row | 40px; compact preference 34px |
| Task-card padding | 10px vertical / 12px horizontal; compact 8px / 10px |
| Desktop gutter | 24px |
| Smaller-screen gutter | 16px |

The scale is mostly based on 4px increments, with optical exceptions such as 6px gaps, 14px card padding, and 30px controls. Do not force every measurement onto one scale when it damages the reference's density.

```css
/* Reference light-mode elevation values */
--shadow-sm: 0 1px 2px rgba(28, 25, 20, .05);
--shadow-card: 0 1px 2px rgba(28, 25, 20, .04),
               0 0 0 1px var(--border);
--shadow-pop: 0 12px 32px -8px rgba(28, 25, 20, .16),
              0 2px 6px -2px rgba(28, 25, 20, .06),
              0 0 0 1px var(--border);
--shadow-drag: 0 16px 36px -10px rgba(28, 25, 20, .28),
               0 0 0 1px var(--border-strong);
```

## 5. Application shell and page composition

```text
┌──────────────────────┬─────────────────────────────────────────────────┐
│ Workspace identity   │ Breadcrumb       Search / New / account         │
│                      ├─────────────────────────────────────────────────┤
│ Personal navigation  │ Page or project title             Primary action│
│ Workspace navigation │ Project view tabs, when relevant                │
│                      ├─────────────────────────────────────────────────┤
│ Project shortcuts    │ Search / filters / sort             View switch │
│ Nested project views ├─────────────────────────────────────────────────┤
│                      │                                                 │
│ Team shortcuts       │ Project grid, list, table, or board              │
│                      │                                                 │
│ Help / settings      │                                                 │
│ Current account      │                                                 │
└──────────────────────┴─────────────────────────────────────────────────┘
```

- Expanded desktop sidebar: **252px**. Collapsed rail: **52px**.
- Top bar: **46px**, white surface, bottom border, 24px left gutter and 12px right padding.
- Standard content page: maximum width **1160px**, centered, padding 28px / 24px / 72px.
- Wide pages remove the width cap. Flush project pages use a full-height flex layout so boards can scroll independently.
- Sidebar sections use 11px uppercase captions, weight 600, tracking `.06em`.
- Selected sidebar items use a white surface, light outline/shadow, and weight 500. Hover uses the third surface tone.
- View tabs are 36px high. The selected tab has a 2px text-colored underline rather than a filled accent background.

The screenshot's long sidebar is an information architecture reference. Endgame should show only authorized, implemented destinations.

## 6. Component specifications

### Buttons and icon actions

Default buttons are 30px high, with 11px horizontal padding, a 6px gap, 6px radius, and 12.5px medium text. Small buttons are 26px high with 8px padding and 12px text; large buttons are 36px high with 14px padding.

| Variant | Treatment |
|---|---|
| Primary | Active accent fill, contrasting text, subtle inset bottom shadow |
| Secondary | White surface, strong border, very small shadow |
| Ghost | Secondary text; neutral hover/pressed surface |
| Danger | Semantic red fill |
| Danger ghost | Red text with soft red hover background |
| Icon action | Usually a 30px square; 26px and 22px variants |

Disabled buttons have `.45` opacity and no pointer events. Loading buttons reserve their dimensions, hide the label visually, and show a 14px spinner. Accessible busy-state announcements still require verification.

### Inputs, selects, and choice controls

Inputs are 30px high, with 10px horizontal padding, a strong border, white surface, and 6px radius. Large inputs are 36px. Textareas start at 72px high, use 8px/10px padding, and resize vertically.

- Focus: accent border and a 3px soft accent halo.
- Error: red border, soft red halo, and a nearby error message.
- Disabled: secondary surface and muted text.
- Search: leading icon, 30px left inset; compact version is 200px wide and 26px high.
- Checkbox: 15px square, 4px radius; completed-task variant is circular.
- Toggle: 30×18px track with a 14px thumb.
- Custom select: native select remains in the markup; a button and listbox provide the visible menu. Source includes arrow-key navigation and Escape handling.

### Project cards

The grid uses `repeat(auto-fill, minmax(290px, 1fr))` with a 12px gap. Cards have a 10px radius, 14px padding, and 12px internal gaps.

1. Header: 28px tinted project icon, project name, optional favorite indicator, status badge.
2. Description: secondary text clamped to two lines.
3. Progress: 4px track and percentage, when real task progress exists.
4. Footer: subtle top divider, task totals, dates/activity, and overlapping member avatars.

Hover strengthens the border and introduces a small shadow; standard-motion CSS also raises the project card 2px. A project icon uses a 14% category tint; the larger project-header icon is 36px with a 9px radius.

Endgame P02 has no ticket progress. Its cards correctly show project key, membership count, update date, and member previews instead. Keep that distinction until ticket data exists.

### Task cards and board columns (reference only)

Board columns are **284px** wide, separated by **12px**, with a sunken background, 10px radius, and quiet border. Their task lists have a 6px gap and independent vertical scrolling. A collapsed column is 44px wide.

Task cards use an 8px radius, outlined card shadow, 8px content gaps, label chips, a title, optional subtask progress, and compact metadata. Done titles are struck through. Dragging lowers source opacity to `.35`; the ghost receives a stronger shadow and 1.5-degree rotation. Drop targets gain a soft accent tint and border.

These are visual reference patterns for a later approved board phase, not instructions to implement ticket transitions now.

### Badges, labels, avatars, and progress

| Component | Specification |
|---|---|
| Badge | 20px high, 5px radius, 12px medium text, optional 6px round dot |
| Label | 20px high, white background, neutral border, 6px square color marker |
| Filter chip | 26px high, soft accent background and accent-tinted border, removable |
| Avatar | 22px default; 20, 26, 36, and 64px variants; circular initials |
| Avatar stack | 5px overlap; surface-colored outline; overflow count |
| Progress | 4px high; 4px radius; accent fill, green/amber/red variants |
| Segmented control | Neutral track with 2px padding; selected white item and tiny shadow |

### Dialogs, drawers, menus, and feedback

| Pattern | Desktop geometry and behavior declared in source |
|---|---|
| Standard dialog | Up to 560px wide; 420px small / 720px large; 12px radius; positioned 10vh from top; maximum 80vh high |
| Dialog header/body/footer | Header 14px/16px padding; body 14px/16px and 14px field gaps; footer 12px/16px with top border |
| Detail drawer | Right-aligned, up to 620px wide, full height; 46px header |
| Expanded detail view | Up to 980px wide, 24px top/bottom inset, centered, 12px radius |
| Popover | Usually 200–320px wide, 4px padding, 10px radius; up to 420px or 70vh tall |
| Menu item | At least 30px high, 8px horizontal padding, 9px icon/text gap |
| Toast | Bottom-right, 20px inset; minimum 40px high, maximum 400px wide; dark neutral surface; optional action |

The supplied New team dialog illustrates the pattern: short title and supporting sentence, name input, ownership description, icon/color choices, scrollable member picker, and right-aligned Cancel/Create actions. This is a reference component anatomy; Endgame has not implemented team management.

## 7. Responsive rules

These are exact stylesheet breakpoints, not measured device behavior.

| Width | Declared adaptation |
|---|---|
| At or below 1100px | Two-panel grids become one column; top search hides its text and keyboard hint |
| At or below 900px | Sidebar becomes a 280px off-canvas panel with scrim; gutter becomes 16px; settings navigation becomes horizontal; page bottom spacing grows |
| At or below 640px | Top bar becomes 50px; bottom navigation appears; page title becomes 19px; heading actions wrap; list rows become bordered cards; drawers fill the viewport |
| At or below 640px | Dialog becomes a bottom sheet with 14px top corners and maximum 92vh height; toasts move above bottom navigation; top-bar controls have at least 40px dimensions |

Horizontal board scrolling is intentional. Ordinary page layouts should avoid horizontal overflow. Bottom navigation and overlays account for safe-area insets. A navigation drawer must release its focus trap and overlay when the layout returns to desktop; Endgame already has a regression check for that boundary.

## 8. Motion and interaction

The main transition duration is **140ms** with easing `cubic-bezier(.23, 1, .32, 1)`. Drawers use `cubic-bezier(.32, .72, 0, 1)`; spring feedback uses `cubic-bezier(.34, 1.56, .64, 1)`.

- Modal entry: 260ms. Right drawer entry: 340ms. Popover entry: 160ms.
- Toast entry: 200ms; exit: 160ms.
- Button press scales to `.97` when enabled.
- Tooltip entry has a 350ms delay and 120ms fade.
- Reduced motion is supported through both system preference and `data-motion="reduce"`, replacing several movements with short fades.
- Theme, accent, density, sidebar preference, and application state are persisted in browser local storage by the reference bundle.

The bundle contains command-menu keyboard handling, Escape dismissal, select arrow navigation, and modal Tab cycling. Those handlers were inspected, not executed during this review.

## 9. Accessibility review

Positive evidence includes a skip link, visible focus styling, labeled icon actions in component markup, dialog roles and titles, radio-group semantics for icon/color selection, polite toast announcements, loading skeleton `aria-busy`, and keyboard handlers.

Items to validate before adopting the patterns:

- **Small targets:** 22–30px desktop icon controls and 15px checkboxes need adequate surrounding hit areas. The declared 40px mobile top-bar target does not cover every control in the app.
- **Low-emphasis contrast:** evaluate `--text-4`, placeholders, and disabled states against their actual backgrounds; no numerical contrast audit was performed here.
- **Hover-only actions:** task-card actions use `display:none` until hover, while project overflow controls use opacity. Ensure all actions are discoverable and reachable by keyboard and touch.
- **Custom controls:** verify accessible names, selection announcements, arrow keys, focus return, and Escape behavior with a screen reader. ARIA attributes alone do not establish compliance.
- **Color meanings:** retain text/symbols alongside status colors and progress colors.
- **Motion:** verify that reduced-motion behavior covers drag feedback, button transforms, and preference changes as well as overlay entry.
- **Dense text:** test 200% zoom, text scaling, and long project names without hiding essential actions.

## 10. Comparison with current Endgame

| Area | Reference | Current Endgame | Assessment |
|---|---|---|---|
| Font | Geist + Geist Mono | Self-hosted variable Geist Sans; generic monospace utility | Main face aligned; dedicated mono face is not established |
| Primary accent | Configurable; screenshot rose `#b93d68` | Fixed rose `#b53964` | Very close hue; intentionally simpler configuration |
| Main background | Warm `#fbfaf8` | White | Endgame has slightly stronger white-space contrast |
| Sidebar | `#f4f3f0`, 252px | `#f6f5f2`, 224px | Similar treatment; Endgame is narrower |
| Top bar | 46px | 52px | Endgame is roomier |
| Main text | `#1d1c1a` | `#292825` | Endgame is slightly lighter |
| Border | `#e9e7e2` | `#e8e5e0` | Close warm neutral family |
| Card radius / padding | 10px / 14px | 10px / 16px | Shape aligned; Endgame is roomier |
| Grid | Auto-fill with 290px minimum | Explicit responsive column counts | Similar desktop result; different resizing behavior |
| Card content | Task progress and activity | Actual P02 project/member metadata | Appropriate to implemented scope |
| Mobile navigation | 900px off-canvas; 640px bottom nav | Drawer below 1024px; no bottom nav | Endgame uses its own current navigation pattern |
| Dark theme | Warm neutrals and themed accents | Existing generic shadcn dark tokens | Dark palette is not yet matched to the reference |

## 11. Guidance for future Endgame changes

### Foundation alignment applied

Following this review, the user requested the root styles, shared layout, and forms first. Endgame now uses the reference light background `#fbfaf8`, foreground `#1d1c1a`, rose primary `#b93d68`, border `#e9e7e2`, input border `#dad7d0`, and sidebar `#f4f3f0`. Shared CSS variables set the desktop sidebar to 252px and top bar to 46px. The base text is 13.5px with 1.45 line-height.

Shared fields, buttons, account/authentication forms, and project/member/role forms now use compact desktop sizing and consistent focus/error treatment. Text inputs remain larger with 16px text on small screens. The New project dialog uses a mobile bottom sheet and a 560px desktop panel. The comparison table above records the state before this foundation pass; project-card geometry, grid behavior, dark palette, and navigation breakpoints remain as recorded there.

Verification for this pass: 11 Vitest tests and 9 Chromium journeys passed; refreshed desktop, project-dialog, and mobile screenshots were inspected. No backend behavior or dependencies changed.

Use this document as a measured reference alongside `FRONTEND.md`. Keep the existing Vue/Inertia and shadcn-vue components; map reference roles onto semantic tokens rather than copying the reference application's custom DOM framework.

For an explicitly requested visual alignment pass, prioritize shell dimensions, the body/metadata type scale, neutral surface roles, control heights, and consistent card spacing. Address keyboard/touch access at the same time as hover styling. Dark-mode alignment should receive its own visual verification rather than inheriting the reference's values without checking real pages.

Keep Endgame-specific decisions intact: Geist Sans is self-hosted, the team timezone is fixed to `Asia/Manila`, Laravel owns module choices and permissions, and only implemented/authorized destinations appear in navigation. Reference demo states, team management, ticket progress, boards, and theme preferences are not authorization to add those features.
