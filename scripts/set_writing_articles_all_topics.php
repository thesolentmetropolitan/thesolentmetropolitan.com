<?php

/**
 * @file
 * Drush script: switch the Articles listing on /culture/writing to the
 * "Preview, all topics" listing mode.
 *
 * Writing is the one section page whose subject IS the act of writing, so
 * listing only the articles tagged "Writing" undersells it. The page should
 * show the newest articles from across the whole site and then hand over to
 * /explore/articles, which is the dedicated page for filtering and browsing
 * them all.
 *
 * This sets field_listing_mode = preview_all on the page's existing
 * articles_listing View Display paragraph. Everything else about the block
 * is unchanged — the preview chrome (heading, "View more Articles" link,
 * eight cards) is the same as the Events, Organisations and Links blocks
 * alongside it. The mode makes two differences:
 *   - the cards are scoped to all content topics, not to Writing
 *   - "View more Articles" points at /explore/articles rather than
 *     /culture/writing/articles
 *
 * Idempotent: re-running when the mode is already set changes nothing.
 *
 * Usage:
 *   drush php:script scripts/set_writing_articles_all_topics.php
 *   drush php:script scripts/set_writing_articles_all_topics.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PAGE_ALIAS = '/culture/writing';
const PAGE_NID_FALLBACK = 27;
const TARGET_VIEW = 'articles_listing';
const TARGET_MODE = 'preview_all';

// ── Resolve the page by alias, falling back to the known node ID. ────────
$path = \Drupal::service('path_alias.manager')->getPathByAlias(PAGE_ALIAS);
$nid = NULL;
if (preg_match('#^/node/(\d+)$#', $path, $m)) {
  $nid = (int) $m[1];
}
if (!$nid) {
  $nid = PAGE_NID_FALLBACK;
  $say(sprintf('No alias match for %s — falling back to node %d.', PAGE_ALIAS, $nid));
}

$node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
if (!$node) {
  $say(sprintf('ABORT: node %d not found.', $nid));
  return;
}
$say(sprintf('Page: node %d "%s" (%s)', $nid, $node->getTitle(), PAGE_ALIAS));

// ── Find the articles_listing View Display paragraph, at any depth. ──────
$found = [];
$collect = function ($entity) use (&$collect, &$found): void {
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if (!$p instanceof Paragraph) {
        continue;
      }
      if ($p->bundle() === 'view_display'
        && !$p->get('field_view')->isEmpty()
        && $p->get('field_view')->target_id === TARGET_VIEW) {
        $found[] = $p;
      }
      $collect($p);
    }
  }
};
$collect($node);

if (!$found) {
  $say('ABORT: no articles_listing View Display paragraph on this page.');
  $say('       Add one in the editor first, then re-run.');
  return;
}
if (count($found) > 1) {
  $say(sprintf('Note: %d articles listings found — updating all of them.', count($found)));
}

// ── Set the mode. ────────────────────────────────────────────────────────
$changed = 0;
foreach ($found as $paragraph) {
  if (!$paragraph->hasField('field_listing_mode')) {
    $say(sprintf('  paragraph %d: no field_listing_mode — import config first.', $paragraph->id()));
    continue;
  }

  $current = $paragraph->get('field_listing_mode')->value;
  $label = $current === NULL || $current === '' ? '(automatic)' : $current;

  if ($current === TARGET_MODE) {
    $say(sprintf('  paragraph %d: already %s — nothing to do.', $paragraph->id(), TARGET_MODE));
    continue;
  }

  $say(sprintf('  paragraph %d: %s -> %s', $paragraph->id(), $label, TARGET_MODE));
  if ($dry_run) {
    continue;
  }

  $paragraph->set('field_listing_mode', TARGET_MODE);
  $paragraph->setNewRevision(FALSE);
  $paragraph->save();
  $changed++;
}

if ($dry_run) {
  $say(PHP_EOL . 'Dry run — nothing saved.');
  return;
}

$say(PHP_EOL . sprintf('Done: %d paragraph(s) updated. Run `drush cr` next.', $changed));
