<?php

/**
 * @file
 * Drush script to create a composite_page node from landing_page node/17.
 *
 * Copies the title, path alias, and all field_content_component paragraph
 * references from node/17 (landing_page) to a new composite_page node.
 *
 * The paragraphs themselves are shared (re-referenced), not duplicated.
 * After verifying the new node is correct, you can unpublish or delete node/17
 * and update the site front page setting to point to the new node.
 *
 * Usage:
 *   drush php:script scripts/copy_landing_to_composite.php
 *   drush php:script scripts/copy_landing_to_composite.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\pathauto\PathautoState;

function output($message) {
  echo $message . PHP_EOL;
}

// Parse arguments.
$dry_run = in_array('--dry-run', $extra ?? []);

if ($dry_run) {
  output('=== DRY RUN — no changes will be saved ===');
}

// Load the source node.
$source_nid = 17;
$source = Node::load($source_nid);

if (!$source) {
  output("ERROR: Node {$source_nid} not found.");
  return;
}

if ($source->bundle() !== 'landing_page') {
  output("ERROR: Node {$source_nid} is type '{$source->bundle()}', expected 'landing_page'.");
  return;
}

output("Source: node/{$source_nid} — \"{$source->getTitle()}\" (type: {$source->bundle()})");

// Gather paragraph references.
$components = $source->get('field_content_component')->getValue();
output("Found " . count($components) . " paragraph references in field_content_component.");

if (empty($components)) {
  output("WARNING: No paragraphs to copy. Aborting.");
  return;
}

// Create the new composite_page node.
$new_node = Node::create([
  'type' => 'composite_page',
  'title' => $source->getTitle(),
  'uid' => $source->getOwnerId(),
  'status' => 1,
  'field_content_component' => $components,
]);

if ($dry_run) {
  output("Would create composite_page node with title: \"{$source->getTitle()}\"");
  output("Would copy " . count($components) . " paragraph references.");
  output('=== DRY RUN complete ===');
  return;
}

$new_node->save();
$new_nid = $new_node->id();
output("Created composite_page node/{$new_nid} — \"{$new_node->getTitle()}\"");

// Copy the path alias if one exists.
$alias_manager = \Drupal::service('path_alias.manager');
try {
  $alias = $alias_manager->getAliasByPath("/node/{$source_nid}");
  if ($alias && $alias !== "/node/{$source_nid}") {
    $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
    $path_alias_storage->create([
      'path' => "/node/{$new_nid}",
      'alias' => $alias . '-new',
      'langcode' => $source->language()->getId(),
    ])->save();
    output("Created path alias: {$alias}-new → /node/{$new_nid}");
    output("NOTE: Alias has '-new' suffix to avoid conflict. Remove the old alias and rename when ready.");
  }
}
catch (\Exception $e) {
  output("Note: Could not copy path alias — " . $e->getMessage());
}

output('');
output('=== DONE ===');
output("New node: /node/{$new_nid}");
output('');
output('Next steps:');
output("  1. Review node/{$new_nid} in the browser");
output("  2. Update the site front page to /node/{$new_nid} (admin/config/system/site-information)");
output("  3. Update the path alias if needed (remove '-new' suffix)");
output("  4. Unpublish or delete node/{$source_nid} when satisfied");
