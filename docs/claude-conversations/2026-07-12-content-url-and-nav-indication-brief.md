# The Solent Metropolitan — Content URL Patterns & Topic-Based Navigation Indication

## Overview

Two changes for Article, Event, and Organisation content types:

1. **URL pattern change** — remove the `/explore` prefix. Content nodes use `/articles/`, `/events/`, `/organisations/` as their path prefix instead.
2. **Navigation indication from primary topic** — on full node view, the desktop menu highlights the section and sub-section matching the content's `field_primary_topic`, not the URL path.

**Desktop only. Drupal 11 compatible.**

---

## Change 1: URL Patterns

### New patterns

| Content type | Old prefix | New prefix | Example URL |
|-------------|-----------|-----------|-------------|
| Article | `/explore/articles/...` | `/articles/...` | `/articles/the-rise-of-independent-filmmaking` |
| Event | `/explore/events/...` | `/events/...` | `/events/jazz-on-the-seafront-2026-05-24-19-30` |
| Organisation | `/explore/organisations/...` | `/organisations/...` | `/organisations/southampton-forward` |

**Plural prefixes** — the container convention. `/events/jazz-on-the-seafront` reads as "one item from the events collection."

### Pathauto pattern updates

Update the existing Pathauto pattern config files:

- `config/sync/pathauto.pattern.event_full_page_url.yml` — change pattern prefix from `explore/events/` to `events/`
- `config/sync/pathauto.pattern.article_full_page_url.yml` — change pattern prefix from `explore/articles/` to `articles/` (if this config exists; otherwise update via admin UI)
- `config/sync/pathauto.pattern.organisation_full_page_url.yml` — change pattern prefix from `explore/organisations/` to `organisations/`

After updating the YAML: `drush cim && drush cr`

Then run the existing URL alias regeneration script (`scripts/regenerate-url-aliases.php`) to update all existing nodes. The script:

1. Finds all published Article, Event, and Organisation nodes
2. Deletes their current aliases (e.g. `/explore/events/jazz-on-the-seafront-2026-05-24-19-30`)
3. Triggers Pathauto to generate new aliases using the updated patterns (e.g. `/events/jazz-on-the-seafront-2026-05-24-19-30`)
4. Reports old and new aliases for verification

**Important — install and enable the Redirect module BEFORE running the script:**

```bash
composer require drupal/redirect
drush en redirect
drush cr
```

When the Redirect module is active and Pathauto regenerates an alias, Redirect **automatically creates a 301 redirect** from the old URL to the new URL. This means:

- `/explore/events/jazz-on-the-seafront-2026-05-24-19-30` → 301 → `/events/jazz-on-the-seafront-2026-05-24-19-30`
- `/explore/organisations/southampton-forward` → 301 → `/organisations/southampton-forward`
- etc.

No manual redirect creation needed for individual content URLs — the module handles it during alias regeneration. This protects any links shared on social media, search engine indexes, or external sites.

If the Redirect module is NOT installed before running the script, old URLs will 404. You would then need to either re-run the script after installing Redirect (but the old aliases would already be deleted, so Redirect wouldn't know what to redirect from), or manually create redirects for any URLs that have been shared.

**Sequence must be:**
1. Update Pathauto pattern config YAMLs
2. `drush cim && drush cr`
3. Install and enable Redirect module (if not already)
4. Run alias regeneration script
5. Verify old URLs redirect to new URLs

### Redirects for bare plural paths

Configure redirects so the bare collection paths lead somewhere useful:

| Path | Redirects to | Status |
|------|-------------|--------|
| `/events` | `/explore/events` | 301 |
| `/articles` | `/explore/articles` | 301 |
| `/organisations` | `/explore/orgs-directories` | 301 |

Add these manually in the Redirect module admin UI at `/admin/config/search/redirect`. These are structural redirects (collection path → listing page), not content redirects — the Redirect module won't create them automatically.

---

## Change 2: Topic-Based Navigation Indication

### The requirement

When viewing a full node page for Article, Event, or Organisation, the desktop menu should indicate the **content's primary topic section**, not the URL path.

For example, an event with `field_primary_topic` = "Culture / Music":
- **Culture** main menu item → navigation-indicated (bold, bottom line)
- **Music** sub-menu item → navigation-indicated (magenta dark background, off-white text)

This is different from the existing URL-based navigation indication (which would try to match `/events/...` against menu items and find nothing).

### Implementation: data attribute on the node template

The full view Twig templates for Article, Event, and Organisation output the primary topic's menu path as a data attribute on the `<article>` element. The JavaScript reads this attribute and applies navigation classes.

#### Template changes

In each full view template (`node--article--full.html.twig`, `node--event--full.html.twig`, `node--organisation--full.html.twig`), the `<article>` element should include:

```twig
<article{{ attributes.addClass(classes) }}
  {% if primary_topic_menu_path is defined and primary_topic_menu_path %}
    data-primary-topic-path="{{ primary_topic_menu_path }}"
  {% endif %}
>
```

For example, for an event with primary topic "Culture / Music", this outputs:

```html
<article class="node node--type-event ..." data-primary-topic-path="/culture/music">
```

#### Preprocess: derive the menu path from the primary topic

Add to `customsolent_preprocess_node()`:

```php
// For full view mode of article, event, organisation — provide the
// primary topic's URL path for menu state JS.
$content_bundles = ['article', 'event', 'organisation'];
if (in_array($node->bundle(), $content_bundles) && ($variables['view_mode'] ?? '') === 'full') {
  if ($node->hasField('field_primary_topic') && !$node->get('field_primary_topic')->isEmpty()) {
    $term = $node->get('field_primary_topic')->entity;
    if ($term) {
      // Build the URL path from the term's ancestor chain
      // e.g. "Culture / Music" → "/culture/music"
      $chain = [];
      $current = $term;
      while ($current) {
        array_unshift($chain, $current);
        $parents = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->loadParents($current->id());
        $current = !empty($parents) ? reset($parents) : null;
      }
      $variables['primary_topic_menu_path'] = _customsolent_build_term_url($chain, count($chain) - 1);
    }
  }
}
```

**Note:** This reuses the existing `_customsolent_build_term_url()` helper function. Claude Code should verify it exists and produces the correct path format (e.g. `/culture/music`).

#### JavaScript: topic-based navigation state

Add a new function to the existing menu state JS (or a separate file if preferred). This function:

1. Checks if the current page has a `[data-primary-topic-path]` attribute on an `<article>` element
2. If found, uses that path (instead of `window.location.pathname`) to match against menu item hrefs
3. Applies `.is-current-section` and `.is-active-page` classes accordingly

```javascript
/**
 * Topic-based navigation indication for content full view pages.
 *
 * When viewing a full node page (article, event, organisation), the menu
 * highlights the section matching the content's primary topic rather than
 * the URL path. The primary topic's menu path is output as a data attribute
 * on the <article> element by the Twig template.
 *
 * This runs AFTER the URL-based menu state detection. If a topic-based
 * match is found, it REPLACES any URL-based classes (since /events/... 
 * won't match any menu item anyway).
 */
(function () {
  'use strict';

  // Look for a node article element with a primary topic path
  const articleEl = document.querySelector('article[data-primary-topic-path]');
  if (!articleEl) return;

  const topicPath = articleEl.getAttribute('data-primary-topic-path');
  if (!topicPath) return;

  // Clear any existing navigation-indicated classes set by URL-based detection
  // (unlikely to exist since /events/... won't match menu items, but belt-and-braces)
  document.querySelectorAll('.is-current-section').forEach(function (el) {
    el.classList.remove('is-current-section');
  });
  document.querySelectorAll('.is-active-page').forEach(function (el) {
    el.classList.remove('is-active-page');
  });

  // Now apply navigation indication based on the topic path
  const mainMenuItems = document.querySelectorAll(/* main menu item selector — Claude Code to determine */);

  mainMenuItems.forEach(function (item) {
    const link = item.querySelector('a');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href || href === '/') return;

    // Check if the topic path starts with this menu item's href
    if (topicPath === href || topicPath.startsWith(href + '/')) {
      item.classList.add('is-current-section');

      // Sub-menu items within this section
      const subMenuItems = item.querySelectorAll(/* sub menu item selector — Claude Code to determine */);
      subMenuItems.forEach(function (subItem) {
        const subLink = subItem.querySelector('a');
        if (!subLink) return;

        const subHref = subLink.getAttribute('href');
        if (!subHref) return;

        if (topicPath === subHref || topicPath.startsWith(subHref + '/')) {
          subItem.classList.add('is-active-page');
        }
      });
    }
  });
})();
```

**Implementation notes for Claude Code:**

- Replace the placeholder selectors with the actual selectors used in the existing `menu-state.js` (or `customsolent.js`). The selectors must match what the URL-based detection already uses.
- This function should run **after** the URL-based detection function. If both are in the same file, place this function second. If in a separate file, ensure the library weight/dependency ordering runs it after.
- The clearing of existing `.is-current-section` / `.is-active-page` classes ensures no conflict between URL-based and topic-based detection. On content pages, topic wins. On composite pages (where `data-primary-topic-path` is absent), the URL-based detection operates unchanged.
- **Keep this code as separate as possible** from the existing URL-based menu state code. A separate function within the same file is acceptable. A separate file (e.g. `js/menu-state-topic.js`) is also fine — Claude Code should judge which is cleaner given the existing code structure.
- **Desktop only.** If the menu state JS is not already scoped to desktop, this code should check viewport width or the presence of the desktop menu before running.

---

## Link content type

The Link content type is NOT included in Change 2 (topic-based navigation indication) because Link nodes are external — they point to other websites. However, if Link nodes have full view pages on the site (per the recent full node views brief), they should follow the same pattern. **Include Link in the implementation if its full view template exists.** The list of content bundles in the preprocess would then be `['article', 'event', 'organisation', 'link']`.

---

## Testing

1. **URL pattern — new events:** Create a new event. Verify its URL uses `/events/` prefix (e.g. `/events/new-event-name-2026-07-12-19-00`).
2. **URL pattern — new articles:** Create a new article. Verify `/articles/` prefix.
3. **URL pattern — new organisations:** Create a new organisation. Verify `/organisations/` prefix.
4. **Batch update:** Run the alias regeneration script. Verify all existing content moves to the new URL patterns.
5. **Redirect — bare paths:** Visit `/events` — redirects to `/explore/events`. Visit `/articles` — redirects to `/explore/articles`. Visit `/organisations` — redirects to `/explore/orgs-directories`.
6. **Redirect — old URLs (if Redirect module):** Visit an old `/explore/events/...` URL — 301 redirects to the new `/events/...` URL.
7. **Navigation — event page:** Visit an event with primary topic "Culture / Music". "Culture" main menu item has navigation-indicated state (bold, bottom line). Open the Culture submenu — "Music" has navigation-indicated state (magenta dark background).
8. **Navigation — article page:** Visit an article with primary topic "Sectors / Technology". "Sectors" is indicated. "Technology" sub-menu item is indicated.
9. **Navigation — organisation page:** Visit an organisation with primary topic "Sectors / Arts & Culture". "Sectors" is indicated. "Arts & Culture" sub-menu item is indicated.
10. **Navigation — composite pages unchanged:** Visit `/culture/music` (a composite page). URL-based navigation indication still works as before — "Culture" and "Music" indicated.
11. **Navigation — home page unchanged:** Visit `/`. "Home" is indicated as before.
12. **No navigation on unmatched pages:** Visit a page whose topic doesn't match any menu item. No menu items are indicated.
13. **Desktop only:** Verify none of these navigation state changes affect the mobile menu.
14. **data attribute present:** Inspect the HTML of a content full view page. The `<article>` element has `data-primary-topic-path="/culture/music"` (or equivalent).
15. **data attribute absent on composite pages:** Inspect a composite page. The `<article>` element does NOT have `data-primary-topic-path` — the URL-based detection handles these.

---

## Files to create or modify

```
M  config/sync/pathauto.pattern.event_full_page_url.yml (prefix change)
M  config/sync/pathauto.pattern.article_full_page_url.yml (prefix change, if exists)
M  config/sync/pathauto.pattern.organisation_full_page_url.yml (prefix change)

M  web/themes/custom/customsolent/customsolent.theme
     — add primary_topic_menu_path to preprocess for article/event/organisation/link full view

M  web/themes/custom/customsolent/templates/content/node--article--full.html.twig
M  web/themes/custom/customsolent/templates/content/node--event--full.html.twig
M  web/themes/custom/customsolent/templates/content/node--organisation--full.html.twig
M  web/themes/custom/customsolent/templates/content/node--link--full.html.twig (if exists)
     — add data-primary-topic-path attribute to <article> element

A  web/themes/custom/customsolent/js/menu-state-topic.js (or extend existing menu-state.js)
M  web/themes/custom/customsolent/customsolent.libraries.yml
```

---

## Implementation order

| Step | Task | Who |
|------|------|-----|
| 1 | Update Pathauto pattern config YAMLs — remove `/explore` prefix | Claude Code |
| 2 | Import config: `drush cim && drush cr` | Rob or Claude Code |
| 3 | Install and enable Redirect module: `composer require drupal/redirect && drush en redirect && drush cr` | Rob or Claude Code |
| 4 | Run alias regeneration script — Redirect module automatically creates 301s from old to new URLs | Rob or Claude Code |
| 5 | Verify old `/explore/events/...` URLs redirect to new `/events/...` URLs | Rob |
| 6 | Configure bare path redirects: `/events` → `/explore/events`, `/articles` → `/explore/articles`, `/organisations` → `/explore/orgs-directories` (Redirect module admin UI) | Rob |
| 7 | Add `primary_topic_menu_path` to node preprocess for full view modes | Claude Code |
| 8 | Add `data-primary-topic-path` attribute to full view Twig templates | Claude Code |
| 9 | Create topic-based navigation JS (separate from URL-based detection) | Claude Code |
| 10 | Register JS in libraries.yml | Claude Code |
| 11 | Test all scenarios | Rob |
