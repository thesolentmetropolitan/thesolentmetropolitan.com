<?php

/**
 * @file
 * Drush script: add the front-page "Latest articles" grid.
 *
 * Appends an enclosure paragraph to the front page's welcome enclosure
 * (the one that already holds the two-column welcome / "What's on"
 * section) containing:
 *
 *   heading       "Latest articles"  (h2, section-sweep gradient,
 *                                     space below — same as the
 *                                     "What's on" heading)
 *   view_display  articles_listing : view_display_front_page
 *                 with classy style articles_compact_grid
 *   call_to_action "See all articles" → /explore/articles
 *                 (Solent Blue on white — same as "See all events")
 *
 * The new enclosure uses field_padding = 1em so its content aligns
 * with the two-column section above. Everything is content, not
 * config, so this script is the deployable artefact: run it once on
 * each environment after the config that adds the view display and
 * classy style has been imported.
 *
 * Idempotent: if any view_display paragraph on the front page already
 * points at articles_listing:view_display_front_page, nothing is done.
 *
 * Usage:
 *   drush php:script scripts/add_front_page_articles_grid.php
 *   drush php:script scripts/add_front_page_articles_grid.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

function output(string $message): void {
  echo $message . PHP_EOL;
}

$dry_run = FALSE;
if (isset($extra) && is_array($extra)) {
  foreach ($extra as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
      $dry_run = TRUE;
    }
  }
}

const VIEW_ID = 'articles_listing';
const DISPLAY_ID = 'view_display_front_page';
const EVENTS_VIEW_ID = 'events_listing';
const EVENTS_DISPLAY_ID = 'view_display_front_page';
const CLASSY_GRID = 'articles_compact_grid';
const HEADING_TEXT = 'Latest articles';
const HEADING_CLASSY = ['heading_gradient_section_sweep', 'heading_space_below'];
const CTA_TEXT = 'See all articles';
const CTA_URI = 'internal:/explore/articles';
const CTA_BG_COLOR_NAME = 'Solent Blue';
const CTA_TEXT_COLOR_NAME = 'white';

// ── Sanity: config this depends on must already be imported. ──
$view = \Drupal::entityTypeManager()->getStorage('view')->load(VIEW_ID);
if (!$view || !$view->getDisplay(DISPLAY_ID)) {
  output('ERROR: View display ' . VIEW_ID . ':' . DISPLAY_ID . ' does not exist. Run config import first.');
  return;
}
if (!\Drupal::entityTypeManager()->getStorage('classy_paragraphs_style')->load(CLASSY_GRID)) {
  output('ERROR: classy_paragraphs style ' . CLASSY_GRID . ' does not exist. Run config import first.');
  return;
}

// ── Resolve the front page node. ──
$front = \Drupal::config('system.site')->get('page.front');
$path = \Drupal::service('path_alias.manager')->getPathByAlias($front);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  output("ERROR: front page '$front' does not resolve to a node (got '$path').");
  return;
}
$node = Node::load((int) $m[1]);
if (!$node || !$node->hasField('field_content_component')) {
  output("ERROR: front page node {$m[1]} not found or has no field_content_component.");
  return;
}
output("Front page: '{$node->getTitle()}' (nid {$node->id()})");

// ── Walk the paragraph tree. ──
/**
 * Depth-first walk yielding [paragraph, parent_paragraph|null].
 */
function walk_paragraphs($entity, ?Paragraph $parent = NULL): \Generator {
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $child) {
      if ($child instanceof Paragraph) {
        yield [$child, $parent];
        yield from walk_paragraphs($child, $child);
      }
    }
  }
}

$events_grid = NULL;
foreach (walk_paragraphs($node) as [$p, $parent]) {
  if ($p->bundle() !== 'view_display' || !$p->hasField('field_view') || $p->get('field_view')->isEmpty()) {
    continue;
  }
  $ref = $p->get('field_view')->first();
  if ($ref->target_id === VIEW_ID && $ref->display_id === DISPLAY_ID) {
    output("Already present: view_display paragraph {$p->id()} points at " . VIEW_ID . ':' . DISPLAY_ID . '. Nothing to do.');
    return;
  }
  if ($ref->target_id === EVENTS_VIEW_ID && $ref->display_id === EVENTS_DISPLAY_ID) {
    $events_grid = $p;
  }
}

if (!$events_grid) {
  output('ERROR: could not find the events grid (' . EVENTS_VIEW_ID . ':' . EVENTS_DISPLAY_ID . ') on the front page to anchor against.');
  return;
}

// The events grid lives in a section_2_column; that section's parent is
// the enclosure we append to.
$section = $events_grid->getParentEntity();
if (!$section instanceof Paragraph || $section->bundle() !== 'section_2_column') {
  output('ERROR: events grid parent is not a section_2_column paragraph (found ' . ($section ? $section->bundle() : 'nothing') . ').');
  return;
}
$target = $section->getParentEntity();
if (!$target instanceof Paragraph || $target->bundle() !== 'enclosure' || !$target->hasField('field_content_component')) {
  output('ERROR: section_2_column parent is not an enclosure paragraph (found ' . ($target ? $target->bundle() : 'nothing') . ').');
  return;
}
output("Events grid: paragraph {$events_grid->id()} → section_2_column {$section->id()} → enclosure {$target->id()}");

// ── Colour terms for the CTA. ──
$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$color_id = function (string $name) use ($term_storage): ?int {
  $terms = $term_storage->loadByProperties(['vid' => 'color', 'name' => $name]);
  return $terms ? (int) reset($terms)->id() : NULL;
};
$cta_bg = $color_id(CTA_BG_COLOR_NAME);
$cta_text = $color_id(CTA_TEXT_COLOR_NAME);
if (!$cta_bg || !$cta_text) {
  output('ERROR: colour terms "' . CTA_BG_COLOR_NAME . '" / "' . CTA_TEXT_COLOR_NAME . '" not found in the color vocabulary.');
  return;
}

output('');
output("Will append to enclosure {$target->id()} a new enclosure (field_padding 1em) containing:");
output('  heading        "' . HEADING_TEXT . '" (h2, left, classy: ' . implode(', ', HEADING_CLASSY) . ')');
output('  view_display   ' . VIEW_ID . ':' . DISPLAY_ID . ' (classy: ' . CLASSY_GRID . ')');
output('  call_to_action "' . CTA_TEXT . '" → ' . CTA_URI . " (bg term $cta_bg, text term $cta_text)");

if ($dry_run) {
  output('');
  output('DRY RUN — no changes made.');
  return;
}

$heading = Paragraph::create([
  'type' => 'heading',
  'field_heading' => HEADING_TEXT,
  'field_heading_size' => 'h2',
  'field_heading_align' => 'left',
  'field_classy' => array_map(fn($id) => ['target_id' => $id], HEADING_CLASSY),
]);
$heading->save();

$grid = Paragraph::create([
  'type' => 'view_display',
  'field_view' => [
    'target_id' => VIEW_ID,
    'display_id' => DISPLAY_ID,
    'data' => serialize([
      'offset' => NULL, 'pager' => NULL, 'limit' => NULL,
      'header' => NULL, 'title' => NULL, 'argument' => NULL,
    ]),
  ],
  'field_classy' => [['target_id' => CLASSY_GRID]],
]);
$grid->save();

$cta = Paragraph::create([
  'type' => 'call_to_action',
  'field_link' => ['uri' => CTA_URI, 'title' => CTA_TEXT, 'options' => []],
  'field_color_background' => ['target_id' => $cta_bg],
  'field_color_text' => ['target_id' => $cta_text],
]);
$cta->save();

$ref = fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];

$band = Paragraph::create([
  'type' => 'enclosure',
  'field_padding' => '1em',
  'field_content_component' => [$ref($heading), $ref($grid), $ref($cta)],
]);
$band->save();

$items = $target->get('field_content_component')->getValue();
$items[] = $ref($band);
$target->set('field_content_component', $items);
$target->save();

output('');
output("Created enclosure {$band->id()} (heading {$heading->id()}, view_display {$grid->id()}, call_to_action {$cta->id()}) and appended it to enclosure {$target->id()}.");
output('Clear caches (drush cr) if the front page does not update.');
