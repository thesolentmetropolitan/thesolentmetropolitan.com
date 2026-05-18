# "in" kicker flip-flop — root-cause diagnosis and lazy_builder fix — 2026-05-18

Issue [#279](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/279). The "in" topic kicker on teasers (e.g. "in TECHNOLOGY") would intermittently show on the wrong section page after browsing — TechSolent on /sectors/technology would gain an "in Technology" kicker it shouldn't have; the same card on /sectors would lose its "in Technology" kicker. After a `drush cr` the page rendered correctly; a few clicks later, wrong again.

## Deterministic reproduction

```
drush cr
curl /sectors/technology     → nid 224, 225 show NO kicker        ✓ correct (exact match)
drush cr
curl /sectors                → nid 224, 225 show "in Technology"  ✓ correct
curl /sectors/technology     → nid 224, 225 show "in Technology"  ✗ WRONG (contaminated)
```

Visiting /sectors *first* poisons the teaser entity render cache for nodes 224/225 with kicker HTML appropriate to /sectors. The shared entity render cache (keyed by `node:NID + view_mode`, with no per-URL fragmentation) then returns that same HTML on /sectors/technology.

## Why the existing defenses didn't help

Three earlier layers were in place. None of them could fix this:

1. **Depth-based exact-match guard in `_customsolent_build_in_kicker`.** Correctly returns `NULL` when the content's term equals the page's term. But it only runs during `customsolent_preprocess_node`, which only fires on *cache miss*. On a cache hit, preprocess doesn't fire — Drupal returns the previously-cached HTML.

2. **Defensive tid-equality short-circuit in preprocess_node** (commit [119718e](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/commit/119718e)). Same problem: belt-and-braces against a stale `page_term_depth` is no help when preprocess never fires.

3. **`customsolent_node_view_alter`** adding `'url.path'` to the teaser's `#cache.contexts` to fragment the cache per URL. Looked right on paper.

The cache-context approach was the structural fix. So why didn't it work?

## Root cause

`customsolent_node_view_alter` **never fires**. Verified empirically by adding `file_put_contents()` tracing to the function and confirming zero invocations across a real HTTP request that rendered ~20 teasers.

Drupal 11's `\Drupal\Core\Extension\ModuleHandler::alter()` (web/core/lib/Drupal/Core/Extension/ModuleHandler.php) iterates only `$this->alterEventListeners` — which are sourced from **module** implementations. There's no theme-handler integration in the alter path. Theme-level `hook_ENTITY_TYPE_view_alter()` and `hook_entity_view_alter()` implementations are silently ignored.

This is a Drupal 11 architectural change. In Drupal 10 and earlier, theme alters fired. Either through deliberate redesign (alter event listeners) or as a side-effect of it, themes were dropped from the alter pipeline. (`customsolent_paragraph_view_alter` in the same file is also dead code — same mechanism.)

With the alter dead, `url.path` was never on the teaser's cache contexts, the entity render cache was a single shared bucket for `node:NID:teaser`, and the kicker baked in on whichever page rendered first leaked everywhere.

## Fix shape: `#lazy_builder` placeholder via a tiny custom module

The kicker computation has to happen **per request**, after the teaser's cache lookup, regardless of cache state. Drupal's standard mechanism for this is `#lazy_builder`: a render-array element that becomes a placeholder token at render time, gets baked into any enclosing cache, and is resolved per-request when the page response is assembled.

```php
$variables['in_kicker_lazy'] = [
  '#lazy_builder' => [
    'Drupal\customsolent_helpers\Render\KickerLazyBuilder::inKicker',
    [(int) $node->id()],
  ],
  '#create_placeholder' => TRUE,
];
```

- The lazy_builder element is set in `customsolent_preprocess_node` (theme can do this; preprocess fires).
- Drupal renders it to a `<drupal-render-placeholder callback="…" args="…" token="…" />` tag inline in the cached teaser HTML.
- Whatever caches sit above (teaser entity cache, views row cache, dynamic page cache) all store the placeholder token, not resolved HTML.
- After every cache layer is read, Drupal's placeholder strategy calls `KickerLazyBuilder::inKicker($nid)` for each token and substitutes the result. This happens *every request*, regardless of upstream cache state.

The callback resolves the page's topic scope from the **request URL** — not from `\Drupal::request()->attributes`. The view_display paragraph preprocess does set request attributes (`page_term_tid`, `page_term_depth`, `page_topic_tids`), but with multiple paragraphs on a page each one overwrites the previous. By the time placeholder resolution runs (after the full initial render), only the *last* paragraph's attributes survive. URL resolution is deterministic and immune to this ordering hazard.

### Why a module?

Drupal's render system requires lazy_builder callbacks to be on a class implementing `\Drupal\Core\Security\TrustedCallbackInterface`. The class needs a PSR-4 namespace (`Drupal\customsolent_helpers\Render\KickerLazyBuilder`), and only modules participate in PSR-4 autoloading the way the renderer expects. Themes don't.

Hence `web/modules/custom/customsolent_helpers/` — minimal: an `.info.yml`, a `.module` that registers two theme hooks (so the existing `templates/components/in-kicker.html.twig` and `primary-kicker.html.twig` in the theme can render the kicker), the `KickerLazyBuilder` class, and fallback copies of the two templates inside the module (the theme's versions override).

The module is purely a host for the trusted callback. No business logic; the kicker rules still live in the theme's existing helper functions (`_customsolent_build_in_kicker`, `_customsolent_build_from_kicker`, `_customsolent_topic_top_level`, `_customsolent_get_term_with_descendants`, `_customsolent_get_term_depth`). The module just reaches into those.

## Visual

The kicker is rendered through the same template (`templates/components/in-kicker.html.twig`) with the same variables (`in_kicker_items`, `in_kicker_section`, etc.) and in the same DOM position inside the teaser — below the title, inside the card. Byte-identical to the design before the bug.

## Performance

Each placeholder resolution runs once per teaser per request — same work the preprocess was doing on cache miss before, just now it runs unconditionally. Teaser body, views row, paragraph caches all behave normally. No measurable difference in page-cached anonymous traffic.

## Files

```
A  web/modules/custom/customsolent_helpers/customsolent_helpers.info.yml
A  web/modules/custom/customsolent_helpers/customsolent_helpers.module
A  web/modules/custom/customsolent_helpers/src/Render/KickerLazyBuilder.php
A  web/modules/custom/customsolent_helpers/templates/in-kicker.html.twig
A  web/modules/custom/customsolent_helpers/templates/primary-kicker.html.twig
M  web/themes/custom/customsolent/customsolent.theme
   - preprocess_node: kicker logic replaced by lazy_builder vars
   - Removed dead customsolent_node_view_alter
M  web/themes/custom/customsolent/templates/content/node--article--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--event--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--link--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--organisation--teaser.html.twig
   - {% include kicker.html.twig %} → {{ in_kicker_lazy }} {{ from_kicker_lazy }}
M  config/sync/core.extension.yml
   - + customsolent_helpers: 0
```

## Path not taken — relocating the kicker into the views-row template

Considered first: render the kicker as a sibling of the cached teaser inside the views-row wrapper (the view's render cache fragments per contextual-filter argument, so it's per-page). Implemented, tested, contamination cleared — but the kicker visual moved from inside the card (below title) to outside the card (after the CTA button). To preserve "inside the card, below the title" without DOM-level access to where the title is, the visual would have needed `position: absolute` with hand-tuned offsets across multiple card layouts (article horizontal-with-image vs. event vertical-with-CTA-row etc.). Fragile across content changes.

Lazy_builder leaves the kicker in the same DOM position. The visual cost of any other approach was the deciding factor.

## Path not taken — fixing the dead theme alter

Drupal 11 not invoking theme `_view_alter` hooks could be reported as a core issue/regression, but for this site it's a non-issue: the module path is well-established Drupal pattern (BigPipe, contextual links, user toolbar all use lazy_builder) and the alter wouldn't have been a clean fix anyway — the placeholder-based per-request resolution is more robust than per-URL cache fragmentation. A flag for "if this site grows, the dead `customsolent_paragraph_view_alter` should be moved into a module too" — same mechanism is silently failing there.

## How to test on live after deploy

```bash
git pull
drush cim -y      # enables customsolent_helpers
drush cr
```

Then:

1. Visit `/sectors` — TechSolent (nid 122) and similar Technology-primary cards show "in TECHNOLOGY" below their title.
2. Visit `/sectors/technology` — same cards show **no** kicker (exact match).
3. Browse around (/culture, /culture/music, /living, back to /sectors and /sectors/technology several times). Kickers stay correct on every page.
4. On `/culture`, cards whose primary topic sits outside Culture (e.g. an event with primary `Sectors / Environment` appearing via `field_related_topics`) show "from SECTORS / ENVIRONMENT" below the title.

If anything's wrong: capture the URL, the visible kicker, and `drush cr` to clear the slate. Lazy_builder doesn't depend on a clean cache to work correctly, but it's the first thing to try.

## Related

- [#279](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/279) — original report.
- `2026-05-17-term-display-in-kicker-implementation.md` — kicker implementation brief.
- `2026-05-18-in-kicker-exact-match-short-circuit.md` — earlier defensive fix attempt (still in place, now belt-and-braces; the lazy_builder is the structural fix).
- `2026-05-17-cache-staleness-and-drush-it-debug.md` — earlier cache-staleness diagnosis; the deeper "theme alters don't fire" is the actual root cause it was groping toward.
