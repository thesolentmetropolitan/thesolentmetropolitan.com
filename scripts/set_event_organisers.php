<?php

/**
 * @file
 * Drush script: set the Organiser on events whose organiser is known.
 *
 * A small, explicit map of event node id => organisation node id, checked
 * by title so a wrong environment cannot mis-assign. Extend the map as
 * organisers are confirmed; re-running only changes events whose organiser
 * differs.
 *
 * Usage:
 *   drush php:script scripts/set_event_organisers.php
 *   drush php:script scripts/set_event_organisers.php -- --dry-run
 */

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$storage = \Drupal::entityTypeManager()->getStorage('node');

// event nid => [expected event title fragment, organisation nid, expected organisation title].
$map = [
  776 => ['Nature, walks, plant growing, clean-ups', 621, 'Common Ground Regen CIC'],
  777 => ['Nature, walks, plant growing, clean-ups', 621, 'Common Ground Regen CIC'],
];

foreach ($map as $event_nid => [$title_fragment, $org_nid, $org_title]) {
  $event = $storage->load($event_nid);
  $org = $storage->load($org_nid);
  if (!$event || $event->bundle() !== 'event' || !str_contains($event->label(), $title_fragment)) {
    $say("WARN  node $event_nid is not the expected event ('$title_fragment') — skipped.");
    continue;
  }
  if (!$org || $org->bundle() !== 'organisation' || $org->label() !== $org_title) {
    $say("WARN  node $org_nid is not the organisation '$org_title' — skipped.");
    continue;
  }
  if (!$event->hasField('field_organiser')) {
    $say('ABORT events have no field_organiser yet — import configuration first.');
    return;
  }
  if ((int) ($event->get('field_organiser')->target_id ?? 0) === $org_nid) {
    $say("OK    event $event_nid already organised by $org_title.");
    continue;
  }
  $say(($dry_run ? 'WOULD ' : 'SET   ') . "event $event_nid '{$event->label()}': organiser = $org_title.");
  if (!$dry_run) {
    $event->set('field_organiser', ['target_id' => $org_nid]);
    $event->setNewRevision(TRUE);
    $event->setRevisionLogMessage('Organiser set by scripts/set_event_organisers.php');
    $event->save();
  }
}
$say($dry_run ? 'Dry run — nothing changed.' : 'Done.');
