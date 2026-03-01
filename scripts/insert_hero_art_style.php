<?php

/**
 * @file
 * Drush script to insert a hero_with_art_style paragraph into each composite_page node.
 *
 * For each composite_page node, this script:
 * 1. Creates a new hero_with_art_style paragraph with field_title set to the node title
 * 2. Prepends it to the node's field_content_component (before existing paragraphs)
 *
 * Usage:
 *   drush php:script scripts/insert_hero_art_style.php
 *   drush php:script scripts/insert_hero_art_style.php -- --dry-run
 *   drush php:script scripts/insert_hero_art_style.php -- --limit=5
 *   drush php:script scripts/insert_hero_art_style.php -- --dry-run --limit=5
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
$updated = 0;
$skipped = 0;

foreach ($nids as $nid) {
  if ($limit && $updated >= $limit) {
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

  // Check if a hero_with_art_style already exists at the beginning.
  if (!empty($existing_items)) {
    $first_paragraph = Paragraph::load($existing_items[0]['target_id']);
    if ($first_paragraph && $first_paragraph->bundle() === 'hero_with_art_style') {
      output("[$processed] Skipping '$title' (nid $nid) - already has hero_with_art_style at beginning");
      $skipped++;
      continue;
    }
  }

  output("[$processed] Processing '$title' (nid $nid)");

  if ($dry_run) {
    output("  Would create hero_with_art_style paragraph with field_title = '$title'");
    output("  Would prepend it to field_content_component (" . count($existing_items) . " existing paragraph(s))");
    $updated++;
    continue;
  }

  try {
    // Create the hero_with_art_style paragraph with the node's title.
    $hero = Paragraph::create([
      'type' => 'hero_with_art_style',
      'field_title' => $title,
    ]);
    $hero->save();

    // Prepend the hero before existing paragraphs.
    $new_items = [
      [
        'target_id' => $hero->id(),
        'target_revision_id' => $hero->getRevisionId(),
      ],
    ];
    foreach ($existing_items as $item) {
      $new_items[] = $item;
    }

    $node->set('field_content_component', $new_items);
    $node->save();

    output("  Created hero_with_art_style (id: " . $hero->id() . ") with title '$title'");
    $updated++;

  } catch (\Exception $e) {
    output("  ERROR: " . $e->getMessage());
    $skipped++;
  }
}

output("\n--- Summary ---");
output("Total composite_page nodes: " . count($nids));
output("Processed: $processed");
output("Updated: $updated");
output("Skipped: $skipped");

if ($dry_run) {
  output("\nThis was a DRY RUN. Run without --dry-run to make changes.");
}
