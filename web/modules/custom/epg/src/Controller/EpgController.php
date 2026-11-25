<?php

namespace Drupal\epg\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The programme guide: what is on the Solent's radio and TV stations.
 *
 * One page, one DOM, three ways of seeing it (the television-style
 * timeline, the vertical columns, the plain list), chosen with ?view= and
 * switched in page by the script; the list is also what a reader with no
 * script or a screen reader gets. ?day=YYYY-MM-DD picks the day, today by
 * default. The guide day runs from 6am to 6am, as television guides do.
 */
class EpgController extends ControllerBase {

  /**
   * The guide page.
   */
  public function guide(Request $request): array {
    $tz = new \DateTimeZone(\Drupal::config('system.date')->get('timezone.default') ?: 'Europe/London');
    $today = epg_today($tz);
    $day = $today;
    $param = (string) $request->query->get('day', '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $param)) {
      try {
        $day = new \DateTimeImmutable($param, $tz);
      }
      catch (\Exception) {
        $day = $today;
      }
    }
    $view = (string) $request->query->get('view', '');
    if (!in_array($view, ['timeline', 'vertical', 'list'], TRUE)) {
      $view = 'timeline';
    }
    $schedule = epg_schedule($day);
    // The heading names the calendar day the reader is in: after midnight
    // the broadcast day is still yesterday's (the strip runs on to 6am),
    // but the page says Wednesday.
    $is_today = $day->format('Y-m-d') === $today->format('Y-m-d');
    $now = (new \DateTimeImmutable('@' . \Drupal::time()->getRequestTime()))->setTimezone($tz);
    $heading_day = ($is_today && (int) $now->format('G') < EPG_WINDOW_START_HOUR) ? $now : $day;
    $next_midnight = $now->setTime(0, 0)->modify('+1 day')->getTimestamp();
    $build = [
      '#theme' => 'epg_guide',
      '#day' => $day->format('Y-m-d'),
      '#day_label' => $heading_day->format('l j F'),
      '#day_note' => $heading_day != $day ? $this->t('Early hours, from @day\'s schedule', ['@day' => $day->format('l')]) : '',
      '#is_today' => $is_today,
      '#days' => $this->dayLinks($today, $day, $view),
      '#views' => $this->viewLinks($day, $view),
      '#view' => $view,
      '#window_start' => $schedule['window_start'],
      '#window_end' => $schedule['window_end'],
      '#hours' => $schedule['hours'],
      '#stations' => $schedule['stations'],
      '#empty' => !$schedule['stations'],
      '#attached' => ['library' => ['epg/guide']],
      '#cache' => [
        'contexts' => ['url.query_args:day', 'url.query_args:view'],
        'tags' => $schedule['tags'],
        'max-age' => max(60, min($schedule['max_age'], $next_midnight - $now->getTimestamp())),
      ],
    ];
    return $build;
  }

  /**
   * Seven days from today, each a link keeping the chosen view.
   */
  protected function dayLinks(\DateTimeImmutable $today, \DateTimeImmutable $chosen, string $view): array {
    $links = [];
    for ($i = 0; $i < 7; $i++) {
      $d = $today->modify("+$i day");
      $links[] = [
        'label' => $i === 0 ? $this->t('Today') : ($i === 1 ? $this->t('Tomorrow') : $d->format('D j M')),
        'url' => $this->url($d->format('Y-m-d'), $view),
        'current' => $d->format('Y-m-d') === $chosen->format('Y-m-d'),
      ];
    }
    return $links;
  }

  /**
   * The three views, each a link keeping the chosen day.
   */
  protected function viewLinks(\DateTimeImmutable $day, string $view): array {
    $links = [];
    foreach (['timeline' => $this->t('Timeline'), 'vertical' => $this->t('Columns'), 'list' => $this->t('List')] as $key => $label) {
      $links[] = ['key' => $key, 'label' => $label, 'url' => $this->url($day->format('Y-m-d'), $key), 'current' => $key === $view];
    }
    return $links;
  }

  /**
   * The guide's own URL for a day and a view (today and the timeline are
   * the defaults and are left out of the address).
   */
  protected function url(string $day, string $view): string {
    $query = [];
    $tz = new \DateTimeZone(\Drupal::config('system.date')->get('timezone.default') ?: 'Europe/London');
    if ($day !== epg_today($tz)->format('Y-m-d')) {
      $query['day'] = $day;
    }
    if ($view !== 'timeline') {
      $query['view'] = $view;
    }
    return \Drupal\Core\Url::fromRoute('epg.guide', [], ['query' => $query])->toString();
  }

}
