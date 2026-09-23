<?php

/**
 * @file
 * Drush script: put the articles listing on the Articles page.
 *
 * Reproduces on production what Rob built locally on 2026-09-23: an
 * Enclosure paragraph (2em padding) holding one View Display paragraph
 * that embeds articles_listing / view_display_listing_cards with the
 * "Articles Compact Grid" classy style, appended after the page's
 * existing components. The page is found by its alias /explore/articles,
 * not by node id, so it works wherever that page lives.
 *
 * Idempotent: if any View Display in the page's tree already embeds
 * articles_listing, nothing changes. Needs the view display and the
 * classy style, so run config import first.
 *
 * Usage:
 *   drush php:script scripts/articles_page_listing.php -- --dry-run
 *   drush php:script scripts/articles_page_listing.php
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const ARTICLES_ALIAS = '/explore/articles';
const VIEW_ID = 'articles_listing';
const DISPLAY_ID = 'view_display_listing_cards';
const GRID_STYLE = 'articles_compact_grid';

$view = \Drupal::entityTypeManager()->getStorage('view')->load(VIEW_ID);
if (!$view || !$view->getDisplay(DISPLAY_ID)) {
  $say('ERROR: view ' . VIEW_ID . ' has no display ' . DISPLAY_ID . '. Run config import first.');
  return;
}
if (!\Drupal::entityTypeManager()->getStorage('classy_paragraphs_style')->load(GRID_STYLE)) {
  $say('ERROR: classy style ' . GRID_STYLE . ' does not exist. Run config import first.');
  return;
}

$path = \Drupal::service('path_alias.manager')->getPathByAlias(ARTICLES_ALIAS);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  $say('ERROR: no node has the alias ' . ARTICLES_ALIAS . '.');
  return;
}
$node = Node::load((int) $m[1]);
$say(($dry_run ? 'DRY RUN. ' : '') . 'Articles page is node ' . $node->id() . " '" . $node->getTitle() . "'");

// Walk the page's paragraph tree looking for an articles listing.
$found = NULL;
$stack = array_map(static fn($item) => $item['target_id'], $node->get('field_content_component')->getValue());
while ($stack && !$found) {
  $p = Paragraph::load(array_shift($stack));
  if (!$p) {
    continue;
  }
  if ($p->bundle() === 'view_display' && !$p->get('field_view')->isEmpty()
    && $p->get('field_view')->target_id === VIEW_ID) {
    $found = $p;
  }
  if ($p->hasField('field_content_component')) {
    foreach ($p->get('field_content_component')->getValue() as $item) {
      $stack[] = $item['target_id'];
    }
  }
}
if ($found) {
  $say('Already there: paragraph ' . $found->id() . ' embeds ' . VIEW_ID . '/' . $found->get('field_view')->display_id . '. Nothing to do.');
  return;
}

$say('ADD → Enclosure (2em) > View Display ' . VIEW_ID . '/' . DISPLAY_ID . ' with style ' . GRID_STYLE . ', appended as the last component');
if ($dry_run) {
  return;
}

$listing = Paragraph::create([
  'type' => 'view_display',
  'field_view' => [
    'target_id' => VIEW_ID,
    'display_id' => DISPLAY_ID,
  ],
  'field_classy' => [['target_id' => GRID_STYLE]],
  'field_listing_mode' => 'full',
]);
$listing->save();

$enclosure = Paragraph::create([
  'type' => 'enclosure',
  'field_padding' => '2em',
  'field_content_component' => [
    ['target_id' => $listing->id(), 'target_revision_id' => $listing->getRevisionId()],
  ],
]);
$enclosure->save();

$components = $node->get('field_content_component')->getValue();
$components[] = ['target_id' => $enclosure->id(), 'target_revision_id' => $enclosure->getRevisionId()];
$node->set('field_content_component', $components);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Articles listing added by scripts/articles_page_listing.php');
$node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
$node->save();

$say('Done: enclosure ' . $enclosure->id() . ' with view display ' . $listing->id() . ' on node ' . $node->id() . '.');
