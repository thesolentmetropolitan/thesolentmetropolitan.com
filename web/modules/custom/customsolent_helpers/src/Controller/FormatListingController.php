<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The format landing page: /explore/events/festivals, /explore/events/talks … (issue 485).
 *
 * A format cuts across Culture, Sectors and Living, so the page is the
 * site-wide events listing narrowed to the format: the same View Display
 * paragraph the Explore → Events page uses, rendered in listing_page mode
 * with the format put on the request, so the topic sidebar, the date pills
 * and the pager all work inside the format. Nothing here is stored: the
 * banner and the listing are unsaved paragraphs built on the fly. The page
 * does one thing, list events; organisations by format are for elsewhere.
 */
class FormatListingController extends ControllerBase {

  /**
   * Hero art for the formats that were Culture topics until Nov 2026; the
   * rest share the Explore → Events art.
   */
  protected const HERO_STYLES = [
    'Festival' => 'hero_art_style_culture_festivals',
    'Talk' => 'hero_art_style_culture_talks',
    'Workshop' => 'hero_art_style_culture_workshops',
  ];

  /**
   * Page callback.
   */
  public function view(string $format): array {
    $term = customsolent_helpers_format_by_slug($format);
    if (!$term) {
      throw new NotFoundHttpException();
    }
    $events_page = $this->sitewidePage('events');
    if (!$events_page) {
      throw new NotFoundHttpException();
    }
    $request = \Drupal::request();
    $request->attributes->set('slnt_format_tid', (int) $term->id());
    // The banner's big title is this page's h1, under the Explore / Events
    // trail, so the page reads as part of Explore (the hero preprocess
    // reads these for a paragraph that is not saved).
    $request->attributes->set('slnt_hero_title_tag', 'h1');
    if ($events_page->hasField('field_primary_topic') && $events_page->get('field_primary_topic')->entity) {
      $request->attributes->set('slnt_hero_trail_term', $events_page->get('field_primary_topic')->entity);
    }

    $info = customsolent_helpers_format_terms()[(int) $term->id()];
    $request->attributes->set('slnt_hero_trail_current', ['label' => $info['plural'], 'url' => $info['url']]);
    $storage = $this->entityTypeManager()->getStorage('paragraph');
    $view_builder = $this->entityTypeManager()->getViewBuilder('paragraph');

    $hero = $storage->create([
      'type' => 'hero_with_art_style',
      'field_title' => $info['plural'],
      'field_classy' => ['target_id' => self::HERO_STYLES[$term->label()] ?? 'hero_art_style_explore_events'],
    ]);

    $events = $storage->create([
      'type' => 'view_display',
      'field_view' => $this->viewField('events_listing'),
      'field_listing_mode' => 'full',
    ]);
    $events->setParentEntity($events_page, 'field_content_component');

    return [
      '#theme' => 'slnt_format_listing',
      '#format' => $term,
      '#summary' => $info['summary'],
      '#banner' => $view_builder->view($hero),
      '#listing' => $view_builder->view($events, 'listing_page'),
      '#section_key' => 'explore',
      '#cache' => [
        'tags' => Cache::mergeTags($term->getCacheTags(), $events_page->getCacheTags(), ['node_list', 'taxonomy_term_list:format']),
        'contexts' => ['url.path', 'url.query_args'],
      ],
    ];
  }

  /**
   * Title callback: the plural ("Festivals").
   */
  public function title(string $format): string {
    $term = customsolent_helpers_format_by_slug($format);
    return $term ? customsolent_helpers_format_terms()[(int) $term->id()]['plural'] : (string) $this->t('Events');
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

  /**
   * A field_view value for the by-topic display of a listing view.
   */
  protected function viewField(string $view_id): array {
    return [
      'target_id' => $view_id,
      'display_id' => 'view_display_primary_and_related',
      'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]),
    ];
  }

}
