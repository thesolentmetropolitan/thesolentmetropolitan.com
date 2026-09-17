<?php

/**
 * @file
 * One-off migration: node `body` (text_with_summary) -> `field_body` (text_long).
 *
 * Context
 * -------
 * Article used the core `body` field, whose text_with_summary type carries a
 * `summary` column the site never displayed (display_summary was false). The
 * editorial model here is standfirst + body, so `body` is replaced by a plain
 * `field_body` (text_long) that can be reused on other bundles later.
 *
 * What it does
 * ------------
 *   1. Copies body_value / body_format into field_body_value / field_body_format
 *      for both the default table (node__body) and the revision table
 *      (node_revision__body), preserving full revision history.
 *   2. Where a node has a non-empty body_summary but an EMPTY field_standfirst,
 *      the summary is promoted to the standfirst (stripped of markup, trimmed
 *      to 255 chars). Summaries on nodes that already have a standfirst are
 *      reported and left alone — the standfirst wins.
 *
 * It deliberately works at SQL level rather than through entity saves so that
 * revision rows are copied verbatim, no new revisions are created, and
 * `changed` timestamps are untouched.
 *
 * Idempotent: rows already present in the field_body tables are skipped, so a
 * re-run after a partial failure is safe.
 *
 * Order of operations
 * -------------------
 * field_body must EXIST and `body` must still hold its data when this runs, so
 * on production the sequence is:
 *
 *   1. drush config:import      (adds field_body, removes body config)
 *   2. drush scr scripts/migrate_article_body_to_field_body.php
 *   3. drush cr
 *
 * Step 1 removing `body` is safe to do first: Drupal defers field data purging
 * to cron, so node__body still holds its rows at step 2. The script aborts with
 * a clear message if those tables have already gone.
 *
 * Usage:
 *   ddev drush scr scripts/migrate_article_body_to_field_body.php
 *   drush scr scripts/migrate_article_body_to_field_body.php     (production)
 */

use Drupal\Component\Utility\Unicode;

$db = \Drupal::database();
$schema = $db->schema();

/**
 * Copies one field table's rows across, skipping any that already exist.
 */
$copy_table = function (string $from, string $to) use ($db, $schema): array {
  if (!$schema->tableExists($from)) {
    return ['missing' => TRUE, 'copied' => 0, 'skipped' => 0];
  }
  $rows = $db->select($from, 't')->fields('t')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  $copied = 0;
  $skipped = 0;

  foreach ($rows as $row) {
    $exists = (bool) $db->select($to, 'x')
      ->condition('entity_id', $row['entity_id'])
      ->condition('revision_id', $row['revision_id'])
      ->condition('langcode', $row['langcode'])
      ->condition('delta', $row['delta'])
      ->condition('deleted', $row['deleted'])
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($exists) {
      $skipped++;
      continue;
    }

    $db->insert($to)
      ->fields([
        'bundle' => $row['bundle'],
        'deleted' => $row['deleted'],
        'entity_id' => $row['entity_id'],
        'revision_id' => $row['revision_id'],
        'langcode' => $row['langcode'],
        'delta' => $row['delta'],
        'field_body_value' => $row['body_value'],
        'field_body_format' => $row['body_format'],
      ])
      ->execute();
    $copied++;
  }

  return ['missing' => FALSE, 'copied' => $copied, 'skipped' => $skipped];
};

// ── Guard: the destination field must exist. ──────────────────────────────
if (!$schema->tableExists('node__field_body')) {
  print "ABORT: node__field_body does not exist. Import config first so the\n"
    . "       field_body field is created, then re-run this script.\n";
  return;
}

// ── Guard: the source must still hold data. ───────────────────────────────
if (!$schema->tableExists('node__body')) {
  $already = (int) $db->select('node__field_body', 'f')->countQuery()->execute()->fetchField();
  if ($already > 0) {
    print "Nothing to do: node__body is gone and field_body already holds $already row(s).\n"
      . "This migration has already run.\n";
  }
  else {
    print "ABORT: node__body does not exist and field_body is empty. The body data\n"
      . "       has been purged — restore from a database backup before retrying.\n";
  }
  return;
}

print "=== Migrating body -> field_body ===\n\n";

// ── 1. Copy field values (default + revisions). ───────────────────────────
$default = $copy_table('node__body', 'node__field_body');
print sprintf(
  "Default values:  %d copied, %d already present\n",
  $default['copied'],
  $default['skipped']
);

$revisions = $copy_table('node_revision__body', 'node_revision__field_body');
if ($revisions['missing']) {
  print "Revision values: node_revision__body not found — skipped\n";
}
else {
  print sprintf(
    "Revision values: %d copied, %d already present\n",
    $revisions['copied'],
    $revisions['skipped']
  );
}

// ── 2. Promote orphaned summaries to standfirst. ──────────────────────────
// Only where the node has no standfirst of its own; the summary column is
// about to disappear with the body field, so this is the last chance to keep
// that editorial text.
print "\n=== Summary -> standfirst ===\n\n";

$summaries = $db->select('node__body', 'b')
  ->fields('b', ['entity_id', 'body_summary'])
  ->condition('b.body_summary', '', '<>')
  ->isNotNull('b.body_summary')
  ->execute()
  ->fetchAllKeyed();

if (!$summaries) {
  print "No non-empty summaries found.\n";
}

$storage = \Drupal::entityTypeManager()->getStorage('node');

foreach ($summaries as $nid => $summary) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('field_standfirst')) {
    print "  nid $nid: no field_standfirst on this bundle — summary not carried over.\n";
    continue;
  }

  $existing = trim((string) $node->get('field_standfirst')->value);
  if ($existing !== '') {
    print "  nid $nid: has a standfirst already — summary NOT used.\n";
    print "           discarded summary: \"" . trim(strip_tags($summary)) . "\"\n";
    continue;
  }

  $text = trim(preg_replace('/\s+/', ' ', strip_tags($summary)));
  if ($text === '') {
    print "  nid $nid: summary was markup only — nothing to carry over.\n";
    continue;
  }
  $text = Unicode::truncate($text, 255, TRUE, TRUE);

  $node->set('field_standfirst', $text);
  $node->setNewRevision(FALSE);
  $node->save();
  print "  nid $nid: standfirst set from summary — \"$text\"\n";
}

print "\n=== Done. Run `drush cr` next. ===\n";
