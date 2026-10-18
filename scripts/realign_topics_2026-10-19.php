<?php

/**
 * @file
 * Topic re-alignment, batch 1 (18–19 Oct 2026): three renames, one deletion.
 *
 *   Culture / Outdoor & Active  ->  Culture / Active & Outdoor     (issue 360)
 *   Sectors / Media             ->  Sectors / Media & Publishing   (issue 318)
 *   Culture / Maritime          ->  Culture / Maritime & Marine    (issue 321)
 *   Sectors / Maritime          ->  Sectors / Maritime & Marine    (issue 321)
 *   Explore / Jobs Boards       ->  deleted, with its unpublished page
 *
 * A topic is five things that must agree, because the kicker links are
 * BUILT FROM THE TERM NAME by convention (_customsolent_build_term_url:
 * "Culture / Active & Outdoor" -> /culture/active-outdoor):
 *
 *   1. the Topic term's name            "Culture / Active & Outdoor"
 *   2. the composite page's title       "Active & Outdoor"
 *      and its hero paragraph's title   "Active & Outdoor"
 *   3. the composite page's URL alias   /culture/active-outdoor
 *   4. the main-menu link: title + uri  "Active & Outdoor" -> /culture/active-outdoor
 *   5. redirects from the old address:  /culture/outdoor-active            -> the page
 *                                       /culture/outdoor-active/events     -> the listing
 *                                       …/articles /series /collaborations /organisations
 *
 * Terms, nodes, menu links and redirects are content, so this script is the
 * deployable artefact (config/sync/structure_sync.data.yml is re-exported for
 * the record). Idempotent: every step checks before it writes, so a half-run
 * can simply be run again. Matches by name / alias, never by id.
 *
 * Usage:
 *   drush php:script scripts/realign_topics_2026-10-19.php
 *   drush php:script scripts/realign_topics_2026-10-19.php -- --dry-run
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\redirect\Entity\Redirect;
use Drupal\taxonomy\Entity\Term;

$dry = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $line) { echo $line, PHP_EOL; };
$act = $dry ? 'WOULD ' : '';

$renames = [
  ['old' => 'Culture / Outdoor & Active', 'new' => 'Culture / Active & Outdoor', 'old_alias' => '/culture/outdoor-active', 'new_alias' => '/culture/active-outdoor'],
  ['old' => 'Sectors / Media', 'new' => 'Sectors / Media & Publishing', 'old_alias' => '/sectors/media', 'new_alias' => '/sectors/media-publishing'],
  ['old' => 'Culture / Maritime', 'new' => 'Culture / Maritime & Marine', 'old_alias' => '/culture/maritime', 'new_alias' => '/culture/maritime-marine'],
  ['old' => 'Sectors / Maritime', 'new' => 'Sectors / Maritime & Marine', 'old_alias' => '/sectors/maritime', 'new_alias' => '/sectors/maritime-marine'],
];
$deletions = ['Explore / Jobs Boards'];

$etm = \Drupal::entityTypeManager();
$db = \Drupal::database();
$term_storage = $etm->getStorage('taxonomy_term');
$node_storage = $etm->getStorage('node');
$alias_storage = $etm->getStorage('path_alias');
$redirect_storage = $etm->getStorage('redirect');
$menu_storage = $etm->getStorage('menu_link_content');
$listing_words = function_exists('customsolent_helpers_listing_route_words')
  ? customsolent_helpers_listing_route_words()
  : ['events', 'articles', 'series', 'collaborations', 'organisations'];

$short = static fn(string $name): string => trim((string) end(explode(' / ', $name)));

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

foreach ($renames as $r) {
  $say("== {$r['old']}  ->  {$r['new']}");
  $old_short = $short($r['old']);
  $new_short = $short($r['new']);

  // 1. The term.
  $terms = $term_storage->loadByProperties(['vid' => 'topic', 'name' => $r['new']])
    ?: $term_storage->loadByProperties(['vid' => 'topic', 'name' => $r['old']]);
  if (!$terms) {
    $say("  FAIL  no topic term named '{$r['old']}' or '{$r['new']}' — skipping this rename");
    continue;
  }
  $term = reset($terms);
  if ($term->getName() === $r['new']) {
    $say("  OK    term {$term->id()} already named '{$r['new']}'");
  }
  else {
    $say("  {$act}SET   term {$term->id()} name '{$term->getName()}' -> '{$r['new']}'");
    if (!$dry) { $term->setName($r['new']); $term->save(); }
  }

  // 2. The composite page (title + hero title) and 3. its alias.
  $nids = $node_storage->getQuery()->accessCheck(FALSE)
    ->condition('type', 'composite_page')
    ->condition('field_primary_topic', $term->id())
    ->execute();
  if (count($nids) !== 1) {
    $say('  FAIL  expected one composite page for the term, found ' . count($nids) . ' — leaving page, alias, menu and redirects alone');
    continue;
  }
  $node = Node::load(reset($nids));
  $nid = $node->id();
  if ($node->getTitle() === $old_short) {
    $say("  {$act}SET   node $nid title '$old_short' -> '$new_short'");
    if (!$dry) { $node->setTitle($new_short); $node->save(); }
  }
  else {
    $say("  OK    node $nid title is '{$node->getTitle()}'");
  }
  foreach ($node->get('field_content_component')->referencedEntities() as $para) {
    if ($para->bundle() === 'hero_with_art_style' && $para->hasField('field_title')) {
      $hero_title = (string) $para->get('field_title')->value;
      if ($hero_title === $old_short) {
        $say("  {$act}SET   hero paragraph {$para->id()} title '$old_short' -> '$new_short'");
        if (!$dry) { $para->set('field_title', $new_short); $para->save(); }
      }
      else {
        $say("  OK    hero paragraph {$para->id()} title is '$hero_title'");
      }
    }
  }

  $aliases = $alias_storage->loadByProperties(['path' => "/node/$nid"]);
  $has_new = FALSE;
  foreach ($aliases as $alias) {
    if ($alias->getAlias() === $r['new_alias']) {
      $has_new = TRUE;
      $say("  OK    alias {$r['new_alias']} -> /node/$nid already in place");
    }
    elseif ($alias->getAlias() === $r['old_alias']) {
      // Saving the changed alias lets the Redirect module add the
      // old -> new 301 itself (redirect_path_alias_update).
      $say("  {$act}SET   alias {$r['old_alias']} -> {$r['new_alias']} (node $nid)");
      if (!$dry) { $alias->setAlias($r['new_alias']); $alias->save(); }
      $has_new = TRUE;
    }
    else {
      $say("  NOTE  node $nid also has alias {$alias->getAlias()} (left alone)");
    }
  }
  if (!$has_new) {
    $say("  {$act}ADD   alias {$r['new_alias']} -> /node/$nid");
    if (!$dry) {
      PathAlias::create(['path' => "/node/$nid", 'alias' => $r['new_alias'], 'langcode' => 'en'])->save();
    }
  }

  // 4. The main-menu link.
  $links = $menu_storage->loadByProperties(['menu_name' => 'main', 'link.uri' => 'internal:' . $r['old_alias']])
    + $menu_storage->loadByProperties(['menu_name' => 'main', 'link.uri' => 'internal:' . $r['new_alias']]);
  if (!$links) {
    $say("  NOTE  no main-menu link points at {$r['old_alias']} or {$r['new_alias']}");
  }
  foreach ($links as $link) {
    $changes = [];
    if ($link->getTitle() === $old_short) { $changes[] = "title '$old_short' -> '$new_short'"; }
    if ($link->link->uri === 'internal:' . $r['old_alias']) { $changes[] = "uri {$r['old_alias']} -> {$r['new_alias']}"; }
    if (!$changes) {
      $say("  OK    menu link {$link->id()} '{$link->getTitle()}' -> {$link->link->uri}");
      continue;
    }
    $say("  {$act}SET   menu link {$link->id()}: " . implode(', ', $changes));
    if (!$dry) {
      if ($link->getTitle() === $old_short) { $link->set('title', $new_short); }
      $link->set('link', ['uri' => 'internal:' . $r['new_alias']]);
      $link->save();
    }
  }

  // 5. Redirects: the page itself, and each automatic listing under it.
  $ensure_redirect($r['old_alias'], "internal:/node/$nid");
  foreach ($listing_words as $word) {
    // /topic-listing/{nid}/{word} is the real route; the outbound path
    // processor prints it as /{new alias}/{word}, now and after any later
    // rename.
    $ensure_redirect($r['old_alias'] . '/' . $word, "internal:/topic-listing/$nid/$word");
  }

  // 6. Hand-written links to the old address inside rich text.
  $needle = 'href="' . $r['old_alias'] . '"';
  $replacement = 'href="' . $r['new_alias'] . '"';
  $pids = $db->query('SELECT DISTINCT entity_id FROM {paragraph__field_text} WHERE field_text_value LIKE :n', [':n' => '%' . $db->escapeLike($needle) . '%'])->fetchCol();
  foreach ($pids as $pid) {
    $para = Paragraph::load($pid);
    if (!$para) { continue; }
    $say("  {$act}SET   paragraph $pid text: {$r['old_alias']} -> {$r['new_alias']}");
    if (!$dry) {
      $item = $para->get('field_text')->first()->getValue();
      $item['value'] = str_replace($needle, $replacement, $item['value']);
      $para->set('field_text', $item);
      $para->save();
    }
  }
  $bnids = $db->query('SELECT DISTINCT entity_id FROM {node__field_body} WHERE field_body_value LIKE :n', [':n' => '%' . $db->escapeLike($needle) . '%'])->fetchCol();
  foreach ($bnids as $bnid) {
    $bnode = Node::load($bnid);
    if (!$bnode) { continue; }
    $say("  {$act}SET   node $bnid body: {$r['old_alias']} -> {$r['new_alias']}");
    if (!$dry) {
      $item = $bnode->get('field_body')->first()->getValue();
      $item['value'] = str_replace($needle, $replacement, $item['value']);
      $bnode->set('field_body', $item);
      $bnode->save();
    }
  }
}

foreach ($deletions as $name) {
  $say("== delete $name");
  $terms = $term_storage->loadByProperties(['vid' => 'topic', 'name' => $name]);
  if (!$terms) {
    $say("  OK    no term named '$name' (already gone)");
    continue;
  }
  $term = reset($terms);
  $tid = $term->id();
  // Refuse if any real content still points at it.
  $primary = $db->query('SELECT n.nid, n.type, n.title FROM {node__field_primary_topic} p JOIN {node_field_data} n ON n.nid = p.entity_id WHERE p.field_primary_topic_target_id = :t AND n.type <> :cp', [':t' => $tid, ':cp' => 'composite_page'])->fetchAll();
  $related = $db->query('SELECT COUNT(*) FROM {node__field_related_topics} WHERE field_related_topics_target_id = :t', [':t' => $tid])->fetchField();
  $para_refs = $db->query('SELECT COUNT(*) FROM {paragraph__field_topic} WHERE field_topic_target_id = :t', [':t' => $tid])->fetchField();
  if ($primary || $related || $para_refs) {
    $say("  FAIL  term $tid is still referenced (primary: " . count($primary) . ", related: $related, paragraphs: $para_refs) — not deleting");
    foreach ($primary as $row) { $say("        node {$row->nid} [{$row->type}] {$row->title}"); }
    continue;
  }
  $nids = $node_storage->getQuery()->accessCheck(FALSE)
    ->condition('type', 'composite_page')
    ->condition('field_primary_topic', $tid)
    ->execute();
  foreach (Node::loadMultiple($nids) as $page) {
    $say("  {$act}DEL   composite page {$page->id()} '{$page->getTitle()}' (" . ($page->isPublished() ? 'published' : 'unpublished') . ')');
    if (!$dry) { $page->delete(); }
  }
  $say("  {$act}DEL   term $tid '$name'");
  if (!$dry) { $term->delete(); }
}

$say($dry ? 'DRY RUN — nothing changed.' : 'Done. Run drush cr so kicker links and menus pick up the new names.');
