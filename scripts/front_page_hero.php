<?php

/**
 * @file
 * Drush script: give the front page a hero banner carrying its Welcome h1.
 *
 * The section landing pages open with a "Hero with art style" paragraph -
 * a patterned gradient banner with the page title cut out of its bottom
 * edge. The front page had a plain h1 heading paragraph instead. This
 * script:
 *
 *   1. Prepends a hero_with_art_style paragraph to the front page's
 *      content components with the title "Welcome to The Solent
 *      Metropolitan" and the classy style hero_art_style_home (the
 *      three-section gradient with every site icon in its tile).
 *   2. Removes the old h1 heading paragraph whose text starts "Welcome"
 *      (found anywhere in the page's paragraph tree), so the page keeps
 *      a single h1 - the theme renders the front page hero's title as h1.
 *
 * Both steps check their own state, so the script is safe to re-run.
 * Needs the classy style hero_art_style_home - import config first.
 *
 * Usage:
 *   drush php:script scripts/front_page_hero.php
 *   drush php:script scripts/front_page_hero.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$verb = $dry_run ? 'WOULD CHANGE →' : 'CHANGED →';

const HERO_STYLE = 'hero_art_style_home';
const HERO_TITLE = 'Welcome to The Solent Metropolitan';
const OLD_HEADING_PREFIX = 'Welcome';

if (!\Drupal::entityTypeManager()->getStorage('classy_paragraphs_style')->load(HERO_STYLE)) {
  $say('ERROR: classy style ' . HERO_STYLE . ' does not exist. Run config import first.');
  return;
}

$front = (string) \Drupal::config('system.site')->get('page.front');
$internal = \Drupal::service('path_alias.manager')->getPathByAlias($front);
if (!preg_match('#^/node/(\d+)$#', $internal, $m) || !($node = Node::load((int) $m[1]))) {
  $say("ERROR: front page '$front' does not resolve to a node.");
  return;
}
$say("Front page: '{$node->getTitle()}' (nid {$node->id()}, {$node->bundle()})");
if (!$node->hasField('field_content_component')) {
  $say('ERROR: front page has no field_content_component.');
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
$ref = fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];

// ── 1. Hero banner first. ──
$components = $node->get('field_content_component')->referencedEntities();
$first = $components[0] ?? NULL;
if ($first && $first->bundle() === 'hero_with_art_style') {
  $style = $first->get('field_classy')->target_id;
  $title = (string) $first->get('field_title')->value;
  if ($style === HERO_STYLE && $title === HERO_TITLE) {
    $say("1. Hero {$first->id()}: already in place.");
  }
  else {
    $say("1. Hero {$first->id()}: $verb style '$style' -> '" . HERO_STYLE . "', title \"$title\" -> \"" . HERO_TITLE . '".');
    if (!$dry_run) {
      $first->set('field_classy', [['target_id' => HERO_STYLE]]);
      $first->set('field_title', HERO_TITLE);
      $first->save();
    }
  }
}
else {
  $say('1. ' . $verb . ' new hero_with_art_style paragraph ("' . HERO_TITLE . '", ' . HERO_STYLE . ') prepended to the page.');
  if (!$dry_run) {
    $hero = Paragraph::create([
      'type' => 'hero_with_art_style',
      'field_title' => HERO_TITLE,
      'field_classy' => [['target_id' => HERO_STYLE]],
    ]);
    $hero->setParentEntity($node, 'field_content_component');
    $hero->save();
    $items = [$ref($hero)];
    foreach ($components as $c) {
      $items[] = $ref($c);
    }
    $node->set('field_content_component', $items);
    $node->save();
  }
}

// ── 2. Old Welcome heading out. ──
$old = NULL;
foreach ($walk($node) as $p) {
  if ($p->bundle() === 'heading' && !$p->get('field_heading')->isEmpty()
    && str_starts_with(ltrim((string) $p->get('field_heading')->value), OLD_HEADING_PREFIX)) {
    $old = $p;
    break;
  }
}
if (!$old) {
  $say('2. Old Welcome heading: none found (already removed).');
}
else {
  $parent = $old->getParentEntity();
  $field_name = $old->get('parent_field_name')->value;
  $text = preg_replace('/\s+/', ' ', trim((string) $old->get('field_heading')->value));
  if (!$parent || !$field_name || !$parent->hasField($field_name)) {
    $say("2. Heading {$old->id()} \"$text\": parent not resolvable — skipped.");
  }
  else {
    $say("2. Heading {$old->id()} \"$text\" ({$old->get('field_heading_size')->value}): $verb removed from {$parent->getEntityTypeId()} {$parent->id()} $field_name.");
    if (!$dry_run) {
      $items = [];
      foreach ($parent->get($field_name)->referencedEntities() as $c) {
        if ((int) $c->id() !== (int) $old->id()) {
          $items[] = $ref($c);
        }
      }
      $parent->set($field_name, $items);
      $parent->save();
      $old->delete();
    }
  }
}

$say($dry_run ? 'Dry run: nothing changed.' : 'Done.');
