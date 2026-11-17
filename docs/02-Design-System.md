# CampusFind — Design System

**Version:** 1.0 · **Date:** 2026-11-11

Defines the visual design, UI components and design rules for CampusFind.
Single source of truth: [assets/css/style.css](file:///C:/xampp/htdocs/CampusFind/assets/css/style.css)
(`.sc-*` tokens and components). Built on Bootstrap 5.3.3 + Bootstrap Icons
1.11.3 + Inter.

Derived from the "soko campus" Figma reference; the shipped brand is
**CampusFind**: Forest `#173D2C`, Lime `#B9E769`, Mint `#EAF3E7`, Ivory
`#F8F9F5`, Inter throughout, KSh pricing. **Light-first**, with a polished
dark theme — never an all-dark-green product.

---

## 1. Design tokens

All tokens are CSS custom properties on `:root`, redefined under
`[data-bs-theme="dark"]`. Never hardcode hex values in components — reference
tokens only.

### 1.1 Brand

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `--sc-forest` | `#173D2C` | `#B9E769` | Primary brand color: buttons, links, headings, focus rings |
| `--sc-forest-dark` | `#0F2B1F` | `#CFF28A` | Hover / pressed states |
| `--sc-forest-600` | `#23503B` | `#CFF28A` | Secondary forest surfaces |
| `--sc-lime` | `#B9E769` | `#B9E769` | Accents, selection, highlights, active nav |
| `--sc-lime-strong` | `#A6DA4D` | — | Accent hover |
| `--sc-lime-ink` | `#22380C` | — | Text placed on lime |
| `--sc-mint` | `#EAF3E7` | `#1B2A21` | Tinted panels, code chips |

### 1.2 Surfaces and text

| Token | Light | Dark |
| --- | --- | --- |
| `--sc-bg` (app background) | `#F8F9F5` | `#0D1510` |
| `--sc-surface` (cards) | `#FFFFFF` | `#141F18` |
| `--sc-surface-2` (raised) | `#FCFDFA` | `#17241C` |
| `--sc-text` | `#1B2620` | `#E9EFEA` |
| `--sc-heading` | `#173D2C` | `#F1F6F2` |
| `--sc-muted` | `#4F5C54` | `#9AA89F` |
| `--sc-border` | `#DFE6DB` | `#26352B` |
| `--sc-border-strong` | `#CBD6C8` | `#33453A` |
| `--sc-tint` | `#EAF3E7` | `#1B2A21` |
| `--sc-lime-soft` | `#F3F9E7` | `#232D18` |
| `--sc-sand` | `#F6F1E6` | `#2B2519` |
| `--sc-sage` | `#E9F1EE` | `#172622` |
| `--sc-price` | `#173D2C` | `#D9F5A9` |

### 1.3 State colors

| Token | Light | Dark | Meaning |
| --- | --- | --- | --- |
| `--sc-btn-bg` / `--sc-btn-fg` | `#173D2C` / `#FFFFFF` | `#B9E769` / `#173D2C` | Primary button (inverts in dark) |
| `--sc-red` / `--sc-red-bg` | `#B3261E` / `#FCE9E7` | `#F2A29B` / `#3A1B19` | Errors, destructive |
| `--sc-amber` / `--sc-amber-bg` | `#7A5200` / `#FCF1D6` | `#F0C35A` / `#342B13` | Warnings, pending states |
| `--sc-ok-fg` / `--sc-ok-bg` | `#24603D` / `#EAF3E7` | `#CDEE96` / `#22351F` | Success, verified |

### 1.4 Shape, elevation, navigation

| Token | Value |
| --- | --- |
| `--sc-radius-sm` | `10px` (inputs, chips) |
| `--sc-radius` | `16px` (cards, panels) |
| `--sc-radius-lg` | `24px` (hero, hero cards) |
| `--sc-shadow-sm` | subtle lift for inputs |
| `--sc-shadow` | card hover / dropdown |
| `--sc-shadow-lg` | hero imagery, modals |
| `--sc-ring` | focus ring color |
| `--sc-nav-bg` | `rgba(255,255,255,.92)` light / `rgba(15,24,18,.92)` dark (blurred navbar) |
| `--sc-nav-text`, `--sc-nav-border` | navbar text/border |

### 1.5 Bootstrap bridge

Bootstrap variables are mapped to the tokens (`--bs-body-bg`,
`--bs-body-color`, `--bs-border-color`, `--bs-link-color`, emphasis colors) so
Bootstrap components inherit the palette automatically. Do not override
Bootstrap's palette per-component.

---

## 2. Typography

- **Font:** Inter (system fallback: system-ui, Segoe UI). Declared once via
  `--bs-body-font-family`.
- **Body:** 1rem / 1.55.
- **Headings (h1–h5):** `--sc-heading`, weight 650, line-height 1.22,
  letter-spacing `-0.015em`.
- **Prices:** `--sc-price`, bold.
- **Meta text:** `--sc-muted`, smaller sizes.
- **Interface copy rule:** short and natural. No decorative labels, no
  duplicate metadata, no marketing phrasing in UI text.

---

## 3. Theming rules

- Light is default. Dark is opt-in via the navbar toggle, persisted in the
  `cf_theme` cookie and applied as `data-bs-theme="dark"` on `<html>`.
- Every component reads tokens; adding a component that works in one theme
  only is a bug.
- Never use raw white-on-forest large areas (the "all-dark-green" look) — the
  forest/lime pairing stays light-first with mint and ivory breathing room.

---

## 4. Core components

Rules for the components defined in `style.css`. New UI reuses these; new
components must follow the same token/shape rules.

### 4.1 Surfaces
- **`.sc-card`** — the standard surface: white, `--sc-radius`, `--sc-shadow`.
  Used for listings, panels, forms.
- **`.panel`** — admin/settings panels with a `.panel-head` (title + status
  chip) and `.panel-body`.
- **`.sc-note` / `.sc-error` / `.sc-success` / `.sc-warn`** — inline alert
  banners on tinted backgrounds. **Flash alerts:** success/info auto-dismiss
  after ~4 s; errors stay until closed.

### 4.2 Buttons
- Primary: `--sc-btn-bg` background, `--sc-btn-fg` text, radius-sm. In dark
  mode it inverts (lime bg / forest text) — this is intentional.
- Outline variants for secondary actions; danger uses the red pair.
- Icon+text buttons keep icons from Bootstrap Icons; text is never redundant
  with the icon.

### 4.3 Status chips (`.st`)
`.st-ok` (verified/approved) · `.st-warn` (pending) · `.st-bad`
(rejected/suspended) · `.st-muted` (not started/simulated). Chips pair with
panels and listing states everywhere.

### 4.4 Badges (trust)
`badge_chips()` renders the public trust badges (phone, email, student,
trusted seller). Only genuinely earned badges render; simulated phone
verification shows a muted "Simulated" state, never the public badge.

### 4.5 Listing card
- Image area (fallback art for missing images), category chip, title, price in
  `--sc-price`, seller + badge row, meta line (condition/time).
- 3-column grid on desktop, collapsing per breakpoints below.

### 4.6 Filter toolbar (`.market-filter`)
Compact horizontal bar: keyword input with search icon, category/type/sort
selects, min/max price, Apply (funnel icon) + reset. States:
- **Desktop:** single 62px row.
- **≤ 991 px:** fields flex-wrap.
- **≤ 575 px:** 2-column grid; keyword spans full width; price shares the
  sort row; inputs shrink (smaller padding/font). Height ~212px.
The legacy `.filter-panel` (tall stacked card) remains for admin reports —
new marketplace UI must use `.market-filter`.

### 4.7 Messenger
Two-pane layout: conversation list + thread. Bubbles distinguish self/other;
composer is a 2-column grid (input + send) — the send button occupies the
second grid column. Read receipts, unread counts, attachment previews.
Responsive: list/thread swap with a back affordance on mobile.

### 4.8 Navigation
Blurred sticky navbar (`--sc-nav-bg`), active state in lime, theme toggle,
user menu. **Entry points:** Marketplace, My listings (also highlights
create/edit), Messages, Admin (role-gated). The "Post a listing" CTA lives on
**My listings only** — not in the navbar, not on the marketplace page.

---

## 5. Layout and responsiveness

Container-based layout; section rhythm with consistent vertical spacing.
Breakpoints (max-width):

| Breakpoint | Target |
| --- | --- |
| `1199.98px` | Keyword input full width in filter bar; grid adjustments |
| `991.98px` | Tablet: nav collapse, filter fields flex, 2-col card grids |
| `767.98px` | Stacked forms, messenger panes switch |
| `575.98px` | Mobile: filter 2-col grid, full-width CTAs, 1-col cards |

Verification: every visual change is checked at 1440 / 820 / 390 widths with
headless Chrome screenshots; unrelated pages are re-checked for regressions.

---

## 6. Imagery rules

- Listing images: real uploaded photos; checkerboard/corrupt images detected
  and replaced with category fallback art (`includes/fallback_art.php`).
- Hero/illustration imagery stays light, airy, campus-life oriented.
- No watermarks, no stock-photo gloss in listing areas.

---

## 7. Rules checklist for new UI

1. Token-first: no hex literals outside the token blocks.
2. Both themes before shipping.
3. Reuse `.sc-card`/`.panel`/chips/buttons before inventing components.
4. Short, natural copy (see Typography).
5. Alerts: success/info auto-dismiss ~4 s, errors persist.
6. Check 390 px and 820 px; confirm no page regressed.
