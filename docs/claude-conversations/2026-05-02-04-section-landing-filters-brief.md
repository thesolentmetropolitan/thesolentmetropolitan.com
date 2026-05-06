# The Solent Metropolitan — Section Landing Page Filters Brief

## Overview

Add filtered content listings to section landing pages (Culture, Sectors, Living, Explore, About and their sub-pages). Content is displayed using **separate Views per content type** (articles, events, and later organisations, links, etc.), embedded on pages via the existing **View Display paragraph** type.

Desktop uses a persistent left sidebar for filters. Mobile uses a sticky filter bar with a slide-up panel. Both are deferred until after the basic listings are working.

The implementation is **hierarchy-agnostic** — the same code works at any level of the taxonomy tree. On the Culture page it shows Culture's children as filter options. On the Music page it shows Music's children (genres). The logic is identical at every level.

**Drupal version:** 11. All preprocess code and module usage must be compatible with Drupal 11.

---

## Architecture

### How the listing works

Each section landing page is a **Composite Page** node with a `field_primary_topic` set to the corresponding Topic taxonomy term (e.g. "Culture", "Culture / Music", "Sectors / Technology").

The editor builds each page by adding **View Display paragraphs** (using the existing paragraph type with `field_view`), choosing which View and which display to embed. Each paragraph becomes one listing block on the page.

The **preprocess function** on the View Display paragraph reads the parent node's `field_primary_topic`, collects the term and its descendants, and passes them as the contextual filter argument to the View.

### Separate Views per content type

Each content type has its own View. Each View has two block displays — one filtering on `field_primary_topic`, one on `field_related_topics`:

| View machine name | Content type | Display: primary | Display: related |
|-------------------|-------------|-----------------|-----------------|
| `articles_listing` | Article | `view_display_primary_topic` | `view_display_related_topics` |
| `events_listing` | Event | `view_display_primary_topic` | `view_display_related_topics` |

Future content types (Organisation, Link, Author) follow the same pattern — add a new View with two displays.

### Why separate Views per content type

- **Sort order differs:** Articles sort by authored date (desc). Events sort by event date (asc for upcoming).
- **Teaser display differs:** Each content type has its own teaser view mode with different fields and layout.
- **No conditional logic needed:** No Twig or PHP to determine "is this an article or event" — each View already knows.
- **Scalable:** Adding a new content type means adding a new View. Existing Views don't change.

### Editor workflow

To build a Culture / Music landing page, the editor adds paragraphs:

1. **Hero art paragraph** — Music hero banner with topic trail
2. **View Display paragraph** — View: `events_listing`, Display: `view_display_primary_topic` → "Music events"
3. **View Display paragraph** — View: `articles_listing`, Display: `view_display_primary_topic` → "Music articles"
4. **View Display paragraph** — View: `articles_listing`, Display: `view_display_related_topics` → "Related articles"

A different page might order them differently, include only events, or add editorial paragraphs between the listings. The editor controls the page composition.

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

### Related content heading

```css
/* ── Related content section ── */
.slnt-section-listing__related-heading {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--solent-blue, #2c4f6e);
  margin-top: 2.5rem;
  margin-bottom: 1rem;
  padding-top: 2rem;
  border-top: 1px solid #e0e0e0;
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

### Separate Views per content type

Each content type gets its own View. Each View has two block displays. The contextual filter configuration is identical across all Views — only the content type filter and sort order differ.

---

### View: Articles listing

- **View name:** Articles listing
- **Machine name:** `articles_listing`
- **Show:** Content
- **Content type filter:** Article only

**Display: view_display_primary_topic**
- Format: Unformatted list | Show: Content | Teaser
- Filter criteria: Content: Published (= Yes), Content: Type (= Article)
- Sort criteria: Content: Authored on (desc) — newest first
- Contextual filter: Content: primary topic (field_primary_topic)
  - When NOT available: Display all results for the specified field
  - When IS available: Taxonomy term validation → Topic vocabulary
  - Multiple arguments: "One or more IDs separated by , or +"
  - Under "More": Allow multiple values — enabled
  - Action if does not validate: Hide view
- Pager: 20 items
- No results: "No articles in this section yet. Check back soon."

**Display: view_display_related_topics**
- Same as above but contextual filter on: Content: related topics (field_related_topics)
- Pager: 10 items
- No results: (leave empty — paragraph handles the absence)

---

### View: Events listing

- **View name:** Events listing
- **Machine name:** `events_listing`
- **Show:** Content
- **Content type filter:** Event only

**Display: view_display_primary_topic**
- Format: Unformatted list | Show: Content | Teaser
- Filter criteria: Content: Published (= Yes), Content: Type (= Event)
- Sort criteria: Content: when (asc) — soonest first
- Contextual filter: Content: primary topic (field_primary_topic)
  - When NOT available: Display all results for the specified field
  - When IS available: Taxonomy term validation → Topic vocabulary
  - Multiple arguments: "One or more IDs separated by , or +"
  - Under "More": Allow multiple values — enabled
  - Action if does not validate: Hide view
- Pager: 20 items
- No results: "No events in this section yet. Check back soon."

**Display: view_display_related_topics**
- Same as above but contextual filter on: Content: related topics (field_related_topics)
- Pager: 10 items
- No results: (leave empty)

---

### The existing section_content_listing View

The `section_content_listing` View you've already created can be **kept for reference or deleted**. It's been superseded by the per-content-type Views above. If you keep it, it could serve as a "show everything" view for a future use case. If you delete it, no code depends on it.

---

### How the contextual filter argument reaches the View

The View Display paragraph's preprocess function reads the parent node's `field_primary_topic`, collects the term and its descendants, and passes the term IDs as a `+` separated string to the View as its contextual filter argument.

```php
$term_ids = _customsolent_get_term_with_descendants($term->id());
// Results in e.g. "5+12+13+14+27" for Culture and all its children
```

The viewreference module renders the View. The preprocess function overrides the argument before rendering. See the "Paragraph preprocess" section below for the full implementation.

---

### Teaser view mode configuration

The View renders each content item using its Teaser view mode. Configure the field display for each content type:

**Article teaser** — `/admin/structure/types/manage/article/display/teaser`:

| Field | Visible | Label | Formatter / notes |
|-------|---------|-------|-------------------|
| Title | Yes | (automatic) | Linked to node |
| field_standfirst | Yes | Hidden | Plain text |
| field_image | Yes | Hidden | Image, style: article_teaser (16:9). Hide if empty. |
| Body | No | — | Drag to Disabled |
| field_primary_topic | No | — | Drag to Disabled |
| field_related_topics | No | — | Drag to Disabled |
| field_where | No | — | Drag to Disabled (or show if you want location on articles) |

**Field order in teaser:** image → title → standfirst

**Event teaser** — `/admin/structure/types/manage/event/display/teaser`:

| Field | Visible | Label | Formatter / notes |
|-------|---------|-------|-------------------|
| Title | Yes | (automatic) | Not linked (rabbit_hole blocks node access) |
| field_standfirst | Yes | Hidden | Plain text |
| field_when | Yes | Hidden | Smart Date default formatter |
| field_where | Yes | Hidden | Entity reference label (shows the Location term name) |
| field_url | Yes | Hidden | Link — use link text provided by editor |
| field_image | Yes | Hidden | Image, style: event_thumbnail (300×300). Hide if empty. |
| Body | No | — | Drag to Disabled |
| field_primary_topic | No | — | Drag to Disabled |
| field_related_topics | No | — | Drag to Disabled |

**Field order in teaser:** image → title → standfirst → when → where → url

**Note:** The image styles (article_teaser, event_thumbnail) should already exist from the article styling brief. If event_thumbnail (300×300) hasn't been created yet, create it at `/admin/config/media/image-styles` with a Focal Point Scale and Crop effect at 300×300. Images are optional for MVP — if no image is uploaded, the field simply doesn't render.

---

### Past events

Past events should be **retained and visible**, not hidden. The site is curating a sense of scene and continuity — showing that events have been happening builds confidence that the region is culturally active and that scenes have momentum. Past events entered retrospectively (before the site existed) are also valuable for this purpose.

**Display logic:**

- **Upcoming events** appear first in the listing (default view)
- **Past events** appear in a separate section or are accessible via a date filter option ("Past events")
- Past events are visually marked so readers understand they've already happened

**Visual marking for past events in Twig:**

The event teaser template can check the date and add a label:

```twig
{% set is_past = (event_date < 'now'|date('U')) %}

<div class="slnt-event {{ is_past ? 'slnt-event--past' : '' }}">
  {% if is_past %}
    <span class="slnt-event__past-label">Past event</span>
  {% endif %}
  {# ... rest of event teaser ... #}
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

---

### Exposed filters (for the sidebar / filter panel) — deferred

The topic, date, and location filters in the sidebar and mobile panel are **deferred until after the basic two-listing structure is working**. Get the primary and related listings rendering correctly first, then add filtering.

When ready, the three exposed filters are:

**Topic filter:**
- Filter on `field_primary_topic` taxonomy term
- Exposed to visitors
- Limit options to children of the current page's topic term
- Include an "All" option
- Show content counts next to each option (later enhancement)

**Date filter:**
- For events: filter on `field_when` (Smart Date)
- For articles: filter on `node.created`
- Preset options: "All upcoming", "This week", "This weekend", "This month", "Past events"

**Location filter:**
- Filter on `field_where` (Location taxonomy reference)
- Exposed to visitors
- Limit options to area-level terms (children of Solent in the Location taxonomy)
- Multi-select (checkboxes) — user can select multiple areas
- Include an "All areas" option
- Include child terms (selecting "Fareham" includes content at Fareham Live, etc.)

### AJAX (optional enhancement)

For MVP, the filters can use standard form submission (page reload). For a smoother experience later, enable **Views AJAX** so the listing updates without a full page reload.

---

## Preprocess: View Display Paragraph

The critical piece of custom code. When a View Display paragraph renders, the preprocess function:

1. Reads which View and display the editor selected via `field_view`
2. Walks up to the parent node (the composite page)
3. Reads the parent node's `field_primary_topic`
4. Collects that term's ID plus all descendant IDs
5. Passes the term IDs to the View as the contextual filter argument

### Preprocess function

Add to `customsolent.theme`:

```php
/**
 * Implements hook_preprocess_paragraph() for the View Display paragraph.
 *
 * Reads the parent node's field_primary_topic and passes the term ID
 * (plus all descendant IDs) as the contextual filter argument to the
 * embedded View.
 *
 * Compatible with Drupal 11.
 */
function customsolent_preprocess_paragraph__view_display(&$variables) {
  $paragraph = $variables['paragraph'];

  // Walk up to the host entity (the composite page node)
  $parent = $paragraph->getParentEntity();
  if (!$parent || !$parent->hasField('field_primary_topic') || $parent->get('field_primary_topic')->isEmpty()) {
    return;
  }

  $term = $parent->get('field_primary_topic')->entity;
  if (!$term) {
    return;
  }

  // Collect this term's ID plus all descendant IDs
  $term_ids = _customsolent_get_term_with_descendants($term->id());
  $variables['primary_topic_tid'] = implode('+', $term_ids);

  // Also pass section filter data for sidebar/panel (deferred use)
  $variables['section_filters'] = _customsolent_build_section_filters($term);
}

/**
 * Get a term ID and all its descendant term IDs.
 *
 * @param int $tid
 *   The parent term ID.
 *
 * @return array
 *   Array of term IDs: the parent plus all descendants.
 */
function _customsolent_get_term_with_descendants($tid) {
  $ids = [$tid];
  $children = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadTree('topic', $tid, NULL, FALSE);
  foreach ($children as $child) {
    $ids[] = $child->tid;
  }
  return $ids;
}

/**
 * Build filter data for section landing pages.
 *
 * Returns the section key (for colour coding) and child term options
 * (for the sidebar/panel filters — deferred for MVP).
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

  // Load children of the current term (for filter options)
  $children = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadChildren($term->id());

  $filter_options = [];
  foreach ($children as $child) {
    $label = _customsolent_strip_term_prefix($child->getName());
    $filter_options[] = [
      'tid'   => $child->id(),
      'label' => $label,
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

### How the argument reaches the View

The paragraph template for the View Display paragraph needs to render the optional heading and pass `primary_topic_tid` to the viewreference rendering. There are two approaches for the argument passing:

**Approach A: Override the viewreference argument in the paragraph template.**

The viewreference module renders the View via its field formatter. The paragraph template can intercept the rendering and pass the argument. Create or update:

`web/themes/custom/customsolent/templates/paragraphs/paragraph--view-display.html.twig`

```twig
{%
  set classes = [
    'paragraph',
    'paragraph--type--' ~ paragraph.bundle|clean_class,
    view_mode ? 'paragraph--view-mode--' ~ view_mode|clean_class,
  ]
%}

{% block paragraph %}
  <div{{ attributes.addClass(classes) }}>
    {% block content %}
      {# ── Heading — editor-defined label for this listing ── #}
      {% if content.field_heading|render|striptags|trim %}
        <h2 class="slnt-listing__heading">{{ content.field_heading }}</h2>
      {% endif %}

      {#
        The viewreference field renders the selected View and display.
        We need to pass primary_topic_tid as the contextual argument.

        If the viewreference module supports argument passing via its
        field formatter settings, use that. Otherwise, use drupal_view()
        directly with the view name and display from the field values.
      #}
      {% if primary_topic_tid is defined and primary_topic_tid %}
        {#
          Read the view name and display from the field_view value.
          The viewreference field stores: view_id and display_id.
        #}
        {% set view_name = paragraph.field_view.0.target_id %}
        {% set display_id = paragraph.field_view.0.display_id %}
        {{ drupal_view(view_name, display_id, primary_topic_tid) }}
      {% else %}
        {# Fallback: render without argument (shows all content) #}
        {{ content.field_view }}
      {% endif %}
    {% endblock %}
  </div>
{% endblock paragraph %}
```

**CSS for the listing heading:**

```css
.slnt-listing__heading {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--solent-blue, #2c4f6e);
  margin-bottom: 1rem;
}
```

**Note:** The exact field value structure for `field_view` (viewreference) may differ. Claude Code should inspect the field's stored values to confirm the correct property names (`target_id`, `display_id`, or similar). Enable Twig debug or use `kint()` / `dump()` to inspect `paragraph.field_view.0` and find the correct properties.

**Approach B: Use a hook to alter the View arguments before rendering.**

If the paragraph template approach is too fragile, a `hook_views_pre_view()` or `hook_views_pre_build()` in the theme or a custom module can inject the argument. This is more robust but requires Claude Code to determine the correct hook and context detection. Approach A (paragraph template) is simpler and should be tried first.

---

## Design Notes

### Filter is displayed once per page

When the sidebar/filter panel is implemented (deferred), it should appear **once** on the page, not repeated per View Display paragraph. The filter applies to all listings on the page since they all share the same contextual filter argument (the page's primary topic). When the user filters by a sub-term or location, all listings update. The sidebar would live either in the composite page template (wrapping the paragraph content area) or as its own paragraph placed once by the editor.

### Headings per listing

Each View Display paragraph has an optional `field_heading` where the editor enters the label for that listing — "Events", "Articles", "Organisations", "Related reading", etc. This gives the editor full control over how each listing is introduced. If the heading field is empty, no heading renders.

### View Display paragraph works with any View

The paragraph preprocess sets `primary_topic_tid` as a variable, and the template passes it as an argument to `drupal_view()`. **Views that don't have a contextual filter simply ignore the extra argument.** Drupal Views discard arguments that don't match a configured contextual filter.

This means the same View Display paragraph type can be used for:
- Topic-filtered listings (articles, events, organisations, links) — the argument is used
- Generic listings (latest news, staff directory, featured content) — the argument is ignored
- Any other View — safe regardless of configuration

**No separate paragraph type is needed for non-topic-filtered views.** The one edge case to be aware of: if a different View has its own contextual filter expecting a different type of argument (e.g. a user ID), the term ID string would fail validation. If that View's validation failure is set to "Hide view," the View would disappear. The fix is to set that View's validation failure action to "Display all results" instead. This is unlikely to arise in practice.

---

## Composite Page Template

**File:** `web/themes/custom/customsolent/templates/content/node--composite-page--full.html.twig`

The composite page template is now simpler — it just renders paragraphs. The View Display paragraphs handle their own listing rendering with the contextual filter argument.

```twig
{%
  set classes = [
    'node',
    'node--type-' ~ node.bundle|clean_class,
    view_mode ? 'node--view-mode-' ~ view_mode|clean_class,
  ]
%}

<article{{ attributes.addClass(classes) }}>

  {# ── All paragraphs — hero art, text, CTAs, View Display paragraphs, etc. ── #}
  <div class="slnt-composite-content">
    {{ content.field_content }}
  </div>

  {# ── Optional: editorial paragraphs below (future — field_content_below) ── #}
  {% if content.field_content_below is defined and content.field_content_below|render|striptags|trim %}
    <div class="slnt-composite-content slnt-composite-content--below">
      {{ content.field_content_below }}
    </div>
  {% endif %}

</article>
```

**Notes:**
- `field_content` is the existing paragraph reference field. Adjust the name to match your actual field.
- The View Display paragraphs are just paragraphs within `field_content` — the editor places them wherever they want in the paragraph order.
- `field_content_below` doesn't exist yet — add when needed. The template handles its absence.
- The two-column sidebar layout (for filters) will be handled within the View Display paragraph template or a wrapper component, not in the composite page template. This keeps the composite page template clean and generic.

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
| `templates/content/node--composite-page--full.html.twig` | Composite page template — renders paragraphs (simplified) |
| `templates/paragraphs/paragraph--view-display.html.twig` | View Display paragraph — passes contextual argument to the embedded View |
| `templates/components/filter-sidebar.html.twig` | Twig include for desktop sidebar filter sections (deferred) |
| `templates/components/filter-panel.html.twig` | Twig include for mobile slide-up panel content (deferred) |
| `css/section-listing.css` | Listing layout and styling |
| `css/filter-panel.css` | Mobile slide-up panel, overlay, filter bar (deferred) |
| `js/filter-panel.js` | Panel open/close toggle, body scroll lock (deferred) |
| `customsolent.theme` | Preprocess function for paragraph--view_display |

All paths relative to `web/themes/custom/customsolent/`. Register CSS and JS in `customsolent.libraries.yml`.

---

## Checklist: What Rob should have configured in Drupal admin

Export the View configs and teaser view mode configs so Claude Code can verify. Here's what should be in place:

### View: articles_listing

- [ ] View machine name: `articles_listing`
- [ ] Show: Content
- [ ] Two block displays: `view_display_primary_topic` and `view_display_related_topics`

**Display: view_display_primary_topic**
- [ ] Format: Unformatted list
- [ ] Show: Content | Teaser
- [ ] Filter criteria: Content: Published (= Yes)
- [ ] Filter criteria: Content: Type (= Article)
- [ ] Sort criteria: Content: Authored on (desc)
- [ ] Contextual filter: Content: primary topic (field_primary_topic)
  - [ ] When NOT available: Display all results for the specified field
  - [ ] When IS available: Specify validation → Taxonomy term → Topic vocabulary
  - [ ] Multiple arguments (under validation): "One or more IDs separated by , or +"
  - [ ] Under "More": Allow multiple values — **enabled**
  - [ ] Action if does not validate: Hide view
- [ ] Pager: 20 items
- [ ] No results: "No articles in this section yet. Check back soon."

**Display: view_display_related_topics**
- [ ] Same as above but contextual filter on: Content: related topics (field_related_topics)
- [ ] Pager: 10 items
- [ ] No results: (leave empty)

### View: events_listing

- [ ] View machine name: `events_listing`
- [ ] Show: Content
- [ ] Two block displays: `view_display_primary_topic` and `view_display_related_topics`

**Display: view_display_primary_topic**
- [ ] Format: Unformatted list
- [ ] Show: Content | Teaser
- [ ] Filter criteria: Content: Published (= Yes)
- [ ] Filter criteria: Content: Type (= Event)
- [ ] Sort criteria: Content: when (asc) — soonest event first
- [ ] Contextual filter: Content: primary topic (field_primary_topic)
  - [ ] When NOT available: Display all results for the specified field
  - [ ] When IS available: Specify validation → Taxonomy term → Topic vocabulary
  - [ ] Multiple arguments (under validation): "One or more IDs separated by , or +"
  - [ ] Under "More": Allow multiple values — **enabled**
  - [ ] Action if does not validate: Hide view
- [ ] Pager: 20 items
- [ ] No results: "No events in this section yet. Check back soon."

**Display: view_display_related_topics**
- [ ] Same as above but contextual filter on: Content: related topics (field_related_topics)
- [ ] Pager: 10 items
- [ ] No results: (leave empty)

### Existing section_content_listing View

- [ ] Decision: keep for reference or delete (superseded by per-content-type Views)

### Teaser view mode: Article

Configured at `/admin/structure/types/manage/article/display/teaser`:

- [ ] field_image — Visible, label hidden, formatter: Image, image style: article_teaser (16:9)
- [ ] field_standfirst — Visible, label hidden
- [ ] Body — Disabled (dragged to Disabled section)
- [ ] field_primary_topic — Disabled
- [ ] field_related_topics — Disabled
- [ ] field_where — Disabled (or visible if location on articles is wanted)
- [ ] Field order: image → standfirst (title renders automatically from node template)

### Teaser view mode: Event

Configured at `/admin/structure/types/manage/event/display/teaser`:

- [ ] field_image — Visible, label hidden, formatter: Image, image style: event_thumbnail (300×300). Create this image style if not done yet.
- [ ] field_standfirst — Visible, label hidden
- [ ] field_when — Visible, label hidden, formatter: Smart Date default
- [ ] field_where — Visible, label hidden, formatter: Entity reference label
- [ ] field_url — Visible, label hidden, formatter: Link (uses editor-provided link text)
- [ ] Body — Disabled (if present)
- [ ] field_primary_topic — Disabled
- [ ] field_related_topics — Disabled
- [ ] Field order: image → standfirst → when → where → url (title renders automatically)

### View Display paragraph type

- [ ] `field_view` — viewreference field configured to allow Block displays
- [ ] `field_heading` — Plain text field, optional. Editor enters the heading for each listing (e.g. "Events", "Articles", "Related reading"). **Add this field to the paragraph type.**
- [ ] "Argument" checkbox enabled in the viewreference field settings (under "Enable extra settings")
- [ ] The paragraph type is available within the composite page's paragraph reference field

### Image styles

- [ ] `article_hero` — Focal Point Scale and Crop, 1200×675 (16:9)
- [ ] `article_teaser` — Focal Point Scale and Crop, 400×225 (16:9)
- [ ] `event_thumbnail` — Focal Point Scale and Crop, 300×300 (1:1). Create if not done yet.

---

## Implementation order

| Step | Task | Who | Priority |
|------|------|-----|----------|
| 1 | Create `articles_listing` View with two displays (primary + related), export config | Rob | Now |
| 2 | Create `events_listing` View with two displays (primary + related), export config | Rob | Now |
| 3 | Configure Article teaser view mode, export config | Rob | Now |
| 4 | Configure Event teaser view mode, export config | Rob | Now |
| 5 | Add `field_heading` (plain text, optional) to the View Display paragraph type | Rob | Now |
| 6 | Write paragraph preprocess function (read parent node's topic, pass to View) | Claude Code | Now — MVP |
| 7 | Create/update `paragraph--view-display.html.twig` (pass contextual argument, render heading) | Claude Code | Now — MVP |
| 8 | Simplify `node--composite-page--full.html.twig` (just renders paragraphs) | Claude Code | Now — MVP |
| 9 | Create `css/section-listing.css` for listing layout and heading styling | Claude Code | Now — MVP |
| 10 | Reuse `field_where` on Article content type | Rob | Now |
| 11 | Add View Display paragraphs to a few test composite pages | Rob | Now — test |
| 11 | Create sidebar filter components (Twig, CSS, JS) | Claude Code | Soon after MVP |
| 12 | Create mobile filter panel components | Claude Code | Soon after MVP |
| 13 | Add exposed topic filter to Views | Rob + Claude Code | Soon after MVP |
| 14 | Add date filter | Later |
| 15 | Add location filter | Later |
| 16 | Enable Views AJAX | Later |

---

## Testing

1. **View Display paragraph renders:** Add a View Display paragraph to a composite page (e.g. Culture), select `articles_listing` / `view_display_primary_topic`. Verify articles tagged with Culture or its sub-terms appear.
2. **Events listing:** Add a View Display paragraph with `events_listing` / `view_display_primary_topic`. Verify events appear sorted by event date (soonest first).
3. **Related content:** Add a View Display paragraph with `articles_listing` / `view_display_related_topics`. Verify only articles whose `field_related_topics` contains the page's topic appear.
4. **Contextual filter:** On the Culture page, verify only Culture-related content appears — not all published content. On the Music page, verify only Music-related content appears.
5. **Hierarchy depth:** On the Culture page, verify that content tagged with "Culture / Music" or "Culture / Screen" (child terms) appears, not just content tagged directly with "Culture."
6. **Multiple View Display paragraphs on one page:** Add events listing + articles listing to the same page. Verify both render correctly and independently.
7. **Paragraph ordering:** Verify that editorial paragraphs (hero art, text) and View Display paragraphs render in the order the editor placed them.
8. **Headings:** Verify that when `field_heading` is populated (e.g. "Events"), the heading renders above the listing. When empty, no heading element appears.
9. **Generic View (no topic filter):** Add a View Display paragraph with a View that has no contextual filter. Verify it renders normally — the topic argument is ignored and the View shows its default content.
8. **Empty View:** On a page with no matching content, verify the "No articles/events in this section" message appears (or the paragraph renders empty without errors).
9. **No primary topic on parent:** If the composite page has no `field_primary_topic` set, the View Display paragraph should show all content (fallback) or render empty without errors.
10. **Teaser display — Article:** Verify image, standfirst, and title appear. Body and topic fields are hidden.
11. **Teaser display — Event:** Verify image, standfirst, when, where, and URL link appear. Title is not linked (rabbit_hole).
12. **Past events:** Events with past dates display with "Past event" label and reduced opacity in the teaser.

---

## Future: field_content_below (editorial content below the listing)

When you need editorial paragraphs below the filtered listing (closing statements, calls to action, quotes, "get involved" sections), add a second paragraph reference field to the composite page content type:

- **Field name:** `field_content_below`
- **Type:** Entity reference (paragraphs) — same configuration as `field_content`
- **Required:** No
- **Help text:** "Optional content displayed below the content listing."

The template already includes the code to render this field — it checks if the field has content and only renders it if populated. Pages that don't use it are unaffected.

**Don't create this field now.** Add it when you first have a page that needs content below the listing. It's a single field addition and requires no template changes — the template is already prepared for it.

---

## What this does NOT cover (deferred — separate briefs)

- **Teaser view mode styling and images** — the listing items' visual presentation (thumbnail images, teaser layout) is a separate brief. This brief handles the listing structure, filtering, and layout. The teaser styling plugs in independently without changes to the filter system.
- **Bitmap placeholder images** from hero art tiles at various aspect ratios (16:9 for articles, 1:1 and 9:16 for events) — separate brief
- **Explore special case** — Explore and its sub-pages (Explore/Articles, Explore/Events) need to filter across Culture, Sectors, and Living rather than Explore's own children. This requires a preprocess adjustment to pass combined root term IDs instead of the page's own term descendants. The Views, paragraph type, and template are unaffected — only the argument passed to the View changes. Small preprocess enhancement when Explore pages are built out.
- **Series (replacing Themes)** — child terms under Explore / Series (BHM, Boat Show, etc.) with their own filtering. Standard architecture, just needs the terms created and content tagged.
- **organisations_listing View** — same two-display pattern as articles/events. Add when Organisation content type is in active use.
- **links_listing View** — same pattern. Add when Link content type is in use (e.g. Jobs Boards).
- Facets module integration (AJAX filtering without page reload)
- Content counts next to filter items
- `field_filterable` boolean to hide non-content terms from filters
- Map view of events by location
- Article age marking ("This article is more than X years old")
- Recurring event handling (smart_date_recur)
- Data and Maps pages under Explore (TBC, may be handled differently)
