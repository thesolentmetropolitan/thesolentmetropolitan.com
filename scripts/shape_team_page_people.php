<?php

/**
 * @file
 * Drush script: the team on /about/team as People.
 *
 * 1. Ensures a Person node for the founder, Rob Davis, exists and is
 *    published: first name, last name, a short bio drawn from the page's
 *    own text (first draft for Rob to re-voice in the editor; the page text
 *    stays as it is).
 * 2. Appends a View Display paragraph (people_listing / View Display
 *    Listing Cards, "People Grid" classy style) to the enclosure that holds
 *    the page text, so every published Person appears as a team card.
 *
 * Idempotent: the Person is matched by first + last name, the listing by
 * view id.
 *
 * Usage:
 *   drush php:script scripts/shape_team_page_people.php
 *   drush php:script scripts/shape_team_page_people.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };

const PAGE_ALIAS = '/about/team';
const PAGE_NID_FALLBACK = 88;
const VIEW_ID = 'people_listing';
const DISPLAY_ID = 'view_display_listing_cards';
const CLASSY_STYLE = 'people_grid';
const FOUNDER_FIRST = 'Rob';
const FOUNDER_LAST = 'Davis';
const FOUNDER_BIO = <<<HTML
<p>Rob founded The Solent Metropolitan out of a love for both Solent cities, Southampton and Portsmouth, and the areas around them, with roots in both from growing up, working, living, studying and playing across the region.</p>
<p>He presented and produced speech radio shows about volunteering and community across the Solent, on Unity 101.1 FM in Southampton, Skyline 102.5 FM in Hedge End and 93.7 Express FM in Portsmouth. Professionally he builds online digital experiences and collaboration platforms, most recently for the United Nations and for universities in the Solent region.</p>
HTML;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');

// 1. The founder as a Person.
$existing = $node_storage->loadByProperties(['type' => 'person', 'field_first_name' => FOUNDER_FIRST, 'field_last_name' => FOUNDER_LAST]);
if ($existing) {
  $person = reset($existing);
  $say("OK    person {$person->id()} '" . FOUNDER_FIRST . ' ' . FOUNDER_LAST . "' exists.");
}
else {
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "person '" . FOUNDER_FIRST . ' ' . FOUNDER_LAST . "'.");
  if (!$dry_run) {
    $southampton = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'location', 'name' => 'Southampton']);
    $person = Node::create([
      'type' => 'person',
      'title' => FOUNDER_FIRST . ' ' . FOUNDER_LAST,
      'uid' => 1,
      'status' => 1,
      'field_first_name' => FOUNDER_FIRST,
      'field_last_name' => FOUNDER_LAST,
      'field_bio' => ['value' => FOUNDER_BIO, 'format' => 'basic_html'],
      'field_where' => $southampton ? ['target_id' => reset($southampton)->id()] : [],
    ]);
    $person->save();
    $say('      ' . $person->toUrl()->toString());
  }
}

// 2. The listing on the team page.
$path = \Drupal::service('path_alias.manager')->getPathByAlias(PAGE_ALIAS);
$nid = preg_match('#^/node/(\d+)$#', $path, $m) ? (int) $m[1] : PAGE_NID_FALLBACK;
$node = $node_storage->load($nid);
if (!$node || $node->bundle() !== 'composite_page') {
  $say('ABORT no composite page at ' . PAGE_ALIAS . " (node $nid).");
  return;
}
$found = FALSE;
$walk = function ($entity, int $depth) use (&$walk, &$found): void {
  if ($depth <= 0) {
    return;
  }
  foreach ($entity->getFields(FALSE) as $field) {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($field->referencedEntities() as $p) {
      if ($p instanceof Paragraph) {
        if ($p->bundle() === 'view_display' && $p->get('field_view')->target_id === VIEW_ID) {
          $found = TRUE;
        }
        $walk($p, $depth - 1);
      }
    }
  }
};
$walk($node, 6);
if ($found) {
  $say('OK    ' . PAGE_ALIAS . ' already lists ' . VIEW_ID . '.');
  $say($dry_run ? 'Dry run — nothing changed.' : 'Done.');
  return;
}
$enclosure = NULL;
foreach ($node->get('field_content_component')->referencedEntities() as $p) {
  if ($p->bundle() === 'enclosure') {
    $enclosure = $p;
    break;
  }
}
if (!$enclosure) {
  $say('ABORT ' . PAGE_ALIAS . ' has no enclosure paragraph to add the listing to.');
  return;
}
$say(($dry_run ? 'WOULD ' : 'ADD   ') . 'View Display paragraph (' . VIEW_ID . ' / ' . DISPLAY_ID . ', style ' . CLASSY_STYLE . ') to enclosure ' . $enclosure->id() . '.');
if ($dry_run) {
  $say('Dry run — nothing changed.');
  return;
}
$listing = Paragraph::create([
  'type' => 'view_display',
  'field_heading' => 'The team',
  'field_view' => [
    'target_id' => VIEW_ID,
    'display_id' => DISPLAY_ID,
    'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]),
  ],
  'field_classy' => [['target_id' => CLASSY_STYLE]],
]);
$listing->save();
$items = [];
foreach ($enclosure->get('field_content_component') as $item) {
  if ($item->entity) {
    $items[] = ['target_id' => $item->target_id, 'target_revision_id' => $item->target_revision_id];
  }
}
$items[] = ['target_id' => $listing->id(), 'target_revision_id' => $listing->getRevisionId()];
$enclosure->set('field_content_component', $items);
$enclosure->save();
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('People listing added by scripts/shape_team_page_people.php');
$node->save();
$say("Done. Paragraph {$listing->id()} added.");
