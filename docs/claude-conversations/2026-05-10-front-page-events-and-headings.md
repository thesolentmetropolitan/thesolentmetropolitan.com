# Front-page events refinements + heading gradients — 2026-05-10

Continuation of the events-brief implementation
(`2026-05-08-front-page-events-brief.md` /
`2026-05-09-front-page-events-implementation.md`) plus a new
heading-gradient feature for visual variety on the front page.

## Front-page compact events grid — refinements

### Layout pivot: stacked single column inside each card

The original brief showed the date on the left and other fields on
the right (two-column inside the card). The result was that long
Smart Date strings ("25 May 2025, 11:30am - 1:30pm") squeezed the
right column and clipped longer area names like "Southampton".

Fix: rewrote the card body as a stacked single column — title (with
external-link icon), date, venue, area each on its own line — and
removed the faint border. The faint border was replaced later by a
section-coloured top stripe (see kicker work below).

### Pager on /explore/events

Pager existed but rendered as a plain bullet list because the View
Display paragraph wasn't yet using the `Events List` classy style
(`slnt-events-list`) that the pager CSS in `css/node.css` is scoped
to. Once applied via the editor the boxed solent-blue / magenta-dark
pager picked up automatically — same rules the articles listing
already uses.

### Items per page

Synced `view_display_events_page`'s default-display pager to
`items_per_page: 20` per the brief; the views display still inherits
from default and uses Teaser view mode for the full listing.

### Kicker, section-coloured top border, when/where icons

Per Rob's request, each compact card now has:

- A 3px top border tinted with the top-level Topic colour (purple for
  Culture, blue for Sectors, green for Living, slate for About,
  amber for Explore).
- Above the title and **outside the clickable link area**, a kicker
  rendered via the existing `@customsolent/components/topic-trail.html.twig`
  component (so parent terms remain independently navigable). The
  kicker uses the same section colour as the border and a charcoal
  slash separator, matching the article-full kicker.
- Phosphor calendar-dots icon ahead of the date row and map-pin icon
  ahead of the venue row, matching the event teaser icons exactly.

The compact preprocess (`_customsolent_preprocess_event_compact_extras`
in `customsolent.theme`) now exposes `section_color` / `section_key`
in addition to `where_name` / `location_area`, so the template can
inline the border colour from the topic.

### Kicker alignment + tighter gap

Initial kicker was flush to the card's left edge; the title sat
indented by 0.5rem. Tuned by giving the kicker its own
`padding: 0 0.5rem` so it lines up horizontally with the title while
leaving the top border full-width across the card. Vertical gap
between kicker and title was tightened (kicker margins to 0, inner
article top padding to 0.15rem).

### Mobile breakpoint

Original implementation dropped to one column at 799px. Tightened to
**499px** so large phones (≥500px), landscape phones, and small
tablets keep two columns; only narrow portrait phones drop to one.
The site-wide 799px breakpoint is left untouched for other
components that depend on it.

## Heading gradient text fills (new feature)

Rob asked for occasional gradient-text colour on front-page headings
to break up the visual monotony.

### Three styles, all WCAG AA-compliant

| Classy id | Class | Colours |
|---|---|---|
| `heading_gradient_warm_deep` | `slnt-heading-gradient-warm-deep` | deep magenta `#9c0040` → magenta `#c5007a` → amber `#d97706` |
| `heading_gradient_cool` | `slnt-heading-gradient-cool` | solent blue `#2c4f6e` → culture purple `#6B21A8` |
| `heading_gradient_section_sweep` | `slnt-heading-gradient-section-sweep` | Culture `#7C3AED` → Sectors `#2563EB` → Living `#059669` (publication's three pillars in order) |

Every gradient stop measures ≥3:1 against the page background
(`#faf9f7`) — WCAG AA for large text. Some are well above 4.5:1.

### Implementation

- `paragraph--heading.html.twig` was extended with a small Twig loop
  that walks `field_classy` (multi-value) and joins all referenced
  styles' class strings onto the heading element. Multiple classy
  values (e.g. a gradient + a spacing utility) can be applied at once.
- `css/heading-gradient.css` (new, registered in
  `customsolent.libraries.yml`) sets `background-image`,
  `-webkit-background-clip: text`, `background-clip: text`,
  `color: transparent` on each gradient class.
- The class lands on the heading element itself, so a direct
  `color: transparent` overrides the colour the heading inherits
  from the outer wrapper's `field_color_text`-driven inline style —
  no `!important` needed.
- `field_heading_size` (element tag) and `field_heading_align`
  (text-align) are unaffected — gradient classes don't touch those
  properties.
- Forced-colours / Windows high-contrast modes override gradient
  text to a system colour automatically — built-in safety net, no
  extra CSS needed.

### "Heading: Space Below" classy

Companion utility for adding a small gap between a heading and the
content that follows. Applied as a classy alongside the gradient
class.

- Style id: `heading_space_below`
- Class: `slnt-heading-space-below`
- CSS: `padding-bottom: 0.5em` (settled after iterating from 1.5rem
  → 0.7em → 0.5em)
- Uses padding rather than margin to avoid margin-collapse with the
  next paragraph's top margin.

### Heading paragraph form/view display

`field_classy` was added to the Heading paragraph in admin and
exported. Form/view display configs and the field instance YAML
(`field.field.paragraph.heading.field_classy.yml`) were committed
alongside the gradient styles.

## Drupal config note (carried over)

When configuring views with row overrides (e.g. the front-page
display using Compact view mode), the override must live in the
**synced YAML** — `view_display_front_page.display_options.row` plus
`defaults.row: false`. An earlier `drush cim` rolled an in-database
override back to the default's Teaser; the fix was to set the
override permanently in the synced config.

## Related

- Issue: https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/237
- Security follow-up filed: #238 (7 Dependabot alerts on `composer.lock`)

## Commit log for today's session (2026-05-10)

```
6d9db6c front-page compact events grid (initial)
6be9dab events_listing: 20 items per page on /explore/events
d10e26d compact event card: kicker, top border, when/where icons
b2159cb compact event card: tune kicker alignment + spacing
bc6c301 compact event card: keep 2 columns on larger phones, drop to 1 below 500px
24baacc heading-paragraph gradient text fills
157f79e heading-paragraph: "space below" classy style
```
