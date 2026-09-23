# Hero tiles: every Culture icon on /culture, every site icon on the front page

**Date:** 2026-09-23
**Branch:** `hero-tiles-culture-and-home`
**Brief:** `docs/claude-conversations/2026-04-26-BRIEF-update-hero-tiles.md` (tile pipeline reference) plus Rob's request this morning.

## Ask

1. Rework the `/culture` banner tile (`culture_culture.svg`) so it carries every icon used across all the Culture topic tiles (`culture_*.svg`), not just three.
2. Give the front page a hero banner in the same style as the section pages, a little shorter, with "Welcome to The Solent Metropolitan" cut out of it. Its tile is the superset of every icon on every banner. Gradient: diagonal, Culture purple top-left → Sectors blue in the middle → Living green bottom-right, roughly a third each.

## What was found first

- The existing `culture_culture.svg` (and `culture_stage.svg`) had a hole: Phosphor has no `masks-theater` icon, the fetch script had saved GitHub's "404: Not Found" body as the icon, and the build embedded that text where the icon should be. One of the four positions in each tile was empty. Phosphor's icon is `mask-happy`.
- The hex tile is 2 columns × 2 rows = 4 positions, 120 × 103.92px. The banners are 150px tall (110px for the new home one), so only about two hex rows are ever visible. A composite tile therefore has to stay **two rows tall and grow sideways**: every icon gets its own column slot.
- Tile SVGs are served with a ten-year cache lifetime (`cache-control: max-age=315360000`), so reworking a tile under the same filename needs a cache-buster or returning visitors see the old file, centred and tiny inside the new, wider `background-size` box.

## The repeatable block

`scripts/generate-patterned-hero-banners/build_tiles.py`:

- `hex_positions(cols)` replaces the fixed four-position list. Row 0 sits on the top edge, row 1 half a spacing down and right. Width = cols × 60px, height = 103.92px. Every icon is still 60px from its six neighbours, including across tile edges — the classic tile is just `cols = 2`.
- `COMPOSITE_TILES` names the two composites: `culture_culture` = union of the `culture_*` entries in `PAGE_MAP` (73 icons → 37 columns → 2220px wide); `home` = union of everything (164 icons → 82 columns → 4920px wide). The union is in first-seen order, then shuffled with a fixed seed so the build is repeatable. An odd count fills the spare slot from the middle of the list, never with icon 0 (the last row-1 slot wraps round to touch position 0).
- Output now uses one `<symbol>` per icon and `<use>` per placement, so the 164-icon tile is 111 KB rather than roughly three times that. Edge duplicates for seamless repeat work exactly as before.
- The build prints the `background-size` each tile needs; the composite ones differ from `120px 103.92px` and are pasted into `hero-art-styles.css`.
- `build_tiles.py ... [tile ...]` and `fetch_and_build_tiles.sh [tile ...]` rebuild only the named tiles. `curl -fsL` so a 404 is a failure rather than a cached "icon". A cache file without a `<path>` is skipped with a warning.

Only `culture_culture`, `culture_stage` and `home` were rebuilt; the other 93 tiles are untouched (rebuilding them would only change their markup to symbol/use).

## Theme

- `css/hero-art-styles.css`: `.hero-art-style--culture-culture` now `background-size: 2220px 103.92px` with `?v=2` on the URL; `culture_stage` likewise gets `?v=2`. New `.hero-art-style--home`: `linear-gradient(to bottom right, #6B21A8 0%, #7C3AED 24%, #2563EB 50%, #059669 76%, #10B981 100%)` under `home.svg` at `4920px 103.92px`; 110px tall on desktop, 130px on phones (the two-line title needs the room), against 150px for section banners.
- `paragraph--hero-with-art-style.html.twig` + `customsolent_preprocess_paragraph__hero_with_art_style()`: when the host node is the site front page the cut-out title renders as `<h1>` (it is the page's only heading); everywhere else it stays `<h2>` as before.
- Article compact cards reuse a section's tile as their top band at 70% scale with a hard-coded `84px 72.74px`. That would squash the 2220px Culture tile for any Culture article whose topic has no tile of its own (e.g. Heritage & History falls back to `culture_culture`). The node preprocess now reads the tile's `width` from the SVG and passes `--card-tile-width` (70% of it); `article-compact.css` uses `var(--card-tile-width, 84px)`. Card tile URLs also carry `?v=<mtime>` from now on.

## Content

- New classy style `hero_art_style_home` ("Home - Front page", class `hero-art-style--home`), config exported with a uuid.
- `scripts/front_page_hero.php` (idempotent, `--dry-run`): prepends a `hero_with_art_style` paragraph titled "Welcome to The Solent Metropolitan" with that style to the front page's `field_content_component`, then removes the old h1 heading paragraph whose text starts "Welcome" from wherever it sits in the tree (it was three enclosures deep, paragraph 2 inside 712). Re-running reports "already in place".
- `scripts/release-2026-09-23-hero-tiles.sh`: backup → maintenance → cim → cr → front_page_hero.php → cr → maintenance off, with eyeball checks.

## Gradient note for Rob

`to bottom right` puts the 50% stop on the exact diagonal from the top-right corner to the bottom-left one, so the three colours are corner-to-corner thirds whatever the banner's width. On a banner this wide and short, the blue band inevitably reaches the top edge over its right-hand stretch — a middle band that never touches the top edge is only possible with near-horizontal stripes (angle ≈ 176°), which would not read as diagonal. If the current look is not what was pictured, the stops are one line in `.hero-art-style--home`; an alternative is a blue ellipse laid over a purple→green diagonal, which keeps blue off the top edge at the cost of looking like a spotlight.

## Layout effect on the fold (desktop, 1360 wide)

The events grid top moved from 290px to 333px below the viewport top: the 110px banner replaces a 70px heading block. Phone: the banner is 130px where the wrapped heading was about 90px.

## Verified locally

- `/`: one `<h1>`, inside the banner; `background-size: 4920px 103.92px, cover`; banner 110px desktop / 130px phone; different icon in every slot.
- `/culture`: `2220px 103.92px`; 37 different icons across a 1360px viewport, two rows.
- Card preprocess: `culture_culture` → `--card-tile-width: 1554px`, `culture_music` → 84px.
- `front_page_hero.php` dry-run, run, re-run all behave.

## Not done / open

- The other 93 tiles were not rebuilt; next full rebuild switches them to symbol/use markup (harmless, larger diff).
- The tiles' `README.md` in the generator folder describes the composites; `PAGE_MAP` still lists `culture_culture` as an ordinary entry key for the composite (it is skipped in favour of `COMPOSITE_TILES`).
