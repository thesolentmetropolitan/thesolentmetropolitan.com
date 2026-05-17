# The Solent Metropolitan — Term Display: "in" Kicker & Conditional Linking

## Overview

Add an **"in [sub-term]"** kicker above content titles on section page listings. This complements the existing **"from [primary topic]"** kicker that already shows on related content. Together, they give readers full context about what they're looking at.

**This brief builds on existing work. The following should be EXTENDED, not rewritten from scratch:**
- `primary-kicker.html.twig` — the existing "from" kicker component. **Will be updated** to separate prefix from term text, add uppercase, and support slash-separated ancestor display. The existing preprocess variables (`show_primary_kicker`, `primary_kicker_label`, `primary_kicker_color`, `primary_kicker_section`) are retained and extended.
- The request attribute mechanism (`page_topic_tids`) set by the View Display paragraph preprocess
- The cache fragmentation in `customsolent_node_view_alter` (varies by `url.path` + `url.query_args:topic`)
- `_customsolent_get_section_color()` and related helper functions in `customsolent.theme`
- The existing teaser templates — modify the position of the "from" kicker include (move below title) and add the "in" kicker include above title

---

## What exists vs what's new

### Already implemented (don't touch)

**"from" kicker** — when content appears on a section page via `field_related_topics`, the content's own primary topic is shown **below** the title. Currently implemented in `templates/components/primary-kicker.html.twig` but needs updating: it currently renders above the title and doesn't use uppercase for the term text.

```
Southampton Forward                          ← title
from SECTORS / ARTS                          ← "from" kicker BELOW title
Championing the creative sector...           ← standfirst
```

**Changes needed to the existing "from" kicker:**
- Move include from above the title to **below** the title in all teaser templates
- Term text in **uppercase** (`text-transform: uppercase`), section colour
- "from" prefix stays **lowercase**, charcoal colour (`var(--text, #1a1a1a)`)
- Slash separator in charcoal colour
- Update `primary-kicker.html.twig` to separate "from" prefix from term text for independent styling

### New in this brief

**"in" kicker** — when content appears on a section page via `field_primary_topic`, the content's specific sub-topic within the current section is shown **above** the title, with obvious ancestors stripped:

```
in MUSIC                                     ← NEW "in" kicker ABOVE title, term uppercase
Jazz on the Seafront Returns to Southsea     ← title
Southsea Seafront                            ← location
```

**Conditional linking** — terms in the "in" kicker link to their landing page if one exists. Terms without a landing page render as plain text.

---

## The ancestor stripping rule

When displaying a content item's primary topic on a section page, remove every ancestor that the page itself already represents. The reader knows they're in Culture — don't repeat it.

| Current page | Content's primary topic | Kicker displays | What's stripped |
|-------------|------------------------|-----------------|----------------|
| `/culture` | Culture / Music | in MUSIC | "Culture" stripped |
| `/culture` | Culture / Stage / Theatre School | in STAGE / THEATRE SCHOOL | "Culture" stripped |
| `/culture/stage` | Culture / Stage / Theatre School | in THEATRE SCHOOL | "Culture" and "Stage" stripped |
| `/culture/music` | Culture / Music / Hip Hop | in HIP HOP | "Culture" and "Music" stripped |
| `/culture/music` | Culture / Music | *(nothing — term matches page exactly)* | Everything stripped, no kicker |
| `/culture` | Culture | *(nothing — term matches page exactly)* | Everything stripped, no kicker |

**The rule:** Find the page's term in the content's term chain. Strip everything up to and including it. Display what remains. If nothing remains, show no kicker.

---

## When each kicker type shows

For a content item in a listing on a section page:

| Content's primary topic relative to page | Kicker type | Position | Prefix |
|------------------------------------------|------------|----------|--------|
| Within the page's topic tree, with sub-terms remaining after stripping | "in" kicker | Above title | "in" |
| Within the page's topic tree, exact match (nothing to strip) | No kicker | — | — |
| Outside the page's topic tree (appearing via related topics) | "from" kicker (existing) | Below title | "from" |

In a `view_display_primary_and_related` listing, both types can appear — primary content gets "in" kickers, related content gets "from" kickers.

---

## Implementation

### 1. New helper: check if a term has a landing page

```php
/**
 * Check if a taxonomy term has a corresponding published composite page.
 *
 * Results are statically cached within the request to avoid repeated queries
 * on listing pages with many items.
 *
 * @param int $tid
 *   The term ID.
 *
 * @return bool
 *   TRUE if a published composite page exists with this term as primary topic.
 */
function _customsolent_term_has_landing_page($tid) {
  $cache = &drupal_static(__FUNCTION__, []);
  if (isset($cache[$tid])) {
    return $cache[$tid];
  }
  $query = \Drupal::entityQuery('node')
    ->condition('type', 'composite_page')
    ->condition('field_primary_topic', $tid)
    ->condition('status', 1)
    ->range(0, 1)
    ->accessCheck(TRUE);
  $results = $query->execute();
  $cache[$tid] = !empty($results);
  return $cache[$tid];
}
```

### 2. New helper: get term depth

```php
/**
 * Get the depth of a term in the taxonomy hierarchy.
 * Root terms (Culture, Sectors, etc.) = 0, their children = 1, grandchildren = 2.
 *
 * @param \Drupal\taxonomy\TermInterface $term
 *   The taxonomy term.
 *
 * @return int
 *   The depth (0-indexed from root).
 */
function _customsolent_get_term_depth($term) {
  $depth = 0;
  $current = $term;
  while ($current) {
    $parents = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadParents($current->id());
    if (empty($parents)) break;
    $depth++;
    $current = reset($parents);
  }
  return $depth;
}
```

### 3. New helper: build the "in" kicker display chain

```php
/**
 * Build the "in" kicker display for a content item on a section page.
 *
 * Strips ancestors already represented by the page. Returns the remaining
 * term chain with labels, URLs (conditional on landing page existence),
 * and section colour.
 *
 * @param \Drupal\taxonomy\TermInterface $content_term
 *   The content item's primary topic term.
 * @param int $page_term_depth
 *   The depth of the page's topic term in the hierarchy (0 = root like Culture).
 *
 * @return array|null
 *   Array of display items, each with 'label', 'url' (string or null), 'color'.
 *   Returns null if nothing to display (term matches page exactly).
 */
function _customsolent_build_in_kicker($content_term, $page_term_depth) {
  // Build the full ancestor chain for the content's primary topic
  $chain = [];
  $current = $content_term;
  while ($current) {
    array_unshift($chain, $current);
    $parents = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadParents($current->id());
    $current = !empty($parents) ? reset($parents) : null;
  }

  // Strip ancestors up to and including the page's depth
  // On /culture (depth 0): strip index 0 ("Culture"), show from index 1
  // On /culture/stage (depth 1): strip indices 0-1 ("Culture", "Stage"), show from index 2
  $display_from = $page_term_depth + 1;

  if ($display_from >= count($chain)) {
    // Nothing to display — the content's term IS the page's term (or shallower)
    return null;
  }

  $color = _customsolent_get_section_color($content_term);

  $items = [];
  for ($i = $display_from; $i < count($chain); $i++) {
    $term = $chain[$i];
    $label = _customsolent_strip_term_prefix($term->getName());

    // Conditional linking: link to landing page if it exists, otherwise plain text
    $url = _customsolent_term_has_landing_page($term->id())
      ? _customsolent_build_term_url($chain, $i)
      : null;

    $items[] = [
      'label' => $label,
      'url'   => $url,
      'color' => $color,
    ];
  }

  return $items;
}
```

### 4. Set page term depth as a request attribute

In the **existing** View Display paragraph preprocess (`customsolent_preprocess_paragraph__view_display`), after the point where `page_topic_tids` is set as a request attribute, add the page term depth:

```php
// ADD to the existing preprocess — do not replace existing code
// Set alongside the existing page_topic_tids attribute
$resolved_term = /* the term resolved by _customsolent_resolve_topic_context or equivalent */;
if ($resolved_term) {
  \Drupal::request()->attributes->set('page_term_depth', _customsolent_get_term_depth($resolved_term));
}
```

Claude Code: find where `page_topic_tids` is set as a request attribute in the existing preprocess and add `page_term_depth` alongside it. The exact location depends on the current code structure — inspect `customsolent.theme` to find the right insertion point.

### 5. Extend the node teaser preprocess

The existing `customsolent_preprocess_node` already handles the "from" kicker. Extend it to also handle the "in" kicker. The logic:

```php
// ADD to the existing customsolent_preprocess_node — extend, don't replace

// Inside the teaser handling block that already exists:
if (in_array($view_mode, ['teaser', 'compact'])) {

  $page_tids = \Drupal::request()->attributes->get('page_topic_tids', []);
  $page_term_depth = \Drupal::request()->attributes->get('page_term_depth', 0);

  if ($node->hasField('field_primary_topic') && !$node->get('field_primary_topic')->isEmpty()) {
    $primary_term = $node->get('field_primary_topic')->entity;
    $primary_tid = $primary_term->id();

    if (!empty($page_tids) && in_array($primary_tid, $page_tids)) {
      // Content's primary topic is WITHIN the page's topic tree
      // Show "in" kicker with ancestor stripping
      $in_kicker_items = _customsolent_build_in_kicker($primary_term, $page_term_depth);
      if ($in_kicker_items) {
        $variables['show_in_kicker'] = true;
        $variables['in_kicker_items'] = $in_kicker_items;
      }
      // Note: show_primary_kicker (the "from" kicker) should NOT show for primary content
      // The existing code may already handle this — verify it sets show_primary_kicker = false
      // when the primary topic IS in page_tids

    }
    // The existing "from" kicker logic handles the else case (primary topic outside page tree)
    // — leave that untouched
  }
}
```

**Important:** Claude Code should inspect the existing teaser preprocess code and integrate this alongside the "from" kicker logic. The two are mutually exclusive per content item:
- Primary topic within page tree → "in" kicker (new)
- Primary topic outside page tree → "from" kicker (existing)
- Primary topic matches page exactly → no kicker at all (new — `_customsolent_build_in_kicker` returns null)

### 6. New Twig component: in-kicker.html.twig

Create: `web/themes/custom/customsolent/templates/components/in-kicker.html.twig`

```twig
{#
  "in" kicker — shows the content's sub-topic within the current section,
  with obvious ancestors stripped.

  Variables:
    - in_kicker_items: array of { label, url (or null), color }
#}
{% if in_kicker_items is defined and in_kicker_items is not empty %}
  <div class="slnt-teaser__in-kicker">
    <span class="slnt-teaser__in-prefix">in</span>
    {% for item in in_kicker_items %}
      {% if item.url %}
        <a href="{{ item.url }}" class="slnt-teaser__in-term" style="color: {{ item.color }};">{{ item.label }}</a>
      {% else %}
        <span class="slnt-teaser__in-term slnt-teaser__in-term--plain" style="color: {{ item.color }};">{{ item.label }}</span>
      {% endif %}
      {% if not loop.last %}
        <span class="slnt-teaser__in-separator" aria-hidden="true"> / </span>
      {% endif %}
    {% endfor %}
  </div>
{% endif %}
```

### 7. Update teaser templates — kicker positions

In each teaser template:
- `node--article--teaser.html.twig`
- `node--event--teaser.html.twig`
- `node--organisation--teaser.html.twig`
- `node--link--teaser.html.twig`

**Add the "in" kicker ABOVE the title:**

```twig
{# "in" kicker — sub-topic within current section — ABOVE title #}
{% if show_in_kicker is defined and show_in_kicker %}
  {% include '@customsolent/components/in-kicker.html.twig' with {
    in_kicker_items: in_kicker_items,
  } %}
{% endif %}
```

**Move the existing "from" kicker include BELOW the title** (it's currently above — move it):

```twig
{# Title #}
<h3 class="...">{{ label }}</h3>

{# "from" kicker — primary topic of related content — BELOW title #}
{% if show_primary_kicker is defined and show_primary_kicker %}
  {% include '@customsolent/components/primary-kicker.html.twig' %}
{% endif %}
```

The resulting teaser structure for each template should be:

```
[in MUSIC]              ← "in" kicker (above title, only for primary content)
Title                   ← h3
[from SECTORS / ARTS]   ← "from" kicker (below title, only for related content)
Standfirst / meta       ← remaining teaser content
```

Only one kicker will render per item (they're mutually exclusive).

---

### 8. Update primary-kicker.html.twig — "from" kicker with full ancestor chain

The existing `primary-kicker.html.twig` renders "from technology" as a single block. Update it to:
- Show the full ancestor chain (e.g. "from SECTORS / ARTS" not just "from ARTS")
- Separate "from" prefix (lowercase, charcoal) from term text (uppercase, section colour)
- Make ancestor terms clickable links to their landing pages
- Use charcoal for the slash separator

**The preprocess needs to provide ancestor data** — extend the existing "from" kicker preprocess variables to include a `primary_kicker_ancestors` array (similar to `from_kicker_ancestors` in the helper function `_customsolent_build_from_kicker`).

**Updated template** — replace the content of `templates/components/primary-kicker.html.twig`:

```twig
{#
 * "from" kicker — shows where related content primarily lives.
 * Displayed BELOW the title on content appearing via related topics.
 *
 * Variables (set by customsolent_preprocess_node):
 *  - show_primary_kicker      bool
 *  - primary_kicker_ancestors  array of { label, url, color }
 *  - primary_kicker_section    lowercased top-level name (data-section)
 #}
{% if show_primary_kicker is defined and show_primary_kicker and primary_kicker_ancestors is defined %}
  <div class="slnt-teaser__from-kicker"
       data-section="{{ primary_kicker_section }}">
    <span class="slnt-teaser__from-prefix">from</span>
    {% for ancestor in primary_kicker_ancestors %}
      {% if ancestor.url %}
        <a href="{{ ancestor.url }}" class="slnt-teaser__from-term" style="color: {{ ancestor.color }};">{{ ancestor.label }}</a>
      {% else %}
        <span class="slnt-teaser__from-term" style="color: {{ ancestor.color }};">{{ ancestor.label }}</span>
      {% endif %}
      {% if not loop.last %}
        <span class="slnt-teaser__from-separator" aria-hidden="true"> / </span>
      {% endif %}
    {% endfor %}
  </div>
{% endif %}
```

**Preprocess update** — extend the existing "from" kicker logic in `customsolent_preprocess_node` to set `primary_kicker_ancestors`:

```php
// In the existing "from" kicker block where show_primary_kicker is set to TRUE:
$variables['primary_kicker_ancestors'] = _customsolent_build_from_kicker($primary_term);
// Keep existing variables for backward compatibility:
$variables['primary_kicker_label'] = _customsolent_strip_term_prefix($primary_term->getName());
$variables['primary_kicker_color'] = _customsolent_get_section_color($primary_term);
```

---

## CSS

Add to `css/section-listing.css` (or wherever the existing `.slnt-teaser__kicker` styles live). The existing `.slnt-teaser__kicker` class from the current "from" kicker implementation should be replaced with the more specific classes below.

```css
/* ══════════════════════════════════════
   Term display on listed content
   ══════════════════════════════════════ */

/* ── Shared kicker typography ── */
.slnt-teaser__in-kicker,
.slnt-teaser__from-kicker {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.3;
}

/* ── "in" kicker — above title ── */
.slnt-teaser__in-kicker {
  margin-bottom: 0.15rem;
}

.slnt-teaser__in-prefix {
  color: var(--text, #1a1a1a);
  margin-right: 0.2em;
  font-weight: 400;
  text-transform: none;  /* "in" stays lowercase */
}

/* Linked term (landing page exists) */
a.slnt-teaser__in-term {
  text-decoration: none;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  transition: text-decoration 0.15s;
  /* colour set inline from section colour */
}

a.slnt-teaser__in-term:hover {
  text-decoration: underline;
  text-underline-offset: 2px;
  text-decoration-thickness: 1.5px;
}

/* Non-linked term (no landing page exists) */
.slnt-teaser__in-term--plain {
  cursor: default;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  /* colour set inline from section colour — same as linked, just not interactive */
}

/* Separator between terms in multi-level display (e.g. "STAGE / THEATRE SCHOOL") */
.slnt-teaser__in-separator {
  color: var(--text, #1a1a1a);
  font-size: 0.75rem;
  font-weight: 400;
  text-transform: none;
}

/* ── "from" kicker — below title ── */
.slnt-teaser__from-kicker {
  margin-top: 0.1rem;
  margin-bottom: 0.3rem;
}

.slnt-teaser__from-prefix {
  color: var(--text, #1a1a1a);
  margin-right: 0.2em;
  font-weight: 400;
  text-transform: none;  /* "from" stays lowercase */
}

/* Linked ancestor term */
a.slnt-teaser__from-term {
  text-decoration: none;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  transition: text-decoration 0.15s;
  /* colour set inline from section colour */
}

a.slnt-teaser__from-term:hover {
  text-decoration: underline;
  text-underline-offset: 2px;
  text-decoration-thickness: 1.5px;
}

/* Non-linked ancestor term (unlikely for "from" since root terms always have pages, but for safety) */
span.slnt-teaser__from-term {
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

/* Separator between ancestors (e.g. "SECTORS / ARTS") */
.slnt-teaser__from-separator {
  color: var(--text, #1a1a1a);
  font-size: 0.75rem;
  font-weight: 400;
  text-transform: none;
}

/* ── Remove old kicker class if present ── */
/* The old .slnt-teaser__kicker is replaced by the specific __in-kicker and __from-kicker classes.
   If the old class is still referenced elsewhere, Claude Code should remove those references. */
```

---

## Helper function dependencies

This brief requires these existing functions (already in `customsolent.theme`):
- `_customsolent_get_section_color($term)` — returns hex colour for a term's section
- `_customsolent_strip_term_prefix($name)` — strips "Culture / " prefix from term names
- `_customsolent_build_term_url($chain, $index)` — builds URL path from term chain

And the existing `_customsolent_get_top_level_parent($term)` if not already available.

**If `_customsolent_build_term_url` doesn't exist yet** (it was in earlier briefs but may not have been implemented), Claude Code should check and implement it:

```php
/**
 * Build the URL path for a term based on its position in the ancestor chain.
 *
 * @param array $chain
 *   Array of term entities from root to leaf.
 * @param int $index
 *   The index in the chain to build the URL up to.
 *
 * @return string
 *   The URL path, e.g. "/culture/music" or "/culture/stage/comedy".
 */
function _customsolent_build_term_url($chain, $index) {
  $path_parts = [];
  for ($i = 0; $i <= $index; $i++) {
    $part = _customsolent_strip_term_prefix($chain[$i]->getName());
    $path_parts[] = strtolower(str_replace(
      [' & ', ' '],
      ['-', '-'],
      $part
    ));
  }
  return '/' . implode('/', $path_parts);
}
```

---

## Testing

1. **"in" kicker on /culture:** Content with primary topic "Culture / Music" shows "in MUSIC" above the title. "MUSIC" is in purple (#7C3AED), uppercase. If `/culture/music` exists as a composite page, "MUSIC" is a link to it. If not, plain text.

2. **"in" kicker on /culture/stage:** Content with primary topic "Culture / Stage / Theatre School" shows "in THEATRE SCHOOL" above the title (both "Culture" and "Stage" stripped). Uppercase. If no `/culture/stage/theatre-school` page exists, "THEATRE SCHOOL" is plain text.

3. **"in" kicker on /culture with sub-sub-term:** Content with "Culture / Stage / Theatre School" on the `/culture` page shows "in STAGE / THEATRE SCHOOL". "STAGE" links to `/culture/stage` (if page exists). "THEATRE SCHOOL" links to `/culture/stage/theatre-school` (if page exists) or is plain text. Slash is charcoal.

4. **No kicker for exact match:** Content whose primary topic IS the page's topic (e.g. "Culture / Music" on `/culture/music`) shows no "in" kicker. Nothing to add.

5. **"from" kicker position:** Related content shows the "from" kicker **below** the title (not above). Verify it has moved from its previous position.

6. **"from" kicker styling:** "from" is lowercase charcoal. Term text is uppercase in section colour (e.g. "from SECTORS / ARTS" — "SECTORS" and "ARTS" in blue #2563EB). Slash is charcoal.

7. **"from" kicker linking:** Both "SECTORS" and "ARTS" in "from SECTORS / ARTS" are clickable links to `/sectors` and `/sectors/arts` respectively.

8. **Mutual exclusivity:** No content item shows both an "in" kicker and a "from" kicker. One or the other, or neither.

9. **Mixed listing (primary_and_related):** In a `view_display_primary_and_related` listing, primary content shows "in" kickers above titles, related content shows "from" kickers below titles.

10. **Conditional linking — future-proof:** Create a composite page for a sub-sub-term (e.g. `/culture/music/hiphop`). Verify the "in" kicker on `/culture/music` now links "HIP HOP" to that page. Delete the page, verify it reverts to plain text.

11. **Static caching:** On a listing page with many items sharing the same sub-term, `_customsolent_term_has_landing_page` should only query once per unique term ID.

12. **"in" and "from" prefix styling:** Both "in" and "from" are lowercase, charcoal colour (`var(--text, #1a1a1a)`), regular weight. They do NOT take the section colour — only the term text does.

13. **No regressions:** Existing filter functionality unaffected. Teaser templates still render correctly on pages without kicker context (e.g. the front page compact events).

---

## Files to create or modify

```
M  web/themes/custom/customsolent/customsolent.theme
     — add _customsolent_term_has_landing_page()
     — add _customsolent_get_term_depth()
     — add _customsolent_build_in_kicker()
     — add _customsolent_build_from_kicker()
     — add _customsolent_build_term_url() (if not already present)
     — extend customsolent_preprocess_node() teaser handling: add "in" kicker logic
     — extend customsolent_preprocess_node() teaser handling: add primary_kicker_ancestors to "from" kicker
     — extend view_display paragraph preprocess to set page_term_depth request attribute

M  web/themes/custom/customsolent/templates/components/primary-kicker.html.twig
     — update to use ancestor chain with individual links, uppercase terms, charcoal prefix/separators

A  web/themes/custom/customsolent/templates/components/in-kicker.html.twig

M  web/themes/custom/customsolent/templates/content/node--article--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--event--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--organisation--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--link--teaser.html.twig
     — add in-kicker include above title
     — move primary-kicker include below title

M  web/themes/custom/customsolent/css/section-listing.css
     — replace .slnt-teaser__kicker with .slnt-teaser__in-kicker and .slnt-teaser__from-kicker styles
     — uppercase terms, charcoal prefixes and separators
```
