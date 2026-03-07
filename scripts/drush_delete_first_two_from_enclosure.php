<?php

/**
 * @file
 * Drush script to delete the first and second paragraphs from inside the
 * enclosure paragraph on Composite Page nodes.
 *
 * Usage:
 *   drush php:script scripts/drush_delete_first_two_from_enclosure.php
 *   drush php:script scripts/drush_delete_first_two_from_enclosure.php -- --dry-run
 *   drush php:script scripts/drush_delete_first_two_from_enclosure.php -- --limit=5
 *   drush php:script scripts/drush_delete_first_two_from_enclosure.php -- --dry-run --limit=5
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Output a message.
 */
function output($message) {
  echo $message . PHP_EOL;
}

// Parse command line arguments.
$dry_run = false;
$limit = null;

if (isset($extra) && is_array($extra)) {
  foreach ($extra as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
      $dry_run = true;
    }
    if (strpos($arg, '--limit=') === 0) {
      $limit = (int) substr($arg, 8);
    }
  }
}

// Load all composite_page node IDs.
$nids = \Drupal::entityTypeManager()
  ->getStorage('node')
  ->getQuery()
  ->condition('type', 'composite_page')
  ->accessCheck(FALSE)
  ->execute();

output("Found " . count($nids) . " composite_page nodes.");
if ($dry_run) {
  output("DRY RUN MODE - No changes will be made.\n");
}
if ($limit) {
  output("Limiting to $limit nodes.\n");
}

$processed = 0;
$modified = 0;
$skipped = 0;

foreach ($nids as $nid) {
  if ($limit && $modified >= $limit) {
    output("\nReached limit of $limit nodes.");
    break;
  }

  $processed++;
  $node = Node::load($nid);

  if (!$node) {
    output("[$processed] Skipping nid $nid - could not load node");
    $skipped++;
    continue;
  }

  $title = $node->getTitle();
  $node_items = $node->get('field_content_component')->getValue();

  if (empty($node_items)) {
    output("[$processed] Skipping '$title' (nid $nid) - no paragraphs");
    $skipped++;
    continue;
  }

  // Find the enclosure paragraph.
  $enclosure = null;
  foreach ($node_items as $item) {
    $paragraph = Paragraph::load($item['target_id']);
    if ($paragraph && $paragraph->bundle() === 'enclosure') {
      $enclosure = $paragraph;
      break;
    }
  }

  if (!$enclosure) {
    output("[$processed] Skipping '$title' (nid $nid) - no enclosure paragraph found");
    $skipped++;
    continue;
  }

  $enclosure_children = $enclosure->get('field_content_component')->getValue();
  $child_count = count($enclosure_children);

  if ($child_count < 2) {
    output("[$processed] Skipping '$title' (nid $nid) - enclosure has only $child_count child paragraph(s), need at least 2");
    $skipped++;
    continue;
  }

  // Get the first two paragraphs for reporting.
  $first = Paragraph::load($enclosure_children[0]['target_id']);
  $second = Paragraph::load($enclosure_children[1]['target_id']);
  $first_type = $first ? $first->bundle() : 'unknown';
  $second_type = $second ? $second->bundle() : 'unknown';

  output("[$processed] Processing '$title' (nid $nid) - enclosure has $child_count child paragraph(s)");
  output("  1st: $first_type (id: {$enclosure_children[0]['target_id']})");
  output("  2nd: $second_type (id: {$enclosure_children[1]['target_id']})");

  if ($dry_run) {
    output("  Would delete these 2 paragraphs and keep the remaining " . ($child_count - 2) . " paragraph(s)");
    $modified++;
    continue;
  }

  try {
    // Remove the first two items from the enclosure's children.
    $remaining_children = array_slice($enclosure_children, 2);
    $enclosure->set('field_content_component', $remaining_children);
    $enclosure->save();

    // Delete the paragraph entities.
    if ($first) {
      $first->delete();
    }
    if ($second) {
      $second->delete();
    }

    $node->save();

    output("  Deleted 2 paragraphs, " . count($remaining_children) . " remaining in enclosure");
    $modified++;

  } catch (\Exception $e) {
    output("  ERROR: " . $e->getMessage());
    $skipped++;
  }
}

output("\n--- Summary ---");
output("Total composite_page nodes: " . count($nids));
output("Processed: $processed");
output("Modified: $modified");
output("Skipped: $skipped");

if ($dry_run) {
  output("\nThis was a DRY RUN. Run without --dry-run to make changes.");
}
else {
  output("\nRun 'drush cr' to clear caches.");
}
