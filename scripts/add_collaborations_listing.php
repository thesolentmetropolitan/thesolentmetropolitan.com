<?php

/**
 * @file
 * Drush script: put the Collaborations listing on /explore/collaborations.
 *
 * The Collaborations page (composite page, alias /explore/collaborations)
 * keeps its hero and intro text and gains a View Display paragraph showing
 * the collaborations_listing view's "View Display Listing Cards" display
 * with the "Collaborations Grid" classy style (three cards per row), at
 * the end of the enclosure that holds the intro text. The page's other
 * enclosure (by-topic listings) is left untouched.
 *
 * Idempotent: nothing is added when the page already holds a
 * collaborations_listing View Display paragraph.
 *
 * Usage:
 *   drush php:script scripts/add_collaborations_listing.php
 *   drush php:script scripts/add_collaborations_listing.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PAGE_ALIAS = '/explore/collaborations';
const PAGE_NID_FALLBACK = 95;
const VIEW_ID = 'collaborations_listing';
const DISPLAY_ID = 'view_display_listing_cards';
const CLASSY_STYLE = 'collaborations_grid';

$etm = \Drupal::entityTypeManager();
$path = \Drupal::service('path_alias.manager')->getPathByAlias(PAGE_ALIAS);
$nid = preg_match('#^/node/(\d+)$#', $path, $m) ? (int) $m[1] : PAGE_NID_FALLBACK;
$node = $etm->getStorage('node')->load($nid);
if (!$node || $node->bundle() !== 'composite_page') {
  $say('ABORT no composite page at ' . PAGE_ALIAS . " (node $nid).");
  return;
}

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

// The first enclosure that holds a text paragraph directly.
$enclosure = NULL;
foreach ($node->get('field_content_component')->referencedEntities() as $p) {
  if ($p->bundle() !== 'enclosure') {
    continue;
  }
  foreach ($p->get('field_content_component')->referencedEntities() as $child) {
    if ($child->bundle() === 'text') {
      $enclosure = $p;
      break 2;
    }
  }
}
if (!$enclosure) {
  $say('ABORT ' . PAGE_ALIAS . ' has no enclosure with an intro text to add the listing to.');
  return;
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
$items = [];
foreach ($enclosure->get('field_content_component') as $item) {
  if ($item->entity) {
    $items[] = ['target_id' => $item->target_id, 'target_revision_id' => $item->target_revision_id];
  }
}
$items[] = ['target_id' => $listing->id(), 'target_revision_id' => $listing->getRevisionId()];
$enclosure->set('field_content_component', $items);
$enclosure->save();
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Collaborations listing added by scripts/add_collaborations_listing.php');
$node->save();
$say("Done. Paragraph {$listing->id()} added.");
