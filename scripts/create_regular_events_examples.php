<?php

/**
 * @file
 * Drush script: two regular, multi-venue events as UNPUBLISHED drafts.
 *
 * Rob's examples for recurring events that run at several venues at once:
 *   - Andysmanclub — every Monday, 7pm, at WEA Clovelly Road (Southampton),
 *     The Royal British Legion Park Gate (Fareham), Dennis Sciama Building
 *     (University of Portsmouth) and Cosham Community Centre (Portsmouth).
 *   - Mangang — every Wednesday, 6.30pm, at The Saints Pub, Millbrook
 *     (Southampton), Hexagon Centre (Eastleigh) and Emmaus Wellbeing Hub
 *     (Winchester).
 *
 * Andysmanclub is created PUBLISHED (Rob confirmed the details on
 * 2026-10-18); Mangang stays an unpublished draft until he has checked it. Venue Location terms are created under
 * their towns (University of Portsmouth under Portsmouth). Each event gets
 * a weekly Smart Date rule with twelve months of instances, as the form
 * would; cron keeps extending them.
 *
 * Idempotent: events matched by title, terms by name.
 *
 * Usage:
 *   drush php:script scripts/create_regular_events_examples.php
 *   drush php:script scripts/create_regular_events_examples.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\smart_date_recur\Entity\SmartDateRule;
use Drupal\taxonomy\Entity\Term;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$term_storage = $etm->getStorage('taxonomy_term');

if (!\Drupal::moduleHandler()->moduleExists('smart_date_recur')) {
  $say('ABORT smart_date_recur is not enabled.');
  return;
}

const TZ = 'Europe/London';
const MONTHS_AHEAD = 12;

$topic = static function (string $name) use ($term_storage, $say): ?int {
  $found = $term_storage->loadByProperties(['vid' => 'topic', 'name' => $name]);
  if (!$found) {
    $say("WARN  topic '$name' not found.");
    return NULL;
  }
  return (int) reset($found)->id();
};
$ref = static fn(?int ...$tids) => array_map(static fn($tid) => ['target_id' => $tid], array_filter($tids));

// Location term by name under a parent (by name); creates when missing.
$location = function (string $name, ?string $parent_name) use (&$location, $term_storage, $dry_run, $say): ?int {
  $found = $term_storage->loadByProperties(['vid' => 'location', 'name' => $name]);
  if ($found) {
    return (int) reset($found)->id();
  }
  $parent_id = $parent_name ? $location($parent_name, NULL) : NULL;
  if ($parent_name && !$parent_id) {
    $say("WARN  parent location '$parent_name' not found for '$name'.");
  }
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "location '$name'" . ($parent_name ? " under $parent_name" : '') . '.');
  if ($dry_run) {
    return NULL;
  }
  $term = Term::create(['vid' => 'location', 'name' => $name, 'parent' => $parent_id ? [$parent_id] : [0]]);
  $term->save();
  return (int) $term->id();
};

// Next occurrence of a weekday at a wall-clock time, as a timestamp.
$next = static function (string $weekday, string $time): int {
  $tz = new \DateTimeZone(TZ);
  $d = new \DateTimeImmutable("$weekday $time", $tz);
  if ($d->getTimestamp() < time()) {
    $d = new \DateTimeImmutable("next $weekday $time", $tz);
  }
  return $d->getTimestamp();
};

$events = [
  [
    'title' => 'Andysmanclub',
    'status' => 1,
    'standfirst' => 'Free, peer-to-peer talking groups for men, every Monday at 7pm — walk in, no referral needed. Four groups across the Solent.',
    'weekday' => 'Monday',
    'start' => '19:00',
    'end' => '21:00',
    'byday' => 'MO',
    'venues' => [['WEA, Clovelly Road', 'Southampton'], ['The Royal British Legion, Park Gate', 'Fareham'], ['Dennis Sciama Building', 'University of Portsmouth'], ['Cosham Community Centre', 'Portsmouth']],
    'extra_locations' => [['University of Portsmouth', 'Portsmouth']],
    'url' => ['uri' => 'https://andysmanclub.co.uk/', 'title' => 'Find your group'],
    'primary' => $topic('Living / Mental Health'),
    'related' => $ref($topic('Living / Community'), $topic('Culture / Talks')),
  ],
  [
    'title' => 'Mangang',
    'status' => 0,
    'standfirst' => 'A weekly Wednesday evening get-together, 6.30pm, at three venues across the Solent.',
    'weekday' => 'Wednesday',
    'start' => '18:30',
    'end' => '20:30',
    'byday' => 'WE',
    'venues' => [['The Saints Pub, Millbrook', 'Southampton'], ['Hexagon Centre', 'Eastleigh'], ['Emmaus Wellbeing Hub', 'Winchester']],
    'extra_locations' => [],
    'url' => [],
    'primary' => $topic('Living / Community'),
    'related' => $ref($topic('Living / Mental Health')),
  ],
];

foreach ($events as $e) {
  foreach ($e['extra_locations'] as [$name, $parent]) {
    $location($name, $parent);
  }
  $where = [];
  foreach ($e['venues'] as [$name, $parent]) {
    $tid = $location($name, $parent);
    if ($tid) {
      $where[] = ['target_id' => $tid];
    }
  }

  // The organisation of the same name, when the site already has one, is
  // the organiser (Andysmanclub and Mangang both exist as Organisations).
  $organiser = $node_storage->loadByProperties(['type' => 'organisation', 'title' => $e['title']]);
  $organiser_nid = $organiser ? (int) reset($organiser)->id() : NULL;

  $existing = $node_storage->loadByProperties(['type' => 'event', 'title' => $e['title']]);
  if ($existing) {
    $event = reset($existing);
    $changes = [];
    if ($organiser_nid && $event->hasField('field_organiser') && (int) ($event->get('field_organiser')->target_id ?? 0) !== $organiser_nid) {
      $changes[] = "organiser = organisation $organiser_nid";
    }
    if ($e['status'] && !$event->isPublished()) {
      $changes[] = 'publish';
    }
    if ($changes) {
      $say(($dry_run ? 'WOULD ' : 'SET   ') . "event '{$e['title']}' ({$event->id()}): " . implode(', ', $changes) . '.');
      if (!$dry_run) {
        if ($organiser_nid && $event->hasField('field_organiser')) {
          $event->set('field_organiser', ['target_id' => $organiser_nid]);
        }
        if ($e['status']) {
          $event->setPublished();
        }
        $event->save();
      }
    }
    else {
      $say("OK    event '{$e['title']}' exists ({$event->id()}) — left as is.");
    }
    continue;
  }
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "event '{$e['title']}' (" . ($e['status'] ? 'published' : 'unpublished') . ", every {$e['weekday']} {$e['start']}, " . count($e['venues']) . ' venues).');
  if ($dry_run) {
    continue;
  }

  // The rule, as smart_date_recur_generate_rows() would build it from the
  // form: weekly, unlimited, instances for the next MONTHS_AHEAD months.
  $start = $next($e['weekday'], $e['start']);
  $end = $next($e['weekday'], $e['end']);
  if ($end < $start) {
    $end = $start + 2 * 3600;
  }
  $rule = SmartDateRule::create([
    'freq' => 'WEEKLY',
    'start' => $start,
    'end' => $end,
    'entity_type' => 'node',
    'bundle' => 'event',
    'field_name' => 'field_when',
    'parameters' => 'BYDAY=' . $e['byday'],
    'limit' => '',
    'unlimited' => TRUE,
  ]);
  $rule->setTimezone(TZ);
  $instances = $rule->getRuleInstances(strtotime('+' . MONTHS_AHEAD . ' months'));
  $rule->set('instances', ['data' => $instances]);
  $rule->save();

  $when = [];
  foreach ($instances as $index => $instance) {
    $when[] = [
      'value' => $instance['value'],
      'end_value' => $instance['end_value'],
      'duration' => (int) (($instance['end_value'] - $instance['value']) / 60),
      'rrule' => $rule->id(),
      'rrule_index' => $index,
      'timezone' => TZ,
    ];
  }

  $node = Node::create([
    'type' => 'event',
    'title' => $e['title'],
    'uid' => 1,
    'status' => $e['status'],
    'field_standfirst' => $e['standfirst'],
    'field_when' => $when,
    'field_where' => $where,
    'field_url' => $e['url'],
    'field_primary_topic' => $e['primary'] ? ['target_id' => $e['primary']] : [],
    'field_related_topics' => $e['related'],
    'field_organiser' => $organiser_nid ? ['target_id' => $organiser_nid] : [],
  ]);
  $node->save();
  $say('      ' . $node->toUrl()->toString() . ' (' . count($when) . ' instances)');
}
$say($dry_run ? 'Dry run — nothing changed.' : 'Done.');
