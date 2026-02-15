# Desktop Submenu Switch Animation - Claude Code Conversation
## 12 February 2026

### Context
Continuation of mobile menu work. Session also included mobile menu enhancements
(larger text, submenu sizing, animated reveal/hide) before moving to desktop.

### Mobile menu changes (committed to main earlier)
- Main menu items 50% larger (1.4em -> 2.1em)
- Submenu items at 80% of main (1.68em)
- Submenu items indented beneath main items (padding-left: 2em)
- Animated reveal/hide using max-height + opacity transitions (0.5s)
- Replaced display:none/block with max-height:0/Xpx approach

### Desktop submenu switch animation - the main topic

**Goal**: When switching between main menu items on desktop, animate the height
change of the submenu area. Previously, switching was instant (old submenu hidden,
new submenu shown, only text faded in/out).

**Approach explored - max-height**: First attempt used max-height + overflow:hidden
on the submenu container, transitioning from old height to new height. This didn't
produce a visible effect because the submenu's `::before` pseudo-element (the
full-width #f0f0f0 background) and the absolute positioning meant max-height
clipping wasn't visually apparent.

**Approach adopted - CSS top transition**: The submenu already has a CSS transition
on the `top` property (0.5s ease). The solution offsets the new submenu's starting
`top` position so its bottom edge aligns with the old submenu's bottom edge, then
animates `top` to the final position.

Formula: `startTop = finalTop + oldSubmenuHeight - newSubmenuHeight`

- Taller new submenu: startTop < finalTop, submenu slides DOWN
- Shorter new submenu: startTop > finalTop, submenu slides UP

**Key technical challenges discovered**:

1. **CSS `!important` override**: The desktop CSS has `.sub-menu-container.visible-2l
   { top: 90px !important; }`. Inline `top` values set without `!important` were
   silently overridden, causing the animation to have no effect. Fix: use
   `setProperty("top", value, "important")`.

2. **Closing animation regression**: After the switch animation, the inline
   `top !important` remained on the submenu, blocking the CSS `.hidden-2l
   { top: -50px !important }` from working when the user clicked to close.
   Fix: remove all inline style overrides (top, z-index, transition, clip-path)
   in the cleanup setTimeout after the animation completes (550ms).

3. **Submenu appearing on top of menu bar**: For taller submenus, the starting
   `top` is above the menu bar. The submenu content was visible above the menu
   buttons. Fix: combination of `isolation: isolate` on `#slnt-header` (creates
   stacking context), `z-index: -1` on submenu (renders behind header's white
   background), and `clip-path: inset(Npx 0 0 0)` to clip the portion above the
   menu bar, transitioning to `inset(0 0 0 0)`.

4. **JS constant vs CSS value mismatch**: `submenu_desktop_top_reveal` is "96px"
   but CSS `.visible-2l` uses `top: 90px !important`. Fix: read the actual computed
   `top` from the old submenu via `getComputedStyle()` before hiding it, and use
   that as the animation target.

**Files modified**:
- `web/themes/custom/customsolent/css/menu-mobile.css` - mobile menu sizing and animation
- `web/themes/custom/customsolent/js/customsolent.js` - mobile animation JS + desktop switch animation

### White gap fix (completed same session)
- When switching to a shorter submenu, a white gap appeared between the menu bar
  and submenu during the upward slide
- Fix: used `padding-top` approach instead of `top` offset for the shorter case
- Submenu stays at `finalTop` (no gap), `padding-top` pushes content down initially,
  then transitions to 0. The `::before` background (`height: 100%`) fills the padding
  area so #f0f0f0 connects seamlessly with the menu bar

---

### Search menu integration - later same day (12 Feb 2026)

**Goal**: Make the search form reveal/hide like other submenu items. Previously,
clicking Search toggled `display: none` via a `closed` class, and the search form
appeared below the submenu area. User wanted it positioned in the submenu area with
the same animation and interaction as regular submenus.

**Key structural challenge**: The search form (`#search-form-container`) is in a
completely separate Drupal region (`page.search` rendered in `#slnt-srch`) from the
main menu (`page.primary_menu` in `#slnt-prim-menu`). The search button
(`#search-in-menu`) in the menu has no `.sub-menu-container` child unlike other
menu items with submenus.

**Approach**: Position `#search-form-container` absolutely (like `.sub-menu-container`)
so it appears in the submenu area. Both are positioned relative to `#slnt-header`
(which has `position: relative` on desktop). Created dedicated `showSearchForm()` and
`hideSearchForm()` functions mirroring `showSubmenu`/`hideSubmenu`.

**Changes made**:

1. **Template** (`block--search-form-block.html.twig`):
   - Changed `class="closed"` to `class="hidden-2l"` to use the same class system

2. **CSS** (`search.css`):
   - Replaced `#search-form-container.closed { display: none; }` with submenu-style
     positioning: `position: absolute; width: 100%; top: -50px;` (hidden) /
     `top: 90px` (visible)
   - Added full-width `#f0f0f0` background via `::before` pseudo-element
   - Added `navigation__link--selected` background styles for search button and
     parent `li` using `:has()` selector
   - Mobile: `max-height` + `opacity` animation for `hidden-2l`

3. **JS** (`customsolent.js`):
   - `showSearchForm()`: desktop slide-down (opacity/z-index/nav-height animation),
     mobile max-height animation
   - `hideSearchForm(instant, skipHeightReset)`: mirrors hideSubmenu with guards
   - New search click handler: opens search with animation while closing any open
     submenus; closes search on re-click; toggles chevron
   - Normal submenu open/close path: added `hideSearchForm()` call so opening a
     submenu closes the search form
   - Desktop init: sets `visibility: hidden; opacity: 0` on search form
   - Mode change handler: resets search form state in both mobile and desktop
     transition paths

**Files modified**:
- `web/themes/custom/customsolent/css/search.css`
- `web/themes/custom/customsolent/js/customsolent.js`
- `web/themes/custom/customsolent/templates/block/block--search-form-block.html.twig`

### Clean slate on resize - same session

**Goal**: Previously, open submenu state was preserved when resizing between desktop
and mobile. This felt contrived - especially desktop-to-mobile where the burger menu
had to be opened to see the preserved submenu. Changed to close all submenus and
search on any resize mode change.

**Changes**:
- Simplified `menu_refreshSize()`: shared reset logic at the top closes all submenus,
  resets all chevrons, and resets the search form. Each mode branch then only handles
  mode-specific setup (burger menu, nav display, desktop styles).
- Removed "preserve open state" conditional logic (mobile scrollable style
  re-application, desktop position/visibility re-application for open submenus).
- Fixed chevron animation on mode change: temporarily disabling `transition` on all
  `.navigation__link__down-icon` elements with `!important` before removing
  `navigation__link--selected`, forcing reflow, then re-enabling transitions. This
  prevents the unwanted 0.4s animated rotation when resizing.

**Tag**: `v1.0-search-menu-integration` marks the state before resize changes.

**Tested**: Firefox, Chrome, Edge on Windows 11; Chrome and Safari on macOS.

### Mobile menu fade, inline SVG, and nav style leak fix - same day

**1. Mobile burger menu fade in/out**:
- Added opacity fade when toggling the mobile menu via the burger icon
- Uses inline `transition` set via JS (not CSS) for reliability with `display:none→block`
- Fade in: 0.8s (slower needed for appearing content to be perceptible)
- Fade out: 0.3s (faster feels natural for disappearing content)
- `transition: none` set first to establish opacity:0 state, then transition + opacity:1
  set together in a `setTimeout(50)` to guarantee browser has painted the invisible state

**2. Inline SVG magnifying glass (Safari/iOS fix)**:
- SVG `<use xlink:href="external.svg">` doesn't work in Safari/WebKit - only fragment
  identifiers (`#id`) within the same document are supported
- Replaced with inline SVG (circle + line) in both `menu--main.html.twig` (menu button)
  and `block--search-form-block.html.twig` (search form submit)
- Also fixed wrong path in search form template (`/themes/custom/slnt/` should have been
  `/themes/custom/customsolent/`)
- Uses steelblue `rgb(70,130,180)` to match original SVG colour

**3. Nav inline style leak after mobile→desktop resize**:
- **Bug**: After using mobile burger menu and resizing to desktop, the nav container
  retained inline `transition: opacity 0.8s ease` from the burger fade handler. This
  overrode the desktop CSS `.animation { transition-property: height }`, breaking the
  smooth submenu close animation (nav height snapped instead of transitioning).
- **Fix**: Changed mode change handler (going to desktop) from `$("nav").css("display","")`
  (which only cleared display) to `nav.removeAttribute('style')` (clears ALL inline
  styles including transition and opacity leftovers from the burger handler).

**Files modified**:
- `web/themes/custom/customsolent/css/menu-mobile.css`
- `web/themes/custom/customsolent/js/customsolent.js`
- `web/themes/custom/customsolent/templates/block/block--search-form-block.html.twig`
- `web/themes/custom/customsolent/templates/navigation/menu--main.html.twig`

---

### Mobile submenu max-height calculation fix - 12 Feb 2026 (evening)

**Goal**: On smaller mobile screens (e.g. iPhone SE3), the Search main menu item was
pushed off the bottom of the screen when another menu item's submenu was opened. The
submenu's `max-height` was too large for the available space.

**Approach**: Replaced the previous clone-based measurement (which used temporary DOM
elements, 85% scaling factor, and a 60px buffer) with a direct calculation:

`max-height = viewport height - branding block height - all menu li heights - bottom padding`

- Branding block: `#block-customsolent-sitebranding` (falls back to `#slnt-logo`)
- Menu li heights: all `li` children of `ul.main-menu-item-container.mobile`, measured
  with `offsetHeight` + computed margins
- Bottom padding: 20px so Search isn't flush with screen edge
- Minimum floor: 150px

**Key implementation detail**: Moved the initial collapsed state setup (`max-height: 0;
overflow: hidden`) in `showSubmenu()` to BEFORE the height calculation. This ensures the
target submenu is collapsed when measuring its parent `li` height, preventing the
naturally-expanded submenu content from inflating the measurement.

**Files modified**:
- `web/themes/custom/customsolent/js/customsolent.js`

### Desktop submenu switch: full-width background during slide-down - same evening

**Goal**: When switching to a taller submenu on desktop, the `.sub-menu-container` div
showed its `#f0f0f0` background during the slide-down animation, but the full-width
`::before` pseudo-element (100vw wide) only appeared after the animation completed.

**Root cause**: `clip-path: inset(Npx 0 0 0)` on the submenu clips to the element's
border box. The `::before` pseudo-element (width: 100vw, extending far beyond the
submenu's own width) was being clipped to the submenu width throughout the animation.
After cleanup removed `clip-path` at 550ms, the `::before` became visible.

**Fix**: Changed `clip-path` left/right inset values from `0` to `-100vw`:
- Before: `inset(Npx 0 0 0)` → `inset(0 0 0 0)`
- After: `inset(Npx -100vw 0 -100vw)` → `inset(0 -100vw 0 -100vw)`

Negative inset values expand the clipping region beyond the element's bounds, so the
100vw-wide `::before` is no longer clipped sideways. The top inset still clips the
portion above the menu bar as before. Fixes GitHub issue #86.

**Files modified**:
- `web/themes/custom/customsolent/js/customsolent.js`

### Desktop submenu reveal: fix visible jump at end of animation - same evening

**Goal**: On desktop, when revealing a submenu (no other submenus open), a small visible
jump appeared at the top of the submenu div at the very end of the slide-down animation.

**Root cause**: Mismatch between the submenu's CSS `top: 90px` and the header height of
`96px`. During animation the submenu is at `z-index: -1` (behind the header's white
background). The 6px overlap (from 90px to 96px) was hidden behind the header. When
`transitionend` fired and the handler set `z-index: 0`, the submenu moved in front of
the header, and the 6px strip suddenly appeared — the visible jump.

Three different values were out of alignment:
- CSS `.visible-2l { top: 90px !important }` — submenu position
- JS `submenu_desktop_top_reveal = "96px"` — inline top (overridden by CSS !important)
- JS `get_desktop_offset_height() = 100` — nav height calculation offset

**Fix**: Aligned all values to 96px (matching the header and menu bar height):
- `menu-desktop.css`: both `.visible-2l` rules changed from `top: 90px` to `top: 96px`
- `search.css`: `.visible-2l` changed from `top: 90px` to `top: 96px`
- `customsolent.js`: `get_desktop_offset_height()` changed from 100 to 96

The submenu now starts exactly at the header's bottom edge, so the z-index change at
transition end reveals no hidden overlap. Fixes GitHub issue #83.

**Files modified**:
- `web/themes/custom/customsolent/css/menu-desktop.css`
- `web/themes/custom/customsolent/css/search.css`
- `web/themes/custom/customsolent/js/customsolent.js`
