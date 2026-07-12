<?php

/**
 * @file
 * Create the bare collection-path 301 redirects to the listing pages.
 *
 * Run with: drush php:script scripts/create-collection-redirects.php
 *
 * Content nodes now live at /articles, /events, /organisations (the /explore
 * prefix was dropped). The bare collection paths themselves have no page, so
 * point them at the corresponding listing (which stays under /explore):
 *
 *   /events        -> /explore/events         (301)
 *   /articles      -> /explore/articles       (301)
 *   /organisations -> /explore/organisations  (301)
 *
 * These are structural redirects (collection path -> listing page), so the
 * Redirect module does not create them automatically — hence this script.
 * It is idempotent: a redirect already present for a given source is left
 * as-is. Exact-match only, so /events/<slug> node URLs are unaffected.
 */

use Drupal\redirect\Entity\Redirect;

$map = [
  'events' => '/explore/events',
  'articles' => '/explore/articles',
  'organisations' => '/explore/organisations',
];

$storage = \Drupal::entityTypeManager()->getStorage('redirect');

foreach ($map as $source => $target) {
  $existing = $storage->loadByProperties(['redirect_source__path' => $source]);
  if (!empty($existing)) {
    echo "exists:  /$source (leaving as-is)\n";
    continue;
  }

  Redirect::create([
    'redirect_source' => ['path' => $source, 'query' => []],
    'redirect_redirect' => ['uri' => 'internal:' . $target],
    'status_code' => 301,
    'language' => 'und',
  ])->save();

  echo "created: /$source -> $target (301)\n";
}

echo "Done.\n";
