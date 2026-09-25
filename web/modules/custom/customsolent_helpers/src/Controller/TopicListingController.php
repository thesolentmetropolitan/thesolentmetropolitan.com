<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The automated by-topic listing page: /{topic path}/{events|articles|organisations}.
 *
 * It does not build its own list. It finds the View Display paragraph of
 * that type on the topic's own page and renders THAT paragraph in the
 * "listing_page" paragraph view mode, which makes it show the full listing
 * — topic filter, one pager — instead of the 8-card preview. So a section
 * page and its listing page resolve their topic scope through exactly the
 * same code and cannot disagree about what belongs to the topic.
 */
class TopicListingController extends ControllerBase {

  /**
   * Listing views by URL word, in order of preference.
   */
  protected const VIEWS = [
    'events' => ['events_listing'],
    'articles' => ['articles_listing'],
    'organisations' => ['organisations_listing', 'links_listing'],
  ];

  protected const DISPLAYS = [
    'view_display_primary_and_related',
    'view_display_primary_topic',
    'view_display_related_topics',
  ];

  /**
   * Page callback.
   */
  public function view(NodeInterface $node, string $listing_type): array|LocalRedirectResponse {
    if ($node->bundle() !== 'composite_page' || !$node->hasField('field_primary_topic') || $node->get('field_primary_topic')->isEmpty()) {
      throw new NotFoundHttpException();
    }
    $paragraph = $this->findListingParagraph($node, $listing_type);
    // When the page's ONLY block of this kind is "Preview, all topics"
    // (Writing's articles before it had any of its own), that block is a
    // window on the whole site and its "View more" already points at the
    // site-wide page. This page would show only the topic's own items —
    // contradicting it — so hand over. Temporary, not permanent: the mode
    // is an editor choice that can change back. A page that also places a
    // topic-scoped block never gets here: findListingParagraph() prefers it.
    if ($paragraph && self::isAllTopics($paragraph)
      && ($sitewide = customsolent_helpers_sitewide_listing_url($listing_type))) {
      $response = new LocalRedirectResponse($sitewide, 302);
      $response->addCacheableDependency($node);
      return $response;
    }
    if (!$paragraph) {
      // The section page has no listing of this kind placed on it. Every
      // topic still has its listing pages (the section strip links to them
      // whenever the topic holds something), so render the same listing a
      // placed paragraph would give — from an unsaved paragraph that
      // belongs to this node, so the usual scope logic applies to it.
      $paragraph = $this->entityTypeManager()->getStorage('paragraph')->create([
        'type' => 'view_display',
        'field_view' => [
          'target_id' => self::VIEWS[$listing_type][0],
          'display_id' => self::DISPLAYS[0],
          'data' => serialize(['offset' => NULL, 'pager' => NULL, 'limit' => NULL, 'header' => NULL, 'title' => NULL, 'argument' => NULL]),
        ],
      ]);
      $paragraph->setParentEntity($node, 'field_content_component');
    }
    $view_builder = $this->entityTypeManager()->getViewBuilder('paragraph');

    // The section's own banner, so a listing page reads as part of the
    // section. Rendered in listing_page mode: same look, but the topic
    // name is not an h1 here — this page's h1 is "Events".
    $banner = NULL;
    $first = $node->get('field_content_component')->first();
    if ($first && $first->entity && $first->entity->bundle() === 'hero_with_art_style') {
      $banner = $view_builder->view($first->entity, 'listing_page');
    }

    return [
      '#theme' => 'slnt_topic_listing',
      '#node' => $node,
      '#listing_type' => $listing_type,
      '#heading' => $this->heading($listing_type),
      '#banner' => $banner,
      '#listing' => $view_builder->view($paragraph, 'listing_page'),
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => ['url.path', 'url.query_args'],
      ],
    ];
  }

  /**
   * Title callback: "Music: Events" in the browser tab and search results.
   */
  public function title(NodeInterface $node, string $listing_type): string {
    return $node->label() . ': ' . $this->heading($listing_type);
  }

  /**
   * The visible h1.
   */
  protected function heading(string $listing_type): string {
    return match ($listing_type) {
      'events' => (string) $this->t('Events'),
      'articles' => (string) $this->t('Articles'),
      default => (string) $this->t('Organisations & links'),
    };
  }

  /**
   * First by-topic View Display paragraph of the given type on the node.
   */
  protected function findListingParagraph(NodeInterface $node, string $listing_type): ?ParagraphInterface {
    $all_topics = NULL;
    foreach (self::VIEWS[$listing_type] as $view_id) {
      foreach ($this->searchAll($node, $view_id, 6) as $found) {
        if (!self::isAllTopics($found)) {
          return $found;
        }
        $all_topics ??= $found;
      }
    }
    return $all_topics;
  }

  /**
   * TRUE for a block in the "Preview, all topics" listing mode.
   */
  protected static function isAllTopics(ParagraphInterface $paragraph): bool {
    return $paragraph->hasField('field_listing_mode')
      && $paragraph->get('field_listing_mode')->value === 'preview_all';
  }

  /**
   * Every View Display paragraph for the view on the node, in page order.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   */
  protected function searchAll($entity, string $view_id, int $depth): array {
    if ($depth <= 0) {
      return [];
    }
    $found = [];
    foreach ($entity->getFields(FALSE) as $field) {
      if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
        continue;
      }
      foreach ($field->referencedEntities() as $paragraph) {
        if (!$paragraph instanceof ParagraphInterface) {
          continue;
        }
        if ($paragraph->bundle() === 'view_display' && !$paragraph->get('field_view')->isEmpty()
          && $paragraph->get('field_view')->target_id === $view_id
          && in_array($paragraph->get('field_view')->display_id, self::DISPLAYS, TRUE)) {
          $found[] = $paragraph;
        }
        $found = array_merge($found, $this->searchAll($paragraph, $view_id, $depth - 1));
      }
    }
    return $found;
  }

}
