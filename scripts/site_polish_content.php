<?php

/**
 * @file
 * Drush script: content changes for Rob's site-polish items 3, 4 and 5.
 *
 *   3. Front page heading "What's on across the Greater Solent" gets a typed
 *      line break — "What's on across" / "the Greater Solent" — and the
 *      "Heading One Line Desktop" style. One line on desktop; on narrow
 *      screens it breaks there, so "Greater Solent" is never split. Each
 *      line still wraps within itself, so nothing is pushed off a very
 *      narrow screen. (The wording is Rob's to change; this only adds the
 *      break.)
 *   4. Front page links "View more" become "View more Events" and
 *      "View more Articles".
 *   5. Explore gets content: an events preview, the latest articles (the
 *      same construction as the front page) and the organisations signpost.
 *      Each shows only if there is something to show.
 *
 * Found structurally, not by id. Each step checks its own state, so the
 * script is safe to re-run.
 *
 * Usage:
 *   drush php:script scripts/site_polish_content.php
 *   drush php:script scripts/site_polish_content.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$verb = $dry_run ? 'WOULD CHANGE →' : 'CHANGED →';

const STYLE_ONE_LINE = 'heading_one_line_desktop';
const LINK_LABELS = [
  'internal:/explore/events' => 'View more Events',
  'internal:/explore/articles' => 'View more Articles',
];

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
$view_data = serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]);

$alias_manager = \Drupal::service('path_alias.manager');
$front = (string) \Drupal::config('system.site')->get('page.front');
$home = preg_match('#^/node/(\d+)$#', $alias_manager->getPathByAlias($front), $m) ? Node::load((int) $m[1]) : NULL;
if (!$home) {
  $say('ERROR: front page node not found.');
  return;
}

// ── 3. "Greater Solent" stays together. ──
foreach ($walk($home) as $p) {
  if ($p->bundle() !== 'heading' || !str_contains((string) $p->get('field_heading')->value, 'Greater Solent')) {
    continue;
  }
  $text = (string) $p->get('field_heading')->value;
  $styles = array_column($p->get('field_classy')->getValue(), 'target_id');
  $needs_break = !str_contains($text, "\n") && str_contains($text, ' the Greater Solent');
  $needs_style = !in_array(STYLE_ONE_LINE, $styles, TRUE);
  if (!$needs_break && !$needs_style) {
    $say("3. Heading {$p->id()}: already breaks before \"the Greater Solent\".");
    continue;
  }
  $new = $needs_break ? str_replace(' the Greater Solent', "\r\nthe Greater Solent", $text) : $text;
  $say("3. Heading {$p->id()}: $verb \"" . str_replace("\r\n", '" / "', $new) . '", one line on desktop.');
  if (!$dry_run) {
    $p->set('field_heading', $new);
    if ($needs_style) {
      $p->get('field_classy')->appendItem(['target_id' => STYLE_ONE_LINE]);
    }
    $p->save();
  }
}

// ── 4. Link labels. ──
foreach ($walk($home) as $p) {
  if ($p->bundle() !== 'link' || $p->get('field_link')->isEmpty()) {
    continue;
  }
  $uri = $p->get('field_link')->uri;
  $want = LINK_LABELS[$uri] ?? NULL;
  if (!$want) {
    continue;
  }
  if ($p->get('field_link')->title === $want) {
    $say("4. Link {$p->id()}: already \"$want\".");
    continue;
  }
  $say("4. Link {$p->id()}: $verb \"$want\".");
  if (!$dry_run) {
    $p->set('field_link', ['uri' => $uri, 'title' => $want, 'options' => []]);
    $p->save();
  }
}

// ── 5. Explore. ──
$explore = preg_match('#^/node/(\d+)$#', $alias_manager->getPathByAlias('/explore'), $m) ? Node::load((int) $m[1]) : NULL;
if (!$explore) {
  $say('5. /explore not found — skipped.');
}
else {
  $has_listing = FALSE;
  foreach ($walk($explore) as $p) {
    if ($p->bundle() === 'view_display') {
      $has_listing = TRUE;
    }
  }
  if ($has_listing) {
    $say('5. /explore: already has listings.');
  }
  else {
    $say("5. /explore: $verb new slice with an events preview, Latest articles and the organisations signpost.");
    if (!$dry_run) {
      // Match the padding of the enclosure already on the page, so the new
      // slice lines up with its text.
      $padding = '1em';
      foreach ($explore->get('field_content_component')->referencedEntities() as $top) {
        if ($top->bundle() === 'enclosure' && !$top->get('field_padding')->isEmpty()) {
          $padding = $top->get('field_padding')->value;
        }
      }
      $slice = Paragraph::create([
        'type' => 'enclosure',
        'field_padding' => $padding,
        'field_style' => ['target_id' => 'section_heading_row'],
      ]);
      $slice->setParentEntity($explore, 'field_content_component');
      $slice->save();

      $make = function (array $values) use ($slice): Paragraph {
        $p = Paragraph::create($values);
        $p->setParentEntity($slice, 'field_content_component');
        $p->save();
        return $p;
      };
      $children = [
        // Automatic preview: its own heading row and "View more Events".
        $make(['type' => 'view_display', 'field_view' => ['target_id' => 'events_listing', 'display_id' => 'view_display_primary_and_related', 'data' => $view_data]]),
        // Latest articles — built exactly as on the front page.
        $make(['type' => 'heading', 'field_heading' => 'Latest articles', 'field_heading_size' => 'h2', 'field_heading_align' => 'left',
          'field_classy' => [['target_id' => 'heading_gradient_section_sweep'], ['target_id' => 'heading_space_below']]]),
        $make(['type' => 'link', 'field_link' => ['uri' => 'internal:/explore/articles', 'title' => 'View more Articles', 'options' => []], 'field_classy' => [['target_id' => 'view_more_wide_screens']]]),
        $make(['type' => 'view_display', 'field_view' => ['target_id' => 'articles_listing', 'display_id' => 'view_display_front_page', 'data' => $view_data], 'field_classy' => [['target_id' => 'articles_compact_grid']]]),
        $make(['type' => 'link', 'field_link' => ['uri' => 'internal:/explore/articles', 'title' => 'View more Articles', 'options' => []], 'field_classy' => [['target_id' => 'view_more_narrow_screens']]]),
        // Automatic signpost.
        $make(['type' => 'view_display', 'field_view' => ['target_id' => 'organisations_listing', 'display_id' => 'view_display_primary_and_related', 'data' => $view_data]]),
      ];
      $slice->set('field_content_component', array_map($ref, $children));
      $slice->save();

      $items = $explore->get('field_content_component')->getValue();
      $items[] = $ref($slice);
      $explore->set('field_content_component', $items);
      $explore->setNewRevision(FALSE);
      $explore->save();
    }
  }
}

$say($dry_run ? 'DRY RUN — no changes made.' : 'Done. Run drush cr next.');
