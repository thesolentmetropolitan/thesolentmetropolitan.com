<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The format landing page: /events/festivals, /events/talks … (issue 485).
 *
 * A format cuts across Culture, Sectors and Living, so the page is the
 * site-wide events listing narrowed to the format: the same View Display
 * paragraph the Explore → Events page uses, rendered in listing_page mode
 * with the format put on the request, so the topic sidebar, the date pills
 * and the pager all work inside the format. Below it, the organisations
 * that put this kind of thing on. Nothing here is stored: the banner, the
 * listing and the directory are unsaved paragraphs built on the fly.
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
    // The banner's big title is this page's h1 (the hero preprocess reads
    // this for a paragraph that is not saved).
    $request->attributes->set('slnt_hero_title_tag', 'h1');

    $info = customsolent_helpers_format_terms()[(int) $term->id()];
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

    $organisations = NULL;
    if ($this->countOrganisations($term) > 0) {
      $organisations = $storage->create([
        'type' => 'view_display',
        'field_view' => $this->viewField('organisations_listing'),
        'field_classy' => ['target_id' => 'directory_compact_grid'],
        'field_listing_mode' => 'full',
        'field_heading' => $this->t('Organisations that put on @plural', ['@plural' => mb_strtolower($info['plural'])]),
      ]);
      $organisations->setParentEntity($events_page, 'field_content_component');
    }

    return [
      '#theme' => 'slnt_format_listing',
      '#format' => $term,
      '#summary' => $info['summary'],
      '#banner' => $view_builder->view($hero),
      '#listing' => $view_builder->view($events, 'listing_page'),
      '#organisations' => $organisations ? $view_builder->view($organisations) : NULL,
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
   * Published organisations carrying the format.
   */
  protected function countOrganisations(TermInterface $term): int {
    return (int) \Drupal::database()->query(
      "SELECT COUNT(DISTINCT f.entity_id) FROM {node__field_format} f JOIN {node_field_data} n ON n.nid = f.entity_id WHERE n.type = 'organisation' AND n.status = 1 AND f.field_format_target_id = :t",
      [':t' => $term->id()]
    )->fetchField();
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
