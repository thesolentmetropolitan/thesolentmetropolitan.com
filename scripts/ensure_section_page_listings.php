<?php

/**
 * @file
 * Drush script: every Culture / Sectors / Living section page can show
 * events and articles, and can signpost its organisations.
 *
 * Many section pages only ever had an Organisations listing placed on them,
 * because there were no events for the topic when the page was built. A
 * listing with nothing in it renders nothing at all, so it costs nothing to
 * place one everywhere: the Events block then appears by itself the day the
 * first event is tagged with the topic. The same goes for Articles (added
 * 2026-09-24): the strip's Articles tab and the block appear once the topic
 * has an article.
 *
 * For each published Composite Page whose primary topic is under Culture,
 * Sectors or Living:
 *   - no Events listing   → add one (events_listing, by primary + related
 *                           topic; listing mode left automatic = preview)
 *   - no Articles listing → add one (articles_listing, same display;
 *                           automatic = preview)
 *   - no Organisations or Links listing → add an Organisations one
 *                           (automatic = signpost)
 * New paragraphs go in the same container as the page's existing listing —
 * Events BEFORE it (and before the heading paragraph directly above it, so
 * that heading still belongs to its own listing), Articles directly AFTER
 * the Events listing. A page with no listing at all gets them at the end of
 * its first enclosure.
 *
 * Editors can move or remove any of them afterwards. Idempotent.
 *
 * Usage:
 *   drush php:script scripts/ensure_section_page_listings.php
 *   drush php:script scripts/ensure_section_page_listings.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const SECTIONS = ['Culture', 'Sectors', 'Living'];
const DISPLAY = 'view_display_primary_and_related';
const DIRECTORY_VIEWS = ['organisations_listing', 'links_listing'];

$collect = function ($entity, array &$found) use (&$collect): void {
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if ($p instanceof Paragraph) {
        if ($p->bundle() === 'view_display' && !$p->get('field_view')->isEmpty()) {
          $found[$p->get('field_view')->target_id][] = $p;
        }
        $collect($p, $found);
      }
    }
  }
};
$make = function (string $view, $parent, string $field_name): Paragraph {
  $p = Paragraph::create([
    'type' => 'view_display',
    'field_view' => [
      'target_id' => $view,
      'display_id' => DISPLAY,
      'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]),
    ],
  ]);
  $p->setParentEntity($parent, $field_name);
  $p->save();
  return $p;
};
$ref = fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$top_level = function ($term) use ($term_storage) {
  while ($parents = $term_storage->loadParents($term->id())) {
    $term = reset($parents);
  }
  return $term->getName();
};

$added_events = $added_articles = $added_orgs = $pages = 0;
$nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'composite_page', 'status' => 1]);
foreach ($nodes as $node) {
  $term = $node->get('field_primary_topic')->entity;
  if (!$term || !in_array($top_level($term), SECTIONS, TRUE)) {
    continue;
  }
  $pages++;
  $found = [];
  $collect($node, $found);
  $has_events = !empty($found['events_listing']);
  $has_articles = !empty($found['articles_listing']);
  $directory = array_merge($found['organisations_listing'] ?? [], $found['links_listing'] ?? []);
  if ($has_events && $has_articles && $directory) {
    continue;
  }

  // Where to put new paragraphs.
  $anchor = $directory[0] ?? ($found['events_listing'][0] ?? ($found['articles_listing'][0] ?? NULL));
  if ($anchor) {
    $parent = $anchor->getParentEntity();
    $field_name = $anchor->get('parent_field_name')->value;
  }
  else {
    $parent = NULL;
    foreach ($node->get('field_content_component')->referencedEntities() as $top) {
      if ($top->bundle() === 'enclosure') {
        $parent = $top;
        break;
      }
    }
    $field_name = 'field_content_component';
    if (!$parent) {
      $say(sprintf('  SKIP   %-40s no enclosure to put a listing in', $node->toUrl()->toString()));
      continue;
    }
  }

  $what = array_filter([!$has_events ? 'Events' : NULL, !$has_articles ? 'Articles' : NULL, !$directory ? 'Organisations' : NULL]);
  $say(sprintf('  %s %-40s + %s', $dry_run ? 'WOULD ' : 'ADD   ', $node->toUrl()->toString(), implode(' + ', $what)));
  $added_events += (int) !$has_events;
  $added_articles += (int) !$has_articles;
  $added_orgs += (int) !$directory;
  if ($dry_run) {
    continue;
  }

  $items = $parent->get($field_name)->getValue();
  $children = $parent->get($field_name)->referencedEntities();
  $index_of = function ($paragraph) use ($children): ?int {
    foreach ($children as $i => $child) {
      if ((int) $child->id() === (int) $paragraph->id()) {
        return $i;
      }
    }
    return NULL;
  };
  // Before a listing, and before the heading paragraph directly above it.
  $before = fn(int $index): int => ($index > 0 && $children[$index - 1]->bundle() === 'heading') ? $index - 1 : $index;

  $new_events = !$has_events ? $make('events_listing', $parent, $field_name) : NULL;
  $new_articles = !$has_articles ? $make('articles_listing', $parent, $field_name) : NULL;
  $new_orgs = !$directory ? $make('organisations_listing', $parent, $field_name) : NULL;

  $events_at = NULL;
  if ($new_events) {
    $at = ($directory && ($i = $index_of($directory[0])) !== NULL) ? $before($i) : count($items);
    array_splice($items, $at, 0, [$ref($new_events)]);
    $events_at = $at;
  }
  elseif (($i = $index_of($found['events_listing'][0])) !== NULL) {
    $events_at = $i;
  }
  if ($new_articles) {
    // Directly after the Events listing when it is in this container;
    // otherwise where Events would have gone.
    if ($events_at !== NULL) {
      $at = $events_at + 1;
    }
    elseif ($directory && ($i = $index_of($directory[0])) !== NULL) {
      $at = $before($i);
    }
    else {
      $at = count($items);
    }
    array_splice($items, $at, 0, [$ref($new_articles)]);
  }
  if ($new_orgs) {
    $items[] = $ref($new_orgs);
  }
  $parent->set($field_name, $items);
  $parent->save();
}
$say("Section pages checked: $pages. Events listings added: $added_events. Articles listings added: $added_articles. Organisations listings added: $added_orgs." . ($dry_run ? ' DRY RUN — no changes made.' : ' Run drush cr next.'));
