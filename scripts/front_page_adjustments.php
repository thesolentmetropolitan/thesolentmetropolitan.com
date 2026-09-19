<?php

/**
 * @file
 * Drush script: front page adjustments, after reorganise_front_page.php.
 *
 *   1. Welcome h1 gets the "Heading One Line Desktop" style — one line on
 *      desktop; on tablet/phone it breaks where the editor's line break is
 *      ("Welcome to" / "The Solent Metropolitan").
 *   2. The tagline gets a line break after its comma:
 *      "The broader perspective," / "for a distinct region."
 *   3. The About button moves out of the intro section into its own slice
 *      (an enclosure with the "Centre Call To Action" style) directly below
 *      it, so it sits in the middle of the page.
 *   4. The About and See-all-articles buttons take the Explore colour.
 *      About and Articles both live under Explore, and section colour is a
 *      deliberate cue. White text stays: 5.1:1 on #BC4A08 (AA); near-black
 *      would be 3.4:1 and fail.
 *
 * Everything is found structurally from the front page node, not by id.
 * Each step checks its own state, so the script is safe to re-run.
 * Needs the two new classy styles — import config first.
 *
 * Usage:
 *   drush php:script scripts/front_page_adjustments.php
 *   drush php:script scripts/front_page_adjustments.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$verb = $dry_run ? 'WOULD CHANGE →' : 'CHANGED →';

const STYLE_ONE_LINE = 'heading_one_line_desktop';
const STYLE_CENTRE = 'centre_call_to_action';
const STYLE_INTRO = 'front_intro_two_column';
const EXPLORE_COLOUR_TERM = 'Explore';
const ARTICLES_LINK = 'internal:/explore/articles';

$styles = \Drupal::entityTypeManager()->getStorage('classy_paragraphs_style');
foreach ([STYLE_ONE_LINE, STYLE_CENTRE, STYLE_INTRO] as $id) {
  if (!$styles->load($id)) {
    $say("ERROR: classy style $id does not exist. Run config import first.");
    return;
  }
}
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'color', 'name' => EXPLORE_COLOUR_TERM]);
if (!$terms) {
  $say('ERROR: colour term "' . EXPLORE_COLOUR_TERM . '" not found.');
  return;
}
$explore_tid = (int) reset($terms)->id();

$front = (string) \Drupal::config('system.site')->get('page.front');
$internal = \Drupal::service('path_alias.manager')->getPathByAlias($front);
if (!preg_match('#^/node/(\d+)$#', $internal, $m) || !($node = Node::load((int) $m[1]))) {
  $say("ERROR: front page '$front' does not resolve to a node.");
  return;
}
$say("Front page: '{$node->getTitle()}' (nid {$node->id()}, {$node->bundle()})");

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
$all = iterator_to_array($walk($node), FALSE);
$ref = fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];

// ── The intro section is the two-column section carrying the intro style. ──
$section = NULL;
foreach ($all as $p) {
  if ($p->bundle() === 'section_2_column' && $p->get('field_style')->target_id === STYLE_INTRO) {
    $section = $p;
    break;
  }
}
if (!$section) {
  $say('ERROR: intro section (style ' . STYLE_INTRO . ') not found. Run reorganise_front_page.php first.');
  return;
}

// ── 1. Welcome h1: one line on desktop. ──
foreach ($all as $p) {
  if ($p->bundle() === 'heading' && $p->get('field_heading_size')->value === 'h1') {
    $have = array_column($p->get('field_classy')->getValue(), 'target_id');
    if (in_array(STYLE_ONE_LINE, $have, TRUE)) {
      $say("1. Welcome heading {$p->id()}: already one-line-on-desktop.");
    }
    else {
      $say("1. Welcome heading {$p->id()}: $verb style " . STYLE_ONE_LINE . '.');
      if (!$dry_run) {
        $p->get('field_classy')->appendItem(['target_id' => STYLE_ONE_LINE]);
        $p->save();
      }
    }
    break;
  }
}

// ── 2. Tagline: break after the comma. ──
$tagline = NULL;
foreach ($section->get('field_content_component_left')->referencedEntities() as $p) {
  if ($p->bundle() === 'heading') {
    $tagline = $p;
    break;
  }
}
if (!$tagline) {
  $say('2. Tagline: no heading found in the intro section\'s left column — skipped.');
}
else {
  $value = (string) $tagline->get('field_heading')->value;
  if (str_contains($value, "\n")) {
    $say("2. Tagline {$tagline->id()}: already has a line break.");
  }
  elseif (($pos = strpos($value, ', ')) === FALSE) {
    $say("2. Tagline {$tagline->id()}: no comma to break after (\"$value\") — skipped.");
  }
  else {
    $new = substr($value, 0, $pos + 1) . "\r\n" . substr($value, $pos + 2);
    $say("2. Tagline {$tagline->id()}: $verb \"" . str_replace("\r\n", '" / "', $new) . '".');
    if (!$dry_run) {
      $tagline->set('field_heading', $new);
      $tagline->save();
    }
  }
}

// ── 3. About button: its own centred slice, directly below the intro. ──
$about = NULL;
foreach ($section->get('field_content_component_right')->referencedEntities() as $p) {
  if ($p->bundle() === 'call_to_action') {
    $about = $p;
    break;
  }
}
if (!$about) {
  // Already moved? Find it for the colour step.
  foreach ($all as $p) {
    if ($p->bundle() === 'enclosure' && $p->get('field_style')->target_id === STYLE_CENTRE) {
      foreach ($p->get('field_content_component')->referencedEntities() as $c) {
        if ($c->bundle() === 'call_to_action') {
          $about = $c;
          break 2;
        }
      }
    }
  }
  $say('3. About button: already in its own centred slice' . ($about ? " (paragraph {$about->id()})." : ' — but not found; skipped.'));
}
else {
  $outer = $section->getParentEntity();
  $say("3. About button {$about->id()}: $verb own centred slice after section {$section->id()}.");
  if (!$dry_run) {
    $slice = Paragraph::create([
      'type' => 'enclosure',
      'field_padding' => '1em',
      'field_style' => ['target_id' => STYLE_CENTRE],
      'field_content_component' => [$ref($about)],
    ]);
    $slice->setParentEntity($outer, 'field_content_component');
    $slice->save();
    $about->setParentEntity($slice, 'field_content_component');
    $about->save();

    $right = array_values(array_filter(
      $section->get('field_content_component_right')->getValue(),
      fn($item) => (int) $item['target_id'] !== (int) $about->id()
    ));
    $section->set('field_content_component_right', $right);
    $section->save();

    $items = [];
    foreach ($outer->get('field_content_component')->getValue() as $item) {
      $items[] = $item;
      if ((int) $item['target_id'] === (int) $section->id()) {
        $items[] = $ref($slice);
      }
    }
    $outer->set('field_content_component', $items);
    $outer->save();
  }
}

// ── 4. Explore colour on the About and See-all-articles buttons. ──
$buttons = array_filter([$about]);
foreach ($all as $p) {
  if ($p->bundle() === 'call_to_action' && !$p->get('field_link')->isEmpty()
    && $p->get('field_link')->uri === ARTICLES_LINK) {
    $buttons[] = $p;
  }
}
foreach ($buttons as $button) {
  $title = $button->get('field_link')->title;
  if ((int) $button->get('field_color_background')->target_id === $explore_tid) {
    $say("4. \"$title\" button {$button->id()}: already the Explore colour.");
    continue;
  }
  $say("4. \"$title\" button {$button->id()}: $verb Explore colour (term $explore_tid).");
  if (!$dry_run) {
    $button->set('field_color_background', ['target_id' => $explore_tid]);
    $button->save();
  }
}

$say($dry_run ? 'DRY RUN — no changes made.' : 'Done. Run drush cr next.');
