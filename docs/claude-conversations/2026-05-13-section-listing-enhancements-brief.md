# The Solent Metropolitan — Section Listing Enhancements Brief

## Overview

Enhancements to the existing View Display paragraph and section listing system. Builds on the section landing filters brief and front page events brief. All code must be compatible with Drupal 11.

### What this brief covers

1. **field_topic on the View Display paragraph** — allows independent topic filtering, not tied to the page's primary topic
2. **Combined primary + related view display** — a single display showing content where the term matches either field
3. **Primary term kicker on related content** — reveals a content item's primary topic when it appears on a page via related topics
4. **Filter displayed once per page** — move topic filter to page level, not per paragraph
5. **Date filter for events** — wordy preset options
6. **Pagination consistency** — ensure pagination CSS applies to all views uniformly

---

## 1. field_topic on the View Display paragraph

### Purpose

Allow the editor to explicitly set which topic term drives the contextual filter on a View Display paragraph, independent of the parent page's `field_primary_topic`. This enables:

- Explore / Events showing all events across Culture, Sectors, and Living
- Explore / Orgs & Directories showing all organisations across all topics
- Any page displaying content filtered by a topic other than its own

### Field to add

- **Field name:** `field_topic`
- **Type:** Entity reference → Topic vocabulary
- **Cardinality:** Single value
- **Required:** No (optional)
- **Widget:** Autocomplete
- **Help text:** "Optional: set a topic to filter by. If empty, the page's own primary topic is used."

Add this field to the existing View Display paragraph type.

### Preprocess logic change

Update `customsolent_preprocess_paragraph__view_display()` in `customsolent.theme`:

```php
function customsolent_preprocess_paragraph__view_display(&$variables) {
  $paragraph = $variables['paragraph'];
  $term = null;

  // Priority 1: field_topic on the paragraph itself
  if ($paragraph->hasField('field_topic') && !$paragraph->get('field_topic')->isEmpty()) {
    $term = $paragraph->get('field_topic')->entity;
  }

  // Priority 2: fall back to parent node's field_primary_topic
  if (!$term) {
    $parent = $paragraph->getParentEntity();
    if ($parent && $parent->hasField('field_primary_topic') && !$parent->get('field_primary_topic')->isEmpty()) {
      $term = $parent->get('field_primary_topic')->entity;
    }
  }

  if ($term) {
    $term_ids = _customsolent_get_term_with_descendants($term->id());
    $variables['primary_topic_tid'] = implode('+', $term_ids);
    $variables['section_filters'] = _customsolent_build_section_filters($term);
  } else {
    // No topic set anywhere — use "all topics" fallback
    // Collect Culture + Sectors + Living and all descendants
    $variables['primary_topic_tid'] = _customsolent_get_all_content_topic_ids();
    $variables['section_filters'] = _customsolent_build_all_topics_filters();
  }
}
```

### "All topics" fallback — no "All" parent term needed

When neither `field_topic` nor the page's `field_primary_topic` provides a term, the preprocess collects term IDs for Culture, Sectors, and Living (and all their descendants) and passes them as the contextual filter argument. This avoids adding an "All" parent term to the taxonomy, which would disrupt the existing hierarchy, breadcrumbs, menu state detection, and URL aliases.

```php
/**
 * Get all content-relevant topic IDs (Culture + Sectors + Living and descendants).
 *
 * Excludes About and Explore as they are structural, not content categories.
 *
 * @return string
 *   Plus-separated term IDs.
 */
function _customsolent_get_all_content_topic_ids() {
  $root_names = ['Culture', 'Sectors', 'Living'];
  $all_ids = [];

  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'topic', 'parent' => 0]);

  foreach ($terms as $term) {
    if (in_array($term->getName(), $root_names)) {
      $all_ids = array_merge($all_ids, _customsolent_get_term_with_descendants($term->id()));
    }
  }

  return implode('+', $all_ids);
}

/**
 * Build filter options showing Culture, Sectors, Living as top-level options.
 *
 * Used when no specific topic is set and the filter should show all root sections.
 *
 * @return array
 *   Filter data array with section_key, filter_options, has_filters.
 */
function _customsolent_build_all_topics_filters() {
  $root_names = ['Culture', 'Sectors', 'Living'];
  $filter_options = [];

  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'topic', 'parent' => 0]);

  foreach ($terms as $term) {
    if (in_array($term->getName(), $root_names)) {
      $filter_options[] = [
        'tid'   => $term->id(),
        'label' => $term->getName(),
      ];
    }
  }

  return [
    'section_key'    => 'explore',  // Use explore colour for all-topics context
    'filter_options' => $filter_options,
    'has_filters'    => !empty($filter_options),
  ];
}
```

### How the editor uses it

**On a section page (e.g. Culture / Music):**
- `field_topic` is left **empty**
- Preprocess falls back to the page's `field_primary_topic` (Culture / Music)
- View filters on Music and its descendants

**On Explore / Events:**
- `field_topic` is left **empty**
- Page's `field_primary_topic` is "Explore / Events" — but Explore is not a content category
- Preprocess detects no content-relevant term and uses the "all topics" fallback
- View shows all events across Culture, Sectors, and Living
- Filter sidebar shows Culture, Sectors, Living as top-level options

**On a custom curated page:**
- Editor sets `field_topic` to a specific term (e.g. "Sectors / Maritime")
- Preprocess uses that term regardless of what page it's on
- View filters on Maritime and its descendants

### Edge case: Explore and About terms

The preprocess needs to detect when the resolved term is an Explore or About term (which aren't content categories) and fall through to the "all topics" fallback. Add a check:

```php
// Check if the resolved term is under Explore or About — if so, use all-topics fallback
$top_level = _customsolent_get_top_level_parent($term);
if ($top_level && in_array($top_level->getName(), ['Explore', 'About'])) {
  $variables['primary_topic_tid'] = _customsolent_get_all_content_topic_ids();
  $variables['section_filters'] = _customsolent_build_all_topics_filters();
  return;
}
```

---

## 2. Combined primary + related view display

### Purpose

A single View display that shows content where the page's topic term appears in **either** `field_primary_topic` or `field_related_topics`. This is useful for Organisation and Link listings where you want to show "everything connected to this topic" without needing two separate paragraphs.

### Implementation

Add a third block display to each View that needs it:

**Display name:** Primary and Related
**Machine name:** `view_display_primary_and_related`

**Configuration:**
- Format: Unformatted list | Show: Content | Teaser
- Filter criteria: Content: Published (= Yes), Content: Type (= the relevant type)
- Sort criteria: as per the existing displays for that content type

**Contextual filter approach — Views filter groups:**

This display needs to match content where the contextual argument term ID appears in `field_primary_topic` OR `field_related_topics`. In Drupal Views, this requires:

1. Add **two** contextual filters: one on `field_primary_topic`, one on `field_related_topics`
2. Both configured identically: Taxonomy term validation, Topic vocabulary, allow multiple values
3. Under the View's advanced settings → **Filter criteria group operator**, there should be support for OR grouping of contextual filters

**However:** Drupal Views does not natively support OR grouping between contextual filters. The standard approach for this is:

**Option A: Two separate displays rendered together.** Keep the existing `view_display_primary_topic` and `view_display_related_topics` displays. Render both in the same paragraph, deduplicating results. The paragraph template renders both and CSS presents them as one continuous list.

**Option B: Custom Views filter plugin.** Write a small custom Views contextual filter plugin that accepts a term ID and checks both `field_primary_topic` and `field_related_topics` with OR logic. This is the cleanest solution but requires a custom module.

**Option C: Use a Views relationship and regular filter.** Add a relationship to both taxonomy reference fields, then use a regular (non-contextual) filter with OR grouping. The contextual argument is passed as an exposed filter value via the preprocess. More complex configuration but achievable within Views UI.

**Recommendation: Option A for MVP.** Render both existing displays within a single View Display paragraph. The paragraph template detects a display name convention (e.g. `view_display_primary_and_related`) and internally renders both the primary and related displays, concatenating the output. Deduplication can be handled later if needed — in practice, an item rarely has the same term in both primary and related fields.

### Implementation for Option A

The paragraph template, when it encounters a display ID of `view_display_primary_and_related`, renders both underlying displays:

```twig
{% if display_id == 'view_display_primary_and_related' %}
  {# Render primary and related together #}
  {{ drupal_view(view_name, 'view_display_primary_topic', primary_topic_tid) }}
  {{ drupal_view(view_name, 'view_display_related_topics', primary_topic_tid) }}
{% else %}
  {{ drupal_view(view_name, display_id, primary_topic_tid) }}
{% endif %}
```

**Important:** This means `view_display_primary_and_related` does not need to exist as an actual View display. It's a **convention** that the paragraph template interprets. The editor selects it as a display option, and the template handles the rendering. To make this work, either:

- Create a placeholder View display with this machine name (can be a duplicate of the primary display — it won't actually be rendered directly)
- Or add a separate field to the paragraph that lets the editor choose "Primary", "Related", or "Primary and Related" as a mode — but this adds complexity

The placeholder display approach is simpler. Create `view_display_primary_and_related` as a block display in each View, configured identically to `view_display_primary_topic`. The template intercepts the name and renders both.

### Which Views need this display

For MVP:
- `organisations_listing` — most useful here, since organisations surface across sections via related topics
- `links_listing` — same rationale

Add to `articles_listing` and `events_listing` later if needed.

---

## 3. Primary term kicker on related content

### Purpose

When content appears on a section page via its `field_related_topics` (not its primary topic), show the content's actual primary topic as a small kicker label. This helps readers understand why the content is appearing and enables cross-section discovery.

### Example

On the **Culture / Technology** page, an organisation with primary topic **Sectors / Technology** and related topic Culture / Technology appears in the listing. Its teaser shows:

```
from Sectors / Technology          ← small, blue (#2563EB), kicker
TechSolent                         ← title
Connecting the Solent's tech...    ← standfirst
```

On the **Culture / Technology** page, an article with primary topic **Culture / Technology** does NOT show a kicker — it's primary content, no disambiguation needed.

### Implementation

The teaser template needs to know two things:
1. The content item's own `field_primary_topic` term
2. The page's topic (from the View's contextual filter)

**Approach: Views row template with context.**

The View Display paragraph preprocess already has `primary_topic_tid`. Pass this to the View as a custom variable so the row template can compare:

In the preprocess, set a shared variable:

```php
// Store the page's topic term ID(s) for teaser comparison
$variables['page_topic_tids'] = $term_ids; // array of IDs
```

In the paragraph template, pass this to the View's rendering context. This is the complex part — Views doesn't natively receive arbitrary template variables. Two approaches:

**Approach A: Use drupal_static() or a request attribute.**

In the preprocess:
```php
// Store in a request attribute for teaser templates to read
\Drupal::request()->attributes->set('page_topic_tids', $term_ids);
```

In the teaser template (e.g. `node--article--teaser.html.twig`):
```twig
{% set page_topic_tids = drupal_request_attribute('page_topic_tids') ?? [] %}
{% set primary_tid = node.field_primary_topic.0.target_id %}

{% if primary_tid not in page_topic_tids %}
  {# This content's primary topic differs from the page — show kicker #}
  {% set primary_term = node.field_primary_topic.entity %}
  {% set kicker_label = primary_term.name.value %}
  {% set kicker_color = _get_section_color(primary_term) %}
  <div class="slnt-teaser__kicker" style="color: {{ kicker_color }};">
    from {{ kicker_label }}
  </div>
{% endif %}
```

**Note:** `drupal_request_attribute()` is not a standard Twig function. This would need a custom Twig extension or a preprocess on the node that reads the request attribute. Claude Code should determine the cleanest Drupal 11 compatible approach for passing page context into a teaser template rendered within a View.

**Approach B: Preprocess on the node teaser.**

In `customsolent_preprocess_node()`, when the view mode is `teaser`, read the request attribute and compare:

```php
function customsolent_preprocess_node(&$variables) {
  $node = $variables['node'];
  $view_mode = $variables['view_mode'] ?? 'full';

  if ($view_mode === 'teaser' && $node->hasField('field_primary_topic') && !$node->get('field_primary_topic')->isEmpty()) {
    $page_tids = \Drupal::request()->attributes->get('page_topic_tids', []);
    $primary_tid = $node->get('field_primary_topic')->target_id;

    if (!empty($page_tids) && !in_array($primary_tid, $page_tids)) {
      $primary_term = $node->get('field_primary_topic')->entity;
      $variables['show_primary_kicker'] = true;
      $variables['primary_kicker_label'] = _customsolent_strip_term_prefix($primary_term->getName());
      $variables['primary_kicker_color'] = _customsolent_get_section_color($primary_term);
    }
  }
}
```

Then in teaser templates:
```twig
{% if show_primary_kicker is defined and show_primary_kicker %}
  <div class="slnt-teaser__kicker" style="color: {{ primary_kicker_color }};">
    from {{ primary_kicker_label }}
  </div>
{% endif %}
```

**Approach B is recommended** — it keeps logic in PHP (preprocess) and presentation in Twig (template), following Drupal conventions.

### CSS for the kicker

```css
/* Primary topic kicker on related content teasers */
.slnt-teaser__kicker {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: lowercase;
  margin-bottom: 0.15rem;
  /* colour set inline via style attribute from section colour */
}
```

### Section colour helper function

```php
/**
 * Get the section colour hex for a term based on its top-level parent.
 */
function _customsolent_get_section_color($term) {
  $colors = [
    'Culture' => '#7C3AED',
    'Sectors' => '#2563EB',
    'Living'  => '#059669',
    'About'   => '#475569',
    'Explore' => '#D97706',
  ];

  $top_level = _customsolent_get_top_level_parent($term);
  $name = $top_level ? $top_level->getName() : '';
  return $colors[$name] ?? '#475569';
}

/**
 * Walk up the taxonomy hierarchy to find the top-level parent.
 */
function _customsolent_get_top_level_parent($term) {
  $current = $term;
  while ($current) {
    $parents = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadParents($current->id());
    if (empty($parents)) {
      return $current; // This is the top-level term
    }
    $current = reset($parents);
  }
  return $term;
}
```

---

## 4. Filter displayed once per page

### The problem

Currently, each View Display paragraph renders its own filter sidebar. If a page has Events + Organisations paragraphs, two filter sidebars appear.

### The solution

Create a **Section Filter paragraph type** — a standalone paragraph that the editor places once on the page. It renders the filter sidebar (desktop) or filter bar + slide-up panel (mobile). It affects all View Display paragraphs on the page via query parameters. The composite page template stays generic — it just renders paragraphs.

### New paragraph type: Section Filter

**Machine name:** `section_filter`

**Fields:**

| Field | Machine name | Type | Notes |
|-------|-------------|------|-------|
| Topic override | field_topic | Entity reference → Topic vocabulary | Optional. If set, the filter shows this term's children. If empty, uses the page's `field_primary_topic`. Reuse the same field definition as on the View Display paragraph. |

No other fields needed. The paragraph's purpose is purely functional — render the filter component.

### Paragraph template

Create: `web/themes/custom/customsolent/templates/paragraphs/paragraph--section-filter.html.twig`

```twig
{%
  set classes = [
    'paragraph',
    'paragraph--type--' ~ paragraph.bundle|clean_class,
    view_mode ? 'paragraph--view-mode--' ~ view_mode|clean_class,
  ]
%}

{% if section_filters is defined and section_filters.has_filters %}
  {% block paragraph %}
    <div{{ attributes.addClass(classes) }}>
      {% block content %}

        {# ── Desktop: sidebar filter ── #}
        <aside class="slnt-section-filter" data-section="{{ section_filters.section_key }}" aria-label="Filter options">
          {% include '@customsolent/components/filter-sidebar.html.twig' with {
            filter_options: section_filters.filter_options,
            section_key: section_filters.section_key,
          } %}
        </aside>

        {# ── Mobile: filter bar (visible below 800px) ── #}
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

        {# ── Mobile: slide-up panel ── #}
        <div class="slnt-filter-overlay" aria-hidden="true"></div>
        <div class="slnt-filter-panel" id="slnt-filter-panel" role="dialog" aria-label="Filter options" aria-modal="true">
          {% include '@customsolent/components/filter-panel.html.twig' with {
            filter_options: section_filters.filter_options,
            section_key: section_filters.section_key,
          } %}
        </div>

      {% endblock %}
    </div>
  {% endblock paragraph %}
{% endif %}
```

### Paragraph preprocess

```php
/**
 * Implements hook_preprocess_paragraph() for the Section Filter paragraph.
 */
function customsolent_preprocess_paragraph__section_filter(&$variables) {
  $paragraph = $variables['paragraph'];
  $term = null;

  // Priority 1: field_topic on the paragraph itself
  if ($paragraph->hasField('field_topic') && !$paragraph->get('field_topic')->isEmpty()) {
    $term = $paragraph->get('field_topic')->entity;
  }

  // Priority 2: fall back to parent node's field_primary_topic
  if (!$term) {
    $parent = $paragraph->getParentEntity();
    if ($parent && $parent->hasField('field_primary_topic') && !$parent->get('field_primary_topic')->isEmpty()) {
      $term = $parent->get('field_primary_topic')->entity;
    }
  }

  if ($term) {
    // Check if this is an Explore/About term — use all-topics filter
    $top_level = _customsolent_get_top_level_parent($term);
    if ($top_level && in_array($top_level->getName(), ['Explore', 'About'])) {
      $variables['section_filters'] = _customsolent_build_all_topics_filters();
    } else {
      $variables['section_filters'] = _customsolent_build_section_filters($term);
    }
  } else {
    // No topic anywhere — show all topics
    $variables['section_filters'] = _customsolent_build_all_topics_filters();
  }
}
```

### How the editor uses it

On a section page (e.g. Culture / Music), the editor adds paragraphs in this order:

1. **Hero art paragraph** — Music hero banner
2. **Section Filter paragraph** — `field_topic` empty (inherits page's primary topic). Filter shows Music's children.
3. **View Display paragraph** — Events listing, heading "Events"
4. **View Display paragraph** — Articles listing, heading "Articles"
5. **View Display paragraph** — Organisations listing, heading "Organisations"

The Section Filter paragraph renders the sidebar (desktop) or filter bar (mobile) once. The View Display paragraphs below it render their listings. The filter appears visually above/beside all listings.

On Explore / Events, the editor would:

1. **Hero art paragraph**
2. **Section Filter paragraph** — `field_topic` empty, page topic is Explore / Events → preprocess detects Explore, falls back to all topics. Filter shows Culture, Sectors, Living.
3. **View Display paragraph** — Events listing

### CSS layout: sidebar alongside content

The Section Filter paragraph needs to position itself as a sidebar alongside the subsequent content. This is achieved with CSS on the composite page's content wrapper:

```css
/* ══════════════════════════════════════
   Section filter — sidebar layout
   ══════════════════════════════════════ */

/* When a section filter paragraph is present, the page content area
   uses a two-column layout with the filter as sidebar */

@media (min-width: 800px) {
  /* The composite page content wrapper becomes a flex container
     when it contains a section filter */
  .slnt-composite-content:has(.paragraph--type--section-filter) {
    display: flex;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
  }

  /* The filter sidebar */
  .paragraph--type--section-filter {
    width: 220px;
    flex-shrink: 0;
    order: -1;  /* Ensure filter is on the left even if not first in DOM */
  }

  .slnt-section-filter {
    position: sticky;
    top: 1rem;
    align-self: flex-start;
  }

  /* The filter bar is desktop-hidden */
  .slnt-filter-bar {
    display: none;
  }

  /* Everything else in the content area takes remaining space */
  .slnt-composite-content:has(.paragraph--type--section-filter) > *:not(.paragraph--type--section-filter) {
    flex: 1;
    min-width: 0;
  }
}

/* Mobile: filter paragraph renders as sticky bar + panel, no sidebar */
@media (max-width: 799px) {
  .slnt-section-filter {
    display: none;  /* Hide desktop sidebar on mobile */
  }

  .slnt-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    border-bottom: 1px solid #e0e0e0;
    padding: 0.6rem var(--content-pad-mobile, 1.2rem);
    position: sticky;
    top: 38px;
    z-index: 9;
  }
}

/* Mobile filter panel styles — from earlier briefs */
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
}
```

**Note on `:has()` selector:** The CSS `:has()` selector is supported in all modern browsers (Chrome 105+, Firefox 121+, Safari 15.4+). It allows the composite page content wrapper to detect whether it contains a Section Filter paragraph and adjust its layout accordingly — without the composite page template needing any special classes or logic. If older browser support is needed, a class-based approach via preprocess can be used instead.

### How filter selections reach the View Display paragraphs

When a user clicks a filter option, the page reloads with a query parameter: `?topic=123`. Each View Display paragraph's preprocess reads this query parameter and overrides its contextual filter argument:

```php
// In customsolent_preprocess_paragraph__view_display():

// Check for topic filter override from query parameter
$request = \Drupal::request();
$filter_tid = $request->query->get('topic');

if ($filter_tid && is_numeric($filter_tid)) {
  // User selected a specific sub-term — filter on that and its descendants
  $filter_term_ids = _customsolent_get_term_with_descendants((int) $filter_tid);
  $variables['primary_topic_tid'] = implode('+', $filter_term_ids);
}
// Otherwise primary_topic_tid remains as set by field_topic / page fallback logic
```

### Composite page template stays generic

The composite page template does NOT change. It continues to just render paragraphs:

```twig
<article{{ attributes.addClass(classes) }}>
  <div class="slnt-composite-content">
    {{ content.field_content }}
  </div>
</article>
```

The Section Filter paragraph handles its own rendering. The CSS `:has()` selector handles the layout change. The composite page template has no knowledge of filters, sidebars, or two-column layouts. This preserves the flexibility to use composite pages for any purpose — with or without filters.

### Pages without a Section Filter paragraph

Pages that don't include a Section Filter paragraph behave exactly as before — the content renders full-width, no sidebar, no filter bar. The `:has()` CSS rule only activates when the Section Filter paragraph is present in the DOM.

---

## 5. Date filter for events

### Preset options

The date filter uses wordy presets rather than a date range picker:

- **Today** — events happening today
- **This weekend** — Saturday and Sunday of the current week (or Friday evening to Sunday if preferred)
- **This week** — Monday to Sunday of the current week
- **This month** — first to last day of the current month
- **Coming up** — next 28 days from today

### Implementation

The date filter is an **exposed filter** on the Events listing Views, configured as a grouped filter with preset date ranges.

In the View configuration:
1. Add a filter on `field_when` (Smart Date value)
2. Set it as an exposed filter
3. Use **"Grouped filters"** with preset values:

| Label | Operator | Value |
|-------|----------|-------|
| Today | Between | today midnight ... today +1 day midnight |
| This weekend | Between | this Saturday midnight ... next Monday midnight |
| This week | Between | this week Monday midnight ... this week Sunday +1 day midnight |
| This month | Between | first day of this month midnight ... first day of next month midnight |
| Coming up | Between | today midnight ... today +28 days midnight |

**Note:** The exact filter value syntax depends on how Smart Date exposes its date values to Views. Claude Code should verify the correct filter configuration — Smart Date may use timestamps, ISO dates, or relative date expressions. The Views UI for grouped filters accepts relative date strings like `now`, `+7 days`, `first day of this month` depending on the filter plugin.

### Display

The date filter renders as **pill buttons** in the sidebar and mobile panel, matching the design from the filter panel mockup:

```css
/* Date pills — reuse from section-listing.css */
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

### Date filter only applies to events views

The topic filter applies to all View Display paragraphs on the page. The date filter only applies to events listings. When a user selects a date preset, only the Events View reloads with the date parameter. Article and Organisation listings are unaffected.

This is handled by the date filter being an **exposed filter on the Events View only**, not a page-level filter. The query parameter (e.g. `?date=weekend`) is only read by the Events View's exposed filter.

---

## 6. Pagination consistency

### The requirement

Pagination CSS should apply uniformly to all Views with pagers — events, articles, organisations, links. No view-specific pagination styling.

### Implementation

Ensure the pagination CSS selectors target the generic Views pager classes rather than view-specific classes:

```css
/* ══════════════════════════════════════
   Pagination — all Views
   ══════════════════════════════════════ */

.pager {
  margin: 2rem 0;
  display: flex;
  justify-content: center;
  gap: 0.3rem;
}

.pager__item {
  list-style: none;
}

.pager__item a,
.pager__item span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.2rem;
  height: 2.2rem;
  padding: 0.3rem 0.6rem;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--solent-blue, #2c4f6e);
  text-decoration: none;
  border-radius: 4px;
  transition: all 0.15s;
}

.pager__item a:hover {
  background: var(--warm-grey, #f5f3f0);
}

.pager__item.is-active span {
  background: var(--solent-blue, #2c4f6e);
  color: white;
  font-weight: 700;
}

.pager__item--previous a,
.pager__item--next a {
  font-weight: 700;
}

.pager__item--first a,
.pager__item--last a {
  font-size: 0.78rem;
}
```

**Check:** If existing pagination CSS in `node.css` is scoped to a specific view (e.g. `.view-events-listing .pager`), generalise the selectors to `.pager` so they apply to all views. Move pagination styles to their own section in `node.css` or to a new `css/pager.css` if cleaner.

---

## Checklist: What Rob should configure

### View Display paragraph type
- [ ] Add `field_topic` — Entity reference → Topic vocabulary, single value, optional, autocomplete widget

### New paragraph type: Section Filter
- [ ] Create `section_filter` paragraph type
- [ ] Add `field_topic` — Entity reference → Topic vocabulary, single value, optional, autocomplete widget (same field definition as on View Display paragraph — reuse if possible)
- [ ] No other fields needed
- [ ] Make the paragraph type available within the composite page's paragraph reference field

### Views — new displays
- [ ] Add `view_display_primary_and_related` to `organisations_listing` (placeholder display — template handles rendering)
- [ ] Add `view_display_primary_and_related` to `links_listing`
- [ ] Add date exposed filter (grouped) to all events_listing displays that show event lists (section page displays, events page display)

### Teaser view modes
- [ ] No changes needed — existing teasers support the kicker via preprocess

### Editor tasks after implementation
- [ ] Add a Section Filter paragraph to each section page that needs filtering (Culture, Culture / Music, Sectors, Sectors / Technology, etc.)
- [ ] Place it above the View Display paragraphs in the paragraph order
- [ ] Leave `field_topic` empty on most pages (inherits page's primary topic)
- [ ] On Explore pages, leave `field_topic` empty — preprocess detects Explore and falls back to all topics

---

## Implementation order

| Step | Task | Who | Priority |
|------|------|-----|----------|
| 1 | Add `field_topic` to View Display paragraph type | Rob | Now |
| 2 | Create Section Filter paragraph type with `field_topic` | Rob | Now |
| 3 | Update View Display paragraph preprocess: field_topic priority, Explore/About fallback, all-topics collection, query parameter override | Claude Code | Now |
| 4 | Create Section Filter paragraph preprocess and template | Claude Code | Now |
| 5 | Create filter sidebar CSS (desktop sidebar, mobile bar + panel) | Claude Code | Now |
| 6 | Create `js/filter-panel.js` for mobile panel toggle | Claude Code | Now |
| 7 | Add `view_display_primary_and_related` placeholder displays to organisations_listing and links_listing | Rob | Now |
| 8 | Update View Display paragraph template: detect primary_and_related display, render both underlying displays | Claude Code | Now |
| 9 | Add primary term kicker: request attribute in paragraph preprocess, node teaser preprocess, teaser template update | Claude Code | Now |
| 10 | Add `_customsolent_get_section_color()` and `_customsolent_get_top_level_parent()` helper functions | Claude Code | Now |
| 11 | Generalise pagination CSS to all Views | Claude Code | Now |
| 12 | Add Section Filter paragraphs to section pages | Rob | Now — after code is deployed |
| 13 | Add date exposed filter to events_listing displays | Rob + Claude Code | Soon |
| 14 | Style date pills in sidebar and mobile panel | Claude Code | Soon |

---

## Testing

1. **field_topic override:** Set `field_topic` on a View Display paragraph to a specific term. Verify the listing filters on that term regardless of the page's primary topic.
2. **field_topic empty:** Leave `field_topic` empty on a section page. Verify the listing uses the page's `field_primary_topic` as before.
3. **Explore pages (all topics):** On Explore / Events with no specific topic, verify all events across Culture, Sectors, and Living appear. Filter sidebar shows Culture, Sectors, Living as options.
4. **Combined primary + related display:** Select `view_display_primary_and_related` on a paragraph. Verify content from both primary and related topic matches appears in one listing.
5. **Primary term kicker:** On a Culture / Technology page, verify that an organisation with primary topic Sectors / Technology shows "from Sectors / Technology" as a kicker. Content with primary topic Culture / Technology does NOT show a kicker.
6. **Kicker colours:** Verify kicker text uses the correct section colour (purple for Culture, blue for Sectors, green for Living).
7. **Section Filter paragraph — displayed once:** Add one Section Filter paragraph + two View Display paragraphs (Events + Organisations). Verify only one filter sidebar appears.
8. **Section Filter paragraph — sidebar layout:** On desktop, verify the filter renders as a sticky left sidebar with all listings to the right. The `:has()` CSS activates the two-column layout.
9. **Section Filter paragraph — mobile:** Below 800px, verify the desktop sidebar is hidden, the sticky filter bar appears, and tapping "Filter" opens the slide-up panel.
10. **No Section Filter paragraph:** On a page without a Section Filter paragraph, verify content renders full-width with no sidebar and no filter bar. The `:has()` CSS does not activate.
11. **Topic filter selection:** Click a sub-term in the filter. Verify all View Display paragraph listings on the page update to show content matching that sub-term.
12. **Date filter:** Select "This weekend." Verify only events happening this weekend appear. Other content type listings are unaffected.
13. **Pagination:** Verify pager appears and is styled consistently on articles, events, organisations, and links listings.
14. **No regressions:** Existing section page listings continue to work without a Section Filter paragraph — field_topic empty, page primary topic used, correct filtering.
15. **Composite page template unchanged:** Verify the composite page template has no filter-specific code — it just renders paragraphs.

---

## File structure

| File | Purpose |
|------|---------|
| `templates/paragraphs/paragraph--section-filter.html.twig` | Section Filter paragraph — renders sidebar, filter bar, and panel |
| `templates/components/filter-sidebar.html.twig` | Twig include for desktop sidebar filter sections |
| `templates/components/filter-panel.html.twig` | Twig include for mobile slide-up panel content |
| `css/section-filter.css` | Sidebar layout (`:has()` based), filter bar, panel, filter items, date pills |
| `js/filter-panel.js` | Mobile panel open/close toggle, body scroll lock |
| `customsolent.theme` | Preprocess functions for section_filter and view_display paragraphs, node teaser kicker logic |

All paths relative to `web/themes/custom/customsolent/`. Register CSS and JS in `customsolent.libraries.yml`.

---

## What this does NOT cover (deferred)

- AJAX filter updates (page reload for MVP)
- Content counts next to filter options
- Location filter (adding later is non-invasive — a new filter section in the sidebar, a new query parameter in the preprocess, a new exposed filter on the Views)
- Views filter groups for native OR contextual filtering (Option B/C from section 2)
