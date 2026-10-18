<?php

/**
 * @file
 * Drush script: Black History Month South 2026 as the first Series.
 *
 * Creates (or updates) the seven events published on
 * https://www.blackhistorymonthsouth.com/events (read 2026-10-18), the
 * three venue Location terms that did not yet exist, and the Series node
 * "Black History Month" (1–31 October 2026) holding the events in date
 * order. Everything is published.
 *
 * Idempotent: Location terms are matched by name under Southampton, events
 * and the series by exact title. A re-run updates the fields from the data
 * below and never duplicates.
 *
 * Editorial notes for Rob:
 *   - End times: "8pm – late" gigs are stored as three hours (Smart Date
 *     needs an end); SOUL 45 had no time published, so it is all-day.
 *   - Breaking Patterns is a continuous gallery screening, 29 Sep – 19 Nov,
 *     stored as one multi-day span.
 *   - Topics are a first pass (Music / Screen primary; Heritage & History,
 *     Identity, Community, Festivals, Style, Food & Drink related).
 *
 * Usage:
 *   drush php:script scripts/create_black_history_month_2026.php
 *   drush php:script scripts/create_black_history_month_2026.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$term_storage = $etm->getStorage('taxonomy_term');

const TZ = 'Europe/London';
const SOUTHAMPTON = 'Southampton';

// ── Topics by full name ("Culture / Music") ─────────────────────────────
$topic = static function (string $name) use ($term_storage, $say): ?int {
  $found = $term_storage->loadByProperties(['vid' => 'topic', 'name' => $name]);
  if (!$found) {
    $say("WARN  topic term '$name' not found — left empty.");
    return NULL;
  }
  return (int) reset($found)->id();
};
$t_music = $topic('Culture / Music');
$t_screen = $topic('Culture / Screen');
$t_heritage = $topic('Culture / Heritage & History');
$t_identity = $topic('Culture / Identity');
$t_community = $topic('Culture / Community');
$t_festivals = $topic('Culture / Festivals');
$t_style = $topic('Culture / Style');
$t_food = $topic('Culture / Food & Drink');
$ref = static fn(?int ...$tids) => array_map(static fn($tid) => ['target_id' => $tid], array_filter($tids));

// ── Locations: venue terms under Southampton ───────────────────────────
$southampton = $term_storage->loadByProperties(['vid' => 'location', 'name' => SOUTHAMPTON]);
$southampton = $southampton ? reset($southampton) : NULL;
if (!$southampton) {
  $say('ABORT location term "Southampton" not found.');
  return;
}
$venue = static function (string $name) use ($term_storage, $southampton, $dry_run, $say): ?int {
  $found = $term_storage->loadByProperties(['vid' => 'location', 'name' => $name]);
  if ($found) {
    $say("OK    location '$name' exists.");
    return (int) reset($found)->id();
  }
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "location '$name' under " . SOUTHAMPTON . '.');
  if ($dry_run) {
    return NULL;
  }
  $term = Term::create(['vid' => 'location', 'name' => $name, 'parent' => [(int) $southampton->id()]]);
  $term->save();
  return (int) $term->id();
};
$v_jhg = $venue('John Hansard Gallery');
$v_turner = $venue('Turner Sims');
$v_good_things = $venue('Good Things');
$v_engine_rooms = $venue('Engine Rooms');
$v_mast = $venue('MAST Mayflower Studios');
$v_brook = $venue('The Brook');

// ── Smart Date helper: local wall-clock → [value, end_value, duration] ──
$when = static function (string $start, string $end): array {
  $tz = new \DateTimeZone(TZ);
  $s = new \DateTimeImmutable($start, $tz);
  $e = new \DateTimeImmutable($end, $tz);
  return [[
    'value' => $s->getTimestamp(),
    'end_value' => $e->getTimestamp(),
    'duration' => (int) round(($e->getTimestamp() - $s->getTimestamp()) / 60),
    'timezone' => TZ,
  ]];
};
// Smart Date's all-day convention on this site: 00:00 → 23:59 (duration 1439).
$all_day = static fn(string $day) => $when("$day 00:00", "$day 23:59");

// ── The events ─────────────────────────────────────────────────────────
$events = [
  [
    'title' => 'Breaking Patterns',
    'standfirst' => 'A documentary celebrating the talent and impact of Black creatives in British fashion, screening continuously in the gallery\'s Digital Array until 19 November. Free.',
    'when' => $when('2026-09-29 11:00', '2026-11-19 17:00'),
    'where' => $v_jhg,
    'url' => ['uri' => 'https://jhg.art/events/breaking-patterns/', 'title' => 'Find out more'],
    'primary' => $t_screen,
    'related' => $ref($t_style, $t_heritage, $t_identity),
  ],
  [
    'title' => 'Manding Melody: Habib Koité & Sura Susso',
    'standfirst' => 'Malian guitarist Habib Koité and Gambian kora player Sura Susso perform their collaborative album, reviving the griot tradition with kora, balafon and ngoni.',
    'when' => $when('2026-10-24 20:00', '2026-10-24 23:00'),
    'where' => $v_turner,
    'url' => ['uri' => 'https://www.turnersims.co.uk/whats-on/manding_melody-habib-koite-sura-susso/', 'title' => 'Tickets'],
    'primary' => $t_music,
    'related' => $ref($t_heritage, $t_identity),
  ],
  [
    'title' => 'SOUL 45 at Natty\'s Jerk',
    'standfirst' => 'DJs spinning original 7" vinyl — heavyweight funk, soul, reggae and hip hop — alongside Natty\'s Jamaican soul food at Good Things.',
    'when' => $all_day('2026-10-25'),
    'where' => $v_good_things,
    'url' => ['uri' => 'https://www.facebook.com/soul45djs/', 'title' => 'Find out more'],
    'primary' => $t_music,
    'related' => $ref($t_food, $t_identity),
  ],
  [
    'title' => 'Chali 2na & A-Skillz Live Band',
    'standfirst' => 'The unmistakable baritone of Jurassic 5\'s Chali 2na meets the A-Skillz Live Band: hip hop, funk, soul and breakbeats for one night at Engine Rooms. £20.',
    'when' => $when('2026-10-28 18:00', '2026-10-28 22:00'),
    'where' => $v_engine_rooms,
    'url' => ['uri' => 'https://www.engineroomssouthampton.co.uk/events/chali-2na-a-skillz-live-band', 'title' => 'Tickets'],
    'primary' => $t_music,
    'related' => $ref($t_identity, $t_heritage),
  ],
  [
    'title' => 'Black Stories Screening: Honouring Our Communities',
    'standfirst' => 'Around ten short films made on mobile phones by participants in Our Version Media\'s storytelling programme, celebrating the region\'s African, Caribbean and Black British communities. Reception from 7pm, screening at 7.50pm.',
    'when' => $when('2026-10-29 19:00', '2026-10-29 21:00'),
    'where' => $v_mast,
    'url' => ['uri' => 'https://www.mayflower.org.uk/whats-on/honouring-our-communities-2026/', 'title' => 'Find out more'],
    'primary' => $t_screen,
    'related' => $ref($t_community, $t_identity),
  ],
  [
    'title' => 'EST Gee: Bigger Than The Devil Tour',
    'standfirst' => 'Louisville rapper EST Gee brings his tour to The Brook with guests RAI2RAI and NX PANTHER, plus freestyles from The Drop MCs. Doors 7pm. Age 18+ (14+ accompanied).',
    'when' => $when('2026-10-29 19:00', '2026-10-29 23:00'),
    'where' => $v_brook,
    'url' => ['uri' => 'https://www.the-brook.com/tm-event/est-gee-bigger-than-the-devil-tour/', 'title' => 'Tickets'],
    'primary' => $t_music,
    'related' => $ref($t_identity),
  ],
  [
    'title' => 'Jazz Platform Double Bill: Genevieve Namazzi & Christ-Stéphane Boizi',
    'standfirst' => 'Emerging jazz with Tomorrow\'s Warriors: bassist Genevieve Namazzi\'s new quartet and trombonist Christ-Stéphane Boizi. Free Vibe Lounge from 7pm with Marsupial.',
    'when' => $when('2026-10-30 20:00', '2026-10-30 23:00'),
    'where' => $v_turner,
    'url' => ['uri' => 'https://www.turnersims.co.uk/whats-on/jazz-platform-genevieve-namazzi-christ-stephane-boizi/', 'title' => 'Tickets'],
    'primary' => $t_music,
    'related' => $ref($t_heritage, $t_identity),
  ],
];

$event_nids = [];
foreach ($events as $e) {
  $existing = $node_storage->loadByProperties(['type' => 'event', 'title' => $e['title']]);
  $node = $existing ? reset($existing) : NULL;
  $values = [
    'field_standfirst' => $e['standfirst'],
    'field_when' => $e['when'],
    'field_where' => $e['where'] ? ['target_id' => $e['where']] : [],
    'field_url' => $e['url'],
    'field_primary_topic' => $e['primary'] ? ['target_id' => $e['primary']] : [],
    'field_related_topics' => $e['related'],
    'status' => 1,
  ];
  if ($node) {
    $say(($dry_run ? 'WOULD ' : 'SET   ') . "event {$node->id()} '{$e['title']}' — update fields.");
    if (!$dry_run) {
      foreach ($values as $k => $v) {
        $node->set($k, $v);
      }
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Updated from blackhistorymonthsouth.com by scripts/create_black_history_month_2026.php');
      $node->save();
    }
  }
  else {
    $say(($dry_run ? 'WOULD ' : 'ADD   ') . "event '{$e['title']}'.");
    if (!$dry_run) {
      $node = Node::create(['type' => 'event', 'title' => $e['title'], 'uid' => 1] + $values);
      $node->save();
    }
  }
  if ($node) {
    $event_nids[] = (int) $node->id();
    $say('      ' . $node->toUrl()->toString());
  }
}

// ── The Series ─────────────────────────────────────────────────────────
$series_title = 'Black History Month';
$description = <<<HTML
<p>Black History Month South is Southampton's Black History Month programme — "Honouring Our Communities", celebrating our past, present and future — run by the <a href="https://www.blackhistorymonthsouth.com/">Black History Month South</a> community organisation with partner venues across the city, and sponsored this year by Westquay.</p>
<p>This series gathers the October 2026 programme as it reaches us: live music from Mali and the Gambia at Turner Sims, hip hop and jazz, a documentary on Black creatives in British fashion at John Hansard Gallery, and an evening of short films made by local people with Our Version Media. Each event links through to the venue or organiser for tickets and details.</p>
<p>Black History Month South also launched a free membership for 2026, free until 31 October. Follow them on <a href="https://www.instagram.com/bhmsouth">Instagram</a>, <a href="https://www.facebook.com/groups/BHMSouthampton">Facebook</a> and <a href="https://www.youtube.com/@bhmsouth3731">YouTube</a>.</p>
HTML;
$series_values = [
  'field_standfirst' => 'Southampton\'s Black History Month South programme for October 2026: music, film and stories honouring our communities.',
  'field_description' => ['value' => $description, 'format' => 'basic_html'],
  'field_series_start' => '2026-10-01',
  'field_series_end' => '2026-10-31',
  'field_primary_topic' => $t_heritage ? ['target_id' => $t_heritage] : [],
  'field_related_topics' => $ref($t_identity, $t_community, $t_festivals, $t_music, $t_screen),
  'field_series_items' => array_map(static fn(int $nid) => ['target_id' => $nid], $event_nids),
  'status' => 1,
];
$existing = $node_storage->loadByProperties(['type' => 'series', 'title' => $series_title]);
$series = $existing ? reset($existing) : NULL;
if ($series) {
  $say(($dry_run ? 'WOULD ' : 'SET   ') . "series {$series->id()} '$series_title' — update fields and items.");
  if (!$dry_run) {
    foreach ($series_values as $k => $v) {
      $series->set($k, $v);
    }
    $series->setNewRevision(TRUE);
    $series->save();
  }
}
else {
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "series '$series_title' with " . count($event_nids) . ' events.');
  if (!$dry_run) {
    $series = Node::create(['type' => 'series', 'title' => $series_title, 'uid' => 1] + $series_values);
    $series->save();
  }
}
if ($series) {
  $say('      ' . $series->toUrl()->toString());
}
$say($dry_run ? 'Dry run — nothing changed.' : 'Done.');
