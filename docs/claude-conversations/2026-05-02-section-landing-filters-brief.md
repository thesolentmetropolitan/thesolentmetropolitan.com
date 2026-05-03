# The Solent Metropolitan — Section Landing Page Filters Brief

## Overview

Add filtered content listings to section landing pages (Culture, Sectors, Living, Explore, About and their sub-pages). The listing displays content (articles and events) filtered by the page's primary topic, with three filter facets: topic (sub-terms), date, and location.

Desktop uses a persistent left sidebar. Mobile uses a sticky filter bar with a slide-up panel.

The implementation is **hierarchy-agnostic** — the same code works at any level of the taxonomy tree. On the Culture page it shows Culture's children as filter options. On the Music page it shows Music's children (genres). The logic is identical at every level.

---

## Architecture

### How the listing works

Each section landing page is a **Composite Page** node with a `field_primary_topic` set to the corresponding Topic taxonomy term (e.g. "Culture", "Culture / Music", "Sectors / Technology").

The listing View:
1. Reads the page's primary topic term ID
2. Shows all published content (Article, Event) where the content's `field_primary_topic` OR `field_related_topics` matches that term **or any of its descendant terms**
3. Allows filtering by sub-terms (children of the current term), date, and location

### Hierarchy-agnostic filter logic

The topic filter always shows the **children of the current page's primary topic term**:

| Page | Current term | Filter shows |
|------|-------------|-------------|
| Culture | Culture | Music, Screen, Dance, Faith, Heritage, etc. |
| Culture / Music | Culture / Music | Jazz, Classical, Electronic, etc. (sub-sub-terms) |
| Sectors | Sectors | Arts, Construction, Technology, Education, etc. |
| Sectors / Technology | Sectors / Technology | (children if any, otherwise hidden) |
| Explore | Explore | Archive, Articles, Series, Events, etc. |
| About | About | Why?, Editorial Policy, Our Team, etc. |

If the current term has **no children**, the topic filter section is hidden entirely. No special casing — the template simply doesn't render the filter section when there are zero options.

---

## Desktop Layout

### Structure

```
┌─────────────────────────────────────────────────────┐
│ Navigation bar                                       │
├─────────────────────────────────────────────────────┤
│ Hero art banner with topic trail (Culture > Music)   │
├──────────────┬──────────────────────────────────────┤
│              │                                       │
│  SIDEBAR     │  CONTENT LISTING                      │
│              │                                       │
│  Topic       │  ─────────────────────────────        │
│  · All       │  Jazz on the Seafront                 │
│  · Jazz      │  Free evening of live jazz...         │
│  · Classical │  Sat 26 April · Southsea              │
│  · Folk      │  ─────────────────────────────        │
│  · Electronic│  Southampton Philharmonic             │
│              │  Brahms and Dvořák...                  │
│  Date        │  Sun 4 May · Turner Sims              │
│  · All       │  ─────────────────────────────        │
│  · This week │  Funk Format: Monthly                 │
│  · Weekend   │  Portsmouth's funk night...           │
│  · This month│  Fri 9 May · The Loft                 │
│              │  ─────────────────────────────        │
│  Location    │                                       │
│  · All areas │                                       │
│  · Soton     │                                       │
│  · Pompey    │                                       │
│  · Fareham   │                                       │
│              │                                       │
├──────────────┴──────────────────────────────────────┤
│ Footer                                               │
└─────────────────────────────────────────────────────┘
```

### CSS layout

```css
/* ══════════════════════════════════════
   Section landing page — filtered listing
   ══════════════════════════════════════ */

.slnt-section-listing {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--content-pad, 2rem);
}

/* Desktop: sidebar + content */
@media (min-width: 800px) {
  .slnt-section-listing__layout {
    display: flex;
    gap: 2rem;
  }

  .slnt-section-listing__sidebar {
    width: 220px;
    flex-shrink: 0;
    position: sticky;
    top: 1rem;
    align-self: flex-start;
  }

  .slnt-section-listing__content {
    flex: 1;
    min-width: 0;
  }
}

/* Mobile: stack, sidebar hidden (replaced by filter panel) */
@media (max-width: 799px) {
  .slnt-section-listing {
    padding: 0 var(--content-pad-mobile, 1.2rem);
  }

  .slnt-section-listing__sidebar {
    display: none;
  }
}
```

### Sidebar styling

```css
/* ── Filter sidebar ── */
.slnt-filter-section {
  margin-bottom: 1.5rem;
}

.slnt-filter-section__title {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #888;
  margin-bottom: 0.5rem;
}

.slnt-filter-item {
  display: block;
  padding: 0.4rem 0.6rem;
  margin-bottom: 0.15rem;
  border-radius: 4px;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--text, #1a1a1a);
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s;
}

.slnt-filter-item:hover {
  background: var(--section-color-pale, #f3e8ff);
  color: var(--section-color, #7C3AED);
}

.slnt-filter-item.is-active {
  background: var(--section-color, #7C3AED);
  color: white;
  font-weight: 600;
}

.slnt-filter-item__count {
  float: right;
  font-size: 0.68rem;
  color: #aaa;
  font-weight: 400;
}

.slnt-filter-item.is-active .slnt-filter-item__count {
  color: rgba(255, 255, 255, 0.7);
}

/* Date filter pills */
.slnt-date-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.slnt-date-pill {
  padding: 0.35em 0.8em;
  border: 2px solid #ddd;
  border-radius: 100px;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--text-mid, #444);
  cursor: pointer;
  transition: all 0.15s;
  background: transparent;
  text-decoration: none;
}

.slnt-date-pill:hover {
  border-color: var(--section-color, #7C3AED);
  color: var(--section-color, #7C3AED);
}

.slnt-date-pill.is-active {
  background: var(--section-color, #7C3AED);
  border-color: var(--section-color, #7C3AED);
  color: white;
  font-weight: 600;
}
```

### Section colour CSS variables

The sidebar filter highlight colours should match the current section. Set CSS variables on the listing wrapper based on the top-level parent term:

```css
/* Set per section via Twig inline style or class */
.slnt-section-listing[data-section="culture"] {
  --section-color: #7C3AED;
  --section-color-pale: #f3e8ff;
}
.slnt-section-listing[data-section="sectors"] {
  --section-color: #2563EB;
  --section-color-pale: #dbeafe;
}
.slnt-section-listing[data-section="living"] {
  --section-color: #059669;
  --section-color-pale: #d1fae5;
}
.slnt-section-listing[data-section="about"] {
  --section-color: #475569;
  --section-color-pale: #f1f5f9;
}
.slnt-section-listing[data-section="explore"] {
  --section-color: #D97706;
  --section-color-pale: #fef3c7;
}
```

---

## Mobile Layout

### Sticky filter bar

A thin bar that sticks below the navigation (and below the hero art once scrolled past). It shows the current filter state and a "Filter" button.

```css
/* ── Mobile filter bar ── */
.slnt-filter-bar {
  display: none;  /* hidden on desktop */
}

@media (max-width: 799px) {
  .slnt-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    border-bottom: 1px solid #e0e0e0;
    padding: 0.6rem var(--content-pad-mobile, 1.2rem);
    position: sticky;
    top: 38px;  /* below the mobile nav bar */
    z-index: 9;
  }

  .slnt-filter-bar__status {
    font-size: 0.78rem;
    color: var(--text-mid, #444);
  }

  .slnt-filter-bar__status strong {
    color: var(--section-color, #7C3AED);
    font-weight: 700;
  }

  .slnt-filter-bar__button {
    display: inline-flex;
    align-items: center;
    gap: 0.4em;
    background: var(--solent-blue, #2c4f6e);
    color: white;
    border: none;
    padding: 0.4em 1em;
    border-radius: 4px;
    font-family: 'Atkinson Hyperlegible Next', sans-serif;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
  }
}
```

### Slide-up filter panel

The panel slides up from the bottom of the viewport, covering ~85% of the screen. A dark overlay dims the content behind.

```css
/* ── Filter panel overlay ── */
.slnt-filter-overlay {
  display: none;
}

@media (max-width: 799px) {
  .slnt-filter-overlay {
    display: block;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    z-index: 100;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
  }

  .slnt-filter-overlay.is-open {
    opacity: 1;
    pointer-events: all;
  }
}

/* ── Filter panel ── */
.slnt-filter-panel {
  display: none;
}

@media (max-width: 799px) {
  .slnt-filter-panel {
    display: flex;
    flex-direction: column;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-radius: 16px 16px 0 0;
    z-index: 101;
    transform: translateY(100%);
    transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
    max-height: 85vh;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
  }

  .slnt-filter-panel.is-open {
    transform: translateY(0);
  }

  /* Grab handle */
  .slnt-filter-panel__handle {
    padding: 0.8rem 0 0.3rem;
    text-align: center;
  }

  .slnt-filter-panel__handle-bar {
    width: 36px;
    height: 4px;
    background: #ccc;
    border-radius: 2px;
    display: inline-block;
  }

  /* Panel header */
  .slnt-filter-panel__header {
    padding: 0.3rem 1.2rem 0.8rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e8e8e8;
    flex-shrink: 0;
  }

  .slnt-filter-panel__title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--solent-blue, #2c4f6e);
  }

  .slnt-filter-panel__clear {
    font-size: 0.78rem;
    color: var(--section-color, #7C3AED);
    font-weight: 600;
    cursor: pointer;
    background: none;
    border: none;
    font-family: 'Atkinson Hyperlegible Next', sans-serif;
  }

  /* Panel body — scrollable */
  .slnt-filter-panel__body {
    padding: 1rem 1.2rem;
    overflow-y: auto;
    flex: 1;
  }

  /* Panel footer — Apply button */
  .slnt-filter-panel__footer {
    padding: 0.8rem 1.2rem;
    border-top: 1px solid #e8e8e8;
    flex-shrink: 0;
  }

  .slnt-filter-panel__apply {
    width: 100%;
    padding: 0.75em;
    background: var(--solent-blue, #2c4f6e);
    color: white;
    border: none;
    border-radius: 4px;
    font-family: 'Atkinson Hyperlegible Next', sans-serif;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
  }
}
```

### JavaScript for panel toggle

```javascript
/**
 * Mobile filter panel toggle.
 */
(function () {
  'use strict';

  const filterButton = document.querySelector('.slnt-filter-bar__button');
  const filterOverlay = document.querySelector('.slnt-filter-overlay');
  const filterPanel = document.querySelector('.slnt-filter-panel');
  const applyButton = document.querySelector('.slnt-filter-panel__apply');

  if (!filterButton || !filterPanel) return;

  function openPanel() {
    filterOverlay.classList.add('is-open');
    filterPanel.classList.add('is-open');
    document.body.style.overflow = 'hidden'; // prevent background scroll
  }

  function closePanel() {
    filterOverlay.classList.remove('is-open');
    filterPanel.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  filterButton.addEventListener('click', openPanel);
  filterOverlay.addEventListener('click', closePanel);
  if (applyButton) {
    applyButton.addEventListener('click', closePanel);
  }
})();
```

Place this in a new file: `js/filter-panel.js` and register it in `customsolent.libraries.yml`.

---

## Views Configuration

### View: Section content listing

- **View name:** Section content listing
- **Machine name:** `section_content_listing`
- **Show:** Content (Article, Event)
- **Display:** Block display, rendered directly in the composite page node template. Not embedded via viewreference — the template handles placement and the two-column sidebar layout.

### Fields to display

1. **Content type indicator** — "Article" or "Event" label (optional, useful when mixing types)
2. **Title** — linked to the node for articles, not linked for events (rabbit_hole)
3. **Standfirst** (field_standfirst)
4. **Date** — `node.created` for articles, `field_when` for events. Display as "23 April 2026" for articles, "Saturday 26 April 2026, 7:30 PM" for events.
5. **Location** (field_where) — events only
6. **External link** (field_event_url) — events only, CTA button style
7. **Image thumbnail** — if present (deferred until images are in use)

### Sort order

- Events: `field_when` ascending (soonest first) for upcoming events, descending (most recent first) for past events
- Articles: `node.created` descending (newest first)
- If mixing both content types, consider two separate view displays or a combined view sorted by relevance/date

### Past events

Past events should be **retained and visible**, not hidden. The site is curating a sense of scene and continuity — showing that events have been happening builds confidence that the region is culturally active and that scenes have momentum. Past events entered retrospectively (before the site existed) are also valuable for this purpose.

**Display logic:**

- **Upcoming events** appear first in the listing (default view)
- **Past events** appear in a separate section or are accessible via a date filter option ("Past events")
- Past events are visually marked so readers understand they've already happened

**Visual marking for past events in Twig:**

```twig
{% set is_past = (event_date < 'now'|date('U')) %}

<div class="slnt-event {{ is_past ? 'slnt-event--past' : '' }}">
  {% if is_past %}
    <span class="slnt-event__past-label">Past event</span>
  {% endif %}
  {# ... rest of event display ... #}
</div>
```

```css
/* Past event visual treatment */
.slnt-event--past {
  opacity: 0.75;
}

.slnt-event__past-label {
  display: inline-block;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-mid, #444);
  background: var(--warm-grey, #f5f3f0);
  padding: 0.2em 0.6em;
  border-radius: 3px;
  margin-bottom: 0.3rem;
}
```

**Date filter options for events (updated):**

- All upcoming (default)
- This week
- This weekend
- This month
- **Past events** — shows past events, most recent first

For articles, no age marking is needed for MVP. This can be added later (e.g. "This article is more than 2 years old") when the archive is deep enough for it to matter.

### Adding retrospective past events

Editors can create Event nodes with past dates in the `field_when` (Smart Date) field. Smart Date allows arbitrary date entry, not just future dates. These events will automatically appear in the "Past events" filter and be marked with the "Past event" label. No special handling needed — the date comparison in the template and the Views filter handle it.

### Contextual filter

- **Taxonomy term ID** from `field_primary_topic`
- **Depth:** Include child terms (critical — this is what makes the hierarchy-agnostic filtering work). If the contextual argument is the Culture term ID, the view shows all content tagged with Culture OR any of its descendants.
- **Default value:** Provided by the custom contextual filter plugin (from the taxonomy brief) or derived from the host page's `field_primary_topic`

### Exposed filters (for the sidebar / filter panel)

**Topic filter:**
- Filter on `field_primary_topic` taxonomy term
- Exposed to visitors
- **Limit options to children of the contextual argument term** — this is the key configuration. On the Culture page, only Culture's children appear. On the Music page, only Music's children appear.
- Display as: links (for sidebar) or radios (for mobile panel)
- Include an "All" option that clears the topic filter (showing all content under the current term)
- Show content counts next to each option

**Date filter:**
- For events: filter on `field_when` (Smart Date)
- For articles: filter on `node.created`
- Exposed as preset options: "All upcoming" (default), "This week", "This weekend", "This month", "Past events"
- Implementation: custom exposed filter plugin or grouped filter with preset date ranges
- "All upcoming" for events means `field_when >= now`
- "Past events" for events means `field_when < now`, sorted most recent first
- "All" for articles means no date restriction

**Location filter:**
- Filter on the Location taxonomy reference field (if present on the content type)
- Exposed to visitors
- Limit options to area-level terms (top-level Location taxonomy terms)
- Display as checkboxes (multi-select — user can select multiple areas)
- Include an "All areas" option
- Include child terms in the filter (selecting "Fareham" includes content at Fareham Live, etc.)

### AJAX (optional enhancement)

For MVP, the filters can use standard form submission (page reload). This works with Views exposed filters out of the box.

For a smoother experience later, enable **Views AJAX** so the listing updates without a full page reload. This is a single checkbox in the View's advanced settings. The sidebar and filter panel would submit via AJAX, and only the content listing refreshes.

---

## Preprocess: Building the filter data

The sidebar and mobile panel need to know:
1. The children of the current page's primary topic (for the topic filter options)
2. The top-level parent term name (for the section colour)
3. Content counts per child term (optional for MVP — can be added later)

### Preprocess function

Add to `customsolent.theme` or extend the existing topic trail preprocess:

```php
/**
 * Build filter data for section landing pages.
 */
function _customsolent_build_section_filters($term) {
  $section_colors = [
    'Culture' => 'culture',
    'Sectors' => 'sectors',
    'Living'  => 'living',
    'About'   => 'about',
    'Explore' => 'explore',
  ];

  // Walk up to find top-level parent
  $chain = [];
  $current = $term;
  while ($current) {
    array_unshift($chain, $current);
    $parents = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadParents($current->id());
    $current = !empty($parents) ? reset($parents) : null;
  }

  $top_level_name = $chain[0]->getName();
  $section_key = $section_colors[$top_level_name] ?? 'about';

  // Load children of the current term
  $children = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadChildren($term->id());

  $filter_options = [];
  foreach ($children as $child) {
    $label = _customsolent_strip_term_prefix($child->getName());
    $filter_options[] = [
      'tid'   => $child->id(),
      'label' => $label,
      'url'   => _customsolent_build_term_url_from_term($child),
    ];
  }

  // Sort by weight
  usort($filter_options, function ($a, $b) {
    return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
  });

  return [
    'section_key'    => $section_key,
    'filter_options' => $filter_options,
    'has_filters'    => !empty($filter_options),
  ];
}
```

### Pass to templates

In the composite page node preprocess, call this function and pass the results to the template:

```php
/**
 * Implements hook_preprocess_node() for composite_page.
 */
function customsolent_preprocess_node__composite_page(&$variables) {
  $node = $variables['node'];

  if ($node->hasField('field_primary_topic') && !$node->get('field_primary_topic')->isEmpty()) {
    $term = $node->get('field_primary_topic')->entity;
    $variables['section_filters'] = _customsolent_build_section_filters($term);
  }
}
```

The Twig template then renders the sidebar and mobile panel using this data.

---

## Composite Page Template: Two-Column Layout

**File:** `web/themes/custom/customsolent/templates/content/node--composite-page--full.html.twig`

The composite page template creates the page structure:

1. **Editorial paragraphs** (hero art, text, CTAs, etc.) — rendered from the paragraph reference field as they are now
2. **Filtered listing section** — a two-column layout with sidebar (desktop) / filter panel (mobile) and the View block

The listing appears **below** the editorial paragraphs on every composite page that has a `field_primary_topic`. Pages without a primary topic (if any) skip the listing section entirely.

### Template structure

```twig
{%
  set classes = [
    'node',
    'node--type-' ~ node.bundle|clean_class,
    view_mode ? 'node--view-mode-' ~ view_mode|clean_class,
  ]
%}

<article{{ attributes.addClass(classes) }}>

  {# ── Editorial paragraphs (hero art, text, CTAs, etc.) ── #}
  <div class="slnt-composite-editorial">
    {{ content.field_content }}
  </div>

  {# ── Filtered content listing (only if primary topic is set) ── #}
  {% if section_filters is defined and section_filters.has_filters %}
    <section class="slnt-section-listing" data-section="{{ section_filters.section_key }}" aria-label="Content listing">

      {# Mobile filter bar #}
      <div class="slnt-filter-bar">
        <div class="slnt-filter-bar__status">
          Showing: <strong>All</strong>
        </div>
        <button class="slnt-filter-bar__button" type="button" aria-expanded="false" aria-controls="slnt-filter-panel">
          <svg class="slnt-filter-bar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
            <line x1="4" y1="6" x2="20" y2="6"/>
            <line x1="4" y1="12" x2="16" y2="12"/>
            <line x1="4" y1="18" x2="12" y2="18"/>
          </svg>
          Filter
        </button>
      </div>

      <div class="slnt-section-listing__layout">

        {# ── Desktop sidebar ── #}
        <aside class="slnt-section-listing__sidebar" aria-label="Filter options">
          {% include '@customsolent/components/filter-sidebar.html.twig' with {
            filter_options: section_filters.filter_options,
            section_key: section_filters.section_key,
          } %}
        </aside>

        {# ── Content listing (View block) ── #}
        <div class="slnt-section-listing__content">
          {{ drupal_view('section_content_listing', 'block_1') }}
        </div>

      </div>
    </section>

    {# ── Mobile filter overlay and panel ── #}
    <div class="slnt-filter-overlay" aria-hidden="true"></div>
    <div class="slnt-filter-panel" id="slnt-filter-panel" role="dialog" aria-label="Filter options" aria-modal="true">
      {% include '@customsolent/components/filter-panel.html.twig' with {
        filter_options: section_filters.filter_options,
        section_key: section_filters.section_key,
      } %}
    </div>

  {% endif %}

</article>
```

**Notes on the template:**

- `{{ content.field_content }}` renders the editorial paragraphs (hero art, text, CTAs). Adjust the field name to match your actual paragraph reference field on the composite page.
- `{{ drupal_view('section_content_listing', 'block_1') }}` renders the View block. This uses **Twig Tweak**'s `drupal_view()` function — verify Twig Tweak is installed. If not, use Drupal's block placement system instead and render the block via the template.
- The `data-section` attribute on `.slnt-section-listing` sets the CSS variables for section colours.
- The mobile filter panel and overlay sit outside the two-column layout so they can be positioned fixed to the viewport.
- `aria-controls`, `aria-expanded`, `aria-modal`, and `role="dialog"` provide proper accessibility for the mobile panel.
- The `<aside>` element with `aria-label="Filter options"` identifies the sidebar as a complementary landmark for screen readers.
- If `section_filters` is undefined or has no filter options (no children of the current term), the entire listing section is skipped — the page just shows the editorial paragraphs.

### Filter sidebar component

**File:** `web/themes/custom/customsolent/templates/components/filter-sidebar.html.twig`

```twig
{# Topic filter #}
{% if filter_options is not empty %}
  <div class="slnt-filter-section">
    <div class="slnt-filter-section__title">Topic</div>
    <a href="?" class="slnt-filter-item is-active">All</a>
    {% for option in filter_options %}
      <a href="?topic={{ option.tid }}" class="slnt-filter-item">
        {{ option.label }}
      </a>
    {% endfor %}
  </div>
{% endif %}

{# Date filter #}
<div class="slnt-filter-section">
  <div class="slnt-filter-section__title">Date</div>
  <div class="slnt-date-pills">
    <a href="?date=upcoming" class="slnt-date-pill is-active">All upcoming</a>
    <a href="?date=week" class="slnt-date-pill">This week</a>
    <a href="?date=weekend" class="slnt-date-pill">This weekend</a>
    <a href="?date=month" class="slnt-date-pill">This month</a>
    <a href="?date=past" class="slnt-date-pill">Past events</a>
  </div>
</div>

{# Location filter — placeholder, populated when location data is available #}
{#
<div class="slnt-filter-section">
  <div class="slnt-filter-section__title">Location</div>
  <a href="?" class="slnt-filter-item is-active">All areas</a>
  {% for location in location_options %}
    <a href="?location={{ location.tid }}" class="slnt-filter-item">
      {{ location.label }}
    </a>
  {% endfor %}
</div>
#}
```

### Filter panel component (mobile)

**File:** `web/themes/custom/customsolent/templates/components/filter-panel.html.twig`

```twig
<div class="slnt-filter-panel__handle">
  <div class="slnt-filter-panel__handle-bar"></div>
</div>

<div class="slnt-filter-panel__header">
  <div class="slnt-filter-panel__title">Filter</div>
  <button class="slnt-filter-panel__clear" type="button">Clear all</button>
</div>

<div class="slnt-filter-panel__body">
  {# Topic filter #}
  {% if filter_options is not empty %}
    <div class="slnt-filter-section">
      <div class="slnt-filter-section__title">Topic</div>
      <div class="filter-item is-active" data-tid="">
        <div class="filter-item__radio"></div>
        <div class="filter-item__label">All</div>
      </div>
      {% for option in filter_options %}
        <div class="filter-item" data-tid="{{ option.tid }}">
          <div class="filter-item__radio"></div>
          <div class="filter-item__label">{{ option.label }}</div>
        </div>
      {% endfor %}
    </div>
  {% endif %}

  {# Date filter #}
  <div class="slnt-filter-section">
    <div class="slnt-filter-section__title">Date</div>
    <div class="slnt-date-pills">
      <button class="slnt-date-pill is-active" data-date="upcoming">All upcoming</button>
      <button class="slnt-date-pill" data-date="week">This week</button>
      <button class="slnt-date-pill" data-date="weekend">This weekend</button>
      <button class="slnt-date-pill" data-date="month">This month</button>
      <button class="slnt-date-pill" data-date="past">Past events</button>
    </div>
  </div>
</div>

<div class="slnt-filter-panel__footer">
  <button class="slnt-filter-panel__apply" type="button">Apply filters</button>
</div>
```

---

## Location field on content types

The Event content type already has `field_where` as an **entity reference to the Location taxonomy**. Reuse this same field on the Article content type (and any future content types like Author, Link, Organisation):

- In the Article content type field configuration, choose **"Re-use an existing field"** and select `field_where`
- The field storage is shared across content types; widget settings and help text can differ per type
- The location filter in the View references `field_where` uniformly across all content types — no OR conditions or multiple field handling needed

---

## File structure

| File | Purpose |
|------|---------|
| `templates/content/node--composite-page--full.html.twig` | Composite page template — editorial paragraphs above, two-column filtered listing below |
| `templates/components/filter-sidebar.html.twig` | Twig include for desktop sidebar filter sections |
| `templates/components/filter-panel.html.twig` | Twig include for mobile slide-up panel content |
| `css/section-listing.css` | Two-column layout, sidebar, filter items, date pills |
| `css/filter-panel.css` | Mobile slide-up panel, overlay, filter bar |
| `js/filter-panel.js` | Panel open/close toggle, body scroll lock |

All paths relative to `web/themes/custom/customsolent/`. Register CSS and JS in `customsolent.libraries.yml`.

---

## Implementation order

| Step | Task | Priority |
|------|------|----------|
| 1 | Reuse `field_where` (entity reference to Location) on Article content type | Now |
| 2 | Create the View (section_content_listing) with contextual filter on primary topic, depth enabled, block display | Now — MVP |
| 3 | Create/update `node--composite-page--full.html.twig` with editorial paragraphs above and two-column listing layout below | Now — MVP |
| 4 | Create `filter-sidebar.html.twig` and `filter-panel.html.twig` Twig components | Now — MVP |
| 5 | Add preprocess function to build section filter data from `field_primary_topic` | Now — MVP |
| 6 | Create `css/section-listing.css` for desktop two-column layout and sidebar styling | Now — MVP |
| 7 | Create `css/filter-panel.css` and `js/filter-panel.js` for mobile slide-up panel | Now — MVP |
| 8 | Add exposed topic filter (children of current term) wired to the View | Now — MVP |
| 9 | Add date filter (preset ranges including "Past events") | Soon after MVP |
| 10 | Add location filter (area-level terms from Location taxonomy) | Soon after MVP |
| 11 | Add content counts to filter items | Later |
| 12 | Enable Views AJAX for smooth updates without page reload | Later |
| 13 | Add `field_filterable` boolean to Topic vocabulary to hide non-content terms | Later |

---

## Testing

1. **Composite page structure:** Verify that editorial paragraphs (hero art, text, CTAs) render above the filtered listing. Both sections should be visible on the same page without conflict.
2. **Culture page:** Shows all content tagged with Culture or any Culture sub-term. Topic filter lists Music, Screen, Dance, etc. Selecting "Music" narrows to Music content only.
2. **Culture / Music page:** Shows all Music content. Topic filter lists Music's children (genres) if any exist. If no children, topic filter section is hidden.
3. **Sectors page:** Same pattern — shows Sectors content, filter lists Sectors sub-terms, section colour is blue.
4. **Explore page:** Shows Explore content, filter lists Archive, Articles, Series, etc. Section colour is amber.
5. **About page:** Shows About content (if any). Most About sub-terms won't have tagged content, so the filter is sparse — this is expected and acceptable.
6. **Mobile filter bar:** Visible below 800px, shows current filter state, "Filter" button opens the panel.
7. **Mobile panel:** Slides up smoothly, scrollable if content exceeds panel height, "Apply" closes panel, overlay click closes panel, body scroll is locked while panel is open.
8. **Section colours:** Filter highlights use the correct section colour (purple for Culture, blue for Sectors, etc.).
9. **No content:** "No results" message when filters produce zero results.
11. **Mixed content types:** If both articles and events appear in the listing, verify dates display correctly for each type.
12. **No primary topic:** Composite pages without `field_primary_topic` set should display editorial paragraphs only — no listing section, no sidebar, no errors.
13. **Twig Tweak:** Verify Twig Tweak module is enabled — the template uses `drupal_view()` to render the View block. If Twig Tweak is not available, Claude Code should use an alternative approach (block placement or custom render).

---

## What this does NOT cover (deferred — separate briefs)

- **Teaser view mode styling and images** — the listing items' visual presentation (thumbnail images, teaser layout) is a separate brief. This brief handles the listing structure, filtering, and layout. The teaser styling plugs in independently without changes to the filter system.
- **Bitmap placeholder images** from hero art tiles at various aspect ratios (16:9 for articles, 1:1 and 9:16 for events) — separate brief
- Facets module integration (AJAX filtering without page reload)
- Content counts next to filter items
- `field_filterable` boolean to hide non-content terms from filters
- Map view of events by location
- Series sub-term structure under Explore
- Article age marking ("This article is more than X years old")
- Recurring event handling (smart_date_recur)
