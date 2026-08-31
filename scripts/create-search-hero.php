<?php

/**
 * @file
 * Create the Search page hero banner: classy style, block content, placement.
 *
 * Run with: drush php:script scripts/create-search-hero.php
 *
 * The Search page is not a composite_page node, so it can't carry a
 * hero_with_art_style paragraph in content. Instead:
 *   1. classy_paragraphs_style 'hero_art_style_search' (class
 *      hero-art-style--search, painted by css/hero-art-styles.css).
 *   2. A composite_block_type custom block holding a hero_with_art_style
 *      paragraph (title "Search", field_classy -> the style above).
 *   3. A block placement in the theme's 'highlighted' region (full width,
 *      above .layout-content), visible only on /search*.
 *
 * Classy style + block placement are config (exported via drush cex);
 * the block_content is content — export with scripts/content-export.sh
 * so production receives it, then run this script on production too
 * (idempotent; it will find the imported block by info label).
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\paragraphs\Entity\Paragraph;

$out = [];

// 1. Classy paragraphs style.
$style_storage = \Drupal::entityTypeManager()->getStorage('classy_paragraphs_style');
if (!$style_storage->load('hero_art_style_search')) {
  $style_storage->create([
    'id' => 'hero_art_style_search',
    'label' => 'Search',
    'classes' => 'hero-art-style--search',
  ])->save();
  $out[] = 'created classy style hero_art_style_search';
}
else {
  $out[] = 'classy style exists';
}

// 2. Block content with hero paragraph.
$bc_storage = \Drupal::entityTypeManager()->getStorage('block_content');
$existing = $bc_storage->loadByProperties(['info' => 'Search page hero banner']);
if ($existing) {
  $block = reset($existing);
  $out[] = 'block content exists (uuid ' . $block->uuid() . ')';
}
else {
  $paragraph = Paragraph::create([
    'type' => 'hero_with_art_style',
    'field_title' => 'Search',
    'field_classy' => ['target_id' => 'hero_art_style_search'],
  ]);
  $paragraph->save();
  $block = BlockContent::create([
    'type' => 'composite_block_type',
    'info' => 'Search page hero banner',
    'field_content_component' => [
      ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()],
    ],
  ]);
  $block->save();
  $out[] = 'created block content (uuid ' . $block->uuid() . ')';
}

// 3. Block placement (config entity referencing the content block's uuid).
$placement_storage = \Drupal::entityTypeManager()->getStorage('block');
$placement = $placement_storage->load('customsolent_searchherobanner');
if ($placement) {
  // Self-heal: if the placement points at a different (or missing) block
  // uuid — e.g. the placement config was imported but the block content was
  // recreated with a new uuid — re-wire it to the block found/created above.
  $expected_plugin = 'block_content:' . $block->uuid();
  if ($placement->getPluginId() !== $expected_plugin) {
    $placement->set('plugin', $expected_plugin);
    $settings = $placement->get('settings');
    $settings['id'] = $expected_plugin;
    $placement->set('settings', $settings);
    $placement->save();
    $out[] = 'placement re-wired to block uuid ' . $block->uuid();
  }
  else {
    $out[] = 'block placement exists and matches';
  }
}
if (!$placement) {
  $placement_storage->create([
    'id' => 'customsolent_searchherobanner',
    'theme' => 'customsolent',
    'region' => 'highlighted',
    'weight' => 0,
    'plugin' => 'block_content:' . $block->uuid(),
    'settings' => [
      'id' => 'block_content:' . $block->uuid(),
      'label' => 'Search page hero banner',
      'label_display' => '0',
      'provider' => 'block_content',
      'view_mode' => 'full',
    ],
    'visibility' => [
      'request_path' => [
        'id' => 'request_path',
        'negate' => FALSE,
        'pages' => '/search*',
      ],
    ],
  ])->save();
  $out[] = 'created block placement customsolent_searchherobanner';
}

echo implode(PHP_EOL, $out) . PHP_EOL . "Done.\n";
