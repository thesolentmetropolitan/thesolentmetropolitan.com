<?php

/**
 * @file
 * LOCAL CONFIG GENERATOR — not part of any release. Run once on a dev site,
 * then `drush cex`; production receives the result through config import.
 *
 * Builds, through the Views config API rather than hand-written YAML:
 *   - events_listing : view_display_preview
 *   - directory_listing (new view, duplicated from organisations_listing):
 *     organisations AND links together, A–Z, with
 *       view_display_primary_and_related  (the paged listing)
 *       view_display_preview
 *
 * A preview display fetches 9 rows, not 8: customsolent_helpers trims the
 * ninth and uses its existence to decide whether to show "View more".
 *
 * Idempotent: displays/views that already exist are left alone.
 */

$storage = \Drupal::entityTypeManager()->getStorage('view');
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PREVIEW = 'view_display_preview';
const LISTING = 'view_display_primary_and_related';

$preview_display = function (array $listing_options, array $extra = []): array {
  return [
    'id' => PREVIEW,
    'display_title' => 'View Display Preview',
    'display_plugin' => 'block',
    'position' => 9,
    'display_options' => [
      'pager' => ['type' => 'some', 'options' => ['offset' => 0, 'items_per_page' => 9]],
      'arguments' => $listing_options['arguments'],
      'query' => $listing_options['query'],
      'row' => ['type' => 'entity:node', 'options' => ['relationship' => 'none', 'view_mode' => 'compact']],
      'defaults' => ['pager' => FALSE, 'arguments' => FALSE, 'query' => FALSE, 'row' => FALSE] + ($extra['defaults'] ?? []),
      'display_description' => 'Up to 8 cards for a section page (9 fetched; the 9th only signals "View more").',
      'display_extenders' => [],
    ] + ($extra['options'] ?? []),
    'cache_metadata' => $listing_options['__cache_metadata'],
  ];
};

// ── events_listing ──
$events = $storage->load('events_listing');
$displays = $events->get('display');
if (isset($displays[PREVIEW])) {
  $say('events_listing: ' . PREVIEW . ' already exists.');
}
else {
  $listing = $displays[LISTING]['display_options'] + ['__cache_metadata' => $displays[LISTING]['cache_metadata']];
  // Same filters as the front-page cards minus "promoted": published events
  // that have not ended. No exposed date filter on a preview.
  $filters = $displays['view_display_front_page']['display_options']['filters'];
  unset($filters['promote']);
  $displays[PREVIEW] = $preview_display($listing, [
    'defaults' => ['filters' => FALSE, 'filter_groups' => TRUE],
    'options' => ['filters' => $filters],
  ]);
  $events->set('display', $displays);
  $events->save();
  $say('events_listing: added ' . PREVIEW . '.');
}

// ── directory_listing ──
if ($storage->load('directory_listing')) {
  $say('directory_listing: already exists.');
}
else {
  $orgs = $storage->load('organisations_listing');
  $dir = $orgs->createDuplicate();
  $dir->set('id', 'directory_listing');
  $dir->set('label', 'Directory Listing (organisations + links)');
  $dir->set('description', 'Organisations and links together, A–Z. Used for section-page previews and the /{topic}/organisations listing page. "Directory" is a machine name only — nothing visitor-facing uses the word.');
  $displays = $dir->get('display');
  $displays['default']['display_options']['filters']['type']['value'] = ['organisation' => 'organisation', 'link' => 'link'];
  $displays['default']['display_options']['row']['options']['view_mode'] = 'compact';
  $keep = ['default' => $displays['default'], LISTING => $displays[LISTING]];
  // 24 per page: fills rows of three.
  $keep[LISTING]['display_options']['pager'] = [
    'type' => 'full',
    'options' => ['offset' => 0, 'items_per_page' => 24, 'total_pages' => NULL, 'id' => 0,
      'tags' => ['next' => '››', 'previous' => '‹‹', 'first' => '« First', 'last' => 'Last »'],
      'expose' => ['items_per_page' => FALSE, 'items_per_page_label' => 'Items per page', 'items_per_page_options' => '5, 10, 25, 50', 'items_per_page_options_all' => FALSE, 'items_per_page_options_all_label' => '- All -', 'offset' => FALSE, 'offset_label' => 'Offset'],
      'quantity' => 9, 'pagination_heading_level' => 'h4'],
  ];
  $keep[LISTING]['display_options']['defaults']['pager'] = FALSE;
  $listing = $keep[LISTING]['display_options'] + ['__cache_metadata' => $keep[LISTING]['cache_metadata']];
  $keep[PREVIEW] = $preview_display($listing);
  $dir->set('display', $keep);
  $dir->save();
  $say('directory_listing: created with ' . LISTING . ' and ' . PREVIEW . '.');
}

// ─────────────────────────────────────────────────────────────────────────
// Listing mode field on the View Display paragraph, and the paragraph view
// mode the /{topic}/{type} listing pages render it in.
// ─────────────────────────────────────────────────────────────────────────
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (!FieldStorageConfig::loadByName('paragraph', 'field_listing_mode')) {
  FieldStorageConfig::create([
    'field_name' => 'field_listing_mode',
    'entity_type' => 'paragraph',
    'type' => 'list_string',
    'cardinality' => 1,
    'settings' => ['allowed_values' => [
      'preview' => 'Preview — up to 8 cards and a "View more" link (section pages)',
      'full' => 'Full listing — filter and pager on this page',
    ]],
  ])->save();
  $say('field_listing_mode: storage created.');
}
if (!FieldConfig::loadByName('paragraph', 'view_display', 'field_listing_mode')) {
  FieldConfig::create([
    'field_name' => 'field_listing_mode',
    'entity_type' => 'paragraph',
    'bundle' => 'view_display',
    'label' => 'Listing mode',
    'description' => 'Applies to the events, organisations and links listings by topic. Preview is the default: a section page shows a few items of each kind and links to the full listing at /{topic}/events or /{topic}/organisations. Choose Full listing only on a page whose whole purpose is this one list (for example Explore → Events).',
    'required' => FALSE,
    'default_value' => [['value' => 'preview']],
  ])->save();
  $say('field_listing_mode: added to view_display.');
}
$form = EntityFormDisplay::load('paragraph.view_display.default');
if (!$form->getComponent('field_listing_mode')) {
  $form->setComponent('field_listing_mode', ['type' => 'options_select', 'weight' => 4])->save();
  $say('field_listing_mode: on the edit form.');
}
$display = EntityViewDisplay::load('paragraph.view_display.default');
if ($display->getComponent('field_listing_mode')) {
  $display->removeComponent('field_listing_mode')->save();
}
if (!EntityViewMode::load('paragraph.listing_page')) {
  EntityViewMode::create([
    'id' => 'paragraph.listing_page',
    'label' => 'Listing page',
    'description' => 'How a View Display paragraph is rendered on its automated /{topic}/{type} listing page: always the full listing, with its own topic filter.',
    'targetEntityType' => 'paragraph',
    'cache' => TRUE,
  ])->save();
  $say('paragraph view mode listing_page: created.');
}
