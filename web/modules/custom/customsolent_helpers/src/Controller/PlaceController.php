<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Views;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A place's page under Explore / Places & Maps:
 * /explore/places-maps/locations/gods-house-tower, …/southsea (Location
 * epic, issue 440), and the …/locations index of the tree.
 *
 * A Location term is a venue, a room in it or the area around it, and its
 * page is the same for all three: the address and a map link, the places
 * it is within and the places it contains, the site-wide events listing
 * narrowed to it (an area's page lists what is on at every venue in it,
 * a venue's page what is on in every room), and the organisations based
 * there. As on the format landing pages nothing is stored: the banner and
 * the events listing are unsaved paragraphs built on the fly, so the
 * topic sidebar, the date pills and the pager all work inside the place.
 */
class PlaceController extends ControllerBase {

  /**
   * Page callback.
   */
  public function view(string $place): array {
    $info = customsolent_helpers_place_by_slug($place);
    if (!$info) {
      throw new NotFoundHttpException();
    }
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $info['term'];
    $events_page = $this->sitewidePage('events');
    $places_page = $this->sitewidePage('places');
    if (!$events_page || !$places_page) {
      throw new NotFoundHttpException();
    }
    $request = \Drupal::request();
    $request->attributes->set('slnt_place_tid', $info['tid']);
    // The hero reads Explore / Places & Maps (small), the place (big).
    $request->attributes->set('slnt_hero_title_tag', 'h1');
    if ($places_page->hasField('field_primary_topic') && $places_page->get('field_primary_topic')->entity) {
      $request->attributes->set('slnt_hero_trail_term', $places_page->get('field_primary_topic')->entity);
    }
    $request->attributes->set('slnt_hero_trail_current', ['label' => $info['name'], 'url' => $info['url']]);

    $storage = $this->entityTypeManager()->getStorage('paragraph');
    $view_builder = $this->entityTypeManager()->getViewBuilder('paragraph');
    $hero = $storage->create([
      'type' => 'hero_with_art_style',
      'field_title' => $info['name'],
      'field_classy' => ['target_id' => 'hero_art_style_explore_maps'],
    ]);
    $events = $storage->create([
      'type' => 'view_display',
      'field_view' => [
        'target_id' => 'events_listing',
        'display_id' => 'view_display_primary_and_related',
        'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]),
      ],
      'field_listing_mode' => 'full',
    ]);
    $events->setParentEntity($events_page, 'field_content_component');

    // The organisations based here: the directory's cards, no pager (a
    // second pager on the page would fight the events one).
    $organisations = NULL;
    $organisations_count = 0;
    $view = Views::getView('organisations_listing');
    if ($view && $view->setDisplay('view_display_orgs_directories_page')) {
      $view->setItemsPerPage(0);
      $view->execute();
      $organisations_count = count($view->result);
      if ($organisations_count) {
        $organisations = $view->render();
      }
    }

    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $places = customsolent_helpers_place_terms();
    $within = [];
    $tags = Cache::mergeTags($term->getCacheTags(), $events_page->getCacheTags(), $places_page->getCacheTags(), ['node_list', 'taxonomy_term_list:location']);
    foreach ($term_storage->loadAllParents($term->id()) as $ancestor) {
      if ((int) $ancestor->id() === $info['tid']) {
        continue;
      }
      $within[] = ['label' => $ancestor->getName(), 'url' => $places[(int) $ancestor->id()]['url'] ?? NULL];
      $tags = Cache::mergeTags($tags, $ancestor->getCacheTags());
    }
    $contains = [];
    foreach ($term_storage->loadChildren($term->id()) as $child) {
      if (isset($places[(int) $child->id()])) {
        $contains[] = ['label' => $child->getName(), 'url' => $places[(int) $child->id()]['url']];
      }
    }

    return [
      '#theme' => 'slnt_place_page',
      '#place' => $term,
      '#banner' => $view_builder->view($hero),
      '#address' => $this->addressLine($term),
      '#map_url' => $this->mapUrl($term),
      '#within' => $within,
      '#contains' => $contains,
      '#events' => $view_builder->view($events, 'listing_page'),
      '#organisations' => $organisations,
      '#organisations_count' => $organisations_count,
      '#section_key' => 'explore',
      '#cache' => [
        'tags' => $tags,
        'contexts' => ['url.path', 'url.query_args'],
      ],
    ];
  }

  /**
   * Title callback: the place's name.
   */
  public function title(string $place): string {
    $info = customsolent_helpers_place_by_slug($place);
    return $info ? $info['name'] : (string) $this->t('Places');
  }

  /**
   * The …/locations index: the tree of areas and venues, each a link.
   */
  public function index(): array {
    $request = \Drupal::request();
    $request->attributes->set('slnt_hero_title_tag', 'h1');
    $places_page = $this->sitewidePage('places');
    $url = customsolent_helpers_sitewide_listing_url('places') . '/locations';
    if ($places_page && $places_page->hasField('field_primary_topic') && $places_page->get('field_primary_topic')->entity) {
      $request->attributes->set('slnt_hero_trail_term', $places_page->get('field_primary_topic')->entity);
      $request->attributes->set('slnt_hero_trail_current', ['label' => (string) $this->t('Locations'), 'url' => $url]);
    }
    $storage = $this->entityTypeManager()->getStorage('paragraph');
    $hero = $storage->create([
      'type' => 'hero_with_art_style',
      'field_title' => (string) $this->t('Locations'),
      'field_classy' => ['target_id' => 'hero_art_style_explore_maps'],
    ]);
    $places = customsolent_helpers_place_terms();
    $children = [];
    foreach ($places as $tid => $place) {
      $children[$place['parent'] ?? 0][] = $tid;
    }
    $build = function (int $parent) use (&$build, $places, $children): array {
      $items = [];
      foreach ($children[$parent] ?? [] as $tid) {
        $items[] = ['label' => $places[$tid]['name'], 'url' => $places[$tid]['url'], 'children' => $build($tid)];
      }
      return $items;
    };
    return [
      '#theme' => 'slnt_place_index',
      '#banner' => $this->entityTypeManager()->getViewBuilder('paragraph')->view($hero),
      '#tree' => $build(0),
      '#section_key' => 'explore',
      '#cache' => ['tags' => array_merge(['taxonomy_term_list:location'], $places_page ? $places_page->getCacheTags() : []), 'contexts' => ['url.path']],
    ];
  }

  /**
   * The term's own address as one line, or NULL (a country alone is none).
   */
  protected function addressLine(TermInterface $term): ?string {
    if (!$term->hasField('field_address') || $term->get('field_address')->isEmpty()) {
      return NULL;
    }
    $a = $term->get('field_address')->first();
    $street = implode(', ', array_filter([$a->get('address_line1')->getValue(), $a->get('address_line2')->getValue()]));
    $town = trim(implode(' ', array_filter([$a->get('locality')->getValue(), $a->get('postal_code')->getValue()])));
    return implode(', ', array_filter([$street, $town])) ?: NULL;
  }

  /**
   * An OpenStreetMap link to the term's point, or NULL.
   */
  protected function mapUrl(TermInterface $term): ?string {
    if (!$term->hasField('field_point') || $term->get('field_point')->isEmpty()) {
      return NULL;
    }
    $point = $term->get('field_point')->first();
    $lat = $point->get('lat')->getValue();
    $lon = $point->get('lon')->getValue();
    if ($lat === NULL || $lon === NULL) {
      return NULL;
    }
    return sprintf('https://www.openstreetmap.org/?mlat=%.6f&mlon=%.6f#map=17/%.6f/%.6f', $lat, $lon, $lat, $lon);
  }

  /**
   * The Explore landing page for a listing kind, by its alias.
   */
  protected function sitewidePage(string $kind): ?NodeInterface {
    $alias = customsolent_helpers_sitewide_listing_url($kind);
    if (!$alias) {
      return NULL;
    }
    $internal = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
    if (preg_match('#^/node/(\d+)$#', $internal, $m)) {
      $node = $this->entityTypeManager()->getStorage('node')->load($m[1]);
      return $node instanceof NodeInterface ? $node : NULL;
    }
    return NULL;
  }

}
