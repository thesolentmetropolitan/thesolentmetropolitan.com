# 2026-10-03 — Drupal core + contrib module updates (security)

Branch: `updates-2026-10-03` (from main at 331ec06 "add day to event dates,
more locations"). Deploy: `scripts/release-2026-10-03-core-module-updates.sh`.

## Rob's brief

> Good evening Claude, can you do a software update to Drupal core and any
> modules and make a deployment script for live production please.

## What was outdated

`./composer outdated --direct` (the project's own Composer 2.4.4 at
`tools/phpcomposer/composer.phar`, run with host PHP 8.3.30):

| Package                    | From    | To      | Notes                                   |
|----------------------------|---------|---------|-----------------------------------------|
| drupal/core-recommended    | 11.4.5  | 11.4.8  | 11.4.7 is SA-CORE-2026-013 (security)   |
| drupal/core-composer-scaffold / core-project-message | 11.4.5 | 11.4.8 | with core |
| drupal/webform             | 6.3.0   | 6.3.1   | security release, 22 coordinated fixes  |
| drupal/focal_point         | 2.1.2   | 2.1.3   |                                         |
| drupal/single_content_sync | 1.4.16  | 1.4.17  |                                         |
| drupal/superfish           | 1.15.0  | 1.16.0  | library 2.3.10 → 2.3.11 (aria-hidden on hidden accordion submenus); module enabled but no block placed, theme has its own menu |
| drush/drush                | 13.7.6  | 13.8.0  |                                         |
| drupal/custom_field        | 4.0.8   | 5.0.3   | **major — left on 4.x**, separate job   |

Transitive: symfony/* 7.4.17 → 7.4.20, twig/twig 3.28 → 3.30 (core 11.4.8
includes the Twig 3.30 compatibility fix), symfony/polyfill-* → 1.43,
phpstan 2.2.9 → 2.2.16, mglaman/phpstan-drupal 2.1.2 → 2.2.2.

Release notes read: 11.4.6 bug-fix (3 Sep), 11.4.7 security-only
(16 Sep, one moderately critical third-party-library issue), 11.4.8
bug-fix (26 Sep: Twig 3.30, memory leaks in long-running processes, field
options data-corruption guard, EntityQuery on unlimited-cardinality
fields). Webform 6.3.1 (23 Sep): XSS / access bypass / SSRF / DoS fixes;
exposure depends on features used — this site has one form (contact) at
/about/contact, with Honeypot, no JSON:API, no Webform Share.

## What was done

    ./composer update --with-all-dependencies --no-interaction

Diff: `composer.lock` plus the two tracked Superfish library files under
`web/libraries/drupal-superfish/`. No scaffold file changed. Composer's
own audit: "No security vulnerability advisories found".

Local rehearsal (ddev, snapshot `pre-updates-2026-10-03` taken first):

- `updatedb:status` / `updb -y`: no pending updates (none of these
  releases ship update hooks).
- `config:status` showed two differences **before** my change: the
  event compact display (`'D j M'`) and `structure_sync.data` (two new
  location terms). Both come from Rob's 2026-10-02 commit, which the local
  DB had not yet imported; ran `cim -y` locally so the test matched main.
  The update itself changes no config.
- Pages: / /explore /explore/articles /explore/opinion /culture /living
  /about /user/login all 200; /search → 302 → /search/node 200; unknown
  path 404; /contact → 301 → /about/contact 200 with the webform rendered.
- Watchdog: no Error entries. `core:requirements --severity=2`: only the
  SMTP-connection error, expected in ddev.
- The release script itself was run locally end to end
  (`DRUSH="ddev drush" COMPOSER_BIN=./composer BACKUP_DIR=/tmp`) — the
  first run caught a real bug, below.

## The release script

`scripts/release-2026-10-03-core-module-updates.sh`, same shape as the
September releases: run on prod from the project root after
`git pull origin main`. Differences from the content releases:

- **Composer install is inside the script**, between maintenance-on and
  updb, so the site never serves a half-swapped `vendor/` + `web/core/`.
  Earlier releases had Rob run it by hand beforehand; for a pure code
  update it is the whole deploy, so it belongs under maintenance mode.
- Pre-flight guards: project root, composer binary present, and
  `composer.lock` contains core 11.4.8 (catches a forgotten git pull).
- Step 6 reports config drift but **does not import** — the release has
  no config of its own, so any difference on prod is pre-existing and
  Rob's call.
- Prints Drupal/Drush/PHP before and after, requirement errors, a
  check-by-eye list, and rollback lines for code (git checkout the
  previous main sha for composer.lock + web/libraries, composer install)
  and DB (the gzip dump it took in step 1).

### Bug caught by the rehearsal

The first draft used a variable named `COMPOSER` for the binary path.
Composer reads an environment variable of that exact name as the path to
its *composer.json*, so `composer install` died with
`"./composer" does not contain valid JSON` — and left the site in
maintenance mode. Renamed to `COMPOSER_BIN`; the second run completed and
took maintenance mode off. Worth remembering for any future script.

## For Rob

- This is a **security deploy** (core SA-CORE-2026-013 + Webform 6.3.1),
  so it is worth running sooner than the usual content releases.
- Prod needs PHP ≥ 8.3 (already true: it runs 11.4.5) and Composer ≥ 2.3.6
  (the tracked `./composer` is 2.4.4, which is fine but old; upgrading the
  phar is a separate, optional tidy-up).
- `drupal/custom_field` 5.0 is a major version: not taken here. Needs its
  own branch with a look at the 5.x change record before touching it.
- Merge `updates-2026-10-03` to main yourself, then on prod:

        git pull origin main
        bash scripts/release-2026-10-03-core-module-updates.sh
