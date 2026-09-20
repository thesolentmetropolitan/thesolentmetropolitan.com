# Site polish — Rob's items 2 to 11

**Date:** 2026-09-20
**Branch:** `site-polish-items-2-to-10` (cut from `desktop-submenu-white`, so it **includes item 11**)
**Model:** Claude Fable 5.1 via Claude Code

Skipped by agreement: an "Also in" related-topics filter on listing pages. The filtering already
works by address (`/culture/music/organisations?topic=56` → music organisations also tagged
Workshops); only a control is missing. Rob wants to sit with the idea, and to read the code, first.

---

## 2 — More width for the grids; smaller card text
The 1200px content width was written literally into **26 declarations across 11 files** (header,
menu, banner, enclosures, listings, search, footer). Widening only the grids would have broken
their alignment with everything else. Every one now reads `var(--container-max, 1200px)` and the
token is set **once**, in `css/elements.css`: **1360px**. Media-query breakpoints stay literal (a
custom property cannot be used in a media query).

**Correction, same day.** The first version set a flat 1360px, and I told Rob "below about
1390px nothing changes". That was wrong: between 1200px and 1390px the side margins fell from 40px
to the 16px floor, which looks cramped on a 1280px laptop. Spotted in a screenshot taken for
something else. A second attempt used a percentage inside the token; percentages are resolved by
each element that uses the token against its *own* container, so a listing nested in an
already-narrowed enclosure narrowed itself again (grid at 96px, logo at 40px). The token is now
window-based, which gives every user the same answer:

`--container-max: min(1360px, max(1200px, 100vw - 6rem))`

| Window | Content column | Result |
|---|---|---|
| ≤ 1280px | 1200px | exactly as before the change (grid 49px from the edge, cards 280px) |
| 1280 – 1456px | window − 6rem | the 40px-ish gutters of 1280px are held while the column widens |
| ≥ 1456px | 1360px | cards ≈ 320px |

`100vw` is safe here although it is banned for full-bleed widths on this site: that ban is about
`width: 100vw` (wider than the page by the scrollbar). Here it only feeds a subtraction, so nothing
is sized to it; 6rem rather than 5rem allows for the ~15px scrollbar it includes.

Measured after the fix — 1280px: grid 49px each side, cards 280px (unchanged). 1440px: grid, intro
box and footer all at 57–60px, cards 316px. 1920px (flat-value run, same cap): cards 320px.

**Card text.** Recommendation, applied: titles stay **16px**; supporting text (event date and
place, article summary, organisation standfirst) goes to **15px** (`0.9375rem`). Atkinson
Hyperlegible has a large x-height and was designed for low vision, contrast here is ≥7:1, and the
unit is `rem`, so browser text-size settings still apply. WCAG sets no minimum size. I would not go
below 15px for running text; the 12.8px topic terms are short uppercase labels, which is a
different case.

## 3 — "Greater Solent" never split
The front page heading gets a **typed line break** — "What's on across" / "the Greater Solent" —
plus the *Heading One Line Desktop* style. One line on desktop and tablet; on a phone it breaks
exactly there. Each line still wraps within itself, so nothing is pushed off a very narrow screen.
Wording untouched: "What's on in the Greater Solent" is a text edit if Rob prefers it, and the
break will need moving to after "in".

## 4 — "View more Events" / "View more Articles"
Front page Link paragraphs relabelled by script; automatic previews say "View more Events" and
"View more Organisations & links". Checked the width I had flagged: at 820px the heading (one
line) and "View more Events" share the row with room to spare. A Link paragraph now gets the hidden
heading context only when its label is a bare "View more" style phrase — "View more Events"
already says it.

## 5 — Explore populated
`/explore` had a banner and one sentence. It now has an events preview, *Latest articles* built
exactly as on the front page, and the organisations signpost ("460 organisations and 21 links in
the Greater Solent" → `/explore/organisations`). Each shows only if there is something to show.
The sentence on that page still begins "To appear here:" — Rob's placeholder.

## 6, 7, 8 — 404, log in, maintenance
`page.html.twig` + `_customsolent_system_page()` give Drupal's bare pages a heading, an intro and a
white box on the off-white ground, with a body class per page. `css/system-pages.css` styles the
forms to mirror the contact form (2px solent-blue border, square corners, orange focus ring, site
button shape) — a separate file with the same values, because the contact form's rules are scoped
to the webform paragraph.

- **404:** "Page not found" plus onward links (events, articles, organisations, the three
  sections, home). **Found:** `system.site page.404` pointed at `/node/15`, the unpublished *Team*
  article flagged for deletion — anonymous visitors fell through to Drupal's one-line default and
  editors saw an old article. Now empty, which routes to the styled page. 403 gets the same care.
- **Log in / reset password:** heading, one-line intro, form in a white box, cross-links.
- **Maintenance:** a standalone document (no menu or footer blocks exist in maintenance mode) with
  its own header band, heading and message box. The message is still the one set in the
  maintenance-mode settings. Checked by switching maintenance mode on locally, then off again.

## 9 — Section headings are links
No new field. A heading paragraph **directly followed by a Link paragraph** links to the same
place — the pair an editor builds for a Section Heading Row. Preview and signpost headings link
too. The link inherits the heading's look, including the transparent fill of the gradient
headings; underline on hover/focus is solent-blue, set explicitly.

## 10 — Footer
Desktop: six items three across, two down, with two dividers placed at the centre of each column
gap. Phones unchanged. Footer menu and the horizontal legal menu show a pink underline (`#f5b0d8`,
5:1 on the footer) **on hover and keyboard focus only** — first built as always-on, which was not
what Rob meant; corrected the same day.

## 11 — Desktop submenu white
See `2026-09-20-section-strip-and-signpost.md`. Included in this branch.

## Production deploy
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
bash scripts/release-2026-09-20-site-polish.sh
```
One config change (`system.site` 404) and one content script (`scripts/site_polish_content.php`,
idempotent). DDEV snapshot `pre-site-polish-20260920` was taken before applying locally.
