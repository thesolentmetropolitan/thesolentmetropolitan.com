<?php

/**
 * @file
 * Redirect /contact to /about/contact (issue: contact form improvements).
 *
 * Run with: drush php:script scripts/create-contact-redirect.php
 *
 * Node 89 (Contact us) historically had two URL aliases: /contact and
 * /about/contact. The canonical URL is /about/contact; /contact should be a
 * 301 redirect, not a duplicate URL serving the same page.
 *
 * This script:
 *   1. Deletes any /contact alias pointing at /node/89 (leaving
 *      /about/contact as the sole alias).
 *   2. Creates a 301 redirect /contact -> /node/89. Pointing the redirect at
 *      the node (rather than the alias string) means the Redirect module
 *      always sends visitors to the node's current canonical alias, even if
 *      that alias changes later.
 *
 * Aliases and redirects are content entities, so this must be run on each
 * environment (local, then production as part of the release).
 * It is idempotent.
 */

use Drupal\redirect\Entity\Redirect;

// 1. Remove the /contact alias from node 89.
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$aliases = $alias_storage->loadByProperties([
  'path' => '/node/89',
  'alias' => '/contact',
]);
if ($aliases) {
  $alias_storage->delete($aliases);
  echo "deleted: /contact alias for /node/89 (" . count($aliases) . ")\n";
}
else {
  echo "no /contact alias for /node/89 (already removed)\n";
}

// 2. Create the redirect.
$redirect_storage = \Drupal::entityTypeManager()->getStorage('redirect');
$existing = $redirect_storage->loadByProperties(['redirect_source__path' => 'contact']);
if ($existing) {
  echo "exists:  /contact redirect (leaving as-is)\n";
}
else {
  Redirect::create([
    'redirect_source' => ['path' => 'contact', 'query' => []],
    'redirect_redirect' => ['uri' => 'internal:/node/89'],
    'status_code' => 301,
    'language' => 'und',
  ])->save();
  echo "created: /contact -> /node/89 (301, resolves to /about/contact)\n";
}

echo "Done.\n";
