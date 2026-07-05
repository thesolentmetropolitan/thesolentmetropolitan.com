<?php

/**
 * @file
 * Token definitions and replacements for Custom Solent Tokens.
 *
 * Smart Date's own tokens do not validate reliably in Pathauto's pattern form
 * on current Drupal 11 / Token versions, so we expose a dedicated, validated
 * token for the event start date that Pathauto accepts.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function customsolent_tokens_token_info() {
  $info = [];

  $info['tokens']['node']['event_date_formatted'] = [
    'name' => t('Event date formatted'),
    'description' => t('The event start date from field_when, in the site timezone. Timed events are formatted YYYY-MM-DD-HHMM (e.g. 2026-05-24-1930); all-day events omit the time (e.g. 2026-05-24). Only available on nodes with a field_when value; empty otherwise.'),
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function customsolent_tokens_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'node' && !empty($data['node'])) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $data['node'];

    foreach ($tokens as $name => $original) {
      if ($name !== 'event_date_formatted') {
        continue;
      }

      // Default to an empty string so Pathauto simply omits the segment when a
      // node has no start date (e.g. a non-event, or an event missing a date).
      $replacements[$original] = '';

      if ($node->hasField('field_when') && !$node->get('field_when')->isEmpty()) {
        // Smart Date stores the start and end as Unix timestamps in the
        // 'value' and 'end_value' properties (see
        // \Drupal\smart_date\Plugin\Field\FieldType\SmartDateItem).
        $item = $node->get('field_when')->first();
        $start = $item->value;
        if ($start !== NULL && $start !== '') {
          // Format in the site's default timezone so the alias is identical
          // regardless of which user (or cron) triggers generation.
          $timezone = \Drupal::config('system.date')->get('timezone.default')
            ?: date_default_timezone_get();
          $formatter = \Drupal::service('date.formatter');

          // All-day events (start 00:00, end 23:59 in the display timezone —
          // Smart Date's own definition, see SmartDateTrait::isAllDay()) omit
          // the time; timed events append it as -HHMM.
          $end = $item->end_value;
          $is_all_day = ($end !== NULL && $end !== '')
            && $formatter->format((int) $start, 'custom', 'H:i', $timezone) === '00:00'
            && $formatter->format((int) $end, 'custom', 'H:i', $timezone) === '23:59';

          $replacements[$original] = $formatter
            ->format((int) $start, 'custom', $is_all_day ? 'Y-m-d' : 'Y-m-d-Hi', $timezone);
          $bubbleable_metadata->addCacheableDependency($node);
        }
      }
    }
  }

  return $replacements;
}
