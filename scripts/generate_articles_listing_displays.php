<?php

/**
 * @file
 * LOCAL CONFIG GENERATOR — not part of any release. Run once on a dev site,
 * then `drush cex`; production receives the result through config import.
 *
 * Gives articles_listing the two by-topic displays the section pages use,
 * built through the Views config API rather than hand-written YAML, with
 * the topic arguments and OR query copied from events_listing so the two
 * views cannot drift apart:
 *
 *   - view_display_primary_and_related : the full listing on
 *     /{topic}/articles — primary OR related topic, newest first, compact
 *     article cards, 24 per page (rows of four) with a pager.
 *   - view_display_preview : up to 8 cards on a section page. Fetches 9;
 *     customsolent trims the ninth and uses its existence to decide
 *     whether to show "View more".
 *
 * view_display_listing_cards (the all-articles page /explore/articles) is
 * deliberately left without topic arguments: the Explore pages fall back
 * to a Culture + Sectors + Living scope, and an article with no topic or
 * an Explore-only topic would vanish from the all-articles page.
 *
 * Idempotent: displays that already exist are left alone.
 *
 * Usage:
 *   drush php:script scripts/generate_articles_listing_displays.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('view');
$say = static function (string $m): void { echo $m . PHP_EOL; };

const LISTING = 'view_display_primary_and_related';
const PREVIEW = 'view_display_preview';

$events = $storage->load('events_listing');
$articles = $storage->load('articles_listing');
if (!$events || !$articles) {
  $say('ERROR: events_listing and articles_listing must both exist.');
  return;
}
$source = $events->get('display')[LISTING];
$arguments = $source['display_options']['arguments'];
$query = $source['display_options']['query'];
$cache_metadata = $source['cache_metadata'];
$compact_row = ['type' => 'entity:node', 'options' => ['relationship' => 'none', 'view_mode' => 'compact']];

$displays = $articles->get('display');
$changed = FALSE;

if (isset($displays[LISTING])) {
  $say('articles_listing: ' . LISTING . ' already exists.');
}
else {
  $displays[LISTING] = [
    'id' => LISTING,
    'display_title' => 'View Display Primary And Related',
    'display_plugin' => 'block',
    'position' => 7,
    'display_options' => [
      'arguments' => $arguments,
      'query' => $query,
      'pager' => [
        'type' => 'full',
        'options' => ['offset' => 0, 'items_per_page' => 24, 'total_pages' => NULL, 'id' => 0,
          'tags' => ['next' => '››', 'previous' => '‹‹', 'first' => '« First', 'last' => 'Last »'],
          'expose' => ['items_per_page' => FALSE, 'items_per_page_label' => 'Items per page', 'items_per_page_options' => '5, 10, 25, 50', 'items_per_page_options_all' => FALSE, 'items_per_page_options_all_label' => '- All -', 'offset' => FALSE, 'offset_label' => 'Offset'],
          'quantity' => 9, 'pagination_heading_level' => 'h4'],
      ],
      'row' => $compact_row,
      'defaults' => ['arguments' => FALSE, 'query' => FALSE, 'pager' => FALSE, 'row' => FALSE],
      'display_description' => 'Articles by topic (primary OR related), newest first: the full listing on /{topic}/articles as a card grid, 24 per page.',
      'display_extenders' => [],
    ],
    'cache_metadata' => $cache_metadata,
  ];
  $say('articles_listing: added ' . LISTING . '.');
  $changed = TRUE;
}

if (isset($displays[PREVIEW])) {
  $say('articles_listing: ' . PREVIEW . ' already exists.');
}
else {
  $displays[PREVIEW] = [
    'id' => PREVIEW,
    'display_title' => 'View Display Preview',
    'display_plugin' => 'block',
    'position' => 8,
    'display_options' => [
      'pager' => ['type' => 'some', 'options' => ['offset' => 0, 'items_per_page' => 9]],
      'arguments' => $arguments,
      'query' => $query,
      'row' => $compact_row,
      'defaults' => ['pager' => FALSE, 'arguments' => FALSE, 'query' => FALSE, 'row' => FALSE],
      'display_description' => 'Up to 8 cards for a section page (9 fetched; the 9th only signals "View more").',
      'display_extenders' => [],
    ],
    'cache_metadata' => $cache_metadata,
  ];
  $say('articles_listing: added ' . PREVIEW . '.');
  $changed = TRUE;
}

if ($changed) {
  $articles->set('display', $displays);
  $articles->save();
  $say('articles_listing saved. Now run: drush cex');
}
