<?php

/**
 * @file
 * Drush script: put the Series listing on /explore/series.
 *
 * The Series page (composite page, alias /explore/series) keeps its hero
 * and intro text and gains a View Display paragraph showing the
 * series_listing view's "View Display Listing Cards" display, with the
 * "Series Grid" classy style (three cards per row). It goes at the end of
 * the enclosure that holds the intro text. Should that enclosure ever hold
 * a reference to a paragraph that no longer exists, it is dropped on the
 * way (none did on 2026-10-18).
 *
 * Idempotent: nothing is added when the page already holds a
 * series_listing View Display paragraph.
 *
 * Usage:
 *   drush php:script scripts/add_series_listing.php
 *   drush php:script scripts/add_series_listing.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PAGE_ALIAS = '/explore/series';
const PAGE_NID_FALLBACK = 94;
const VIEW_ID = 'series_listing';
const DISPLAY_ID = 'view_display_listing_cards';
const CLASSY_STYLE = 'series_grid';

$etm = \Drupal::entityTypeManager();
$path = \Drupal::service('path_alias.manager')->getPathByAlias(PAGE_ALIAS);
$nid = preg_match('#^/node/(\d+)$#', $path, $m) ? (int) $m[1] : PAGE_NID_FALLBACK;
$node = $etm->getStorage('node')->load($nid);
if (!$node || $node->bundle() !== 'composite_page') {
  $say('ABORT no composite page at ' . PAGE_ALIAS . " (node $nid).");
  return;
}

// Already there?
$found = FALSE;
$walk = function ($entity, int $depth) use (&$walk, &$found): void {
  if ($depth <= 0) {
    return;
  }
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if ($p instanceof Paragraph) {
        if ($p->bundle() === 'view_display' && $p->get('field_view')->target_id === VIEW_ID) {
          $found = TRUE;
        }
        $walk($p, $depth - 1);
      }
    }
  }
};
$walk($node, 6);
if ($found) {
  $say('OK    ' . PAGE_ALIAS . ' already lists ' . VIEW_ID . '.');
  return;
}

// The enclosure holding the intro text.
$enclosure = NULL;
foreach ($node->get('field_content_component')->referencedEntities() as $p) {
  if ($p->bundle() === 'enclosure') {
    $enclosure = $p;
    break;
  }
}
if (!$enclosure) {
  $say('ABORT ' . PAGE_ALIAS . ' has no enclosure paragraph to add the listing to.');
  return;
}

// Drop references to paragraphs that no longer exist.
$items = $enclosure->get('field_content_component');
$kept = [];
$dropped = 0;
foreach ($items as $item) {
  if ($item->entity) {
    $kept[] = ['target_id' => $item->target_id, 'target_revision_id' => $item->target_revision_id];
  }
  else {
    $dropped++;
    $say(($dry_run ? 'WOULD ' : 'DROP  ') . "dangling paragraph reference {$item->target_id} from enclosure {$enclosure->id()}.");
  }
}

$say(($dry_run ? 'WOULD ' : 'ADD   ') . 'View Display paragraph (' . VIEW_ID . ' / ' . DISPLAY_ID . ', style ' . CLASSY_STYLE . ') to enclosure ' . $enclosure->id() . '.');
if ($dry_run) {
  $say('Dry run — nothing changed.');
  return;
}

$listing = Paragraph::create([
  'type' => 'view_display',
  'field_view' => [
    'target_id' => VIEW_ID,
    'display_id' => DISPLAY_ID,
    'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]),
  ],
  'field_classy' => [['target_id' => CLASSY_STYLE]],
]);
$listing->save();
$kept[] = ['target_id' => $listing->id(), 'target_revision_id' => $listing->getRevisionId()];
$enclosure->set('field_content_component', $kept);
$enclosure->save();
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Series listing added by scripts/add_series_listing.php');
$node->save();
$say("Done. Paragraph {$listing->id()} added" . ($dropped ? ", $dropped dangling reference(s) dropped" : '') . '.');
