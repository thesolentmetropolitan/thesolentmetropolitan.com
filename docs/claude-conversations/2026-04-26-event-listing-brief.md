# The Solent Metropolitan — Event Listing Brief

## Overview

Set up the Event content type for listing-only display (no full node view for MVP). Create a View to list upcoming events. Generate bitmap placeholder images from the existing hero art SVG tile patterns for use as event thumbnails until real photographs are available.

---

## Part 1: Event Content Type Configuration

### Fields

The following fields should already exist on the Event content type. Verify they are present and configured correctly:

| Field | Machine name | Type | Notes |
|-------|-------------|------|-------|
| Title | title | Built-in | Standard node title |
| Standfirst | field_standfirst | Text (plain), 255 chars | One-sentence summary of the event |
| When | field_when | Smart Date | Date/time of the event |
| Where | field_where | Text (plain) | Venue name and area, e.g. "Mayflower Studios, Southampton" |
| Event URL | field_event_url | Link | URL to external event page. Editor sets both the URL and the link text (e.g. "Get tickets", "Event details", "Venue info"). Link text is used as the CTA button label. |
| Image | field_image | Media entity reference | Optional — will be empty initially |
| Primary topic | field_primary_topic | Entity reference → Topic vocabulary | Single value |
| Related topics | field_related_topics | Entity reference → Topic vocabulary | Multi-value |

### Rabbit Hole configuration

- Install rabbit_hole if not already installed: `composer require drupal/rabbit_hole` then `drush en rabbit_hole rh_node`
- Configure the Event content type to **redirect to the front page** (or show a 403) when a user tries to visit the node directly
- This prevents access to the unstyled full node view while the listing serves as the primary display

---

## Part 2: Event Listing View

### Create a new View

- **View name:** Events listing
- **Machine name:** `events_listing`
- **Show:** Content of type Event
- **Page display:** Yes, path: `/events` (or consider whether this lives under Explore — `/explore/events`)

### View configuration

**Format:** Unformatted list (styled via CSS, not Views' built-in grid)

**Fields to display (in this order):**
1. **Image** (field_image) — rendered as thumbnail (300×300 or fallback placeholder). Hidden if empty AND no placeholder is configured yet.
2. **Title** (title) — not linked (rabbit_hole prevents node access; the CTA button below handles the link to the external event page)
3. **Standfirst** (field_standfirst) — truncated if necessary, though 255 chars should be short enough to display in full
4. **When** (field_when) — formatted readably, e.g. "Saturday 26 April 2026, 7:30 PM"
5. **Where** (field_where) — plain text
6. **External link** (field_event_url) — displayed as a CTA-style button using the `.slnt-cta` pattern (solent blue fill, white text, chevron). The button label is the editor-defined link text from the Link field (e.g. "Get tickets", "Event details", "Venue info"). Each event can have different button text. The link opens in a new tab (`target="_blank"`) since it leaves the site.

**Sort:**
- Smart Date field, ascending (soonest first)

**Filter criteria:**
- Content type = Event
- Published = Yes
- Smart Date: only show events where the date is today or in the future (upcoming events). Smart Date module provides a "current and upcoming" filter option — use this if available, otherwise use a date filter with "greater than or equal to now".

**Pager:**
- 20 items per page with a pager (adjust based on expected volume)

**No results text:**
- "No upcoming events at the moment. Check back soon."

### Contextual filter (for section landing pages — later use)

Add a contextual filter on `field_primary_topic` (taxonomy term ID) with a default value of "Provide default value → Content ID from URL" or the custom contextual filter plugin described in the taxonomy brief. Set the default to display all events when no argument is provided.

This allows the same view to be reused on section pages (e.g. `/culture` shows only Culture events) by passing the page's primary topic as the contextual argument. **For the MVP, the view works without the contextual filter on the main `/events` page.**

---

## Part 3: Event Listing CSS

Add to a new file `css/view-events.css` and register it in `customsolent.libraries.yml`.

The listing reuses concepts from the article styling — same font family, same colour variables, same link styles, same alignment and max-width.

```css
/* ══════════════════════════════════════
   Event listing
   ══════════════════════════════════════ */

/* ── Listing container ── */
.view-events-listing {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--content-pad, 2rem);
}

/* ── Individual event item ── */
.view-events-listing .views-row {
  display: flex;
  gap: 1.5rem;
  padding: 1.5rem 0;
  border-bottom: 1px solid #e0e0e0;
  align-items: flex-start;
}

.view-events-listing .views-row:last-child {
  border-bottom: none;
}

/* ── Thumbnail area ── */
.slnt-event__image {
  flex-shrink: 0;
  width: 150px;
  height: 150px;
  border-radius: 4px;
  overflow: hidden;
}

.slnt-event__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* ── Content area ── */
.slnt-event__content {
  flex: 1;
  min-width: 0;
}

/* ── Event title ── */
.slnt-event__title {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.3;
  color: var(--solent-blue, #2c4f6e);
  margin-bottom: 0.3rem;
}

/* ── Standfirst ── */
.slnt-event__standfirst {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.5;
  color: var(--text, #1a1a1a);
  margin-bottom: 0.5rem;
}

/* ── Date and location ── */
.slnt-event__meta {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.88rem;
  color: var(--text-mid, #444);
  margin-bottom: 0.5rem;
  line-height: 1.5;
}

.slnt-event__when,
.slnt-event__where {
  display: block;
}

/* ── External link — CTA button style ── */
.slnt-event__link {
  margin-top: 0.3rem;
}

.slnt-event__link a {
  display: inline-flex;
  align-items: center;
  gap: 0.5em;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.85rem;
  font-weight: 700;
  padding: 0.5em 1.2em;
  background: var(--solent-blue, #2c4f6e);
  border: 3px solid var(--solent-blue, #2c4f6e);
  border-radius: 4px;
  color: white;
  text-decoration: none;
  transition: all 0.2s ease;
  line-height: 1.2;
}

.slnt-event__link a:hover {
  background: var(--solent-blue-light, #3a6489);
  border-color: var(--solent-blue-light, #3a6489);
}

/* Reuse the shared SVG chevron inside the CTA */
.slnt-event__link .slnt-cta__chevron {
  width: 0.7em;
  height: 0.7em;
  transform: rotate(-90deg);
  transition: transform 0.2s ease;
  flex-shrink: 0;
  fill: currentColor;
}

.slnt-event__link a:hover .slnt-cta__chevron {
  transform: rotate(-90deg) translateY(-3px);
}

/* ── Mobile: stack image above content ── */
@media (max-width: 799px) {
  .view-events-listing {
    padding: 0 var(--content-pad-mobile, 1.2rem);
  }

  .view-events-listing .views-row {
    flex-direction: column;
    gap: 0.8rem;
  }

  .slnt-event__image {
    width: 100%;
    height: auto;
    aspect-ratio: 16 / 9;
  }

  .slnt-event__title {
    font-size: 1.15rem;
  }
}
```

**Notes on the CSS:**
- The horizontal divider lines between events (`border-bottom: 1px solid #e0e0e0`) follow the london.gov.uk pattern discussed earlier.
- Desktop layout: thumbnail on the left (150×150), content on the right. This is a compact listing style that works well for scanning many events.
- Mobile: image stacks above content at full width in 16:9 ratio for visual impact.
- The external link uses the CTA button style (solent blue fill, white text, 3px border, 4px border-radius, chevron) matching the existing CTA buttons elsewhere on the site. The button is slightly smaller than the section CTAs (font-size 0.85rem, tighter padding) to suit the listing context.
- The SVG chevron reuses the shared `#tvip-down-triangle` symbol, rotated -90° to point right — same as the CTA paragraph buttons.
- The thumbnail size of 150×150 on desktop is a starting point — adjust based on how the listing looks with real content. 200×200 is an alternative if the thumbnails feel too small.

### Views template (optional)

If the default Views row output doesn't produce clean enough markup for the CSS above, Claude Code may need to create a views row template at:
`web/themes/custom/customsolent/templates/views/views-view-unformatted--events-listing.html.twig`

This template would wrap each row's fields in the `.slnt-event__image`, `.slnt-event__content`, `.slnt-event__title` (etc.) classes needed by the CSS. The external link field should include the shared SVG chevron inside the link element, using the same `<use href="#tvip-down-triangle">` reference as the CTA buttons:

```twig
<div class="slnt-event__link">
  <a href="{{ event_url }}" target="_blank" rel="noopener">
    {{ event_link_text }}
    <svg class="slnt-cta__chevron" role="presentation" focusable="false">
      <use href="#tvip-down-triangle"></use>
    </svg>
  </a>
</div>
```

The exact template depends on how Views outputs the fields — inspect the markup after creating the view and decide whether a template override is needed.

---

## Part 4: Placeholder Images from Hero Art SVGs

### Concept

Generate bitmap placeholder thumbnails from the existing hero art tile SVGs so that events have visual presence in the listing even without photographs. Each primary topic gets its own placeholder derived from its section's tile pattern and colour scheme.

### What to generate

For each sub-term that has a corresponding hero art SVG tile pattern, generate two bitmap files:

1. **Square thumbnail** — 300×300 px (for the event listing at 1x and 2x density)
2. **Social media card** — 1080×1080 px (for Open Graph / social sharing)

These are the same tile pattern as the composite page hero art banners, just rendered at different dimensions. The pattern tiles seamlessly by design, so no cropping or focal point is needed — just fill the target dimensions with the repeating pattern.

### Output format

- **PNG** if the file size is reasonable (the tile patterns are simple geometric shapes with flat colours, so PNG should compress well)
- **JPEG** as fallback if PNG files exceed ~50KB per thumbnail — use quality 85 to avoid artifacts on the geometric edges
- Test both formats for a few images and choose whichever gives the best size-to-quality ratio

### File naming convention

Use the term machine name or a sanitised version of the term name:
- `placeholder-culture-music--300x300.png`
- `placeholder-culture-music--1080x1080.png`
- `placeholder-sectors-technology--300x300.png`
- etc.

### File location

Place generated images in: `web/themes/custom/customsolent/images/placeholders/`

### Implementation approach

Write a script (bash, Python, or Node) that:

1. Takes each hero art SVG from the existing `images/hero-tiles/` directory
2. Renders it at the target dimensions using a tool available in the environment:
   - **Inkscape CLI** (`inkscape --export-type=png --export-width=300 --export-height=300`)
   - **ImageMagick** (`convert -size 300x300 tile:input.svg output.png`)
   - **librsvg** (`rsvg-convert -w 300 -h 300 input.svg > output.png`)
   - **Sharp** (Node.js) or **Pillow** (Python) if preferred
3. Outputs both the 300×300 and 1080×1080 versions
4. Uses PNG format (test compression, switch to JPEG if needed)

The SVGs are designed as repeating tiles, so the rendering tool needs to handle the tiling correctly. If the SVG has a fixed viewBox smaller than the output dimensions, the tool should tile/repeat the pattern to fill the canvas. Test with one SVG first to verify the output looks correct.

### How placeholders connect to events

The placeholder image selection would eventually be automated based on the event's primary topic. The logic is:

1. Event has primary topic "Culture / Music"
2. Template checks: does the event have an uploaded image? If yes, use it.
3. If no uploaded image, derive the placeholder filename from the primary topic: `placeholder-culture-music--300x300.png`
4. Display the placeholder in the listing

**For the MVP, this automation is deferred.** Placeholder images are generated and available. The automated selection logic (reading the primary topic and falling back to the placeholder) is a later task. For now, events display without images in the listing, or a single generic placeholder is used for all events.

---

## Part 5: Image style for event thumbnails

Create an image style for when real event images are used:

**Event Thumbnail (square)**
- Machine name: `event_thumbnail`
- Effect: Focal Point Scale and Crop — width: 300, height: 300

This handles uploaded photographs. The placeholder SVG-derived bitmaps don't go through image styles — they're pre-rendered at the correct size.

---

## Summary of work

| Task | Who | Priority |
|------|-----|----------|
| Verify Event fields exist and are configured | Rob (admin UI) | Now |
| Install and configure rabbit_hole for Event | Claude Code or Rob | Now |
| Create the Events listing View | Claude Code | Now — MVP |
| Create `css/view-events.css` and register in libraries | Claude Code | Now — MVP |
| Create Views template override if needed | Claude Code | Now — if markup needs adjusting |
| Generate placeholder bitmap images from SVGs | Claude Code | Soon after MVP |
| Create `event_thumbnail` image style (300×300 focal point) | Rob (admin UI) | When real images start being used |
| Automate placeholder selection based on primary topic | Claude Code | Later |
| Enable full node view for events | Separate brief | Later |
| Eventbrite / Meetup API integration | Separate brief | Later |
| Organiser accounts with content moderation | Separate brief | Later |

---

## What this does NOT cover (deferred)

- Full node view for individual events (rabbit_hole prevents access for now)
- Automated placeholder image selection from primary topic
- External API integration (Eventbrite, Meetup)
- Organiser login accounts and editorial workflow
- Event filtering by topic on section landing pages (View supports it via contextual filter but section pages aren't wired up yet)
- Map display of event locations
- Recurring event handling (smart_date_recur)
