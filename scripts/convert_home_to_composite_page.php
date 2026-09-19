<?php

/**
 * @file
 * Drush script: convert the front page node from landing_page to
 * composite_page, IN PLACE.
 *
 * Why in place rather than "create a new node and copy": the node id
 * stays the same, so system.site page.front, revisions, menu links and
 * every paragraph's parent reference are untouched, and local and
 * production cannot drift apart on node ids. The two bundles are
 * near-twins — identical node templates, and both use the same
 * field_content_component field storage — so the only thing that
 * differs in the database is the bundle name recorded against the node.
 *
 * Drupal has no API for changing an entity's bundle, so this updates
 * the bundle columns directly, inside a transaction:
 *   node.type, node_field_data.type, and the `bundle` column of every
 *   dedicated field table (current + revision) that holds rows for
 *   this node.
 * It then reloads the node through the entity API, checks it, and
 * re-saves it (no new revision) so caches and search tracking update.
 *
 * composite_page adds field_primary_topic and field_related_topics.
 * They are left EMPTY on purpose — the home page is not categorised,
 * and all topic-driven behaviour is keyed on those fields being set.
 *
 * Idempotent: does nothing if the node is already a composite_page.
 *
 * Usage:
 *   drush php:script scripts/convert_home_to_composite_page.php
 *   drush php:script scripts/convert_home_to_composite_page.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const FROM_BUNDLE = 'landing_page';
const TO_BUNDLE = 'composite_page';

// ── Resolve the front page node. ──
$front = (string) \Drupal::config('system.site')->get('page.front');
$internal = \Drupal::service('path_alias.manager')->getPathByAlias($front);
if (!preg_match('#^/node/(\d+)$#', $internal, $m)) {
  $say("ERROR: front page '$front' does not resolve to a node (got '$internal').");
  return;
}
$nid = (int) $m[1];
$node = Node::load($nid);
if (!$node) {
  $say("ERROR: node $nid not found.");
  return;
}
$say("Front page: '{$node->getTitle()}' (nid $nid), bundle: {$node->bundle()}");

if ($node->bundle() === TO_BUNDLE) {
  $say('Already a ' . TO_BUNDLE . '. Nothing to do.');
  return;
}
if ($node->bundle() !== FROM_BUNDLE) {
  $say('ERROR: expected bundle ' . FROM_BUNDLE . ", found {$node->bundle()}. Refusing to convert.");
  return;
}
if (!NodeType::load(TO_BUNDLE)) {
  $say('ERROR: content type ' . TO_BUNDLE . ' does not exist.');
  return;
}

// ── Every configurable field holding data on this node must also exist
//    on the target bundle, or that data would be orphaned. ──
$field_manager = \Drupal::service('entity_field.manager');
$target_fields = $field_manager->getFieldDefinitions('node', TO_BUNDLE);
foreach ($node->getFieldDefinitions() as $name => $definition) {
  if ($definition->getFieldStorageDefinition()->isBaseField()) {
    continue;
  }
  if (!$node->get($name)->isEmpty() && !isset($target_fields[$name])) {
    $say("ERROR: field $name holds data but does not exist on " . TO_BUNDLE . '. Refusing to convert.');
    return;
  }
}

// ── Work out which tables carry the bundle name for this node. ──
$db = \Drupal::database();
$schema = $db->schema();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$table_mapping = $storage->getTableMapping();

$updates = [
  ['table' => 'node', 'column' => 'type', 'key' => 'nid'],
  ['table' => 'node_field_data', 'column' => 'type', 'key' => 'nid'],
];
foreach ($table_mapping->getDedicatedTableNames() as $table) {
  if ($schema->tableExists($table) && $schema->fieldExists($table, 'bundle') && $schema->fieldExists($table, 'entity_id')) {
    $updates[] = ['table' => $table, 'column' => 'bundle', 'key' => 'entity_id'];
  }
}

$plan = [];
foreach ($updates as $u) {
  $count = (int) $db->select($u['table'], 't')
    ->condition($u['key'], $nid)
    ->condition($u['column'], FROM_BUNDLE)
    ->countQuery()->execute()->fetchField();
  if ($count > 0) {
    $plan[] = $u + ['rows' => $count];
    $say(sprintf('  %-52s %s: %d row(s)', $u['table'], $u['column'], $count));
  }
}

if ($dry_run) {
  $say('DRY RUN — no changes made.');
  return;
}

$transaction = $db->startTransaction();
try {
  foreach ($plan as $u) {
    $db->update($u['table'])
      ->fields([$u['column'] => TO_BUNDLE])
      ->condition($u['key'], $nid)
      ->condition($u['column'], FROM_BUNDLE)
      ->execute();
  }
}
catch (\Throwable $e) {
  $transaction->rollBack();
  $say('ERROR: ' . $e->getMessage() . ' — rolled back, nothing changed.');
  return;
}
unset($transaction);

// ── Reload through the entity API and verify. ──
$storage->resetCache([$nid]);
\Drupal::service('entity.memory_cache')->deleteAll();
$node = Node::load($nid);
if (!$node || $node->bundle() !== TO_BUNDLE) {
  $say('ERROR: node did not reload as ' . TO_BUNDLE . '. Restore from backup.');
  return;
}
foreach (['field_primary_topic', 'field_related_topics'] as $name) {
  if ($node->hasField($name) && !$node->get($name)->isEmpty()) {
    $node->set($name, []);
  }
}
$components = count($node->get('field_content_component'));
$node->setNewRevision(FALSE);
$node->save();

$say("Converted nid $nid to " . TO_BUNDLE . ". Top-level content components intact: $components. Topic fields empty.");
$say('Run drush cr next.');
