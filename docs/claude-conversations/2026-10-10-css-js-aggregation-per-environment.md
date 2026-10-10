# 2026-10-10 — CSS/JS aggregation on in production, off locally

Branch: `css-js-aggregation` (from main at 3f1d166 "new locations").
Deploy: nothing special — the next `scripts/deploy.sh` (config import + cache
rebuild) switches aggregation on in production.

## Rob's brief

> Good evening Claude, I like your help with the following please: can we
> organise the config so that css and js aggregation is turned on, on live but
> [off] on local. I think https://www.drupal.org/project/config_split can be
> applied for this.

## Starting point

`config/sync/system.performance.yml` had `css.preprocess: false` and
`js.preprocess: false`. Because `deploy.sh` runs `drush config:import`, that
means production has been serving every CSS and JS file individually (the
front page links 35 theme stylesheets alone).

Nothing else differs between environments at the module level: there is no
devel, webprofiler or stage_file_proxy in `core.extension.yml`, and no
`settings.local.php` on the local machine (DDEV's own `settings.ddev.php`
is the only local include).

## Approach: `$config` override, not config_split

Drupal core's own `sites/example.settings.local.php` handles exactly this case
with configuration overrides. Two things make that the right tool here:

1. **Overrides are never exported.** `drush config:export` writes the stored
   value, not the overridden one, so the sync directory keeps the production
   setting. Verified below.
2. **config_split is for diverging config *entities*** — typically a different
   set of enabled modules per environment. With no such divergence yet it
   would add a module, a split directory and a `config_split.config_setting`
   entity to carry two booleans. If dev-only modules are wanted later
   (devel, stage_file_proxy), that is the point to add it; this override
   can then move into the split or stay as it is.

### Changes

- `config/sync/system.performance.yml`: `css.preprocess` and `js.preprocess`
  both `true` (the production value).
- `web/sites/default/settings.php`: after the existing `settings.ddev.php`
  include, a block guarded by the same `IS_DDEV_PROJECT` environment variable
  sets `$config['system.performance']['css']['preprocess'] = FALSE` and the
  same for `js`. Committed, so anyone cloning and running under DDEV gets the
  behaviour without a gitignored file.

One caveat, documented in the settings.php comment: the Performance admin
form (`/admin/config/development/performance`) displays the *stored* value,
so locally it will show both boxes ticked even though aggregation is off.
`drush cget system.performance css.preprocess --include-overridden` shows the
effective value.

## Verification (local, DDEV)

| Check | Result |
|---|---|
| `drush cim --diff` | only `system.performance` changed, both flags false → true |
| `drush cget system.performance css.preprocess` | `true` (stored) |
| same with `--include-overridden` | `false` (effective) |
| front page HTML | 42 stylesheet links, 35 from the theme, 0 under `files/css/`, 0 under `files/js/` |
| `drush cex -y` after import | "active configuration is identical to the export directory" — sync dir unchanged |
| `php -l settings.php` | no syntax errors |

## Production

`deploy.sh` → `config:import` turns both flags on; `drush cr` follows. Drupal
writes aggregates to `sites/default/files/css` and `sites/default/files/js`,
which it creates on first request. After deploying, view source on the live
front page and confirm the stylesheet links point at `files/css/…` rather than
`themes/custom/customsolent/css/…`.

Related but out of scope: the same file also exports `cache.page.max_age: 0`,
so anonymous page caching has no browser/proxy max-age in production. The same
override pattern (or a straight config change) applies if that is wanted.
