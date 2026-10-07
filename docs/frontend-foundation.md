# Frontend Foundation — Design System and Layouts

## Visual direction

PROXIWORK uses a restrained three-color brand palette:

- **Primary — Teal `#0F766E`**: trust, proximity, action, and service.
- **Secondary — Deep Navy `#0F172A`**: navigation, headings, and high-contrast structure.
- **Accent — Amber `#F59E0B`**: attention, highlights, and secondary calls to action.

Neutral white, slate, and semantic status colors are infrastructure colors rather than brand colors.

## Typography

Poppins is the product font for headings, navigation, forms, dashboards, and body copy. The font is loaded through the Laravel Vite fonts integration instead of repeating external font imports in individual views.

## Iconography

Font Awesome provides consistent semantic icons. Icons are used together with visible labels in navigation and actions where the meaning should not depend on the icon alone.

## Motion

Motion is intentionally subtle:

- navigation hover feedback;
- page entrance;
- mobile navigation reveal;
- button elevation;
- focus states.

`prefers-reduced-motion: reduce` disables non-essential animation and transitions.

## Layout hierarchy

The layout hierarchy is:

`layouts.app` → `layouts.public` / `layouts.auth` / `layouts.dashboard`

Then dashboard-specific layouts extend `layouts.dashboard`:

- `layouts.client`;
- `layouts.professional`;
- `layouts.admin`.

This keeps global HTML, metadata, assets, accessibility primitives, and dashboard shell behavior centralized without forcing all pages into one monolithic template.

## CSS organization

Global foundation CSS is split by responsibility:

- `variables.css`: design tokens;
- `reset.css`: browser normalization;
- `typography.css`: type scale and text utilities;
- `layouts.css`: structural shells and responsive behavior;
- `components.css`: only foundation primitives at this stage.

Interface-specific CSS will be added only when the corresponding interface is implemented.

## JavaScript organization

`resources/js/common/app.js` contains only cross-interface behavior:

- mobile navigation;
- sidebar open/close;
- active navigation state;
- password visibility control.

Interface-specific JavaScript will remain isolated from this file.

## Security and accessibility

Layouts provide:

- CSRF metadata;
- semantic landmarks;
- skip-to-content link;
- keyboard-visible focus states;
- ARIA labels for icon-only controls;
- `aria-current` for active navigation;
- responsive mobile navigation.

Critical business authorization remains server-side and will never be delegated to frontend JavaScript.
