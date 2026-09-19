<?php

/**
 * @file
 * Drush script: deepen the Living and Explore colour terms for WCAG AA.
 *
 * The "color" vocabulary holds the section colours editors pick for
 * call-to-action backgrounds etc. Living (#059669) and Explore
 * (#D97706) failed AA with white text (3.8:1 and 3.2:1). This sets them
 * to the same values as _customsolent_topic_section_colors() in
 * customsolent.theme:
 *
 *   Living   #047857   white 5.5:1, on page background 5.2:1
 *   Explore  #BC4A08   white 5.1:1, on page background 4.8:1
 *
 * Terms are content, so this is the deployable artefact (the values
 * are also recorded in config/sync/structure_sync.data.yml). Idempotent:
 * a term already at the target value is left alone. Matches on
 * vocabulary + name, not tid.
 *
 * Usage:
 *   drush php:script scripts/update_section_colour_terms.php
 *   drush php:script scripts/update_section_colour_terms.php -- --dry-run
 */

$dry_run = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));

$targets = [
  'Living' => '#047857',
  'Explore' => '#BC4A08',
];

$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

foreach ($targets as $name => $hex) {
  $terms = $storage->loadByProperties(['vid' => 'color', 'name' => $name]);
  if (!$terms) {
    echo "SKIP  $name: no term of that name in the color vocabulary." . PHP_EOL;
    continue;
  }
  foreach ($terms as $term) {
    $current = strtoupper((string) $term->get('field_color')->color);
    if ($current === strtoupper($hex)) {
      echo "OK    $name (tid {$term->id()}) already $hex." . PHP_EOL;
      continue;
    }
    echo ($dry_run ? 'WOULD ' : 'SET   ') . "$name (tid {$term->id()}): $current -> $hex" . PHP_EOL;
    if (!$dry_run) {
      $opacity = $term->get('field_color')->opacity ?? 1;
      $term->set('field_color', ['color' => $hex, 'opacity' => $opacity]);
      $term->save();
    }
  }
}

echo ($dry_run ? 'DRY RUN — no changes made.' : 'Done. Run drush cr if pages still show the old colour.') . PHP_EOL;
