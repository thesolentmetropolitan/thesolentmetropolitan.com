<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gives a page with a finite lifetime an Expires header to match.
 *
 * Event cards and listings carry a max-age: a card expires when its
 * event (or the next instance of a regular event) ends, a listing at the
 * earliest of those moments or at midnight. The render cache and the
 * Dynamic Page Cache honour that. The Internal Page Cache, which serves
 * anonymous readers, does not: by design it keeps a page until a cache
 * tag invalidates it and ignores max-age (core PageCache::storeResponse),
 * unless the response carries an Expires header in the future, which it
 * then uses as the entry's expiry. Core sets Expires to 1978 on every
 * page, so without this an event that ended at 9.30pm stayed on the front
 * page for anonymous readers until something unrelated was saved (private
 * issue 17, 14 Nov 2026).
 *
 * Runs after core's FinishResponseSubscriber (priority 0), which is what
 * writes the 1978 header. Cache-Control is left as core set it
 * ("no-cache, must-revalidate, private"), and HTTP clients give that
 * precedence over Expires, so browsers and proxies behave as before; only
 * Drupal's own page cache reads the new date.
 */
final class PageExpirySubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onRespond', -50]];
  }

  /**
   * Sets Expires to now + max-age on a cacheable response with a max-age.
   */
  public function onRespond(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }
    $max_age = $response->getCacheableMetadata()->getCacheMaxAge();
    if ($max_age === Cache::PERMANENT || $max_age <= 0) {
      return;
    }
    $expires = (new \DateTimeImmutable('@' . ($this->time->getRequestTime() + $max_age)))
      ->setTimezone(new \DateTimeZone('UTC'));
    $response->setExpires($expires);
  }

}
