<?php

/**
 * @file
 * Regenerate URL aliases for Event, Organisation, and Link nodes.
 *
 * Run with: drush php:script scripts/regenerate-url-aliases.php
 *
 * Prerequisites:
 *   - customsolent_tokens enabled (provides [node:event_date_formatted]).
 *   - Pathauto patterns configured for event, organisation, link.
 *   - (Recommended) Back up the database first, e.g. `ddev snapshot`.
 *
 * Approach — redirect-friendly:
 *   Rather than deleting each existing alias and creating a fresh one (which
 *   would bypass the Redirect module and lose the old→new 301), we mark the
 *   node's Pathauto state as CREATE and let Pathauto *update* the alias in
 *   place. With redirect.settings:auto_redirect = true that update triggers an
 *   automatic 301 from the old alias to the new one. Nodes that currently have
 *   no alias simply get one created (nothing to redirect from). Nodes whose
 *   alias already matches the pattern are left untouched (no redirect churn).
 */

use Drupal\pathauto\PathautoState;

$entity_type_manager = \Drupal::entityTypeManager();
$node_storage = $entity_type_manager->getStorage('node');
$generator = \Drupal::service('pathauto.generator');
$alias_manager = \Drupal::service('path_alias.manager');

$content_types = ['event', 'organisation', 'link'];
$total_changed = 0;
$total_unchanged = 0;
$errors = [];

foreach ($content_types as $bundle) {
  echo "\n--- Processing: $bundle ---\n";

  $nids = $node_storage->getQuery()
    ->condition('type', $bundle)
    ->condition('status', 1)
    ->accessCheck(FALSE)
    ->sort('nid')
    ->execute();

  if (empty($nids)) {
    echo "  No published $bundle nodes found.\n";
    continue;
  }

  $bundle_changed = 0;
  $bundle_unchanged = 0;

  // Load in chunks to keep memory flat on large bundles (e.g. organisations).
  foreach (array_chunk($nids, 50) as $chunk) {
    foreach ($node_storage->loadMultiple($chunk) as $node) {
      $nid = $node->id();
      $title = $node->getTitle();

      // Read the current alias from a fresh lookup (avoid stale static cache).
      $alias_manager->cacheClear('/node/' . $nid);
      $old_alias = $alias_manager->getAliasByPath('/node/' . $nid);

      try {
        // Force Pathauto to (re)generate and update the alias in place.
        $node->path->pathauto = PathautoState::CREATE;
        $generator->updateEntityAlias($node, 'update');

        $alias_manager->cacheClear('/node/' . $nid);
        $new_alias = $alias_manager->getAliasByPath('/node/' . $nid);

        if ($new_alias === '/node/' . $nid) {
          echo "  [$bundle] $nid \"$title\" — no alias generated (check pattern/token)\n";
          $bundle_unchanged++;
        }
        elseif ($new_alias !== $old_alias) {
          echo "  [$bundle] $nid \"$title\"\n";
          echo "      old: $old_alias\n";
          echo "      new: $new_alias\n";
          $bundle_changed++;
        }
        else {
          $bundle_unchanged++;
        }
      }
      catch (\Throwable $e) {
        $msg = "  [$bundle] $nid \"$title\" — ERROR: " . $e->getMessage();
        echo "$msg\n";
        $errors[] = $msg;
      }
    }
  }

  echo "  $bundle: $bundle_changed changed, $bundle_unchanged unchanged/created-in-place\n";
  $total_changed += $bundle_changed;
  $total_unchanged += $bundle_unchanged;
}

echo "\n--- Rebuilding caches ---\n";
drupal_flush_all_caches();

echo "\n=== Summary ===\n";
echo "Total changed:   $total_changed\n";
echo "Total unchanged: $total_unchanged\n";
echo "Errors:          " . count($errors) . "\n";
if (!empty($errors)) {
  echo "\nErrors encountered:\n";
  foreach ($errors as $error) {
    echo "$error\n";
  }
}
echo "\nDone.\n";
