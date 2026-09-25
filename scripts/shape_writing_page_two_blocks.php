<?php

/**
 * @file
 * Drush script: give /culture/writing its two-block article shape.
 *
 * Writing's subject is the written word, and the page carries two
 * messages that a single article grid cannot: "here are articles about
 * writing itself" (the section's own subject, as on every other section
 * page) and "we write, and so can you" (the invitation to contribute).
 * The page therefore gets, in order:
 *
 *   1. the intro text, extended with the "we write" message
 *   2. the topic-scoped articles block — articles tagged Writing, heading
 *      "Articles about writing", automatic mode (renders nothing until
 *      the first such article exists, then leads as on other sections)
 *   3. the existing "Preview, all topics" block, headed
 *      "Latest from our contributors"
 *   4. a call to action, "Write for us", to the contact page
 *
 * Events, organisations and links keep their places. The intro copy and
 * the CTA wording are first drafts for Rob to re-voice in the editor.
 *
 * Idempotent: each of the four parts is checked before it is changed.
 *
 * Usage:
 *   drush php:script scripts/shape_writing_page_two_blocks.php
 *   drush php:script scripts/shape_writing_page_two_blocks.php -- --dry-run
 */

use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PAGE_ALIAS = '/culture/writing';
const PAGE_NID_FALLBACK = 27;
const TOPIC_HEADING = 'Articles about writing';
const SITEWIDE_HEADING = 'Latest from our contributors';
const CTA_URI = 'internal:/about/contact';
const CTA_TITLE = 'Write for us';
const CTA_BG_COLOR = 'Culture';
const CTA_TEXT_COLOR = 'white';
const INTRO_MARKER = 'We write too.';
const INTRO_ADDITION = '<p>We write too. Everything published on The Solent Metropolitan is written by people who live and work in the region, on our own team and among our contributors. If you have a story, an argument or a piece of local knowledge worth sharing, we would like to hear from you.</p>';

$etm = \Drupal::entityTypeManager();
$path = \Drupal::service('path_alias.manager')->getPathByAlias(PAGE_ALIAS);
$nid = preg_match('#^/node/(\d+)$#', $path, $m) ? (int) $m[1] : PAGE_NID_FALLBACK;
$node = $etm->getStorage('node')->load($nid);
if (!$node || $node->bundle() !== 'composite_page') {
  $say('ABORT no composite page at ' . PAGE_ALIAS . " (node $nid).");
  return;
}

// Every articles View Display paragraph on the page, in page order.
$articles = [];
$walk = function ($entity, int $depth) use (&$walk, &$articles): void {
  if ($depth <= 0) {
    return;
  }
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if ($p instanceof Paragraph) {
        if ($p->bundle() === 'view_display' && $p->get('field_view')->target_id === 'articles_listing') {
          $articles[] = $p;
        }
        $walk($p, $depth - 1);
      }
    }
  }
};
$walk($node, 6);

$sitewide = NULL;
$topic_scoped = NULL;
foreach ($articles as $p) {
  if ($p->get('field_listing_mode')->value === 'preview_all') {
    $sitewide ??= $p;
  }
  else {
    $topic_scoped ??= $p;
  }
}
if (!$sitewide) {
  $say('ABORT ' . PAGE_ALIAS . ' has no "Preview, all topics" articles block — run set_writing_articles_all_topics.php first.');
  return;
}
$parent = $sitewide->getParentEntity();
$field_name = $sitewide->get('parent_field_name')->value;
$siblings = $parent->get($field_name)->referencedEntities();
$changed = [];

// 1. Intro text: the first text paragraph among the block's siblings.
$intro = NULL;
foreach ($siblings as $s) {
  if ($s->bundle() === 'text') {
    $intro = $s;
    break;
  }
}
if (!$intro) {
  $say('WARN  no text paragraph beside the articles block — intro not extended.');
}
elseif (str_contains((string) $intro->get('field_text')->value, INTRO_MARKER)) {
  $say('OK    intro already carries the "we write" message.');
}
else {
  $say(($dry_run ? 'WOULD ' : 'SET   ') . "intro text paragraph {$intro->id()}: append the \"we write\" message.");
  if (!$dry_run) {
    $intro->set('field_text', [
      'value' => rtrim((string) $intro->get('field_text')->value) . "\n" . INTRO_ADDITION,
      'format' => $intro->get('field_text')->format,
    ]);
    $intro->save();
    $changed[] = $intro;
  }
}

// 2. Site-wide block heading.
if (trim((string) $sitewide->get('field_heading')->value) !== '') {
  $say("OK    all-topics block {$sitewide->id()} already has a heading (\"{$sitewide->get('field_heading')->value}\").");
}
else {
  $say(($dry_run ? 'WOULD ' : 'SET   ') . "all-topics block {$sitewide->id()}: heading \"" . SITEWIDE_HEADING . '".');
  if (!$dry_run) {
    $sitewide->set('field_heading', SITEWIDE_HEADING);
    $sitewide->save();
    $changed[] = $sitewide;
  }
}

// 3. Topic-scoped block, directly before the site-wide one.
$insert_before = [];
if ($topic_scoped) {
  $say("OK    topic-scoped articles block already placed ({$topic_scoped->id()}).");
}
else {
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . 'topic-scoped articles block "' . TOPIC_HEADING . "\" before block {$sitewide->id()}.");
  if (!$dry_run) {
    $p = Paragraph::create([
      'type' => 'view_display',
      'field_heading' => TOPIC_HEADING,
      'field_view' => [
        'target_id' => 'articles_listing',
        'display_id' => 'view_display_primary_and_related',
        'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]),
      ],
    ]);
    $p->setParentEntity($parent, $field_name);
    $p->save();
    $insert_before[] = $p;
  }
}

// 4. Call to action, directly after the site-wide block.
$insert_after = [];
$cta = NULL;
foreach ($siblings as $s) {
  if ($s->bundle() === 'call_to_action' && $s->get('field_link')->uri === CTA_URI) {
    $cta = $s;
    break;
  }
}
if ($cta) {
  $say("OK    call to action to " . CTA_URI . " already placed ({$cta->id()}).");
}
else {
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . 'call to action "' . CTA_TITLE . '" → ' . CTA_URI . " after block {$sitewide->id()}.");
  if (!$dry_run) {
    $colours = $etm->getStorage('taxonomy_term');
    $bg = $colours->loadByProperties(['vid' => 'color', 'name' => CTA_BG_COLOR]);
    $fg = $colours->loadByProperties(['vid' => 'color', 'name' => CTA_TEXT_COLOR]);
    if (!$bg || !$fg) {
      $say('ABORT colour terms "' . CTA_BG_COLOR . '" / "' . CTA_TEXT_COLOR . '" not found in the color vocabulary.');
      return;
    }
    $p = Paragraph::create([
      'type' => 'call_to_action',
      'field_link' => ['uri' => CTA_URI, 'title' => CTA_TITLE, 'options' => []],
      'field_color_background' => reset($bg)->id(),
      'field_color_text' => reset($fg)->id(),
    ]);
    $p->setParentEntity($parent, $field_name);
    $p->save();
    $insert_after[] = $p;
  }
}

if ($dry_run) {
  $say('DRY RUN — no changes made.');
  return;
}

// Rebuild the parent's item list around the site-wide block.
if ($insert_before || $insert_after) {
  $items = [];
  foreach ($parent->get($field_name)->getValue() as $item) {
    if ((int) $item['target_id'] === (int) $sitewide->id()) {
      foreach ($insert_before as $p) {
        $items[] = ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
      }
      $items[] = ['target_id' => $sitewide->id(), 'target_revision_id' => $sitewide->getRevisionId()];
      foreach ($insert_after as $p) {
        $items[] = ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
      }
    }
    else {
      $items[] = $item;
    }
  }
  $parent->set($field_name, $items);
  $parent->save();
  $changed[] = $parent;
}
elseif ($changed) {
  // Point the parent at the new revisions of the paragraphs edited in place.
  $items = [];
  foreach ($parent->get($field_name)->getValue() as $item) {
    foreach ($changed as $p) {
      if ((int) $item['target_id'] === (int) $p->id()) {
        $item['target_revision_id'] = $p->getRevisionId();
      }
    }
    $items[] = $item;
  }
  $parent->set($field_name, $items);
  $parent->save();
  $changed[] = $parent;
}
if ($changed) {
  // Save the page so its own revision references the new paragraph
  // revisions and its cache tag is invalidated.
  $node->save();
  $say('Done. Run drush cr next.');
}
else {
  $say('Nothing to do.');
}
