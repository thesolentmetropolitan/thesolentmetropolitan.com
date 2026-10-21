<?php

/**
 * @file
 * Moves Link content from /explore/links/<title> to /link/<title> (issue 444).
 *
 * The URL pattern itself is config (pathauto.pattern.link_full_page_url,
 * imported before this runs). This script does the content half:
 *   1. regenerates every Link node's alias from the new pattern;
 *   2. leaves a 301 from each old address to the node, so bookmarks,
 *      search results and links in article bodies keep working.
 * (The redirect module's "auto redirect" setting also records one when an
 * alias changes; the script checks before adding, so there is one per
 * address either way.) The bare /link and /links addresses redirect to
 * /explore/organisations via routes in customsolent_helpers, not here.
 *
 * Idempotent: a node already on /link/… is reported and left alone; a
 * redirect that exists is reported and left alone. The 13 oldest Link nodes
 * have pathauto switched off (they pre-date the pattern) but their aliases
 * are exactly what the old pattern would have made, so they are switched
 * back to automatic and moved with the rest. A node whose alias is
 * genuinely hand-typed (not under /explore/links/) is skipped and listed.
 *
 * Usage:
 *   drush php:script scripts/move_link_urls_2026-10-22.php
 *   drush php:script scripts/move_link_urls_2026-10-22.php -- --dry-run
 */

use Drupal\pathauto\PathautoState;
use Drupal\redirect\Entity\Redirect;

$dry = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $line) { echo $line, PHP_EOL; };
$act = $dry ? 'WOULD ' : '';

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$redirect_storage = $etm->getStorage('redirect');
$alias_manager = \Drupal::service('path_alias.manager');
/** @var \Drupal\pathauto\PathautoGeneratorInterface $generator */
$generator = \Drupal::service('pathauto.generator');

$pattern = $etm->getStorage('pathauto_pattern')->load('link_full_page_url');
if (!$pattern || !str_starts_with($pattern->getPattern(), '/link/')) {
  $say('FAIL  the Link URL pattern is not /link/[node:title] yet — run drush config:import first.');
  return;
}

/** Ensure a 301 from $source (no leading slash) to $uri. */
$ensure_redirect = static function (string $source, string $uri) use ($redirect_storage, $dry, $say, $act): void {
  $source = ltrim($source, '/');
  $existing = $redirect_storage->loadByProperties(['redirect_source__path' => $source]);
  if ($existing) {
    $r = reset($existing);
    $say("  OK    redirect /$source -> " . $r->getRedirect()['uri'] . ' already exists');
    return;
  }
  $say("  {$act}ADD   redirect /$source -> $uri (301)");
  if (!$dry) {
    Redirect::create([
      'redirect_source' => ['path' => $source, 'query' => []],
      'redirect_redirect' => ['uri' => $uri],
      'status_code' => 301,
      'language' => 'und',
    ])->save();
  }
};

$nids = $node_storage->getQuery()->accessCheck(FALSE)->condition('type', 'link')->sort('nid')->execute();
$say('== ' . count($nids) . ' Link nodes');
$moved = $kept = $manual = 0;
foreach ($node_storage->loadMultiple($nids) as $node) {
  $system_path = '/node/' . $node->id();
  $old_alias = $alias_manager->getAliasByPath($system_path);
  $old_alias = $old_alias === $system_path ? '' : $old_alias;
  $label = "node/{$node->id()} \"{$node->label()}\"";

  if ($old_alias !== '' && str_starts_with($old_alias, '/link/')) {
    $say("  OK    $label already at $old_alias");
    $kept++;
    continue;
  }
  $automatic = !$node->hasField('path') || (int) $node->get('path')->pathauto !== PathautoState::SKIP;
  if (!$automatic && $old_alias !== '' && !str_starts_with($old_alias, '/explore/links/')) {
    $say("  SKIP  $label has a hand-typed alias ($old_alias); not changed");
    $manual++;
    continue;
  }
  if (!$automatic) {
    $say("  {$act}AUTO  $label: pathauto switched back on (alias followed the old pattern)");
    if (!$dry) {
      $node->get('path')->pathauto = PathautoState::CREATE;
      $node->path->first()->get('pathauto')->persist();
    }
  }

  $new_alias = '';
  if ($dry) {
    $new_alias = (string) \Drupal::service('pathauto.alias_cleaner')->cleanString($node->label());
    $new_alias = '/link/' . $new_alias;
    $say("  WOULD MOVE  $label  $old_alias -> ~$new_alias");
  }
  else {
    $result = $generator->updateEntityAlias($node, 'update');
    $new_alias = $result['alias'] ?? $alias_manager->getAliasByPath($system_path);
    $say("  MOVE  $label  " . ($old_alias ?: '(no alias)') . " -> $new_alias");
  }
  $moved++;
  if ($old_alias !== '' && $old_alias !== $new_alias) {
    $ensure_redirect($old_alias, 'internal:' . $system_path);
  }
}
if (!$dry) {
  $alias_manager->cacheClear();
}
$say("== done: $moved moved, $kept already in place, $manual hand-typed (skipped)" . ($dry ? ' [dry run]' : ''));
