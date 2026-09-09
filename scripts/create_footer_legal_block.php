<?php

/**
 * @file
 * Creates the "Footer legal" custom block content: an enclosure paragraph
 * (classy style: Footer Legal) containing a menu paragraph that renders
 * the footer-legal menu.
 *
 * Run with: drush php:script scripts/create_footer_legal_block.php
 *
 * Idempotent: skips creation if a block with the same info already exists.
 * The block placement lives in config: block.block.customsolent_footerlegal.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\paragraphs\Entity\Paragraph;

$existing = \Drupal::entityTypeManager()
  ->getStorage('block_content')
  ->loadByProperties(['info' => 'Footer legal']);
if ($existing) {
  $block = reset($existing);
  echo "Block 'Footer legal' already exists (uuid: {$block->uuid()}), nothing to do.\n";
  return;
}

$menu = Paragraph::create([
  'type' => 'menu',
  'field_menu_name' => 'footer-legal',
  'field_menu_aria_label' => 'Legal and policies',
]);
$menu->save();

$enclosure = Paragraph::create([
  'type' => 'enclosure',
  'field_style' => ['target_id' => 'footer_legal'],
  'field_content_component' => [
    ['target_id' => $menu->id(), 'target_revision_id' => $menu->getRevisionId()],
  ],
]);
$enclosure->save();

$block = BlockContent::create([
  'info' => 'Footer legal',
  'type' => 'composite_block_type',
  // Fixed UUID so the block.block.customsolent_footerlegal config placement
  // matches on every environment this script runs on.
  'uuid' => '9c1f6a2e-4b7d-4b9a-9f3e-2c8a51d0f7b1',
  'field_content_component' => [
    ['target_id' => $enclosure->id(), 'target_revision_id' => $enclosure->getRevisionId()],
  ],
]);
$block->save();

echo "Created block 'Footer legal' (uuid: {$block->uuid()}), enclosure paragraph {$enclosure->id()}, menu paragraph {$menu->id()}.\n";
