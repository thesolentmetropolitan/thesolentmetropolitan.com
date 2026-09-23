<?php

/**
 * @file
 * Drush script: delete orphaned paragraphs under one node.
 *
 * A paragraph is orphaned when it still records a parent (parent_type,
 * parent_id, parent_field_name) but that parent's CURRENT revision no
 * longer lists it in the field. Editors leave these behind whenever a
 * paragraph is removed from a page and the page is saved as a new
 * revision: the old revision keeps its reference, the entity stays,
 * and it never renders again. Node 92 (Articles) had one such View
 * Display paragraph, which is what prompted this script.
 *
 * Scope is one node's whole paragraph tree, walked through nested
 * paragraphs (enclosures, columns) as far as it goes. Nothing outside
 * that tree is touched. Deleting an orphan also drops its revisions;
 * the parent's old revisions keep a dangling reference, which
 * entity_reference_revisions tolerates (the missing item just renders
 * as nothing).
 *
 * Usage:
 *   drush php:script scripts/delete_orphan_paragraphs.php -- --nid=92 --dry-run
 *   drush php:script scripts/delete_orphan_paragraphs.php -- --nid=92
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\ParagraphInterface;

$dry_run = FALSE;
$nid = NULL;
if (isset($extra) && is_array($extra)) {
  foreach ($extra as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
      $dry_run = TRUE;
    }
    if (str_starts_with($arg, '--nid=')) {
      $nid = (int) substr($arg, 6);
    }
  }
}
$say = static function (string $m): void { echo $m . PHP_EOL; };

if (!$nid) {
  $say('Usage: drush php:script scripts/delete_orphan_paragraphs.php -- --nid=<nid> [--dry-run]');
  return;
}
$node = Node::load($nid);
if (!$node) {
  $say("ERROR: node $nid does not exist.");
  return;
}
$say(($dry_run ? 'DRY RUN. ' : '') . "Node $nid '" . $node->getTitle() . "'");

$storage = \Drupal::entityTypeManager()->getStorage('paragraph');
$db = \Drupal::database();

// Every paragraph that records a parent inside this node's tree,
// orphaned or not: start from the node, then follow paragraph parents
// until no new ids turn up.
$tree = [];
$frontier = [['node', $nid]];
while ($frontier) {
  [$type, $id] = array_shift($frontier);
  $ids = $db->select('paragraphs_item_field_data', 'p')
    ->fields('p', ['id'])
    ->condition('parent_type', $type)
    ->condition('parent_id', (string) $id)
    ->execute()->fetchCol();
  foreach ($ids as $pid) {
    if (!isset($tree[$pid])) {
      $tree[$pid] = TRUE;
      $frontier[] = ['paragraph', $pid];
    }
  }
}
$say('Paragraphs recording a parent in this tree: ' . count($tree));

$orphans = [];
foreach ($storage->loadMultiple(array_keys($tree)) as $paragraph) {
  /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
  $parent = $paragraph->getParentEntity();
  $field = $paragraph->get('parent_field_name')->value;
  $referenced = FALSE;
  if ($parent && $parent->hasField($field)) {
    foreach ($parent->get($field) as $item) {
      if ((int) $item->target_id === (int) $paragraph->id()) {
        $referenced = TRUE;
        break;
      }
    }
  }
  // A paragraph whose parent is itself an orphan is only reachable through
  // that orphan; it goes too, so the check runs after the whole pass.
  if (!$referenced) {
    $orphans[$paragraph->id()] = $paragraph;
  }
}
// Second pass: children of orphans are orphans, however many levels down.
do {
  $added = 0;
  foreach ($storage->loadMultiple(array_keys($tree)) as $paragraph) {
    if (isset($orphans[$paragraph->id()])) {
      continue;
    }
    if ($paragraph->get('parent_type')->value === 'paragraph'
      && isset($orphans[$paragraph->get('parent_id')->value])) {
      $orphans[$paragraph->id()] = $paragraph;
      $added++;
    }
  }
} while ($added);

if (!$orphans) {
  $say('No orphaned paragraphs. Nothing to do.');
  return;
}

foreach ($orphans as $paragraph) {
  $summary = _orphan_summary($paragraph);
  $say(sprintf('  %s paragraph %d (%s) under %s:%s — %s',
    $dry_run ? 'WOULD DELETE' : 'DELETING',
    $paragraph->id(),
    $paragraph->bundle(),
    $paragraph->get('parent_type')->value,
    $paragraph->get('parent_id')->value,
    $summary
  ));
  if (!$dry_run) {
    $paragraph->delete();
  }
}
$say(($dry_run ? 'Would delete ' : 'Deleted ') . count($orphans) . ' orphaned paragraph(s).');
if (!$dry_run) {
  $say("Run 'drush cr' if the node's page looks stale.");
}

/**
 * One line saying what a paragraph holds, for the log.
 */
function _orphan_summary(ParagraphInterface $p): string {
  $bits = [];
  if ($p->hasField('field_view') && !$p->get('field_view')->isEmpty()) {
    $bits[] = 'view ' . $p->get('field_view')->target_id . '/' . $p->get('field_view')->display_id;
  }
  if ($p->hasField('field_heading') && !$p->get('field_heading')->isEmpty()) {
    $bits[] = 'heading "' . trim($p->get('field_heading')->value) . '"';
  }
  if ($p->hasField('field_title') && !$p->get('field_title')->isEmpty()) {
    $bits[] = 'title "' . trim($p->get('field_title')->value) . '"';
  }
  if ($p->hasField('field_text') && !$p->get('field_text')->isEmpty()) {
    $bits[] = 'text "' . mb_substr(trim(strip_tags($p->get('field_text')->value)), 0, 40) . '"';
  }
  return $bits ? implode(', ', $bits) : 'no notable fields';
}
