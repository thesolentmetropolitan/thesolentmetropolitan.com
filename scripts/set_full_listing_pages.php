<?php

/**
 * @file
 * Drush script: mark the pages that ARE listing pages as "Full listing".
 *
 * By-topic listings now default to Preview mode (8 cards + "View more").
 * That is right for section pages, wrong for a page whose whole purpose is
 * one list — Explore → Events would otherwise preview itself and link to
 * /explore/events/events.
 *
 * Rule: a published Composite Page whose primary topic is under "Explore"
 * and which carries exactly ONE by-topic listing gets field_listing_mode =
 * full on that listing. (Explore → Data carries two, so it behaves as a
 * section page.) Editors can change any of these by hand afterwards.
 *
 * Idempotent. Needs field_listing_mode — import config first.
 *
 * Usage:
 *   drush php:script scripts/set_full_listing_pages.php
 *   drush php:script scripts/set_full_listing_pages.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const LISTING_VIEWS = ['events_listing', 'organisations_listing', 'links_listing'];
const LISTING_DISPLAYS = ['view_display_primary_and_related', 'view_display_primary_topic', 'view_display_related_topics'];

$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'view_display');
if (!isset($fields['field_listing_mode'])) {
  $say('ERROR: field_listing_mode does not exist. Run config import first.');
  return;
}

$collect = function ($entity, array &$found) use (&$collect): void {
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if (!$p instanceof Paragraph) {
        continue;
      }
      if ($p->bundle() === 'view_display' && !$p->get('field_view')->isEmpty()
        && in_array($p->get('field_view')->target_id, LISTING_VIEWS, TRUE)
        && in_array($p->get('field_view')->display_id, LISTING_DISPLAYS, TRUE)) {
        $found[] = $p;
      }
      $collect($p, $found);
    }
  }
};

$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($storage->loadByProperties(['type' => 'composite_page', 'status' => 1]) as $node) {
  $term = $node->get('field_primary_topic')->entity;
  if (!$term || !str_starts_with($term->getName(), 'Explore / ')) {
    continue;
  }
  $found = [];
  $collect($node, $found);
  if (count($found) !== 1) {
    if ($found) {
      $say(sprintf('  leave   %-32s %d listings — behaves as a section page', $node->toUrl()->toString(), count($found)));
    }
    continue;
  }
  $p = $found[0];
  if ($p->get('field_listing_mode')->value === 'full') {
    $say(sprintf('  ok      %-32s already Full listing', $node->toUrl()->toString()));
    continue;
  }
  $say(sprintf('  %s %-32s paragraph %d → Full listing', $dry_run ? 'WOULD  ' : 'SET    ', $node->toUrl()->toString(), $p->id()));
  if (!$dry_run) {
    $p->set('field_listing_mode', 'full');
    $p->save();
  }
}
$say($dry_run ? 'DRY RUN — no changes made.' : 'Done. Run drush cr next.');
