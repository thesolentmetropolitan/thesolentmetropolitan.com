<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;

/**
 * Sends the bare URL prefixes of our content types to their listing pages.
 *
 * Series live at /series/<title>-<year>, collaborations at
 * /collaborations/<title>, people at /people/<name>. The prefix on its own
 * is a natural thing to type or to trim a URL back to, so /series goes to
 * /explore/series, /collaborations (and the singular) to
 * /explore/collaborations and /people to /about/team. The targets are the
 * listing pages' aliases; Url::fromUserInput() resolves them, so renaming
 * a listing page's alias keeps the redirect working as long as the alias
 * still exists.
 *
 * Permanent (301) and cacheable: the response varies only by the route.
 */
final class ListingHomeRedirectController extends ControllerBase {

  /**
   * Redirects to the listing page named in the route's `_listing` default.
   */
  public function redirectToListing(string $listing): LocalRedirectResponse {
    $url = Url::fromUserInput($listing)->setAbsolute(FALSE)->toString(TRUE);
    $response = new LocalRedirectResponse($url->getGeneratedUrl(), 301);
    $response->addCacheableDependency($url);
    $response->addCacheableDependency((new CacheableMetadata())->addCacheContexts(['route']));
    return $response;
  }

}
