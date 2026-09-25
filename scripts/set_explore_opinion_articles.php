<?php

/**
 * @file
 * Drush script: list the articles tagged "Explore / Opinion" on /explore/opinion.
 *
 * Same pattern as Explore → Directories & Networks: a View Display
 * paragraph whose Topic field names the page's own Explore term and whose
 * Listing mode is Full. The Topic field matters — an Explore page's own
 * primary topic is deliberately NOT used as a listing scope (Explore
 * landings such as /explore/events aggregate the whole site), so without
 * it the block would list every article. Full mode gives the card grid
 * and one pager; the by-topic display finds articles with Opinion as
 * primary OR related topic.
 *
 * If the page already has an articles listing paragraph (e.g. one added
 * by hand with the wrong display or no topic), it is corrected in place
 * rather than duplicated: display, topic and mode are set; heading and
 * classy styles are left as they are.
 *
 * Usage:
 *   drush php:script scripts/set_explore_opinion_articles.php
 *   drush php:script scripts/set_explore_opinion_articles.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PAGE_ALIAS = '/explore/opinion';
const PAGE_NID_FALLBACK = 41;
const TOPIC_NAME = 'Explore / Opinion';
const VIEW_ID = 'articles_listing';
const DISPLAY_ID = 'view_display_primary_and_related';
const MODE = 'full';

$etm = \Drupal::entityTypeManager();
$path = \Drupal::service('path_alias.manager')->getPathByAlias(PAGE_ALIAS);
$nid = preg_match('#^/node/(\d+)$#', $path, $m) ? (int) $m[1] : PAGE_NID_FALLBACK;
$node = $etm->getStorage('node')->load($nid);
if (!$node || $node->bundle() !== 'composite_page') {
  $say('ABORT no composite page at ' . PAGE_ALIAS . " (node $nid).");
  return;
}
$terms = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'topic', 'name' => TOPIC_NAME]);
if (!$terms) {
  $say('ABORT topic term "' . TOPIC_NAME . '" not found.');
  return;
}
$term = reset($terms);

// First articles listing paragraph on the page, and the first text
// paragraph (the listing goes directly after it) with its parent.
$listing = NULL;
$text = NULL;
$walk = function ($entity, int $depth) use (&$walk, &$listing, &$text): void {
  if ($depth <= 0) {
    return;
  }
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if (!$p instanceof Paragraph) {
        continue;
      }
      if (!$listing && $p->bundle() === 'view_display' && $p->get('field_view')->target_id === VIEW_ID) {
        $listing = $p;
      }
      if (!$text && $p->bundle() === 'text') {
        $text = $p;
      }
      $walk($p, $depth - 1);
    }
  }
};
$walk($node, 6);

$view_value = [
  'target_id' => VIEW_ID,
  'display_id' => DISPLAY_ID,
  'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]),
];

if ($listing) {
  $fixes = [];
  if ($listing->get('field_view')->display_id !== DISPLAY_ID) {
    $fixes[] = 'display ' . $listing->get('field_view')->display_id . ' → ' . DISPLAY_ID;
  }
  if ((int) $listing->get('field_topic')->target_id !== (int) $term->id()) {
    $fixes[] = 'topic → ' . TOPIC_NAME;
  }
  if ($listing->get('field_listing_mode')->value !== MODE) {
    $fixes[] = 'mode ' . ($listing->get('field_listing_mode')->value ?: '(automatic)') . ' → ' . MODE;
  }
  if (!$fixes) {
    $say('OK    ' . PAGE_ALIAS . ": listing paragraph {$listing->id()} already set up.");
    return;
  }
  $say(($dry_run ? 'WOULD ' : 'FIX   ') . PAGE_ALIAS . ": listing paragraph {$listing->id()}: " . implode('; ', $fixes) . '.');
  if ($dry_run) {
    $say('DRY RUN — no changes made.');
    return;
  }
  $listing->set('field_view', $view_value);
  $listing->set('field_topic', $term->id());
  $listing->set('field_listing_mode', MODE);
  $listing->save();
  $parent = $listing->getParentEntity();
  $field_name = $listing->get('parent_field_name')->value;
  $items = [];
  foreach ($parent->get($field_name)->getValue() as $item) {
    if ((int) $item['target_id'] === (int) $listing->id()) {
      $item['target_revision_id'] = $listing->getRevisionId();
    }
    $items[] = $item;
  }
  $parent->set($field_name, $items);
  $parent->save();
  $node->save();
  $say('Done. Run drush cr next.');
  return;
}

if (!$text) {
  $say('ABORT ' . PAGE_ALIAS . ' has no text paragraph to place the listing after.');
  return;
}
$parent = $text->getParentEntity();
$field_name = $text->get('parent_field_name')->value;
$say(($dry_run ? 'WOULD ' : 'ADD   ') . PAGE_ALIAS . ': articles listing (topic "' . TOPIC_NAME . '", ' . MODE . " mode) after text paragraph {$text->id()}.");
if ($dry_run) {
  $say('DRY RUN — no changes made.');
  return;
}
$listing = Paragraph::create([
  'type' => 'view_display',
  'field_view' => $view_value,
  'field_topic' => $term->id(),
  'field_listing_mode' => MODE,
]);
$listing->setParentEntity($parent, $field_name);
$listing->save();
$items = [];
foreach ($parent->get($field_name)->getValue() as $item) {
  $items[] = $item;
  if ((int) $item['target_id'] === (int) $text->id()) {
    $items[] = ['target_id' => $listing->id(), 'target_revision_id' => $listing->getRevisionId()];
  }
}
$parent->set($field_name, $items);
$parent->save();
$node->save();
$say('Done. Run drush cr next.');
