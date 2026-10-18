<?php

/**
 * @file
 * Drush script: the Push & Play skatepark launch as the first Collaboration.
 *
 * Push & Play, Sunday 23 August 2026, 11–4, Hoglands Park: the community
 * event that opened Southampton's new Hoglands Park skatepark, organised by
 * We Skate Soton CIC with supporters and community groups named on their
 * Instagram posts (instagram.com/p/DcVnQ_6DRWj — "a huge thank you to
 * everyone who made the event possible with their support" — and
 * instagram.com/p/Db2X6clN7MT — "communities you can hang out with at the
 * event"). Information already public; structured here as data journalism.
 *
 * What it does (all idempotent, matched by title):
 *   1. Organisations that did not yet exist: We Skate Soton CIC, Skate
 *      Southampton, Skateboard GB, Carabiner Club Southampton, Southampton
 *      Sapphic Social, Iron Born Ltd. Standfirsts are one-line first drafts
 *      from the public posts and sites; topics a first pass. URLs only where
 *      known for certain.
 *   2. A Collaborator for each of the ten organisations Rob listed, role
 *      "Partner" for all until the real involvement is known.
 *   3. The Collaboration "Push & Play: New Hoglands Park Skatepark" with the
 *      ten collaborators and the event (node 706) as its outcome.
 *   4. Publishes event 706 and sets its Organiser to We Skate Soton CIC.
 *
 * Rob's list did not include Southampton City Council, whose logo is on the
 * thank-you poster; it is left out here (Rob can add a Collaborator in the
 * editor; the organisation exists as node 205).
 *
 * Usage:
 *   drush php:script scripts/create_push_and_play_collaboration.php
 *   drush php:script scripts/create_push_and_play_collaboration.php -- --dry-run
 */

use Drupal\node\Entity\Node;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$term_storage = $etm->getStorage('taxonomy_term');

const EVENT_NID = 706;
const EVENT_TITLE = 'Push & Play: New Hoglands Park Skatepark';
const COLLABORATION_TITLE = 'Push & Play: New Hoglands Park Skatepark';
const DEFAULT_ROLE = 'Partner';

$topic = static function (string $name) use ($term_storage, $say): ?int {
  $found = $term_storage->loadByProperties(['vid' => 'topic', 'name' => $name]);
  if (!$found) {
    $say("WARN  topic '$name' not found.");
    return NULL;
  }
  return (int) reset($found)->id();
};
$ref = static fn(?int ...$tids) => array_map(static fn($tid) => ['target_id' => $tid], array_filter($tids));
$t_sport = $topic('Culture / Sport');
$t_outdoor = $topic('Culture / Outdoor & Active');
$t_community = $topic('Culture / Community');
$t_identity = $topic('Culture / Identity');
$t_sport_sector = $topic('Sectors / Sport & Fitness');
$t_lifestyle = $topic('Sectors / Lifestyle');
$t_nonprofit = $topic('Sectors / Non-profit');

// ── 1. Organisations ───────────────────────────────────────────────────
// title => [existing nid or NULL, standfirst, url, primary, related]
$organisations = [
  'Iron Born Ltd' => [NULL, 'Southampton barbers, formerly Cropz Barbers; a supporter of the Push & Play skatepark launch.', NULL, $t_lifestyle, $ref($t_community)],
  'SoCo Music' => [151],
  'GO! Southampton' => [187],
  'We Skate Soton CIC' => [NULL, 'Community interest company growing skateboarding in Southampton: lessons for beginners and marginalised genders, meet-ups, and the Push & Play launch of the Hoglands Park skatepark.', 'https://weskatesoton.co.uk/', $t_sport, $ref($t_community, $t_outdoor)],
  'Address Soton' => [755],
  'Skateboard GB' => [NULL, 'The national governing body for skateboarding in Great Britain.', 'https://www.skateboardgb.org/', $t_sport_sector, $ref($t_sport)],
  'Common Ground Regen CIC' => [621],
  'Carabiner Club Southampton' => [NULL, 'Southampton community club: a place to meet, climb and hang out; one of the communities at the Push & Play skatepark launch.', NULL, $t_outdoor, $ref($t_community)],
  'Southampton Sapphic Social' => [NULL, 'A social group for sapphic women and non-binary people in Southampton.', NULL, $t_identity, $ref($t_community)],
  'Skate Southampton' => [NULL, 'Southampton skateboarding community and skatepark campaign; co-host of Push & Play at Hoglands Park.', NULL, $t_sport, $ref($t_community, $t_outdoor)],
];
$org_nids = [];
foreach ($organisations as $title => $def) {
  if (!empty($def[0])) {
    $org = $node_storage->load($def[0]);
    if (!$org || $org->bundle() !== 'organisation') {
      $say("WARN  node {$def[0]} for '$title' is not an organisation — skipped.");
      continue;
    }
    $say("OK    organisation {$org->id()} '{$org->label()}' exists.");
    $org_nids[$title] = (int) $org->id();
    continue;
  }
  $existing = $node_storage->loadByProperties(['type' => 'organisation', 'title' => $title]);
  if ($existing) {
    $org = reset($existing);
    $say("OK    organisation {$org->id()} '$title' exists.");
    $org_nids[$title] = (int) $org->id();
    continue;
  }
  [, $standfirst, $url, $primary, $related] = $def;
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "organisation '$title'.");
  if ($dry_run) {
    continue;
  }
  $org = Node::create([
    'type' => 'organisation',
    'title' => $title,
    'uid' => 1,
    'status' => 1,
    'field_standfirst' => $standfirst,
    'field_url' => $url ? ['uri' => $url, 'title' => 'Website'] : [],
    'field_primary_topic' => $primary ? ['target_id' => $primary] : [],
    'field_related_topics' => $related,
  ]);
  $org->save();
  $org_nids[$title] = (int) $org->id();
  $say('      ' . $org->toUrl()->toString());
}

// ── 2. Collaborators ───────────────────────────────────────────────────
$role = $term_storage->loadByProperties(['vid' => 'collaborator_type', 'name' => DEFAULT_ROLE]);
$role_id = $role ? (int) reset($role)->id() : NULL;
if (!$role_id) {
  $say('WARN  collaborator type "' . DEFAULT_ROLE . '" not found — run create_collaborator_types.php first; collaborators get no role.');
}
$collaborator_nids = [];
foreach ($org_nids as $title => $org_nid) {
  $existing = $node_storage->loadByProperties(['type' => 'collaborator', 'field_collaborator' => $org_nid]);
  if ($existing) {
    $c = reset($existing);
    $say("OK    collaborator {$c->id()} for '$title' exists.");
    $collaborator_nids[] = (int) $c->id();
    continue;
  }
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "collaborator '$title' (" . DEFAULT_ROLE . ').');
  if ($dry_run) {
    continue;
  }
  $c = Node::create([
    'type' => 'collaborator',
    'title' => $title,
    'uid' => 1,
    'status' => 1,
    'field_collaborator' => ['target_id' => $org_nid],
    'field_collaborator_type' => $role_id ? [['target_id' => $role_id]] : [],
  ]);
  $c->save();
  $collaborator_nids[] = (int) $c->id();
}

// ── 3. The event: published, organised by We Skate Soton ───────────────
$event = $node_storage->load(EVENT_NID);
if (!$event || $event->bundle() !== 'event' || $event->label() !== EVENT_TITLE) {
  $say('ABORT node ' . EVENT_NID . ' is not the event "' . EVENT_TITLE . '".');
  return;
}
$changes = [];
if (!$event->isPublished()) {
  $changes[] = 'publish';
}
if (!empty($org_nids['We Skate Soton CIC']) && $event->hasField('field_organiser')
  && (int) ($event->get('field_organiser')->target_id ?? 0) !== $org_nids['We Skate Soton CIC']) {
  $changes[] = 'organiser = We Skate Soton CIC';
}
if ($changes) {
  $say(($dry_run ? 'WOULD ' : 'SET   ') . 'event ' . EVENT_NID . ': ' . implode(', ', $changes) . '.');
  if (!$dry_run) {
    $event->setPublished();
    if (!empty($org_nids['We Skate Soton CIC']) && $event->hasField('field_organiser')) {
      $event->set('field_organiser', ['target_id' => $org_nids['We Skate Soton CIC']]);
    }
    $event->setNewRevision(TRUE);
    $event->setRevisionLogMessage('Published and organiser set by scripts/create_push_and_play_collaboration.php');
    $event->save();
  }
}
else {
  $say('OK    event ' . EVENT_NID . ' already published with organiser set.');
}

// ── 4. The Collaboration ───────────────────────────────────────────────
$description = <<<HTML
<p>On Sunday 23 August 2026 Southampton's new skatepark at Hoglands Park opened with Push &amp; Play, a free community day from 11am to 4pm run by We Skate Soton CIC with Skate Southampton and GO! Southampton.</p>
<p>The day was made possible by the support of Address Soton, Skate Southampton, GO! Southampton, Iron Born Ltd, Skateboard GB, Southampton City Council and SoCo Music, and brought together communities you could hang out with on the day: Southampton Sapphic Social, Common Ground, Carabiner Club Southampton and Address Soton, alongside the We Skate Soton gang.</p>
<p>Sources: We Skate Soton's thank-you post and communities post on Instagram, August 2026.</p>
HTML;
$values = [
  'field_standfirst' => 'Skaters, community groups, businesses and the city council came together to open the new Hoglands Park skatepark with a free day of skating.',
  'field_description' => ['value' => $description, 'format' => 'basic_html'],
  'field_collaborators' => array_map(static fn(int $nid) => ['target_id' => $nid], $collaborator_nids),
  'field_outcomes' => [['target_id' => EVENT_NID]],
  'field_primary_topic' => $t_sport ? ['target_id' => $t_sport] : [],
  'field_related_topics' => $ref($t_community, $t_outdoor),
  'status' => 1,
];
$existing = $node_storage->loadByProperties(['type' => 'collaboration', 'title' => COLLABORATION_TITLE]);
if ($existing) {
  $collab = reset($existing);
  $say(($dry_run ? 'WOULD ' : 'SET   ') . "collaboration {$collab->id()} — update collaborators and outcome.");
  if (!$dry_run) {
    foreach ($values as $k => $v) {
      $collab->set($k, $v);
    }
    $collab->setNewRevision(TRUE);
    $collab->save();
  }
}
else {
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "collaboration '" . COLLABORATION_TITLE . "' with " . count($collaborator_nids) . ' collaborators.');
  if (!$dry_run) {
    $collab = Node::create(['type' => 'collaboration', 'title' => COLLABORATION_TITLE, 'uid' => 1] + $values);
    $collab->save();
    $say('      ' . $collab->toUrl()->toString());
  }
}
$say($dry_run ? 'Dry run — nothing changed.' : 'Done.');
