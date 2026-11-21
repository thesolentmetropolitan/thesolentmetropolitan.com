<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A place's page and panel under Explore / Locations (Location epic, 440).
 *
 * /explore/locations is the map page itself (a composite page carrying the
 * Location map paragraph). /explore/locations/medbar is the same page with
 * Medbar chosen: the list marks it, the map goes to it and the panel shows
 * what is there. So a place's own address works with or without script,
 * and the map's script only has to ask for /explore/locations/medbar/panel
 * and push the address.
 */
class PlaceController extends ControllerBase {

  /**
   * The map page with a place chosen.
   */
  public function view(string $place): array {
    $info = customsolent_helpers_place_by_slug($place);
    $page = $this->mapPage();
    if (!$info || !$page) {
      throw new NotFoundHttpException();
    }
    \Drupal::request()->attributes->set('slnt_place_tid', $info['tid']);
    $build = $this->entityTypeManager()->getViewBuilder('node')->view($page, 'full');
    $build['#cache']['contexts'][] = 'url.path';
    $build['#cache']['tags'][] = 'taxonomy_term:' . $info['tid'];
    return $build;
  }

  /**
   * Title callback: the place's name.
   */
  public function title(string $place): string {
    $info = customsolent_helpers_place_by_slug($place);
    return $info ? $info['name'] : (string) $this->t('Locations');
  }

  /**
   * The panel alone, for the map's script.
   */
  public function panel(string $place): CacheableResponse {
    $info = customsolent_helpers_place_by_slug($place);
    if (!$info) {
      throw new NotFoundHttpException();
    }
    $build = customsolent_helpers_place_panel($info);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $response = new CacheableResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    return $response;
  }

  /**
   * The Explore / Locations page, by its alias.
   */
  protected function mapPage(): ?NodeInterface {
    $alias = customsolent_helpers_sitewide_listing_url('places');
    $internal = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
    if (preg_match('#^/node/(\d+)$#', $internal, $m)) {
      $node = $this->entityTypeManager()->getStorage('node')->load($m[1]);
      return $node instanceof NodeInterface ? $node : NULL;
    }
    return NULL;
  }

}
