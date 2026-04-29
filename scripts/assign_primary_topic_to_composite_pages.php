<?php

/**
 * @file
 * Drush script to set field_primary_topic on each composite_page node that
 * a Main navigation submenu item links to. The chosen term is the Topic
 * taxonomy term whose name matches "<ParentMenu> / <SubmenuTitle>".
 *
 * Reads the menu structure from config/sync/structure_sync.data.yml — the
 * same source create_menu_content.php used to create the nodes.
 *
 * Submenu items whose title begins with "View All" are skipped (they point
 * back at section landing pages and aren't topic-specific).
 *
 * Idempotent: by default, a node that already has field_primary_topic set
 * to the correct term is left alone. Use --force to overwrite an existing
 * value. Use --dry-run to preview without saving.
 *
 * Usage:
 *   drush php:script scripts/assign_primary_topic_to_composite_pages.php
 *   drush php:script scripts/assign_primary_topic_to_composite_pages.php -- --dry-run
 *   drush php:script scripts/assign_primary_topic_to_composite_pages.php -- --limit=5
 *   drush php:script scripts/assign_primary_topic_to_composite_pages.php -- --force
 */

use Drupal\node\Entity\Node;
use Symfony\Component\Yaml\Yaml;

const VOCABULARY = 'topic';
const NODE_BUNDLE = 'composite_page';
const TOPIC_FIELD = 'field_primary_topic';

/**
 * Write a message to stdout.
 */
function apt_output($message) {
  echo $message . PHP_EOL;
}

/**
 * Strip the 'internal:' scheme from a menu link URI.
 */
function apt_path_from_uri($uri) {
  return str_replace('internal:', '', (string) $uri);
}

/**
 * Find a composite_page node id by its path alias.
 *
 * Resolves the alias to an internal path (e.g. /node/123) and pulls the nid
 * from there. Returns null if no node matches.
 */
function apt_find_node_by_alias($alias) {
  $internal = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  if ($internal === $alias) {
    // No alias resolved — nothing matches.
    return NULL;
  }
  if (!preg_match('#^/node/(\d+)$#', $internal, $m)) {
    return NULL;
  }
  $nid = (int) $m[1];
  $node = Node::load($nid);
  if (!$node || $node->bundle() !== NODE_BUNDLE) {
    return NULL;
  }
  return $node;
}

// ---------------------------------------------------------------------------
// Argument parsing.
// ---------------------------------------------------------------------------

$dry_run = FALSE;
$force = FALSE;
$limit = NULL;

if (isset($extra) && is_array($extra)) {
  foreach ($extra as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
      $dry_run = TRUE;
    }
    elseif ($arg === '--force' || $arg === '-f') {
      $force = TRUE;
    }
    elseif (strpos($arg, '--limit=') === 0) {
      $limit = (int) substr($arg, 8);
    }
  }
}

// ---------------------------------------------------------------------------
// Locate the YAML file.
// ---------------------------------------------------------------------------

$candidates = [
  DRUPAL_ROOT . '/../config/sync/structure_sync.data.yml',
  DRUPAL_ROOT . '/config/sync/structure_sync.data.yml',
  dirname(__DIR__) . '/config/sync/structure_sync.data.yml',
];
$yml_path = NULL;
foreach ($candidates as $candidate) {
  if (file_exists($candidate)) {
    $yml_path = $candidate;
    break;
  }
}
if ($yml_path === NULL) {
  apt_output('ERROR: could not locate structure_sync.data.yml. Tried:');
  foreach ($candidates as $candidate) {
    apt_output('  - ' . $candidate);
  }
  exit(1);
}
apt_output("Reading menu structure from: $yml_path");

$data = Yaml::parseFile($yml_path);
if (empty($data['menus']) || !is_array($data['menus'])) {
  apt_output('ERROR: no menus found in YAML.');
  exit(1);
}

// ---------------------------------------------------------------------------
// Build top-level menu UUID -> title map (only Main navigation).
// ---------------------------------------------------------------------------

$top_level_by_uuid = [];
foreach ($data['menus'] as $item) {
  if (($item['menu_name'] ?? NULL) !== 'main') {
    continue;
  }
  if (!empty($item['parent'])) {
    continue;
  }
  if (empty($item['uuid']) || empty($item['title'])) {
    continue;
  }
  $top_level_by_uuid[$item['uuid']] = $item['title'];
}
apt_output('Top-level main menu items: ' . count($top_level_by_uuid)
  . ' (' . implode(', ', $top_level_by_uuid) . ').');

// ---------------------------------------------------------------------------
// Build case-insensitive Topic term name -> tid map.
// ---------------------------------------------------------------------------

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$all_topic_tids = $term_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', VOCABULARY)
  ->execute();
if (empty($all_topic_tids)) {
  apt_output("ERROR: vocabulary '" . VOCABULARY . "' has no terms. Run scripts/generate_topic_taxonomy.php first.");
  exit(1);
}
$topic_terms = $term_storage->loadMultiple($all_topic_tids);
$topic_by_lcname = [];
foreach ($topic_terms as $tid => $term) {
  $topic_by_lcname[mb_strtolower($term->label())] = (int) $tid;
}
apt_output('Topic terms loaded: ' . count($topic_by_lcname) . '.');
apt_output('');

// ---------------------------------------------------------------------------
// Confirm field_primary_topic exists on composite_page.
// ---------------------------------------------------------------------------

$field_definitions = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', NODE_BUNDLE);
if (!isset($field_definitions[TOPIC_FIELD])) {
  apt_output("ERROR: field '" . TOPIC_FIELD . "' is not defined on node bundle '" . NODE_BUNDLE . "'. Aborting.");
  exit(1);
}

if ($dry_run) {
  apt_output('DRY RUN MODE — no changes will be saved.');
}
if ($force) {
  apt_output('FORCE MODE — existing primary topic values will be overwritten.');
}
if ($limit) {
  apt_output("Limiting to $limit updates.");
}
apt_output('');

// ---------------------------------------------------------------------------
// Walk submenu items and apply.
// ---------------------------------------------------------------------------

$processed = 0;
$updated = 0;
$already_correct = 0;
$skipped_view_all = 0;
$skipped_no_term = 0;
$skipped_no_node = 0;
$skipped_no_parent = 0;
$skipped_existing = 0;

foreach ($data['menus'] as $item) {
  if (($item['menu_name'] ?? NULL) !== 'main') {
    continue;
  }
  // Top-level items have no parent — skip; they're sections, not topic pages.
  if (empty($item['parent'])) {
    continue;
  }

  $title = (string) ($item['title'] ?? '');
  $uri = (string) ($item['uri'] ?? '');
  $parent_ref = (string) $item['parent'];

  // Skip "View All <Section>" items — they point back at the parent section.
  if (stripos($title, 'View All') === 0) {
    apt_output("[skip] '$title' — View All entry.");
    $skipped_view_all++;
    continue;
  }

  // Parent reference is "menu_link_content:<uuid>".
  $parent_uuid = NULL;
  if (strpos($parent_ref, 'menu_link_content:') === 0) {
    $parent_uuid = substr($parent_ref, strlen('menu_link_content:'));
  }
  if (!$parent_uuid || !isset($top_level_by_uuid[$parent_uuid])) {
    apt_output("[skip] '$title' — parent '$parent_ref' is not a tracked top-level menu item.");
    $skipped_no_parent++;
    continue;
  }
  $parent_title = $top_level_by_uuid[$parent_uuid];

  $path = apt_path_from_uri($uri);
  if ($path === '' || $path === '/') {
    apt_output("[skip] '$title' — empty path.");
    $skipped_no_node++;
    continue;
  }

  $candidate_term_name = "$parent_title / $title";
  $term_tid = $topic_by_lcname[mb_strtolower($candidate_term_name)] ?? NULL;
  if ($term_tid === NULL) {
    apt_output("[warn] '$title' — no topic term matching '$candidate_term_name' (path $path). Skipping.");
    $skipped_no_term++;
    continue;
  }

  $node = apt_find_node_by_alias($path);
  if (!$node) {
    apt_output("[warn] '$title' — no composite_page node found at path '$path'. Skipping.");
    $skipped_no_node++;
    continue;
  }

  $processed++;

  $current_tid = (int) ($node->get(TOPIC_FIELD)->target_id ?? 0);
  if ($current_tid === $term_tid) {
    apt_output("[ok]   '$title' (nid {$node->id()}) — already set to '$candidate_term_name' (tid $term_tid).");
    $already_correct++;
    continue;
  }
  if ($current_tid && !$force) {
    apt_output("[skip] '$title' (nid {$node->id()}) — already has primary topic tid $current_tid; pass --force to overwrite.");
    $skipped_existing++;
    continue;
  }

  $action = $current_tid ? 'overwriting' : 'setting';
  apt_output("[set]  '$title' (nid {$node->id()}) — $action field_primary_topic to '$candidate_term_name' (tid $term_tid).");

  if (!$dry_run) {
    $node->set(TOPIC_FIELD, ['target_id' => $term_tid]);
    $node->save();
  }
  $updated++;

  if ($limit !== NULL && $updated >= $limit) {
    apt_output("Reached limit of $limit updates.");
    break;
  }
}

// ---------------------------------------------------------------------------
// Summary.
// ---------------------------------------------------------------------------

apt_output('');
apt_output('--- Summary ---');
apt_output("Submenu items processed:        $processed");
apt_output("Updated:                        $updated");
apt_output("Already correct:                $already_correct");
apt_output("Skipped (View All):             $skipped_view_all");
apt_output("Skipped (parent not tracked):   $skipped_no_parent");
apt_output("Skipped (no matching term):     $skipped_no_term");
apt_output("Skipped (no node at path):      $skipped_no_node");
apt_output("Skipped (existing, no --force): $skipped_existing");

if ($dry_run) {
  apt_output('');
  apt_output('DRY RUN complete. Re-run without --dry-run to apply.');
}
