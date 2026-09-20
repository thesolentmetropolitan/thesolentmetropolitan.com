<?php

/**
 * @file
 * Drush script: give every organisations / links listing the card grid.
 *
 * The organisations_listing and links_listing views now render rows in the
 * "compact" view mode (a card). The grid those cards sit in is CSS scoped
 * by a classy_paragraphs style on the View Display paragraph, per the site's
 * pattern. This sets that style — directory_compact_grid — on every View
 * Display paragraph, in the CURRENT revision of every node, that points at
 * one of those two views. Existing styles on the paragraph are kept.
 *
 * Idempotent. Needs the classy style — import config first.
 *
 * Usage:
 *   drush php:script scripts/apply_directory_card_grid.php
 *   drush php:script scripts/apply_directory_card_grid.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const GRID_STYLE = 'directory_compact_grid';
const VIEWS = ['organisations_listing', 'links_listing'];

if (!\Drupal::entityTypeManager()->getStorage('classy_paragraphs_style')->load(GRID_STYLE)) {
  $say('ERROR: classy style ' . GRID_STYLE . ' does not exist. Run config import first.');
  return;
}

$walk = function ($entity) use (&$walk): \Generator {
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $child) {
      if ($child instanceof Paragraph) {
        yield $child;
        yield from $walk($child);
      }
    }
  }
};

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = $node_storage->getQuery()->accessCheck(FALSE)->exists('field_content_component')->execute();
$set = $already = 0;
foreach ($node_storage->loadMultiple($nids) as $node) {
  foreach ($walk($node) as $p) {
    if ($p->bundle() !== 'view_display' || $p->get('field_view')->isEmpty()
      || !in_array($p->get('field_view')->target_id, VIEWS, TRUE)) {
      continue;
    }
    $have = array_column($p->get('field_classy')->getValue(), 'target_id');
    if (in_array(GRID_STYLE, $have, TRUE)) {
      $already++;
      continue;
    }
    $set++;
    $say(sprintf('  %s  %-34s paragraph %d (%s)', $dry_run ? 'WOULD SET' : 'SET', mb_strimwidth($node->label(), 0, 34, '…'), $p->id(), $p->get('field_view')->target_id));
    if (!$dry_run) {
      $p->get('field_classy')->appendItem(['target_id' => GRID_STYLE]);
      $p->save();
    }
  }
}
$say("Listings given the card grid: $set. Already had it: $already." . ($dry_run ? ' DRY RUN — no changes made.' : ' Run drush cr next.'));
