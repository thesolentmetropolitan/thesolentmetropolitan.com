<?php

/**
 * @file
 * Drush script: add the "Hero: Title Flush Left (desktop)" classy style to
 * the front page hero.
 *
 * On desktop the hero's cut-out title sat 1em further right than the
 * "What's on" heading below it. The classy style hero_title_flush_left
 * zeroes that indent so the title text lands on the same line as the
 * heading (css/paragraph-hero-art-style.css). This script adds the style
 * to the front page's hero_with_art_style paragraph, keeping whatever
 * styles it already has (Home - Front page). Safe to re-run.
 *
 * Needs the classy style hero_title_flush_left - import config first.
 *
 * Usage:
 *   drush php:script scripts/front_page_hero_flush_left.php
 *   drush php:script scripts/front_page_hero_flush_left.php -- --dry-run
 */

use Drupal\node\Entity\Node;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$verb = $dry_run ? 'WOULD CHANGE →' : 'CHANGED →';

const FLUSH_STYLE = 'hero_title_flush_left';

if (!\Drupal::entityTypeManager()->getStorage('classy_paragraphs_style')->load(FLUSH_STYLE)) {
  $say('ERROR: classy style ' . FLUSH_STYLE . ' does not exist. Run config import first.');
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

$hero = NULL;
foreach ($node->get('field_content_component')->referencedEntities() as $p) {
  if ($p->bundle() === 'hero_with_art_style') {
    $hero = $p;
    break;
  }
}
if (!$hero) {
  $say('ERROR: no hero_with_art_style paragraph on the front page. Run scripts/front_page_hero.php first.');
  return;
}

$styles = array_column($hero->get('field_classy')->getValue(), 'target_id');
if (in_array(FLUSH_STYLE, $styles, TRUE)) {
  $say("Hero {$hero->id()}: already has " . FLUSH_STYLE . ' (' . implode(', ', $styles) . ').');
}
else {
  $say("Hero {$hero->id()}: $verb classy styles '" . implode(', ', $styles) . "' -> '" . implode(', ', [...$styles, FLUSH_STYLE]) . "'.");
  if (!$dry_run) {
    $styles[] = FLUSH_STYLE;
    $hero->set('field_classy', array_map(fn($id) => ['target_id' => $id], $styles));
    $hero->save();
  }
}

$say($dry_run ? 'Dry run: nothing changed.' : 'Done.');
