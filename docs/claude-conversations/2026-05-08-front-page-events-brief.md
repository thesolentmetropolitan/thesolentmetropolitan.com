# The Solent Metropolitan — Front Page Events & Events Listing Brief

## Overview

Two pieces of work:

1. **Front page:** Display 5–7 upcoming events in a compact format, two per row on desktop, in the right column of the existing two-column section. No filter. Link to the full events page.
2. **/explore/events page:** All events listed with pagination. No filter for MVP; topic and date filters added later.

---

## Part 1: Front Page Events

### Layout change to existing front page

The front page currently has a two-column section (Section 2 Column paragraph) below the hero gradient. This section will be restructured:

**Remove the gradient fill** from this two-column section — clear the `classy_paragraphs` value so it sits on the standard body background (`#faf9f7`). No gradient, no background colour. Clean contrast for readability.

**Left column:**
- "We are an independent media platform..." introduction text (moved from its current position)
- "About" CTA button

**Right column:**
- View Display paragraph with:
  - View: `events_listing`
  - Display: `view_display_front_page`
  - Heading: **"What's on"**
- Below the View Display paragraph: a CTA paragraph linking to `/explore/events` with text "View all events"

### New view mode: Event Compact

Create a new view mode for the Event content type for use on the front page.

**View mode name:** Compact
**Machine name:** `compact`

Create at: `/admin/structure/display-modes/view/add`

Then configure the Event compact display at: `/admin/structure/types/manage/event/display/compact`

**Fields to display:**

| Field | Visible | Label | Formatter / notes |
|-------|---------|-------|-------------------|
| field_when | Yes | Hidden | Smart Date — abbreviated date + time/time range, e.g. "8 May, 7:30 PM" or "8 May, 7:00–10:00 PM". Configure the Smart Date formatter to use a short/custom format showing day, abbreviated month, and time. |
| Title | Yes | (automatic) | Not linked — the whole card is the link |
| field_where | Yes | Hidden | Entity reference label — just the location name |
| field_image | No | — | Hidden in compact mode to save space |
| field_standfirst | No | — | Hidden |
| field_url | No | — | Hidden (the whole card links to the event URL) |
| field_primary_topic | No | — | Disabled |
| field_related_topics | No | — | Disabled |

**Field order:** when → title → where

### Events Listing View — new display

Add a new block display to the existing `events_listing` View:

**Display name:** Front Page Compact
**Machine name:** `view_display_front_page`

**Configuration:**
- Format: Unformatted list
- Show: Content | **Compact** (the new view mode)
- Filter criteria: Content: Published (= Yes), Content: Type (= Event)
- Filter criteria: Smart Date — upcoming only (event date >= now)
- Sort criteria: Content: when (asc) — soonest first
- **No contextual filter** — this display shows all upcoming events regardless of topic
- Pager: Display a specified number of items — **6** (even number for the 2-per-row grid)
- No "more" link needed — handled manually in the template

### Compact event card template

Create: `web/themes/custom/customsolent/templates/content/node--event--compact.html.twig`

```twig
{%
  set classes = [
    'node',
    'node--type-' ~ node.bundle|clean_class,
    'node--view-mode-' ~ view_mode|clean_class,
    'slnt-event-compact',
  ]
%}

{#
  The entire card is wrapped in an <a> element linking to the event's
  external URL (field_url). Opens in new tab since it leaves the site.
  If no external URL, link to the node as fallback.
#}
{% set event_url = node.field_url.0.uri ?? path('entity.node.canonical', {'node': node.id}) %}
{% set is_external = node.field_url.0.uri is not empty %}

<a href="{{ event_url }}" class="slnt-event-compact__link"
   {% if is_external %}target="_blank" rel="noopener"{% endif %}>
  <article{{ attributes.addClass(classes) }}>

    <div class="slnt-event-compact__date">
      {{ content.field_when }}
    </div>

    <div class="slnt-event-compact__body">
      <h3 class="slnt-event-compact__title">
        {{ label }}
        {% if is_external %}
          <svg class="slnt-event-compact__external-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            <polyline points="15 3 21 3 21 9"/>
            <line x1="10" y1="14" x2="21" y2="3"/>
          </svg>
        {% endif %}
      </h3>
      <div class="slnt-event-compact__where">
        {{ content.field_where }}
        {# When parent location is available (via preprocess), render on second line: #}
        {% if location_area is defined and location_area %}
          <span class="slnt-event-compact__where-area">{{ location_area }}</span>
        {% endif %}
      </div>
    </div>

  </article>
</a>
```

**Notes on the template:**
- The external link icon (a small arrow-out-of-box SVG) is appended inline after the title text. It signals "this link leaves the site" even though the whole card is clickable.
- `field_when` should display date AND time/time range — configure the Smart Date formatter in the compact view mode to show abbreviated date + time, e.g. "8 May, 7:30 PM" or "8 May, 7:00–10:00 PM".
- `field_where` renders on its own line below the title. When the Location term has a parent (e.g. "God's House Tower" under "Southampton"), the display should show them as two lines:
  ```
  God's House Tower
  Southampton
  ```
  This is handled by the template rendering the term name and its parent on separate lines. Claude Code should load the term's parent in the preprocess and pass both values to the template. For now, if only the venue term is available without parent rendering, a single line is fine — the parent line is a later enhancement.
- If `field_url` is empty, the card links to the node as a fallback and no external icon is shown.

### CSS for compact event cards

Add to `css/front.css` or a new `css/event-compact.css`:

```css
/* ══════════════════════════════════════
   Compact event cards — front page
   ══════════════════════════════════════ */

/* ── Grid: 2 per row on desktop, 1 per row on mobile ── */
.view-events-listing.view-display-id-view_display_front_page .view-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.8rem;
}

@media (max-width: 799px) {
  .view-events-listing.view-display-id-view_display_front_page .view-content {
    grid-template-columns: 1fr;
  }
}

/* ── Card link wrapper ── */
.slnt-event-compact__link {
  display: block;
  text-decoration: none;
  color: inherit;
  border-radius: 6px;
  transition: background 0.15s, box-shadow 0.15s;
  border: 1px solid #e0e0e0;
  overflow: hidden;
}

.slnt-event-compact__link:hover {
  background: var(--warm-grey, #f5f3f0);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.slnt-event-compact__link:hover .slnt-event-compact__title {
  text-decoration: underline;
  text-decoration-color: var(--magenta, #c5007a);
  text-underline-offset: 3px;
  text-decoration-thickness: 2px;
}

/* ── Card layout ── */
.slnt-event-compact {
  display: flex;
  gap: 0.8rem;
  padding: 0.8rem;
  align-items: flex-start;
}

/* ── Date badge ── */
.slnt-event-compact__date {
  flex-shrink: 0;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--solent-blue, #2c4f6e);
  min-width: 3.5rem;
  text-align: center;
  padding: 0.3rem 0;
  line-height: 1.3;
}

/* Remove any field wrapper margins */
.slnt-event-compact__date .field {
  margin: 0;
}

/* ── Card body ── */
.slnt-event-compact__body {
  flex: 1;
  min-width: 0;
}

/* ── Title ── */
.slnt-event-compact__title {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--solent-blue, #2c4f6e);
  line-height: 1.3;
  margin: 0 0 0.25rem 0;
  transition: text-decoration 0.15s;
}

/* External link icon — inline after title text */
.slnt-event-compact__external-icon {
  display: inline-block;
  width: 0.75em;
  height: 0.75em;
  vertical-align: baseline;
  margin-left: 0.25em;
  opacity: 0.6;
  transition: opacity 0.15s;
}

.slnt-event-compact__link:hover .slnt-event-compact__external-icon {
  opacity: 1;
}

/* ── Location — own line(s) below title ── */
.slnt-event-compact__where {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.75rem;
  font-weight: 400;
  color: var(--text-mid, #444);
  line-height: 1.3;
  display: block;
}

/* Parent location term (e.g. "Southampton") on its own line below venue */
.slnt-event-compact__where-area {
  display: block;
  font-size: 0.7rem;
  color: #888;
}

.slnt-event-compact__where .field {
  margin: 0;
}

/* ── Half-width container for the front page ── */
.slnt-front-events {
  /* Applied to the right column of the 2-column section */
}

.slnt-front-events__heading {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--solent-blue, #2c4f6e);
  margin-bottom: 0.8rem;
}

.slnt-front-events__view-all {
  display: inline-flex;
  align-items: center;
  gap: 0.4em;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--solent-blue, #2c4f6e);
  text-decoration: underline;
  text-decoration-color: var(--magenta, #c5007a);
  text-underline-offset: 3px;
  text-decoration-thickness: 2px;
  margin-top: 0.8rem;
  transition: color 0.15s;
}

.slnt-front-events__view-all:hover {
  color: var(--magenta, #c5007a);
}

/* Chevron on the "View all events" link */
.slnt-front-events__view-all .slnt-cta__chevron {
  width: 0.7em;
  height: 0.7em;
  transform: rotate(-90deg);
  transition: transform 0.2s ease;
  fill: currentColor;
}

.slnt-front-events__view-all:hover .slnt-cta__chevron {
  transform: rotate(-90deg) translateY(-3px);
}

/* ── Mobile: full width ── */
@media (max-width: 799px) {
  .slnt-event-compact__title {
    font-size: 0.85rem;
  }
}
```

### "View all events" CTA

Below the compact event grid, a CTA button links to `/explore/events`. This can be implemented either as a separate CTA paragraph placed by the editor below the View Display paragraph, or hardcoded in the View Display paragraph template when it detects the front page display. The **CTA paragraph approach is simpler and more flexible** — the editor adds a CTA paragraph with link `/explore/events`, text "View all events", using the existing CTA styling (solent blue fill, white text, chevron).

Alternatively, the "View all events" can be a styled text link below the grid:

```twig
<a href="/explore/events" class="slnt-front-events__view-all">
  View all events
  <svg class="slnt-cta__chevron" role="presentation" focusable="false">
    <use href="#tvip-down-triangle"></use>
  </svg>
</a>
```

### How this gets onto the front page

The front page is a Composite Page. The editor:

1. Clears the gradient fill from the Section 2 Column paragraph (remove the classy_paragraphs value)
2. Places the introduction text and About CTA in the left column
3. Adds a **View Display paragraph** in the right column, selecting `events_listing` View with `view_display_front_page` display, heading set to **"What's on"**
4. Adds a **CTA paragraph** below the View Display paragraph in the right column, linking to `/explore/events` with text "View all events"

Since the front page display has **no contextual filter**, the preprocess function's `primary_topic_tid` argument will be passed but ignored by the View — as documented in the design notes of the section landing filters brief.

---

## Part 2: /explore/events — Full Events Listing Page

### Page setup

The Explore / Events page is an existing Composite Page at `/explore/events` with `field_primary_topic` set to the "Explore / Events" topic term.

Add a **View Display paragraph** to this page using the `events_listing` View with an appropriate display.

### Events Listing View — events page display

Add another block display to the `events_listing` View:

**Display name:** Events Page Full
**Machine name:** `view_display_events_page`

**Configuration:**
- Format: Unformatted list
- Show: Content | **Teaser** (the standard event teaser view mode — not compact)
- Filter criteria: Content: Published (= Yes), Content: Type (= Event)
- Filter criteria: Smart Date — upcoming events only (for MVP)
- Sort criteria: Content: when (asc) — soonest first
- **No contextual filter for MVP** — shows all events across all topics. When filters are added later, the contextual filter and exposed filters from the section landing filters brief apply.
- Pager: Full pager, 20 items per page
- No results: "No upcoming events at the moment. Check back soon."

### Pagination

Pagination CSS already exists at `web/themes/custom/customsolent/css/node.css` from earlier work. The same styles apply to this View's pager. Verify the pager renders correctly with the existing CSS. If adjustments are needed, Claude Code can update the styles.

### Future filters (deferred)

When ready to add filters to /explore/events:

**Topic filter:**
- The full Topic taxonomy — all parents (Culture, Sectors, Living) and their children
- This is different from the section landing page filter which only shows children of the current term
- Implementation: exposed filter on `field_primary_topic` with the Topic vocabulary, showing all terms in a hierarchical select or grouped display
- The Explore special case noted in the section landing filters brief applies here

**Date filter:**
- "All upcoming" (default), "This week", "This weekend", "This month", "Past events"
- Essential for an events page — temporal relevance is the primary reason someone visits

**Location filter:**
- Area-level terms from the Location taxonomy
- Secondary to topic and date

These use the same sidebar (desktop) and slide-up panel (mobile) patterns from the section landing filters brief. The implementation is shared — same CSS, same JS, same Twig components.

---

## Checklist: What Rob should configure

### New view mode
- [ ] Create "Compact" view mode at `/admin/structure/display-modes/view/add` — machine name `compact`
- [ ] Configure Event compact display at `/admin/structure/types/manage/event/display/compact`
  - [ ] field_when — Visible, label hidden, Smart Date short/custom format: abbreviated date + time/time range (e.g. "8 May, 7:30 PM")
  - [ ] field_where — Visible, label hidden, Entity reference label (venue term name; parent term e.g. "Southampton" displayed on a separate line below — added later via preprocess)
  - [ ] All other fields — Disabled
  - [ ] Field order: when → where (title renders from template)

### Events Listing View — new displays
- [ ] Add `view_display_front_page` display to `events_listing`
  - [ ] Format: Unformatted list | Show: Content | Compact
  - [ ] Filter: Published, Type = Event, Smart Date upcoming only
  - [ ] Sort: when (asc)
  - [ ] No contextual filter
  - [ ] Pager: 6 items, no full pager (just a fixed count)
  - [ ] No results: "No upcoming events yet."

- [ ] Add `view_display_events_page` display to `events_listing`
  - [ ] Format: Unformatted list | Show: Content | Teaser
  - [ ] Filter: Published, Type = Event, Smart Date upcoming only
  - [ ] Sort: when (asc)
  - [ ] No contextual filter (for MVP)
  - [ ] Pager: Full pager, 20 items
  - [ ] No results: "No upcoming events at the moment. Check back soon."

### Front page changes
- [ ] Remove gradient fill from the Section 2 Column paragraph (clear the classy_paragraphs value)
- [ ] Restructure the Section 2 Column:
  - Left column: introduction text + About CTA (moved from current position)
  - Right column: View Display paragraph → events_listing / view_display_front_page, heading: "What's on"
  - Right column below View Display: CTA paragraph → link to `/explore/events`, text "View all events"

### /explore/events page
- [ ] Add View Display paragraph to the Explore / Events composite page
  - View: events_listing
  - Display: view_display_events_page
  - Heading: "Upcoming events" (or similar)

---

## Implementation order

| Step | Task | Who | Priority |
|------|------|-----|----------|
| 1 | Create "Compact" view mode | Rob | Now |
| 2 | Configure Event compact display (field visibility, order, formatters) | Rob | Now |
| 3 | Add `view_display_front_page` display to events_listing View | Rob | Now |
| 4 | Add `view_display_events_page` display to events_listing View | Rob | Now |
| 5 | Export View config | Rob | Now |
| 6 | Create `node--event--compact.html.twig` (whole-card clickable link, minimal layout) | Claude Code | Now |
| 7 | Create compact event card CSS (grid, card styles, hover states) | Claude Code | Now |
| 8 | Restructure front page: left column text + CTA, right column events | Rob | Now |
| 9 | Add View Display paragraph to /explore/events page | Rob | Now |
| 10 | Verify pagination works on /explore/events with existing CSS | Claude Code | Now |
| 11 | Add "View all events" CTA paragraph below the events listing in right column | Rob | Now |
| 12 | Add topic and date filters to /explore/events | Later |
| 13 | Add location filter | Later |

---

## CSS files

| File | Purpose |
|------|---------|
| `css/event-compact.css` | Compact card styles, 2-per-row grid, hover states, "View all events" link |
| `css/node.css` | Existing pagination styles (verify, adjust if needed) |

Register `event-compact.css` in `customsolent.libraries.yml`.

---

## Testing

1. **Front page events:** 6 compact event cards appear in a 2-column grid in the right column. Soonest events first. Only upcoming events shown.
2. **Compact card click:** Clicking anywhere on the card goes to the external event URL (opens in new tab). External link icon (small arrow) appears inline after the title text. If no external URL, card links to node and no icon shown.
3. **Compact card hover:** Background shifts to warm grey, title gains magenta underline, external link icon becomes fully opaque.
4. **Compact date display:** Shows abbreviated date with time — "8 May, 7:30 PM" or "8 May, 7:00–10:00 PM". No labels.
5. **Compact location:** Shows venue name on its own line below the title, no "Where:" label. When parent term is added later, area name (e.g. "Southampton") appears on a second line below the venue name.
6. **"View all events" CTA:** Appears below the grid as a CTA button, links to `/explore/events`.
7. **Mobile:** Cards stack to single column below 800px.
8. **/explore/events:** Full teaser display of all upcoming events with pagination. 20 per page.
9. **Pagination:** Pager renders correctly with existing styles.
10. **Empty state:** If no upcoming events exist, the appropriate "no results" message appears on both the front page and /explore/events.
11. **No filter interference:** The front page display has no contextual filter — the `primary_topic_tid` argument from the View Display paragraph preprocess is ignored.

---

## Links to other briefs

- **Section landing filters brief** — the sidebar/filter system, preprocess function, and View Display paragraph architecture that this brief builds on
- **Event listing brief** — the events_listing View and teaser configuration
- **Article styling brief** — the pagination CSS in node.css
