# Deploy runbook — content URLs, event-date token, full node views & topic nav

Covers the work merged to `main` across the July 2026 sessions:

- **Full node views** for Event / Organisation / Link + Rabbit Hole set to
  "display_page" so they're directly reachable.
- **`customsolent_tokens`** module providing `[node:event_date_formatted]`.
- **URL patterns** — content nodes moved off the `/explore` prefix:
  `/articles/…`, `/events/…`, `/organisations/…` (Link stays `/explore/links/…`).
- **Topic-based navigation indication** — on content full-view pages the desktop
  menu reflects the content's `field_primary_topic`, not the URL.
- **Redirects** — old→new 301s (auto, via the Redirect module) plus bare
  collection-path 301s.

Run the steps in order — the alias scripts depend on config being imported first.

---

## 1. Pre-flight

```bash
# Back up the database (the alias regeneration is a bulk change)
drush sql:dump --gzip --result-file=/path/to/backup-pre-deploy.sql
#   (or your host's snapshot mechanism)

# Deploy the code
git pull origin main

# Install dependencies (in case composer.json changed)
composer install --no-dev --optimize-autoloader
```

## 2. Apply config + code

```bash
# Imports, in one go:
#   - enables the customsolent_tokens module (core.extension)
#   - sets Rabbit Hole to "display_page" for event / organisation / link
#   - imports the Pathauto patterns: event & organisation (no /explore),
#     plus the NEW article pattern (/articles/[title])
drush cim -y

drush cr
```

## 3. Regenerate URLs + redirects (order matters)

```bash
# Move existing node aliases to the new patterns. The Redirect module
# auto-creates a 301 from each old URL to the new one. /about/* articles
# (About > Overview / Team) are skipped so their hand-placed aliases survive.
drush php:script scripts/regenerate-url-aliases.php

# Create the bare collection-path 301s (idempotent):
#   /events        -> /explore/events
#   /articles      -> /explore/articles
#   /organisations -> /explore/organisations
drush php:script scripts/create-collection-redirects.php

drush cr
```

## 4. Verify

```bash
# New content URLs resolve (200)
curl -sI https://<site>/events/<some-event-slug>       | head -1
curl -sI https://<site>/organisations/<some-org-slug>  | head -1
curl -sI https://<site>/articles/<some-article-slug>   | head -1

# Old /explore URLs 301 to the new ones
curl -sI https://<site>/explore/events/<slug>          | grep -i location

# Bare paths 301 to their listings
curl -sI https://<site>/events                         | grep -i location

# About pages untouched (200)
curl -sI https://<site>/about/overview                 | head -1
```

Then eyeball a content page in a desktop browser: the menu should indicate the
item's **primary-topic** section/sub-item (e.g. an event tagged Culture / Music
highlights Culture + Music), while composite pages and Home are unchanged.

## 5. Notes & gotchas

- **Redirect module must be enabled** for the 301s. Step 2's `cim` enables it
  via `core.extension`; if the module code is somehow absent on prod, run
  `composer require drupal/redirect` first.
- **Both scripts are safe to re-run.** `regenerate-url-aliases.php` only creates
  a redirect when an alias actually changes; `create-collection-redirects.php`
  skips any redirect that already exists.
- **Event URL dates use the site's timezone.** If prod's timezone differs from
  dev, event slugs' date/time suffix reflects prod's timezone — expected.
- **Search index:** if search stores URLs, reindex afterwards
  (`drush search-api:reindex` or the core equivalent).
- No manual Redirect-admin-UI step is needed — the collection-path redirects are
  handled by `scripts/create-collection-redirects.php`.
