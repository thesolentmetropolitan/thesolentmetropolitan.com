<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Answers "what is this content part of?" across the site's container types.
 *
 * A Series holds its members in field_series_items (events, articles,
 * links); a Collaboration (later) holds its results in field_outcomes. The
 * member nodes know nothing about their containers, so cards and full pages
 * ask this service instead. Every answer is a plain array of published
 * nodes plus the cache tags a render array must carry so that saving a
 * container refreshes the cards of its members:
 *
 *   - node_list:series — Drupal's per-bundle list tag, invalidated whenever
 *     ANY series is saved or deleted. Coarse but safe: series are edited
 *     rarely, event cards are many.
 *   - each found container's own tag (node:NID).
 *
 * Status words ("Now on", "Past series") depend on today's date, so render
 * arrays that print them also take maxAgeUntilMidnight().
 */
class ContentRelations {

  public const STATUS_UPCOMING = 'upcoming';
  public const STATUS_CURRENT = 'current';
  public const STATUS_ONGOING = 'ongoing';
  public const STATUS_PAST = 'past';

  /**
   * Per-request memo: node id => series nodes.
   *
   * @var array<int, \Drupal\node\NodeInterface[]>
   */
  private array $seriesByMember = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Published series whose field_series_items reference $node.
   *
   * Ordered by series start date, earliest first.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function seriesContaining(NodeInterface $node): array {
    $nid = (int) $node->id();
    if ($nid === 0) {
      return [];
    }
    if (!isset($this->seriesByMember[$nid])) {
      $storage = $this->entityTypeManager->getStorage('node');
      $ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'series')
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('field_series_items.target_id', $nid)
        ->sort('field_series_start.value', 'ASC')
        ->sort('title', 'ASC')
        ->execute();
      $this->seriesByMember[$nid] = $ids ? array_values($storage->loadMultiple($ids)) : [];
    }
    return $this->seriesByMember[$nid];
  }

  /**
   * Cache tags for anything rendered from seriesContaining().
   *
   * @param \Drupal\node\NodeInterface[] $series
   *   The series found (may be empty: the tag set must still invalidate an
   *   "is in no series" result when a series is created).
   *
   * @return string[]
   */
  public function seriesCacheTags(array $series): array {
    $tags = ['node_list:series'];
    foreach ($series as $s) {
      $tags = Cache::mergeTags($tags, $s->getCacheTags());
    }
    return $tags;
  }

  /**
   * Where a series sits relative to today: upcoming, current, ongoing, past.
   *
   * "Ongoing" is a series with a start date but no end date. The comparison
   * is by calendar day in the site's timezone: a series ending on the 31st
   * is still current for the whole of the 31st.
   */
  public function seriesStatus(NodeInterface $series): string {
    [$start, $end] = $this->seriesDates($series);
    $today = $this->today();
    if ($start !== NULL && $start > $today) {
      return self::STATUS_UPCOMING;
    }
    if ($end === NULL) {
      return self::STATUS_ONGOING;
    }
    if ($end < $today) {
      return self::STATUS_PAST;
    }
    return self::STATUS_CURRENT;
  }

  /**
   * The reader-facing word for a status.
   */
  public function statusLabel(string $status): string {
    return match ($status) {
      self::STATUS_UPCOMING => (string) t('Coming up'),
      self::STATUS_CURRENT => (string) t('Now on'),
      self::STATUS_ONGOING => (string) t('Ongoing'),
      default => (string) t('Past series'),
    };
  }

  /**
   * The series' date range as one readable phrase.
   *
   * "1–31 October 2026", "29 September – 19 November 2026",
   * "1 October 2026 – 31 March 2027", "14 October 2026" for a single day,
   * or "From 1 October 2026" when the series has no end date.
   */
  public function seriesDateRange(NodeInterface $series): string {
    [$start, $end] = $this->seriesDates($series);
    if ($start === NULL) {
      return '';
    }
    $s = new \DateTimeImmutable($start);
    if ($end === NULL) {
      return (string) t('From @date', ['@date' => $s->format('j F Y')]);
    }
    $e = new \DateTimeImmutable($end);
    if ($start === $end) {
      return $s->format('j F Y');
    }
    // U+2013 en dash, no spaces when only the day differs.
    if ($s->format('Y-m') === $e->format('Y-m')) {
      return $s->format('j') . '–' . $e->format('j F Y');
    }
    if ($s->format('Y') === $e->format('Y')) {
      return $s->format('j F') . ' – ' . $e->format('j F Y');
    }
    return $s->format('j F Y') . ' – ' . $e->format('j F Y');
  }

  /**
   * ISO 8601 start and end for the <time> element: [start, end|NULL].
   *
   * @return array{0: ?string, 1: ?string}
   */
  public function seriesDates(NodeInterface $series): array {
    $start = $series->hasField('field_series_start') && !$series->get('field_series_start')->isEmpty()
      ? substr((string) $series->get('field_series_start')->value, 0, 10) : NULL;
    $end = $series->hasField('field_series_end') && !$series->get('field_series_end')->isEmpty()
      ? substr((string) $series->get('field_series_end')->value, 0, 10) : NULL;
    return [$start ?: NULL, $end ?: NULL];
  }

  /**
   * How many published items of each kind the series holds.
   *
   * @return array{event: int, article: int, link: int}
   */
  public function seriesCounts(NodeInterface $series): array {
    $counts = ['event' => 0, 'article' => 0, 'link' => 0];
    foreach ($this->seriesMembers($series) as $member) {
      if (isset($counts[$member->bundle()])) {
        $counts[$member->bundle()]++;
      }
    }
    return $counts;
  }

  /**
   * The series' published, viewable members in editor order.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function seriesMembers(NodeInterface $series): array {
    if (!$series->hasField('field_series_items')) {
      return [];
    }
    $members = [];
    foreach ($series->get('field_series_items')->referencedEntities() as $member) {
      if ($member instanceof NodeInterface && $member->isPublished() && $member->access('view')) {
        $members[] = $member;
      }
    }
    return $members;
  }

  /**
   * Published articles whose field_author references $person, newest first.
   *
   * Render arrays built from these must carry articlesByCacheTags().
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function articlesBy(NodeInterface $person): array {
    $nid = (int) $person->id();
    if ($nid === 0) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_author.target_id', $nid)
      ->sort('created', 'DESC')
      ->execute();
    return $ids ? array_values($storage->loadMultiple($ids)) : [];
  }

  /**
   * Cache tags for anything rendered from articlesBy().
   *
   * @return string[]
   */
  public function articlesByCacheTags(): array {
    return ['node_list:article'];
  }

  /**
   * Published collaborations a node is an outcome of, directly or via series.
   *
   * Direct: the node is in a collaboration's field_outcomes. Via series: the
   * node is in a series (field_series_items) that is itself an outcome. The
   * result is unique by id and ordered newest collaboration first.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function collaborationsFor(NodeInterface $node): array {
    $nid = (int) $node->id();
    if ($nid === 0) {
      return [];
    }
    $targets = [$nid];
    if ($node->bundle() !== 'series') {
      foreach ($this->seriesContaining($node) as $series) {
        $targets[] = (int) $series->id();
      }
    }
    return $this->loadCollaborations(
      $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'collaboration')
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('field_outcomes.target_id', $targets, 'IN')
    );
  }

  /**
   * Published collaborations an organisation or person takes part in.
   *
   * Collaborator nodes reference the organisation/person in
   * field_collaborator; collaborations reference collaborators in
   * field_collaborators.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function collaborationsInvolving(NodeInterface $who): array {
    $nid = (int) $who->id();
    if ($nid === 0) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $collaborator_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'collaborator')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_collaborator.target_id', $nid)
      ->execute();
    if (!$collaborator_ids) {
      return [];
    }
    return $this->loadCollaborations(
      $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'collaboration')
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('field_collaborators.target_id', array_values($collaborator_ids), 'IN')
    );
  }

  /**
   * Cache tags for anything rendered from the collaboration lookups.
   *
   * @param \Drupal\node\NodeInterface[] $collaborations
   *   The collaborations found (may be empty).
   *
   * @return string[]
   */
  public function collaborationsCacheTags(array $collaborations): array {
    // Series membership feeds collaborationsFor(); collaborator nodes feed
    // collaborationsInvolving(); both must refresh the result.
    $tags = ['node_list:collaboration', 'node_list:collaborator', 'node_list:series'];
    foreach ($collaborations as $c) {
      $tags = Cache::mergeTags($tags, $c->getCacheTags());
    }
    return $tags;
  }

  /**
   * Runs a collaboration query, newest first, unique.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function loadCollaborations(\Drupal\Core\Entity\Query\QueryInterface $query): array {
    $ids = $query->sort('created', 'DESC')->sort('title', 'ASC')->execute();
    if (!$ids) {
      return [];
    }
    return array_values($this->entityTypeManager->getStorage('node')->loadMultiple(array_unique($ids)));
  }

  /**
   * Seconds until the next local midnight: the status word may change then.
   */
  public function maxAgeUntilMidnight(): int {
    $now = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($this->timezone());
    $midnight = $now->modify('tomorrow');
    return max(60, $midnight->getTimestamp() - $now->getTimestamp());
  }

  /**
   * Today's date, Y-m-d, in the site timezone.
   */
  private function today(): string {
    return (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->setTimezone($this->timezone())
      ->format('Y-m-d');
  }

  private function timezone(): \DateTimeZone {
    return new \DateTimeZone(date_default_timezone_get() ?: 'Europe/London');
  }

}
