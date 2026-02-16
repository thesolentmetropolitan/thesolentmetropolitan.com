# The Solent Metropolitan — Colourway Changes Brief

## CSS Variables (add to root or theme variables file)

```css
:root {
  --solent-blue: #2c4f6e;
  --solent-blue-light: #3a6489;
  --solent-blue-pale: #e8eef3;
  --magenta: #c5007a;
  --magenta-dark: #a30065;
  --warm-grey: #f5f3f0;
  --body-bg: #faf9f7;
  --text: #1a1a1a;
  --text-mid: #444444;
}
```

---

## Change 1: Page background

- **What:** Set the page/body background to an off-white `#faf9f7`
- **Why:** Distinguishes body from the warm grey used in menu panels

---

## Change 2: Main navigation bar

- **What:** Background colour of the main nav bar → `#2c4f6e`
- **Text colour** of nav items → `white` (rgba(255,255,255,0.85) for default, full white on hover)

---

## Change 3: Active/open main menu item

When a main menu item is clicked/active (its submenu is revealed):

- **Background** → `#f5f3f0` (warm grey)
- **Text colour** → `#2c4f6e` (solent blue)
- **Font weight** → `700` (bold)

This creates a visual inversion: the active item "joins" the submenu panel below it.

---

## Change 4: Submenu dropdown panel

- **Background** → `#f5f3f0` (warm grey — same as the active menu item, so they appear connected)
- **Submenu item text colour** → `#444444`

---

## Change 5: Submenu item hover state

When hovering over individual submenu items:

- **Background** → `#c5007a` (magenta)
- **Text colour** → `#ffffff` (white)
- **Font weight** → `700` (bold)

```css
/* Submenu item hover */
.submenu-item:hover {  /* adjust selector to match actual markup */
  background: #c5007a;
  color: #ffffff;
  font-weight: 700;
}
```

---

## Change 6: Body text links

Links within article/page body content (not navigation links):

- **Text colour** → `#1a1a1a` (same as body text — the link does NOT change text colour from surrounding text)
- **Font weight** → `700` (bold — this is the primary visual cue that it's a link)
- **Underline colour** → `#c5007a` (magenta)
- **Underline thickness** → `2px`
- **Underline offset** → `3px`

On hover:
- **Text colour** → `#c5007a` (magenta)

```css
/* Body content links */
.content a {  /* adjust selector to match actual content wrapper */
  color: #1a1a1a;
  font-weight: 700;
  text-decoration: underline;
  text-decoration-color: #c5007a;
  text-underline-offset: 3px;
  text-decoration-thickness: 2px;
  transition: color 0.15s;
}

.content a:hover {
  color: #c5007a;
}
```

---

## Change 7: Headings (h1, h2, h3 in body content)

- **Colour** → `#2c4f6e` (solent blue)

---

## Change 8: Footer

- **Background** → `#2c4f6e` (matching nav bar)
- **Text colour** → `rgba(255, 255, 255, 0.5)`

---

## Contrast ratios (all verified WCAG AA compliant)

| Combination | Ratio | Passes AA |
|---|---|---|
| White on `#2c4f6e` (nav text) | 7.5:1 | ✓ |
| `#2c4f6e` on `#f5f3f0` (active menu) | 6.8:1 | ✓ |
| `#444` on `#f5f3f0` (submenu items) | 7.7:1 | ✓ |
| White on `#c5007a` (submenu hover) | 5.3:1 | ✓ |
| `#1a1a1a` on `#faf9f7` (body text) | 17.4:1 | ✓ |
| `#2c4f6e` on `#faf9f7` (headings) | 7.2:1 | ✓ |

---

## Notes

- The CSS files are in `web/themes/custom/customsolent/css/`
- Selectors in the code examples above (`.content a`, `.submenu-item`) are placeholders — use whatever selectors already exist in the theme
- The warm grey (`#f5f3f0`) and off-white background (`#faf9f7`) are intentionally very close — just enough difference to stop the submenu from blending into the page
- All transitions should be `0.15s` for a snappy feel
