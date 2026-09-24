<?php

/**
 * @file
 * Drush script: show /explore/articles as three columns of cards.
 *
 * The site-wide Articles page has the topic filter above its listing, and
 * with it four cards per row read too narrow. This applies the classy
 * style "Articles Grid, 3 columns" (class slnt-articles-compact-grid--3-col,
 * config classy_paragraphs.classy_paragraphs_style.articles_grid_three_columns)
 * to the page's articles_listing View Display paragraph. The style is a
 * general editor tool: any articles grid can use it from the paragraph's
 * classy field.
 *
 * The page is found by its path alias with the node ID as fallback, the
 * paragraph by walking the page. Idempotent: a paragraph that already has
 * the style is left alone.
 *
 * Usage:
 *   drush php:script scripts/set_explore_articles_three_columns.php
 *   drush php:script scripts/set_explore_articles_three_columns.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PAGE_ALIAS = '/explore/articles';
const PAGE_NID_FALLBACK = 92;
const TARGET_VIEW = 'articles_listing';
const STYLE_ID = 'articles_grid_three_columns';

if (!\Drupal::entityTypeManager()->getStorage('classy_paragraphs_style')->load(STYLE_ID)) {
  $say('ABORT classy style ' . STYLE_ID . ' does not exist — run config import first.');
  return;
}

$path = \Drupal::service('path_alias.manager')->getPathByAlias(PAGE_ALIAS);
$nid = preg_match('#^/node/(\d+)$#', $path, $m) ? (int) $m[1] : PAGE_NID_FALLBACK;
$node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
if (!$node || $node->bundle() !== 'composite_page') {
  $say("ABORT no composite page at " . PAGE_ALIAS . " (node $nid).");
  return;
}

$find = function ($entity, int $depth) use (&$find): ?Paragraph {
  if ($depth <= 0) {
    return NULL;
  }
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if (!$p instanceof Paragraph) {
        continue;
      }
      if ($p->bundle() === 'view_display' && $p->get('field_view')->target_id === TARGET_VIEW) {
        return $p;
      }
      if ($nested = $find($p, $depth - 1)) {
        return $nested;
      }
    }
  }
  return NULL;
};

$paragraph = $find($node, 6);
if (!$paragraph) {
  $say("ABORT " . PAGE_ALIAS . " has no " . TARGET_VIEW . " listing paragraph.");
  return;
}
$styles = array_column($paragraph->get('field_classy')->getValue(), 'target_id');
if (in_array(STYLE_ID, $styles, TRUE)) {
  $say("OK    " . PAGE_ALIAS . ": listing paragraph {$paragraph->id()} already has the 3-column style.");
  return;
}
$say(($dry_run ? 'WOULD ' : 'SET   ') . PAGE_ALIAS . ": add style " . STYLE_ID . " to listing paragraph {$paragraph->id()}"
  . ($styles ? ' (alongside ' . implode(', ', $styles) . ')' : '') . '.');
if ($dry_run) {
  $say('DRY RUN — no changes made.');
  return;
}
$paragraph->get('field_classy')->appendItem(['target_id' => STYLE_ID]);
$paragraph->save();
// Re-save the host so its render cache tag is invalidated with it.
$node->save();
$say('Done. Run drush cr next.');
