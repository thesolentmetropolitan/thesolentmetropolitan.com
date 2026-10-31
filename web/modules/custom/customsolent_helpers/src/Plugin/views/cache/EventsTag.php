<?php

namespace Drupal\customsolent_helpers\Plugin\views\cache;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsCache;
use Drupal\views\Plugin\views\cache\Tag;

/**
 * Tag caching for the events listings, expiring when a regular event moves on.
 *
 * The events listings keep one row per regular (recurring) event: the
 * instance that has not yet ended, chosen in the SQL against the database
 * clock (see _customsolent_helpers_one_row_per_recurring_event()). The
 * "Tag based" plugin would keep those query results until an event is
 * saved, so a regular event whose instance finished at 7pm would sit in
 * the listing at 7pm all evening while its card already said "Next: next
 * Thursday". This plugin caches the same way but expires the results at
 * the first moment they can change: when the earliest listed instance of
 * a regular event ends, or at midnight, whichever comes first. The cards'
 * own max-age (customsolent.theme) expires the rendered output at the same
 * moment, so the view re-runs its query then and the order moves on with
 * the "Next" date.
 *
 * @ingroup views_cache_plugins
 */
#[ViewsCache(
  id: 'customsolent_events_tag',
  title: new TranslatableMarkup('Tag based, events (expires as regular events move on)'),
  help: new TranslatableMarkup('Tag based caching whose query results also expire when the earliest listed event (or instance of a regular event) ends, or at midnight.'),
)]
class EventsTag extends Tag {

  /**
   * {@inheritdoc}
   */
  protected function cacheSetMaxAge($type) {
    if ($type !== 'results') {
      return parent::cacheSetMaxAge($type);
    }
    $now = \Drupal::time()->getRequestTime();
    $timezone = new \DateTimeZone(date_default_timezone_get());
    $expire = (new \DateTimeImmutable('@' . $now))->setTimezone($timezone)->modify('tomorrow')->getTimestamp();
    foreach ($this->view->result as $row) {
      // The columns are added to the query by the events query alter; a
      // row without them (another use of this plugin) just expires at
      // midnight. Any listed event, regular or one-off, drops out when its
      // end passes, except one entered without an end time (end = start),
      // which stays until midnight.
      if (!empty($row->slnt_when_end) && (int) $row->slnt_when_end > $now
        && (empty($row->slnt_when_start) || (int) $row->slnt_when_end > (int) $row->slnt_when_start)) {
        $expire = min($expire, (int) $row->slnt_when_end + 1);
      }
    }
    return max(1, $expire - $now);
  }

}
