<?php

/**
 * @file
 * Drush script: front page "View more" links, intro link, off-white band.
 *
 *   1. The top band (the full-width enclosure holding events, intro and
 *      articles) gets the warm-grey background, so the white event cards
 *      and the gaps between them read as structure.
 *   2. Events and Latest articles: the See-all BUTTON is replaced by two
 *      "View more" text links (Link paragraphs) — one right after the
 *      section heading (style View More Wide Screens; the enclosure's
 *      Section Heading Row style puts it on the heading's line, right
 *      aligned) and one after the grid (View More Narrow Screens). A
 *      media query shows one or the other.
 *   3. Intro: the About button slice is removed and "Read more about" is
 *      appended as a link at the end of the intro paragraph.
 *
 * Everything is found structurally from the front page node. Each step
 * checks its own state, so the script is safe to re-run. Needs the Link
 * paragraph's classy field and three classy styles — import config first.
 *
 * Usage:
 *   drush php:script scripts/front_page_view_more_and_framing.php
 *   drush php:script scripts/front_page_view_more_and_framing.php -- --dry-run
 */

use Drupal\Component\Utility\Html;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$verb = $dry_run ? 'WOULD CHANGE →' : 'CHANGED →';

const STYLE_WIDE = 'view_more_wide_screens';
const STYLE_NARROW = 'view_more_narrow_screens';
const STYLE_HEAD_ROW = 'section_heading_row';
const STYLE_INTRO = 'front_intro_two_column';
const STYLE_CENTRE = 'centre_call_to_action';
const BAND_COLOUR_TERM = 'warm-grey';
const VIEW_MORE_TEXT = 'View more';
const READ_MORE_TEXT = 'Read more about';
const SECTIONS = [
  'events' => ['view' => 'events_listing', 'display' => 'view_display_front_page'],
  'articles' => ['view' => 'articles_listing', 'display' => 'view_display_front_page'],
];

$styles = \Drupal::entityTypeManager()->getStorage('classy_paragraphs_style');
foreach ([STYLE_WIDE, STYLE_NARROW, STYLE_HEAD_ROW] as $id) {
  if (!$styles->load($id)) {
    $say("ERROR: classy style $id does not exist. Run config import first.");
    return;
  }
}
$link_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'link');
if (!isset($link_fields['field_classy'])) {
  $say('ERROR: the Link paragraph type has no field_classy. Run config import first.');
  return;
}
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'color', 'name' => BAND_COLOUR_TERM]);
if (!$terms) {
  $say('ERROR: colour term "' . BAND_COLOUR_TERM . '" not found.');
  return;
}
$band_tid = (int) reset($terms)->id();

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
$ref = fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
$find_view = function (string $view, string $display) use ($walk, $node): ?Paragraph {
  foreach ($walk($node) as $p) {
    if ($p->bundle() === 'view_display' && !$p->get('field_view')->isEmpty()
      && $p->get('field_view')->target_id === $view && $p->get('field_view')->display_id === $display) {
      return $p;
    }
  }
  return NULL;
};

// ── 1. Off-white band. ──
$events_view = $find_view(SECTIONS['events']['view'], SECTIONS['events']['display']);
if (!$events_view) {
  $say('ERROR: events grid not found on the front page.');
  return;
}
$band = $events_view;
while (($up = $band->getParentEntity()) instanceof Paragraph) {
  $band = $up;
}
if ($band->bundle() !== 'enclosure') {
  $say("1. Band: top-level paragraph is a {$band->bundle()}, not an enclosure — skipped.");
}
elseif ((int) $band->get('field_color_background')->target_id === $band_tid) {
  $say("1. Band enclosure {$band->id()}: already " . BAND_COLOUR_TERM . '.');
}
else {
  $say("1. Band enclosure {$band->id()}: $verb background " . BAND_COLOUR_TERM . " (term $band_tid).");
  if (!$dry_run) {
    $band->set('field_color_background', ['target_id' => $band_tid]);
    $band->save();
  }
}

// ── 2. View more links for each section. ──
foreach (SECTIONS as $name => $spec) {
  $view = $find_view($spec['view'], $spec['display']);
  if (!$view) {
    $say("2. $name: grid not found — skipped.");
    continue;
  }
  $enclosure = $view->getParentEntity();
  if (!$enclosure instanceof Paragraph || $enclosure->bundle() !== 'enclosure') {
    $say("2. $name: grid is not directly inside an enclosure — skipped.");
    continue;
  }
  $children = $enclosure->get('field_content_component')->referencedEntities();

  $has_wide = FALSE;
  foreach ($children as $c) {
    if ($c->bundle() === 'link' && in_array(STYLE_WIDE, array_column($c->get('field_classy')->getValue(), 'target_id'), TRUE)) {
      $has_wide = TRUE;
    }
  }
  if ($has_wide) {
    $say("2. $name (enclosure {$enclosure->id()}): already has View more links.");
    continue;
  }

  // Heading = nearest heading before the grid; button = first CTA after it.
  $heading = $button = NULL;
  $seen_view = FALSE;
  foreach ($children as $c) {
    if ((int) $c->id() === (int) $view->id()) {
      $seen_view = TRUE;
    }
    elseif (!$seen_view && $c->bundle() === 'heading') {
      $heading = $c;
    }
    elseif ($seen_view && $c->bundle() === 'call_to_action' && !$button) {
      $button = $c;
    }
  }
  if (!$heading || !$button) {
    $say("2. $name: expected a heading before the grid and a button after it — skipped.");
    continue;
  }
  $uri = $button->get('field_link')->uri;
  $say("2. $name (enclosure {$enclosure->id()}): $verb button \"{$button->get('field_link')->title}\" replaced by two \"" . VIEW_MORE_TEXT . "\" links → $uri; enclosure style " . STYLE_HEAD_ROW . '.');
  if ($dry_run) {
    continue;
  }

  $make_link = function (string $style) use ($uri, $enclosure): Paragraph {
    $p = Paragraph::create([
      'type' => 'link',
      'field_link' => ['uri' => $uri, 'title' => VIEW_MORE_TEXT, 'options' => []],
      'field_classy' => [['target_id' => $style]],
    ]);
    $p->setParentEntity($enclosure, 'field_content_component');
    $p->save();
    return $p;
  };
  $wide = $make_link(STYLE_WIDE);
  $narrow = $make_link(STYLE_NARROW);

  $items = [];
  foreach ($children as $c) {
    if ((int) $c->id() === (int) $button->id()) {
      $items[] = $ref($narrow);
      continue;
    }
    $items[] = $ref($c);
    if ((int) $c->id() === (int) $heading->id()) {
      $items[] = $ref($wide);
    }
  }
  $enclosure->set('field_content_component', $items);
  $enclosure->set('field_style', ['target_id' => STYLE_HEAD_ROW]);
  $enclosure->save();
  $button->delete();
}

// ── 3. Intro: button slice out, link in. ──
$section = NULL;
$about_slice = NULL;
foreach ($walk($node) as $p) {
  if ($p->bundle() === 'section_2_column' && $p->get('field_style')->target_id === STYLE_INTRO) {
    $section = $p;
  }
  if ($p->bundle() === 'enclosure' && $p->get('field_style')->target_id === STYLE_CENTRE) {
    $about_slice = $p;
  }
}
$intro = NULL;
if ($section) {
  foreach ($section->get('field_content_component_right')->referencedEntities() as $p) {
    if ($p->bundle() === 'intro') {
      $intro = $p;
      break;
    }
  }
}
if (!$intro) {
  $say('3. Intro paragraph not found — skipped.');
}
else {
  $href = '/about/overview';
  $about_button = NULL;
  if ($about_slice) {
    foreach ($about_slice->get('field_content_component')->referencedEntities() as $c) {
      if ($c->bundle() === 'call_to_action') {
        $about_button = $c;
        $href = $c->get('field_link')->first()->getUrl()->toString();
      }
    }
  }

  $html = (string) $intro->get('field_text')->value;
  if (str_contains($html, READ_MORE_TEXT)) {
    $say("3. Intro {$intro->id()}: already ends with \"" . READ_MORE_TEXT . '".');
  }
  else {
    $dom = Html::load($html);
    $body = $dom->getElementsByTagName('body')->item(0);
    $last = NULL;
    foreach ($body->childNodes as $child) {
      if ($child->nodeType === XML_ELEMENT_NODE && trim($child->textContent) !== '') {
        $last = $child;
      }
    }
    if (!$last) {
      $say("3. Intro {$intro->id()}: no text block to append to — skipped.");
    }
    else {
      // Trim trailing whitespace / nbsp, then add " <a>Read more about</a>".
      while ($last->lastChild && $last->lastChild->nodeType === XML_TEXT_NODE
        && trim(str_replace("\xC2\xA0", ' ', $last->lastChild->nodeValue)) === '') {
        $last->removeChild($last->lastChild);
      }
      if ($last->lastChild && $last->lastChild->nodeType === XML_TEXT_NODE) {
        $last->lastChild->nodeValue = rtrim(str_replace("\xC2\xA0", ' ', $last->lastChild->nodeValue));
      }
      $last->appendChild($dom->createTextNode(' '));
      $a = $dom->createElement('a', READ_MORE_TEXT);
      $a->setAttribute('href', $href);
      $last->appendChild($a);
      $say("3. Intro {$intro->id()}: $verb ends with link \"" . READ_MORE_TEXT . "\" → $href.");
      if (!$dry_run) {
        $intro->set('field_text', ['value' => Html::serialize($dom), 'format' => $intro->get('field_text')->format]);
        $intro->save();
      }
    }
  }

  if (!$about_slice) {
    $say('3. About button slice: already removed.');
  }
  else {
    $say("3. About button slice {$about_slice->id()}: $verb removed" . ($about_button ? " (with button {$about_button->id()})" : '') . '.');
    if (!$dry_run) {
      $outer = $about_slice->getParentEntity();
      $field = $about_slice->get('parent_field_name')->value;
      $items = array_values(array_filter(
        $outer->get($field)->getValue(),
        fn($item) => (int) $item['target_id'] !== (int) $about_slice->id()
      ));
      $outer->set($field, $items);
      $outer->save();
      if ($about_button) {
        $about_button->delete();
      }
      $about_slice->delete();
    }
  }
}

$say($dry_run ? 'DRY RUN — no changes made.' : 'Done. Run drush cr next.');
