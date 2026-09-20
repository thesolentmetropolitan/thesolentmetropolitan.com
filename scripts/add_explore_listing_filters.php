<?php

/**
 * @file
 * Drush script: add the topic filter to Explore → Directories & Networks
 * and Explore → Organisations.
 *
 * Both are long lists (2 and ~20 pages). Explore → Events already has a
 * Section Filter paragraph above its listing — Culture / Sectors / Living,
 * each expanding to its sub-topics. This puts the same paragraph, with
 * "show sub-topics" on, directly above the listing on the other two pages.
 *
 * The narrowing itself is done by customsolent_helpers_views_query_alter():
 * on these pages ?topic= filters WITHIN the list instead of replacing its
 * scope.
 *
 * Pages are found by their primary topic term, not node id. Idempotent:
 * a page that already has a Section Filter is left alone.
 *
 * Usage:
 *   drush php:script scripts/add_explore_listing_filters.php
 *   drush php:script scripts/add_explore_listing_filters.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PAGE_TOPICS = ['Explore / Directories & Networks', 'Explore / Organisations'];

$find = function ($entity, string $bundle) use (&$find): ?Paragraph {
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if ($p instanceof Paragraph) {
        if ($p->bundle() === $bundle) {
          return $p;
        }
        if ($nested = $find($p, $bundle)) {
          return $nested;
        }
      }
    }
  }
  return NULL;
};

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');

foreach (PAGE_TOPICS as $topic_name) {
  $terms = $term_storage->loadByProperties(['vid' => 'topic', 'name' => $topic_name]);
  if (!$terms) {
    $say("SKIP  $topic_name: topic term not found.");
    continue;
  }
  $nodes = $node_storage->loadByProperties(['type' => 'composite_page', 'status' => 1, 'field_primary_topic' => reset($terms)->id()]);
  if (!$nodes) {
    $say("SKIP  $topic_name: no published page has this as its primary topic.");
    continue;
  }
  $node = reset($nodes);
  $url = $node->toUrl()->toString();

  if ($find($node, 'section_filter')) {
    $say("OK    $url: already has a topic filter.");
    continue;
  }
  $listing = $find($node, 'view_display');
  if (!$listing) {
    $say("SKIP  $url: no listing on the page.");
    continue;
  }
  $parent = $listing->getParentEntity();
  $field_name = $listing->get('parent_field_name')->value;
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "$url: topic filter above listing paragraph {$listing->id()}.");
  if ($dry_run) {
    continue;
  }

  $filter = Paragraph::create(['type' => 'section_filter', 'field_show_subterms' => 1]);
  $filter->setParentEntity($parent, $field_name);
  $filter->save();

  $items = [];
  foreach ($parent->get($field_name)->getValue() as $item) {
    if ((int) $item['target_id'] === (int) $listing->id()) {
      $items[] = ['target_id' => $filter->id(), 'target_revision_id' => $filter->getRevisionId()];
    }
    $items[] = $item;
  }
  $parent->set($field_name, $items);
  $parent->save();
}
$say($dry_run ? 'DRY RUN — no changes made.' : 'Done. Run drush cr next.');
