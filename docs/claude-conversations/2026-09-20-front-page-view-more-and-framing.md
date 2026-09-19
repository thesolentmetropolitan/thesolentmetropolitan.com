# Front page: "View more" links, intro link, off-white framing

**Date:** 2026-09-19/20
**Branch:** `front-page-view-more-and-framing` (cut from `main` at `a4bbb8c`, after Rob deployed the
reorganisation, deleted the Landing Page type, exported config and pushed)
**Model:** Claude Fable 5.1 via Claude Code

---

## What Rob asked for, having seen the reorganised page live

1. "See all events" felt detached from its slice. Replace it, BBC-homepage style, with a **"View
   more"** text link on the same line as the section heading, right-aligned, with a caret — and a
   second "View more" below the grid that shows at narrower widths while the first hides. Both in
   the page; a media query picks one. Clickable area = the text only. Same for Latest articles.
   *Keep using the general tools — classy paragraphs and styles — and report how that works out.*
2. No About button. End the intro paragraph with **"Read more about >"** in bold orange.
3. A **horizontal line** above the intro section, at the edge of the content box.
4. **Visual structure.** Everything floats on white. Like the BBC: off-white background, white
   boxes. Event cards white on off-white, so the gaps between them are off-white. *Do we have an
   off-white in the palette?*

## How the general tools worked out

Well — with one addition. Everything is editor-visible paragraphs and styles; nothing is
hard-wired to the front page.

- **The unused `Link` paragraph type** (zero instances on the site) is now the "View more"
  vehicle. It gained the standard `field_classy` field — the one addition to the toolset — and a
  template, `paragraph--link.html.twig`: a plain text link with the site's caret, no button box.
- **Three new classy styles:**
  - *View More Wide Screens* and *View More Narrow Screens* on Link paragraphs — the media-query
    pair. `display: none` on the hidden one, so it leaves the accessibility tree and tab order;
    nobody meets the link twice.
  - *Section Heading Row* on an enclosure (its existing Style field) — turns the enclosure's items
    into a two-column grid and places a wide-screens link beside the heading before it, baselines
    level. Every other item stays full width. It uses `:has()`; where unsupported, the link just
    sits right-aligned under the heading.
- An editor can now build the same heading-plus-View-more section anywhere: add a Link after the
  heading (wide), another after the content (narrow), set the enclosure's Style.
- **Where the tools ran out:** "Read more about" sits *inside* rich text, and the text format does
  not allow classes on links, so it is styled by scope — any link in the intro paragraph of a
  *Front Intro Two Column* section. That is a CSS convention rather than an editor choice.

## Details

- **Accessible names.** Two links both reading "View more" are indistinguishable in a
  screen-reader's link list. `customsolent_preprocess_paragraph__link()` finds the nearest heading
  before the link in the same parent and the template appends it as visually-hidden text:
  "View more: What's on across the Greater Solent", "View more: Latest articles". Automatic.
- **Target size.** Text-only click area as asked, with `min-height: 24px` (the WCAG 2.5.8 minimum)
  rather than padding. Measured 95 × 24.
- **Colour.** Explore orange `#BC4A08`, bold, for View more and Read more about — Events, Articles
  and About all sit under Explore. 4.6:1 on the new warm-grey band.
- **Off-white.** The palette already has two: `body-bg #FAF9F7` (the page background all along —
  so close to white that white boxes on it are invisible, 1.05:1) and `warm-grey #F5F3F0`. The
  band uses **warm-grey**, via the existing colour term in the top enclosure's background field —
  no CSS. White on warm-grey is 1.11:1: subtle, and close to the BBC's white on `#F6F6F6` (1.08:1).
  No new colour was needed.
- **Event cards** are white boxes; the 1px solent-blue top rule stays as the accent. The grid gap
  is now an even 1rem — it was 1.8rem vertically only so the rule could not be misread, which the
  boxes now prevent.
- **Rule above the intro:** 1px solent-blue on the *Front Intro Two Column* style, pulled out
  0.2rem each side so it lines up with the enclosure edges above and below (49px → 1217px at 1280).
- The About button's own slice, and the *Centre Call To Action* style it used, are no longer used
  on the front page; the style is left in place as a reusable tool.

## Verified (Playwright, 1280px and 375px)

| Check | Result |
|---|---|
| Desktop: wide link on heading line, right edge at content edge | yes, both sections |
| Desktop: narrow link hidden | yes |
| Phone: wide hidden, narrow shown below the grid | yes, both sections |
| Reachable View more links at any one width | 2 (one per section) |
| Band / card background | `#F5F3F0` / `#FFFFFF` |
| Read more about | bold, `#BC4A08`, → `/about/overview` |
| Buttons left on the page | the three Discover buttons only |
| Horizontal scroll | none |
| Script second run | every step "already…"; `config:status` clean |

## Production deploy

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
bash scripts/release-2026-09-20-view-more-framing.sh
```

`scripts/front_page_view_more_and_framing.php` does the content changes (dry-run flag; each step
checks its own state). DDEV snapshot `pre-view-more-20260920` was taken before applying locally.

---

## Follow-up — three CSS tweaks after Rob's review (no content or config change)

- **Article cards: square corners.** Rob is happy with them sitting directly on the off-white.
  `border-radius` removed from the card and from the stretched link's hit area.
- **Intro slice is a white box, exactly as wide as its top rule.** Rob's reasoning was right: if
  the rule can be selected, so can the box. They are now literally the same element — the rule is
  the box's `border-top` — so the two widths cannot drift. The section wrapper takes a 1rem side
  margin (bringing its edges onto the same line as the enclosures above and below) and its padding
  moves inside. Measured: box, events grid and articles grid all 49 → 1217px at 1280, and all
  32 → 328px at 375. The earlier `::before` rule with its −0.2rem nudge is gone. A 1.2rem bottom
  margin balances the gap below the box against the gap above.
- **View more: 1rem → 1.2rem** (19.2px) against the 28.8px section headings, so it stays clearly
  subordinate. Click box now 114 × 27.

Deploy is unchanged: these ride along with `scripts/release-2026-09-20-view-more-framing.sh`.
