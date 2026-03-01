<?php

/**
 * @file
 * Drush script to wrap existing paragraphs in an enclosure paragraph.
 *
 * For each composite_page node, this script:
 * 1. Creates a new enclosure paragraph
 * 2. Moves the node's existing paragraphs into the enclosure's field_content_component
 * 3. Sets the node's field_content_component to contain only the enclosure
 *
 * Usage:
 *   drush php:script scripts/wrap_in_enclosure.php
 *   drush php:script scripts/wrap_in_enclosure.php -- --dry-run
 *   drush php:script scripts/wrap_in_enclosure.php -- --limit=5
 *   drush php:script scripts/wrap_in_enclosure.php -- --dry-run --limit=5
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
$wrapped = 0;
$skipped = 0;

foreach ($nids as $nid) {
  if ($limit && $wrapped >= $limit) {
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
  $existing_items = $node->get('field_content_component')->getValue();

  if (empty($existing_items)) {
    output("[$processed] Skipping '$title' (nid $nid) - no paragraphs to wrap");
    $skipped++;
    continue;
  }

  // Check if already wrapped in a single enclosure to avoid double-wrapping.
  if (count($existing_items) === 1) {
    $only_paragraph = Paragraph::load($existing_items[0]['target_id']);
    if ($only_paragraph && $only_paragraph->bundle() === 'enclosure') {
      output("[$processed] Skipping '$title' (nid $nid) - already wrapped in enclosure");
      $skipped++;
      continue;
    }
  }

  $paragraph_count = count($existing_items);
  output("[$processed] Processing '$title' (nid $nid) - $paragraph_count paragraph(s)");

  if ($dry_run) {
    output("  Would create enclosure paragraph containing $paragraph_count existing paragraph(s):");
    foreach ($existing_items as $i => $item) {
      $p = Paragraph::load($item['target_id']);
      $type = $p ? $p->bundle() : 'unknown';
      output("    " . ($i + 1) . ". $type (id: {$item['target_id']})");
    }
    output("  Would set node's field_content_component to the new enclosure");
    $wrapped++;
    continue;
  }

  try {
    // Build the paragraph references for the enclosure's field_content_component.
    $child_references = [];
    foreach ($existing_items as $item) {
      $child_references[] = [
        'target_id' => $item['target_id'],
        'target_revision_id' => $item['target_revision_id'],
      ];
    }

    // Create the enclosure paragraph with existing paragraphs as children.
    $enclosure = Paragraph::create([
      'type' => 'enclosure',
      'field_content_component' => $child_references,
    ]);
    $enclosure->save();

    // Replace the node's paragraphs with just the enclosure.
    $node->set('field_content_component', [
      [
        'target_id' => $enclosure->id(),
        'target_revision_id' => $enclosure->getRevisionId(),
      ],
    ]);
    $node->save();

    output("  Created enclosure (id: " . $enclosure->id() . ") with $paragraph_count child paragraph(s)");
    $wrapped++;

  } catch (\Exception $e) {
    output("  ERROR: " . $e->getMessage());
    $skipped++;
  }
}

output("\n--- Summary ---");
output("Total composite_page nodes: " . count($nids));
output("Processed: $processed");
output("Wrapped: $wrapped");
output("Skipped: $skipped");

if ($dry_run) {
  output("\nThis was a DRY RUN. Run without --dry-run to make changes.");
}
