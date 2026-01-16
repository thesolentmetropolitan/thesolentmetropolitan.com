<?php

/**
 * @file
 * Drush script to create content nodes from menu items in structure_sync.data.yml.
 *
 * Usage:
 *   drush php:script scripts/create_menu_content.php
 *   drush php:script scripts/create_menu_content.php -- --dry-run
 *   drush php:script scripts/create_menu_content.php -- --limit=5
 *   drush php:script scripts/create_menu_content.php -- --dry-run --limit=5
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\pathauto\PathautoState;
use Symfony\Component\Yaml\Yaml;

/**
 * Output a message.
 */
function output($message) {
  echo $message . PHP_EOL;
}

// Parse command line arguments
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

// Path to the YML file
$yml_path = DRUPAL_ROOT . '/../config/sync/structure_sync.data.yml';

// Alternative path if running from web root
if (!file_exists($yml_path)) {
  $yml_path = DRUPAL_ROOT . '/config/sync/structure_sync.data.yml';
}

// Try relative to script location
if (!file_exists($yml_path)) {
  $yml_path = dirname(__DIR__) . '/config/sync/structure_sync.data.yml';
}

if (!file_exists($yml_path)) {
  output("Error: YML file not found. Tried:");
  output("  - " . DRUPAL_ROOT . '/../config/sync/structure_sync.data.yml');
  output("  - " . DRUPAL_ROOT . '/config/sync/structure_sync.data.yml');
  output("  - " . dirname(__DIR__) . '/config/sync/structure_sync.data.yml');
  exit(1);
}

output("Reading YML file: $yml_path");

// Parse the YML file
$data = Yaml::parseFile($yml_path);

if (empty($data['menus'])) {
  output("Error: No menus found in YML file.");
  exit(1);
}

$menus = $data['menus'];
$count = 0;
$created = 0;
$skipped = 0;

output("Found " . count($menus) . " menu items.");
if ($dry_run) {
  output("DRY RUN MODE - No changes will be made.\n");
}
if ($limit) {
  output("Limiting to $limit items.\n");
}

foreach ($menus as $menu_item) {
  // Check limit
  if ($limit && $created >= $limit) {
    output("\nReached limit of $limit items.");
    break;
  }

  $count++;

  // Only process main menu items
  if ($menu_item['menu_name'] !== 'main') {
    continue;
  }

  $title = $menu_item['title'];
  $uri = $menu_item['uri'];

  // Extract path from uri (remove 'internal:' prefix)
  $path = str_replace('internal:', '', $uri);

  // Skip if path is empty
  if (empty($path) || $path === '/') {
    output("[$count] Skipping '$title' - empty path");
    $skipped++;
    continue;
  }

  // Check if a node with this path alias already exists
  try {
    $existing_path = \Drupal::service('path_alias.manager')->getPathByAlias($path);
    if ($existing_path !== $path) {
      output("[$count] Skipping '$title' - path '$path' already exists (points to $existing_path)");
      $skipped++;
      continue;
    }
  } catch (\Exception $e) {
    // Path doesn't exist, which is fine
  }

  output("[$count] Processing: $title (path: $path)");

  if ($dry_run) {
    output("  Would create composite_page node with:");
    output("    - Title: $title");
    output("    - Path: $path");
    output("    - Paragraph 1: heading");
    output("        field_heading: '$title'");
    output("        field_heading_align: 'left'");
    output("        field_heading_size: 'h2'");
    output("        field_color_text: 'black'");
    output("    - Paragraph 2: text with '$title'");
    $created++;
    continue;
  }

  try {
    // Load the "black" color taxonomy term
    $color_terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'color', 'name' => 'black']);
    $black_term = reset($color_terms);

    if (!$black_term) {
      output("  WARNING: Could not find 'black' color term, skipping color field");
    }

    // Create the heading paragraph
    $heading_data = [
      'type' => 'heading',
      'field_heading' => $title,
      'field_heading_align' => 'left',
      'field_heading_size' => 'h2',
    ];
    if ($black_term) {
      $heading_data['field_color_text'] = ['target_id' => $black_term->id()];
    }
    $heading_paragraph = Paragraph::create($heading_data);
    $heading_paragraph->save();

    // Create the text paragraph
    $text_paragraph = Paragraph::create([
      'type' => 'text',
      'field_text' => [
        'value' => "<p>$title</p>",
        'format' => 'basic_html',
      ],
    ]);
    $text_paragraph->save();

    // Create the node (using composite_page content type)
    $node = Node::create([
      'type' => 'composite_page',
      'title' => $title,
      'status' => 1, // Published
      'field_content_component' => [
        [
          'target_id' => $heading_paragraph->id(),
          'target_revision_id' => $heading_paragraph->getRevisionId(),
        ],
        [
          'target_id' => $text_paragraph->id(),
          'target_revision_id' => $text_paragraph->getRevisionId(),
        ],
      ],
      'path' => [
        'alias' => $path,
        'pathauto' => PathautoState::SKIP,
      ],
    ]);
    $node->save();

    output("  Created node ID: " . $node->id() . " with path alias: $path");
    $created++;

  } catch (\Exception $e) {
    output("  ERROR: " . $e->getMessage());
    $skipped++;
  }
}

output("\n--- Summary ---");
output("Total menu items: " . count($menus));
output("Processed: $count");
output("Created: $created");
output("Skipped: $skipped");

if ($dry_run) {
  output("\nThis was a DRY RUN. Run without --dry-run to create content.");
}
