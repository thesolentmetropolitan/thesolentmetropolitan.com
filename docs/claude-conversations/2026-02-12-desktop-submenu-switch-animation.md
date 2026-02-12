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

### Remaining work
- Testing the switch animation across all menu items with different submenu heights
- White gap fix: when switching to a shorter submenu, a momentary white gap appears
  between the menu bar and the submenu during the upward slide animation
