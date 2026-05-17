# Term display "in"/"from" kicker — implementation — 2026-05-17

Implements the brief at `2026-05-17-term-display-in-kicker-brief.md`. All work landed on branch `275-display-terms-content-list` in commit `1c057ea`.

## What's new

A teaser appearing in a listing on a section page now carries one (or zero) of two small kickers:

- **"in" kicker** sits **above the title**. Shown when the teaser's primary topic falls within the page's topic scope. Strips ancestors already represented by the page so the kicker only adds *new* information.
- **"from" kicker** sits **below the title**. Shown when the teaser's primary topic is *outside* the page's tree (it's appearing via `field_related_topics` from another section). Shows the full ancestor chain so the reader knows where the content primarily lives.

The two are mutually exclusive per item. Exact match (teaser's term IS the page's term) shows neither.

### Worked examples

| Page | Teaser's primary topic | Renders | Why |
|---|---|---|---|
| `/culture` | Culture / Music | `in MUSIC` above title | Page depth 0 strips "Culture"; what remains is "Music". Term colour purple, linked to `/culture/music`. |
| `/culture` | Culture / Stage / Theatre School | `in STAGE / THEATRE SCHOOL` above title | Page depth 0 strips "Culture"; chain shows both. Slash separator charcoal. |
| `/culture/stage` | Culture / Stage / Theatre School | `in THEATRE SCHOOL` above title | Page depth 1 strips "Culture" and "Stage". |
| `/culture/music` | Culture / Music | *(no kicker)* | Exact match — `_customsolent_build_in_kicker` returns NULL. |
| `/culture/screen` | Culture / Workshops / Acting Schools | `from CULTURE / WORKSHOPS / ACTING SCHOOLS` below title | Primary is outside Screen's tree; "from" kicker shows full chain. |
| `/explore/events` | Living / Mental Health | `in LIVING / MENTAL HEALTH` above title | All-topics fallback sets `page_term_depth = -1`; no stripping, full chain shown. |

### Conditional linking

Each term in either kicker is a link IF a published `composite_page` exists with that term as its primary topic; otherwise it renders as plain text. This is graceful degradation as the editor builds out section pages — broken links never appear, and the kicker just upgrades the missing terms to links as soon as their landing page exists.

## Implementation

### New helpers in `customsolent.theme`

| Helper | Purpose |
|---|---|
| `_customsolent_get_section_color($term)` | Convenience wrapper — walks to the term's top-level Topic and returns the section hex (Culture purple, Sectors blue, Living green, About slate, Explore amber). Falls back to slate on unrecognised roots. |
| `_customsolent_get_term_depth($term)` | Walks up to the root counting parents. Root terms (Culture, Sectors, Living, About, Explore) return 0; their direct children return 1; and so on. Used to compute how many ancestors the "in" kicker should strip. |
| `_customsolent_term_has_landing_page($tid)` | Returns `TRUE` when a published `composite_page` exists with this tid as `field_primary_topic`. Statically cached per tid within the request, so a listing with 30 items sharing the same parent term only hits the DB once for that term. |
| `_customsolent_build_in_kicker($content_term, $page_term_depth)` | Builds the array of `{ label, url, color }` items for the "in" kicker. Returns `NULL` when the content's term equals the page's term (nothing to display). |
| `_customsolent_build_from_kicker($term)` | Builds the array of `{ label, url, color }` items for the "from" kicker — full ancestor chain from root to leaf. |

`_customsolent_build_term_url($chain, $index)` already existed from earlier work — reused for the URL part of both kickers.

### `view_display` preprocess change

Where `page_topic_tids` is set as a request attribute, alongside it now sets `page_term_depth`:

```php
// Page depth for the "in" kicker. -1 in all-topics fallback (e.g.
// /explore/events) means "show the full chain".
$page_term_depth = -1;
if (($context['mode'] ?? '') === 'term' && !empty($context['term'])) {
  $page_term_depth = _customsolent_get_term_depth($context['term']);
}
\Drupal::request()->attributes->set('page_term_depth', $page_term_depth);
```

### `customsolent_preprocess_node` — split into "in" / "from" branches

The previous logic only set the "from" kicker (when primary was NOT in `page_topic_tids`). It now splits into three cases:

```php
if (in_array(($variables['view_mode'] ?? ''), ['teaser', 'compact'], TRUE)
  && $node->hasField('field_primary_topic')
  && !$node->get('field_primary_topic')->isEmpty()) {
  $page_tids = \Drupal::request()->attributes->get('page_topic_tids');
  if (is_array($page_tids) && !empty($page_tids)) {
    $primary_tid = (int) $node->get('field_primary_topic')->target_id;
    $primary_term = $node->get('field_primary_topic')->entity;
    if ($primary_term) {
      if (in_array($primary_tid, $page_tids, TRUE)) {
        // Inside page scope — "in" kicker (NULL if exact match).
        $depth = (int) \Drupal::request()->attributes->get('page_term_depth', -1);
        $items = _customsolent_build_in_kicker($primary_term, $depth);
        if ($items) {
          $variables['show_in_kicker']  = TRUE;
          $variables['in_kicker_items'] = $items;
          // … plus section_key for data-section hook
        }
      }
      else {
        // Outside page scope — "from" kicker with full chain.
        $variables['show_primary_kicker']      = TRUE;
        $variables['primary_kicker_ancestors'] = _customsolent_build_from_kicker($primary_term);
        // back-compat single-term vars kept for any legacy template
      }
    }
  }
}
```

Mutual exclusivity is enforced by the `if/else` — a single item can only enter one branch.

### Templates

- `templates/components/in-kicker.html.twig` — new. Renders the prefix "in", then each item as either `<a>` (linked) or `<span class="...--plain">` (plain text), joined with charcoal " / " separators.
- `templates/components/primary-kicker.html.twig` — rewritten. Same shape as in-kicker but with "from" prefix and `primary_kicker_ancestors` source.
- Four teaser templates (`node--{article,event,organisation,link}--teaser.html.twig`) — the in-kicker include is added above the title element, the primary-kicker include is moved below.

### CSS

The old `.slnt-teaser__kicker` block in `section-listing.css` was replaced with paired `.slnt-teaser__in-*` and `.slnt-teaser__from-*` classes:

- Lowercase charcoal prefix words (`in`, `from`).
- Uppercase, section-coloured term text.
- Charcoal slash separators (`text-transform: none`, regular weight — visually quiet).
- Underline-on-hover for linked terms.
- `--plain` modifier for non-linked terms (no underline, default cursor).

## Verification

| URL | Behaviour | Status |
|---|---|---|
| `/culture` | Single-term kicker, root stripped. | ✓ Verified |
| `/explore/events` | Full chain, no stripping (`page_term_depth = -1`). | ✓ Verified |
| `/culture/screen` | Primary-side: no kicker (exact match). Related: "from" with full chain. | ✓ Verified |
| `/explore/data` | Primary-side: no kicker (exact match with Data term). Related: "from CULTURE / TECHNOLOGY". | ✓ Verified |

## Cache contexts (already in place)

`customsolent_node_view_alter` already adds `url.path` and `url.query_args:topic` to teaser builds. These fragment the teaser render cache per page URL — so the same node teaser's "in" / "from" kicker is computed fresh per listing page. A `drush cr` is the only reliable way to clear pre-existing stale caches from before today's `page_term_depth` attribute was being set; after that, normal cache invalidation handles the rest.

## Deploying to live

Theme-only change — no Views config, no Drupal config import. Just a code pull and cache rebuild.

```bash
# On live (SSH or hosting console)

# 1. Pull latest code. Confirm commit 1c057ea is on prod (and any later
#    docs commits if you want them too).

# 2. Check for UI-side config drift — separate from this change, but
#    the kicker conditional-linking heavily uses field_primary_topic on
#    composite_page nodes, so worth verifying nothing's pending.
drush cex -y
git status

# 3. If step 2 produced no diff on Views configs we care about, no
#    config import is needed for this change. (Skip drush cim unless
#    you have other changes queued.)

# 4. Cache rebuild — required. The kicker code runs in preprocess and
#    helpers; the rendered teaser HTML is cached, so a rebuild flushes
#    stale entries from before the kickers existed.
drush cr

# 5. Spot-check in a browser:
#    /culture          → events show "in <SUBTERM>" above title (uppercase, section colour)
#    /explore/events   → events show "in <SECTION> / <SUBTERM>" (full chain)
#    /culture/screen   → orgs primarily in Screen: no kicker; cross-section orgs: "from <FULL CHAIN>" below title
#    /explore/data     → primary-side: no kicker; related-side: "from <FULL CHAIN>"
```

If the kickers don't appear on the first load after deploy, that's almost certainly the stale teaser render cache from before the deploy. A `drush cr` clears it. After that, normal cache invalidation handles future changes.

## Things worth flagging for the next iteration

1. **Taxonomy data drift on term 162.** *Acting Schools* has parent 57 (*Volunteering*) again — yesterday's structure_sync fix to put it under 56 (*Workshops*) was overwritten somewhere between then and now. The kicker faithfully shows the current data ("Culture / Volunteering / Acting Schools"), but the chain reads oddly to a human. Re-applied in a follow-up commit; staying vigilant on `drush cex` before `cim` will help future-proof.

2. **`view_display_primary_and_related` is still a virtual placeholder display.** Today's work didn't change that — `drupal_view()` against the placeholder is intercepted by the paragraph template and rendered as two real `drupal_view()` calls against `view_display_primary_topic` and `view_display_related_topics`. Option B from the original brief (a custom Views filter plugin with native OR) is still the long-term cleaner alternative. Non-urgent.

3. **`customsolent.theme` is now over 1000 lines** with the new helpers. The future-refactor note from the 2026-05-16 doc still applies — a custom module (`customsolent_listings`) is the natural home for the term-resolution and kicker logic. Worth doing when the next significant feature lands.

## Files changed

```
M  web/themes/custom/customsolent/customsolent.theme
   - _customsolent_get_section_color()
   - _customsolent_get_term_depth()
   - _customsolent_term_has_landing_page()
   - _customsolent_build_in_kicker()
   - _customsolent_build_from_kicker()
   - customsolent_preprocess_paragraph__view_display: sets page_term_depth on request
   - customsolent_preprocess_node: split into in / from kicker branches
M  web/themes/custom/customsolent/css/section-listing.css
   - replaced .slnt-teaser__kicker with .slnt-teaser__in-* / .slnt-teaser__from-* classes
A  web/themes/custom/customsolent/templates/components/in-kicker.html.twig
M  web/themes/custom/customsolent/templates/components/primary-kicker.html.twig (rewritten)
M  web/themes/custom/customsolent/templates/content/node--article--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--event--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--organisation--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--link--teaser.html.twig
   - in-kicker include above title / link
   - primary-kicker include moved to below title / link
```
