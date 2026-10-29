# The Solent Metropolitan

Drupal site code for [thesolentmetropolitan.com](https://thesolentmetropolitan.com).
Local development uses [DDEV](https://ddev.com); the custom theme is in
`web/themes/custom/customsolent/` and configuration in `config/`.

## Hero banner generator

`tools/hero-banner-generator/` builds the patterned hero banner tiles from
[Phosphor](https://phosphoricons.com) icons (MIT licence). `build_tiles.py`
places the icons on a hexagonal grid, so every icon is the same distance from
its neighbours, including across tile edges, and writes one seamless repeating
SVG tile per section; `fetch_and_build_tiles.sh` fetches the icons and runs it.
See its README.

## Content scripts

Content refactoring and release scripts live in a separate private repository.
Maintainers with access can fetch and run them in a checkout's context:

```bash
tools/run-private-script.sh fetch                  # clone or update scripts/ (git-ignored)
tools/run-private-script.sh NAME.php -- [ARGS]     # drush php:script scripts/NAME.php
tools/run-private-script.sh NAME.sh [ARGS]         # bash scripts/NAME.sh, with DRUSH set
```

The runner uses `ddev drush` locally and `./drush-dir/drush` on the server;
set `DRUSH` to override it.
