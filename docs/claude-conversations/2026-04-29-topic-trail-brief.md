# The Solent Metropolitan — Topic Breadcrumb Trail Brief

## Overview

Build a custom breadcrumb-style navigation component that derives its trail from the `field_primary_topic` taxonomy hierarchy. The component displays the current page's position within the site structure using the topic term's ancestry — parent terms are clickable and colour-coded by section, the current/leaf term is larger and non-clickable.

This component is **not** Drupal's built-in breadcrumb system and does **not** use the `easy_breadcrumb` module. It is a bespoke, reusable Twig component driven by taxonomy data.

---

## Examples

### On a composite page (submenu landing page):

**Screen landing page** (linked from Culture > Screen submenu):

<span style="small, purple, linked">Culture</span> <span style="charcoal">/</span> <span style="large, charcoal">Screen</span>

- "Culture" is clickable → links to `/culture`
- "/" is decorative, not clickable
- "Screen" is the current page — larger, not clickable

### On a content page (article) — kicker above the title:

**An article about a filmmaker** with primary topic "Culture / Screen":

<span style="small, purple, linked">Culture</span> <span style="charcoal">/</span> <span style="small, purple, linked">Screen</span>
**The Rise of Independent Filmmaking in the Solent** ← article's own h1 title

- "Culture" is clickable → links to `/culture`
- "Screen" is clickable → links to `/culture/screen`
- "/" is decorative, not clickable
- The entire trail acts as a kicker — all terms are clickable because the article title below is the page heading

### On a content page with a sub-sub-term:

**An article about a novel** with primary topic "Culture / Writing / Books":

<span style="small, purple, linked">Culture</span> <span style="charcoal">/</span> <span style="small, purple, linked">Writing</span> <span style="charcoal">/</span> <span style="small, purple, linked">Books</span>
**The Solent's Best Independent Bookshops** ← article's own h1 title

- "Culture" is clickable → links to `/culture`
- "Writing" is clickable → links to `/culture/writing`
- "Books" is clickable → links to `/culture/writing/books`
- All terms are clickable as a kicker; the article title is the page heading

### On a Sectors content page:

**An article about a tech startup** with primary topic "Sectors / Technology":

<span style="small, blue, linked">Sectors</span> <span style="charcoal">/</span> <span style="small, blue, linked">Technology</span>
**SailBot Raises £2M for Autonomous Marine Navigation** ← article's own h1 title

- "Sectors" uses the Sectors section colour (blue #2563EB)
- "Technology" also uses the Sectors section colour — all ancestors share the top-level parent colour

---

## Visual Design

### Parent/ancestor terms (clickable)
- **Font size:** 0.9rem
- **Font weight:** 400
- **Colour:** The section colour of the top-level parent term:
  - Culture: `#7C3AED` (purple)
  - Sectors: `#2563EB` (blue)
  - Living: `#059669` (green)
  - About: `#475569` (slate)
  - Explore: `#D97706` (amber)
- **Text decoration:** None by default. Underline on hover.
- **Cursor:** Pointer
- **Link destination:** The composite page URL for that term (derived from the menu structure or URL alias pattern)

### Separator slashes
- **Character:** ` / ` (space, forward slash, space)
- **Colour:** `var(--text, #1a1a1a)` or `var(--text-mid, #444)` — charcoal/dark grey
- **Font size:** 0.9rem (matches parent term size)
- **Not clickable**, not wrapped in a link — purely decorative
- **Aria hidden:** Yes — screen readers should not read the slashes

### Leaf/current term (not clickable)
- **Font size:** 2.4rem on composite pages (same as page title — this IS the page title), 2.4rem on article pages (same as article title)
- **Font weight:** 700
- **Colour:** `var(--solent-blue, #2c4f6e)` on composite pages, `var(--solent-blue, #2c4f6e)` on articles (matching existing title colour)
- **Not a link** — plain text
- **Line:** Displays on a new line below the parent trail for clarity at the larger size

### Layout

The parent trail and the leaf term should be vertically stacked, not inline:

```
Culture                          ← small, purple, linked
Screen                           ← large, charcoal, page title
```

For sub-sub-terms:

```
Culture / Writing                ← small, purple, both linked, slash in charcoal
Books                            ← large, charcoal, page title
```

This avoids the visual awkwardness of mixing small and large text on the same line. The parent trail acts as a section label above the title, similar to how the BBC and Guardian display section names above article headlines.

**Note:** The slash separator is used in the parent trail line (between clickable ancestors) but NOT between the parent trail and the leaf term. The line break provides the separation.

---

## Accessibility, SEO and Semantics

Wrap the component in a `<nav>` element with appropriate ARIA attributes:

```html
<nav class="slnt-topic-trail" aria-label="Topic breadcrumb">
  <div class="slnt-topic-trail__ancestors">
    <a href="/culture" class="slnt-topic-trail__ancestor" style="color: #7C3AED;">
      Culture
    </a>
    <span class="slnt-topic-trail__separator" aria-hidden="true"> / </span>
    <a href="/culture/writing" class="slnt-topic-trail__ancestor" style="color: #7C3AED;">
      Writing
    </a>
  </div>
  <span class="slnt-topic-trail__current">Books</span>
</nav>
```

Key accessibility points:
- `<nav>` element identifies this as a navigation landmark
- `aria-label="Topic breadcrumb"` distinguishes it from the main navigation and any other nav landmarks
- `aria-hidden="true"` on separator slashes prevents screen readers from reading "slash" between each term
- Screen readers will read: "Topic breadcrumb navigation, link Culture, link Writing, Books" — clear and logical
- The current/leaf term is **not** wrapped in an `<a>` tag — it's a plain `<span>` or heading element, making it clear to assistive technology that it's not interactive

### When used as the page title

On composite pages where the leaf term IS the page title, wrap it in an `<h1>`:

```html
<nav class="slnt-topic-trail" aria-label="Topic breadcrumb">
  <div class="slnt-topic-trail__ancestors">
    <a href="/culture" class="slnt-topic-trail__ancestor" style="color: #7C3AED;">
      Culture
    </a>
  </div>
  <h1 class="slnt-topic-trail__current">Screen</h1>
</nav>
```

On article pages where the article has its own `<h1>` title, the leaf term in the breadcrumb should NOT be an `<h1>` (only one `<h1>` per page). Use a `<span>` instead, and let the article title be the `<h1>`. Alternatively, the breadcrumb on article pages could omit the leaf term entirely and display only the clickable ancestors as a section label above the article's own `<h1>` title:

```html
<nav class="slnt-topic-trail" aria-label="Topic breadcrumb">
  <div class="slnt-topic-trail__ancestors">
    <a href="/culture" class="slnt-topic-trail__ancestor" style="color: #7C3AED;">
      Culture
    </a>
    <span class="slnt-topic-trail__separator" aria-hidden="true"> / </span>
    <a href="/culture/screen" class="slnt-topic-trail__ancestor" style="color: #7C3AED;">
      Screen
    </a>
  </div>
</nav>
<h1 class="slnt-article__title">The Rise of Independent Filmmaking in the Solent</h1>
```

This approach is cleaner for articles because:
- There's only one `<h1>` — the article title
- All terms in the breadcrumb are clickable (no ambiguous non-clickable term)
- The breadcrumb purely serves as a "you are in Culture > Screen" section indicator
- The article title does its own job as the page title

**Recommendation:** Use the h1-as-leaf approach for composite pages (the leaf term is the page title). Use the all-ancestors-as-kicker approach for articles and other content pages (all terms are clickable, the content's own title is the `<h1>` below). This means on content pages, the preprocess function passes the **entire term chain** (including the leaf) as ancestors, with `current_label` set to null.

---

## Implementation: Reusable Twig Component

### Approach

Create a **Twig template include** (not a paragraph, not a block) that can be embedded in any template. This makes it reusable across composite pages, articles, events, and the hero art paragraph.

**Template file:** `web/themes/custom/customsolent/templates/components/topic-trail.html.twig`

### Input variables

The component expects these variables to be passed when included:

| Variable | Type | Description |
|----------|------|-------------|
| `ancestors` | Array of objects | Each with `label` (string), `url` (string), `color` (string — hex colour of the section) |
| `current_label` | String | The leaf term name to display as the current page |
| `current_as_heading` | Boolean | If true, wraps current_label in `<h1>`. If false, wraps in `<span>`. |

### Component template

```twig
{#
  Topic breadcrumb trail component.

  Variables:
    - ancestors: array of { label, url, color }
    - current_label: string (the leaf/current term)
    - current_as_heading: boolean (true = h1, false = span)
#}
{% if ancestors is not empty or current_label %}
<nav class="slnt-topic-trail" aria-label="Topic breadcrumb">
  {% if ancestors is not empty %}
    <div class="slnt-topic-trail__ancestors">
      {% for ancestor in ancestors %}
        <a href="{{ ancestor.url }}" class="slnt-topic-trail__ancestor" style="color: {{ ancestor.color }};">
          {{ ancestor.label }}
        </a>
        {% if not loop.last %}
          <span class="slnt-topic-trail__separator" aria-hidden="true"> / </span>
        {% endif %}
      {% endfor %}
    </div>
  {% endif %}
  {% if current_label %}
    {% if current_as_heading %}
      <h1 class="slnt-topic-trail__current">{{ current_label }}</h1>
    {% else %}
      <span class="slnt-topic-trail__current">{{ current_label }}</span>
    {% endif %}
  {% endif %}
</nav>
{% endif %}
```

### Usage in other templates

**In the composite page hero art paragraph:**

```twig
{% include '@customsolent/components/topic-trail.html.twig' with {
  ancestors: ancestors,
  current_label: current_label,
  current_as_heading: true,
} %}
```

**In node--article--full.html.twig:**

```twig
{% include '@customsolent/components/topic-trail.html.twig' with {
  ancestors: ancestors,
  current_label: null,
  current_as_heading: false,
} %}
<h1 class="slnt-article__title">{{ label }}</h1>
```

(For articles: ancestors only, no leaf term. The article title is its own `<h1>` below.)

---

## Implementation: Preprocess Function

The `ancestors` array needs to be built from the `field_primary_topic` taxonomy term hierarchy. This requires a **theme preprocess function** in `customsolent.theme` (or a custom module) that:

1. Reads the current node's `field_primary_topic` value
2. Loads the referenced taxonomy term
3. Walks up the taxonomy hierarchy to collect all ancestor terms
4. For each ancestor, determines:
   - **label**: the term's display name (stripped of the parent prefix — e.g. "Culture / Screen" becomes just "Screen", "Culture" stays as "Culture")
   - **url**: the URL alias of the composite page for that term (e.g. `/culture`, `/culture/screen`). Derive from the menu structure or build from a convention like `/{parent}/{child}`.
   - **color**: the section colour based on the top-level parent term. Map top-level term names to colours:
     - Culture → `#7C3AED`
     - Sectors → `#2563EB`
     - Living → `#059669`
     - About → `#475569`
     - Explore → `#D97706`
5. Passes `ancestors`, `current_label`, and `current_as_heading` to the template as variables

### Preprocess hook

```php
/**
 * Implements hook_preprocess_node() for article, event, composite_page, etc.
 */
function customsolent_preprocess_node(&$variables) {
  $node = $variables['node'];

  if ($node->hasField('field_primary_topic') && !$node->get('field_primary_topic')->isEmpty()) {
    $term = $node->get('field_primary_topic')->entity;
    $bundle = $node->bundle();

    // Composite pages: leaf term becomes the h1 title, ancestors are clickable
    // Content pages (article, event, etc.): entire chain is clickable as a kicker,
    //   the node's own title is the h1 below
    $is_composite = ($bundle === 'composite_page');

    $trail = _customsolent_build_topic_trail($term, $is_composite);
    $variables['topic_trail_ancestors'] = $trail['ancestors'];
    $variables['topic_trail_current'] = $trail['current_label'];
    $variables['topic_trail_as_heading'] = $is_composite;
  }
}

/**
 * Build the topic trail from a taxonomy term.
 *
 * @param \Drupal\taxonomy\TermInterface $term
 *   The primary topic term.
 * @param bool $leaf_as_title
 *   If true (composite pages), the leaf term is returned as current_label
 *   and excluded from ancestors. If false (content pages), the entire chain
 *   is returned as ancestors and current_label is null — the full chain
 *   acts as a kicker above the content's own title.
 *
 * @return array
 *   Array with 'ancestors' and 'current_label'.
 */
function _customsolent_build_topic_trail($term, $leaf_as_title = true) {
  // Section colour map — top-level parent term name → hex colour
  $section_colors = [
    'Culture' => '#7C3AED',
    'Sectors' => '#2563EB',
    'Living'  => '#059669',
    'About'   => '#475569',
    'Explore' => '#D97706',
  ];

  // Collect the term and all its ancestors
  $chain = [];
  $current = $term;
  while ($current) {
    array_unshift($chain, $current);
    $parents = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadParents($current->id());
    $current = !empty($parents) ? reset($parents) : null;
  }

  // Top-level parent determines the section colour
  $top_level_name = $chain[0]->getName();
  $color = $section_colors[$top_level_name] ?? '#475569';

  // Determine how many terms go into ancestors
  // Composite pages: all except the last (leaf becomes h1 title)
  // Content pages: all terms (entire chain is the kicker)
  $ancestor_count = $leaf_as_title ? count($chain) - 1 : count($chain);

  $ancestors = [];
  for ($i = 0; $i < $ancestor_count; $i++) {
    $ancestor_term = $chain[$i];
    // Strip parent prefix from term name for display
    // e.g. "Culture / Screen" → "Screen", but "Culture" stays "Culture"
    $label = _customsolent_strip_term_prefix($ancestor_term->getName());
    // Build URL from the chain up to this point
    $url = _customsolent_build_term_url($chain, $i);
    $ancestors[] = [
      'label' => $label,
      'url'   => $url,
      'color' => $color,
    ];
  }

  // Current/leaf term — only for composite pages where it becomes the h1
  $current_label = null;
  if ($leaf_as_title) {
    $leaf = end($chain);
    $current_label = _customsolent_strip_term_prefix($leaf->getName());
  }

  return [
    'ancestors' => $ancestors,
    'current_label' => $current_label,
  ];
}

/**
 * Strip the parent prefix from a term name.
 * "Culture / Screen" → "Screen"
 * "Culture" → "Culture"
 */
function _customsolent_strip_term_prefix($name) {
  $parts = explode(' / ', $name);
  return end($parts);
}

/**
 * Build the URL path for a term based on its position in the chain.
 * Chain[0] = "Culture" → "/culture"
 * Chain[1] = "Culture / Screen" → "/culture/screen"
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

### Important notes on URL derivation

The `_customsolent_build_term_url()` function above derives URLs from the term names by lowercasing and replacing spaces/ampersands with hyphens. This works **only if the composite page URL aliases follow this exact convention**. For example:

- Term "Culture" → URL `/culture` ✓
- Term "Culture / Art & Design" → URL `/culture/art-design` — verify this matches the actual alias
- Term "Culture / Screen" → URL `/culture/screen` ✓

**If the URL aliases don't follow this pattern consistently**, a more robust approach is to:
1. Add a `field_url_alias` field to the Topic vocabulary (plain text) storing the canonical path for each term
2. Or look up the actual composite page URL by finding the node with a matching `field_primary_topic` and reading its URL alias
3. Or use the Drupal `\Drupal\Core\Url` / path alias manager to resolve the path

Option 2 is the most robust but involves a database query per ancestor term. Since the taxonomy is shallow (max 3 levels) and this data is highly cacheable, the performance cost is negligible.

**Claude Code should verify the URL alias convention against a few real composite pages before choosing the implementation approach.**

---

## CSS

```css
/* ══════════════════════════════════════
   Topic breadcrumb trail
   ══════════════════════════════════════ */

.slnt-topic-trail {
  margin-bottom: 0.3rem;
}

/* ── Ancestor terms row ── */
.slnt-topic-trail__ancestors {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0;
  margin-bottom: 0.2rem;
}

/* ── Individual ancestor link ── */
.slnt-topic-trail__ancestor {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.9rem;
  font-weight: 400;
  text-decoration: none;
  transition: opacity 0.15s;
  /* colour set inline via style attribute from section colour */
}

.slnt-topic-trail__ancestor:hover {
  text-decoration: underline;
  text-underline-offset: 3px;
  text-decoration-thickness: 2px;
  /* underline colour inherits from the text colour (section colour) */
}

/* ── Separator slash ── */
.slnt-topic-trail__separator {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.9rem;
  font-weight: 400;
  color: var(--text-mid, #444);
  user-select: none;
}

/* ── Current / leaf term — when used as page title ── */
.slnt-topic-trail__current {
  display: block;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 2.4rem;
  font-weight: 700;
  line-height: 1.15;
  color: var(--solent-blue, #2c4f6e);
  margin: 0;
}

/* When current is an h1, reset default h1 margins */
h1.slnt-topic-trail__current {
  margin: 0;
}

/* ── Mobile ── */
@media (max-width: 799px) {
  .slnt-topic-trail__current {
    font-size: 1.75rem;
  }

  .slnt-topic-trail__ancestor {
    font-size: 0.82rem;
  }

  .slnt-topic-trail__separator {
    font-size: 0.82rem;
  }
}
```

---

## Integration Points

### 1. Composite pages (hero art paragraph)

The hero art paragraph currently displays a text field with the page title (e.g. "Screen") inside a **white background cutout box** on the coloured tile pattern. The topic trail sits inside this same white box, so the section colours have full contrast against the white background — no special colour overrides needed.

**Approach:** In the hero art paragraph template, replace the plain title text with the topic trail include. The preprocess function for the paragraph (or the composite page node) needs to build the trail from the composite page's `field_primary_topic` and pass it to the paragraph template.

The trail renders inside the white cutout box as:

```
Culture                    ← small, purple (#7C3AED), clickable, on white background
Screen                     ← large, solent blue, h1, on white background
```

All section colours pass WCAG AA against white:
- Culture #7C3AED on white: 5.4:1 ✓
- Sectors #2563EB on white: 4.7:1 ✓
- Living #059669 on white: 4.6:1 ✓
- About #475569 on white: 7.1:1 ✓
- Explore #D97706 on white: 3.3:1 (passes AA Large, borderline for normal text — acceptable at 0.9rem if bold, or consider darkening to #B45309 for 4.6:1)

### 2. Article pages (and other content pages)

On articles and other content pages, the topic trail acts as a **kicker** — a small coloured section label positioned above the page title. This is the same pattern used on the EELA project site (e.g. "NEWS" above the article headline).

For articles, the trail shows **ancestors only** (all clickable). The leaf/child term is the last clickable link, not displayed as a large heading — the article's own `<h1>` title serves as the page title below.

For an article with primary topic "Culture / Screen":

```
Culture / Screen                                    ← small, purple, both clickable (kicker)
The Rise of Independent Filmmaking in the Solent    ← large, solent blue, h1 (article title)
```

For an article with primary topic "Culture / Writing / Books":

```
Culture / Writing / Books                           ← small, purple, all clickable (kicker)
The Solent's Best Independent Bookshops             ← large, solent blue, h1 (article title)
```

In `node--article--full.html.twig`, insert the topic trail **above** the article title. Use the ancestors-only approach with `current_label: null` — all terms become clickable ancestors since the article title is the true page heading.

Replace:
```twig
<h1 class="slnt-article__title">{{ label }}</h1>
```

With:
```twig
{% if topic_trail_ancestors is not empty %}
  {% include '@customsolent/components/topic-trail.html.twig' with {
    ancestors: topic_trail_ancestors,
    current_label: null,
    current_as_heading: false,
  } %}
{% endif %}
<h1 class="slnt-article__title">{{ label }}</h1>
```

**Note:** On content pages, the full term chain (including the leaf) is passed as `ancestors` — every term in the chain is clickable. This differs from composite pages where the leaf becomes the `<h1>`. The preprocess function should provide a flag or a different variable to control this behaviour based on the content type or view mode.

### 3. Event pages (when full node view is enabled later)

Same approach as articles. Deferred for now since events use rabbit_hole.

### 4. Standalone use

The component can be included in any template that has access to a `field_primary_topic` term. The preprocess function provides the data; the Twig include renders it.

---

## Deferred: Term display in related topics

As discussed previously, when terms are displayed as tags/labels on content (e.g. in a "Related topics" section below an article), a different visual treatment applies:

- **No slashes** between parent and child — avoids misleading affordance
- Parent term displayed as smaller, lighter text; leaf term as larger, darker text
- Or grouped under parent headings with descriptive sentences (the conversational approach)

This is a **separate task** and is not covered by this brief. The topic trail component (this brief) is for navigation/wayfinding. The term display component (future brief) is for content metadata. They serve different purposes and have different visual treatments.

---

## Testing

1. **Composite page:** Visit a submenu landing page (e.g. Culture > Screen). Verify the trail shows "Culture" (purple, clickable, links to `/culture`) above "Screen" (large, solent blue, h1).
2. **Article page:** Visit an article with primary topic "Culture / Screen". Verify the trail shows "Culture / Screen" as a kicker (both purple, both clickable, linked to `/culture` and `/culture/screen` respectively) positioned above the article's own h1 title. The leaf term "Screen" should be clickable here (unlike on the composite page where it's the h1).
3. **Sub-sub-term:** If any content has a sub-sub-term primary topic, verify all ancestors are clickable and only the leaf is the current term.
4. **Section colours:** Check that Culture uses purple, Sectors uses blue, Living uses green, About uses slate, Explore uses amber.
5. **Hero art:** On composite pages with hero art, verify the trail sits inside the white cutout box and the section-coloured ancestor links are legible against the white background.
6. **Clickable links:** Verify ancestor links go to the correct composite pages.
7. **Accessibility:** Test with a screen reader. The nav landmark should be announced, links should be clearly labelled, slashes should not be read.
8. **Mobile:** Verify font sizes reduce appropriately below 800px.
9. **Missing primary topic:** If a node has no primary topic set, verify no trail is displayed (no empty nav element, no errors).
