<?php

/**
 * @file
 * Drush script: starter terms for the collaborator_type vocabulary.
 *
 * How a collaborator takes part in a collaboration. Rob's five (writer,
 * sponsor, project manager, producer, supplier) plus three that events
 * on this site keep needing: partner, venue, funder. Delete or rename in
 * the editor as the real data settles; the vocabulary also allows new
 * terms to be typed straight into the Collaborator form.
 *
 * Idempotent: matched by name.
 *
 * Usage:
 *   drush php:script scripts/create_collaborator_types.php
 *   drush php:script scripts/create_collaborator_types.php -- --dry-run
 */

use Drupal\taxonomy\Entity\Term;

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $m): void { echo $m . PHP_EOL; };
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

$terms = ['Writer', 'Sponsor', 'Project manager', 'Producer', 'Supplier', 'Partner', 'Venue', 'Funder'];
foreach ($terms as $weight => $name) {
  if ($storage->loadByProperties(['vid' => 'collaborator_type', 'name' => $name])) {
    $say("OK    '$name' exists.");
    continue;
  }
  $say(($dry_run ? 'WOULD ' : 'ADD   ') . "'$name'.");
  if (!$dry_run) {
    Term::create(['vid' => 'collaborator_type', 'name' => $name, 'weight' => $weight])->save();
  }
}
$say($dry_run ? 'Dry run — nothing changed.' : 'Done.');
