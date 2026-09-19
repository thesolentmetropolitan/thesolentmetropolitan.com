<?php

/**
 * @file
 * Drush script: reorganise the front page into full-width slices.
 *
 * Before (inside the welcome enclosure):
 *   section_2_column
 *     left:  Welcome h1 · tagline h2 · intro text · About button
 *     right: "What's on" heading · events grid · See-all-events button
 *   enclosure: Latest articles
 *
 * After:
 *   enclosure (1em)      Welcome h1 · "What's on" heading · events grid
 *                        (full width, 4 columns) · See-all-events button
 *   section_2_column     left:  tagline h2
 *   (style: Front Intro  right: intro text (first paragraph only) ·
 *    Two Column)                About button, centred by the style class
 *   enclosure            Latest articles            (unchanged)
 *
 * Rationale (Rob): events are the freshest, most-changing content, so they
 * go above the fold — fruit and veg at the front of the supermarket. The
 * "We're helping to build…" line and the "Work in progress" note are
 * dropped: the page now looks finished and reads less wordy.
 *
 * Everything is located structurally (from the events grid outward), not by
 * paragraph id, so it runs the same on any environment. Existing paragraphs
 * are MOVED, not recreated. Idempotent: if the Welcome heading is already
 * outside the two-column section, nothing is done.
 *
 * Needs the classy style front_intro_two_column — import config first.
 *
 * Usage:
 *   drush php:script scripts/reorganise_front_page.php
 *   drush php:script scripts/reorganise_front_page.php -- --dry-run
 */

use Drupal\Component\Utility\Html;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const EVENTS_VIEW = 'events_listing';
const EVENTS_DISPLAY = 'view_display_front_page';
const INTRO_STYLE = 'front_intro_two_column';

if (!\Drupal::entityTypeManager()->getStorage('classy_paragraphs_style')->load(INTRO_STYLE)) {
  $say('ERROR: classy style ' . INTRO_STYLE . ' does not exist. Run config import first.');
  return;
}

// ── Front page node. ──
$front = (string) \Drupal::config('system.site')->get('page.front');
$internal = \Drupal::service('path_alias.manager')->getPathByAlias($front);
if (!preg_match('#^/node/(\d+)$#', $internal, $m) || !($node = Node::load((int) $m[1]))) {
  $say("ERROR: front page '$front' does not resolve to a node.");
  return;
}
$say("Front page: '{$node->getTitle()}' (nid {$node->id()}, {$node->bundle()})");

// ── Find the events grid, wherever it is. ──
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
$events_grid = NULL;
foreach ($walk($node) as $p) {
  if ($p->bundle() === 'view_display' && !$p->get('field_view')->isEmpty()) {
    $ref = $p->get('field_view')->first();
    if ($ref->target_id === EVENTS_VIEW && $ref->display_id === EVENTS_DISPLAY) {
      $events_grid = $p;
      break;
    }
  }
}
if (!$events_grid) {
  $say('ERROR: events grid (' . EVENTS_VIEW . ':' . EVENTS_DISPLAY . ') not found on the front page.');
  return;
}

// ── Welcome h1: give it the "space below" heading style so it doesn't sit
//    tight against the "What's on" heading that now follows it. Done on
//    every run (it is idempotent on its own) so it applies even where the
//    move below has already happened. ──
foreach ($walk($node) as $p) {
  if ($p->bundle() === 'heading' && $p->get('field_heading_size')->value === 'h1') {
    $styles = array_column($p->get('field_classy')->getValue(), 'target_id');
    if (!in_array('heading_space_below', $styles, TRUE)) {
      $say("Welcome heading {$p->id()}: " . ($dry_run ? 'would add' : 'adding') . ' style heading_space_below.');
      if (!$dry_run) {
        $p->get('field_classy')->appendItem(['target_id' => 'heading_space_below']);
        $p->save();
      }
    }
    break;
  }
}

$grid_parent = $events_grid->getParentEntity();
if ($grid_parent instanceof Paragraph && $grid_parent->bundle() === 'enclosure') {
  $say("Already reorganised: the events grid (paragraph {$events_grid->id()}) sits in enclosure {$grid_parent->id()}, not in a two-column section. Nothing to do.");
  return;
}
if (!$grid_parent instanceof Paragraph || $grid_parent->bundle() !== 'section_2_column'
  || $events_grid->get('parent_field_name')->value !== 'field_content_component_right') {
  $say('ERROR: expected the events grid in the right column of a section_2_column; found something else. Refusing to guess.');
  return;
}
$section = $grid_parent;
$welcome_enclosure = $section->getParentEntity();
if (!$welcome_enclosure instanceof Paragraph || $welcome_enclosure->bundle() !== 'enclosure') {
  $say('ERROR: the two-column section is not inside an enclosure. Refusing to guess.');
  return;
}

// ── Identify the pieces. ──
$left = $section->get('field_content_component_left')->referencedEntities();
$right = $section->get('field_content_component_right')->referencedEntities();

$welcome = $tagline = $intro = $about = NULL;
foreach ($left as $p) {
  if ($p->bundle() === 'heading' && $p->get('field_heading_size')->value === 'h1' && !$welcome) {
    $welcome = $p;
  }
  elseif ($p->bundle() === 'heading' && !$tagline) {
    $tagline = $p;
  }
  elseif ($p->bundle() === 'intro' && !$intro) {
    $intro = $p;
  }
  elseif ($p->bundle() === 'call_to_action' && !$about) {
    $about = $p;
  }
}
$identified = array_filter([$welcome, $tagline, $intro, $about]);
if (count($identified) !== 4 || count($left) !== 4) {
  $say('ERROR: left column is not the expected [h1 heading, heading, intro, call_to_action]. Found: '
    . implode(', ', array_map(fn($p) => $p->bundle() . ':' . $p->id(), $left)) . '. Refusing to guess.');
  return;
}

// ── Trim the intro text to its first real block. ──
$old_html = (string) $intro->get('field_text')->value;
$format = $intro->get('field_text')->format;
$dom = Html::load($old_html);
$body = $dom->getElementsByTagName('body')->item(0);
$kept = NULL;
$dropped = [];
foreach (iterator_to_array($body->childNodes) as $child) {
  $text = trim(str_replace("\xC2\xA0", ' ', $child->textContent ?? ''));
  if ($text === '') {
    $body->removeChild($child);
    continue;
  }
  if ($kept === NULL) {
    $kept = $text;
    continue;
  }
  $dropped[] = $text;
  $body->removeChild($child);
}
$new_html = trim(str_replace('&nbsp;</', '</', Html::serialize($dom)));
if ($kept === NULL) {
  $say('ERROR: intro text is empty. Refusing to continue.');
  return;
}

$label = fn(Paragraph $p) => $p->bundle() . ':' . $p->id();
$say('');
$say("New enclosure (1em), first in enclosure {$welcome_enclosure->id()}:");
$say('  ' . implode(' · ', array_map($label, array_merge([$welcome], $right))));
$say("Two-column section {$section->id()} (style " . INTRO_STYLE . '):');
$say('  left:  ' . $label($tagline));
$say('  right: ' . $label($intro) . ' · ' . $label($about));
$say('Intro text KEPT:    "' . mb_strimwidth($kept, 0, 110, '…') . '"');
foreach ($dropped as $d) {
  $say('Intro text DROPPED: "' . mb_strimwidth($d, 0, 110, '…') . '"');
}

if ($dry_run) {
  $say('');
  $say('DRY RUN — no changes made.');
  return;
}

$ref = fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];

// 1. Intro text.
$intro->set('field_text', ['value' => $new_html, 'format' => $format]);
$intro->save();

// 2. New full-width slice: Welcome + the three events paragraphs.
$slice_children = array_merge([$welcome], $right);
$slice = Paragraph::create([
  'type' => 'enclosure',
  'field_padding' => '1em',
  'field_content_component' => array_map($ref, $slice_children),
]);
$slice->setParentEntity($welcome_enclosure, 'field_content_component');
$slice->save();
foreach ($slice_children as $child) {
  $child->setParentEntity($slice, 'field_content_component');
  $child->save();
}

// 3. Two-column section: tagline left; intro + About right.
foreach ([$intro, $about] as $child) {
  $child->setParentEntity($section, 'field_content_component_right');
  $child->save();
}
$section->set('field_content_component_left', [$ref($tagline)]);
$section->set('field_content_component_right', [$ref($intro), $ref($about)]);
$section->set('field_style', ['target_id' => INTRO_STYLE]);
$section->save();

// 4. Welcome enclosure: new slice first, everything else in its old order.
$items = $welcome_enclosure->get('field_content_component')->getValue();
array_unshift($items, $ref($slice));
$welcome_enclosure->set('field_content_component', $items);
$welcome_enclosure->save();

$say('');
$say("Done. New enclosure {$slice->id()} created; section {$section->id()} restyled. Run drush cr next.");
