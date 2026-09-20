<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The automated by-topic listing page: /{topic path}/{events|organisations}.
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
  public function view(NodeInterface $node, string $listing_type): array {
    $paragraph = $this->findListingParagraph($node, $listing_type);
    if (!$paragraph) {
      // This topic's page has no listing of that kind.
      throw new NotFoundHttpException();
    }
    return [
      '#theme' => 'slnt_topic_listing',
      '#node' => $node,
      '#listing_type' => $listing_type,
      '#heading' => $this->heading($listing_type),
      '#listing' => $this->entityTypeManager()->getViewBuilder('paragraph')->view($paragraph, 'listing_page'),
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
    return $listing_type === 'events'
      ? (string) $this->t('Events')
      : (string) $this->t('Organisations & links');
  }

  /**
   * First by-topic View Display paragraph of the given type on the node.
   */
  protected function findListingParagraph(NodeInterface $node, string $listing_type): ?ParagraphInterface {
    foreach (self::VIEWS[$listing_type] as $view_id) {
      if ($found = $this->search($node, $view_id, 6)) {
        return $found;
      }
    }
    return NULL;
  }

  /**
   * Depth-first search of the node's nested paragraphs.
   */
  protected function search($entity, string $view_id, int $depth): ?ParagraphInterface {
    if ($depth <= 0) {
      return NULL;
    }
    foreach ($entity->getFields(FALSE) as $field) {
      if ($field->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
        continue;
      }
      foreach ($field->referencedEntities() as $p) {
        if ($p instanceof ParagraphInterface) {
          if ($p->bundle() === 'view_display' && !$p->get('field_view')->isEmpty()
            && $p->get('field_view')->target_id === $view_id
            && in_array($p->get('field_view')->display_id, self::DISPLAYS, TRUE)) {
            return $p;
          }
          if ($found = $this->search($p, $view_id, $depth - 1)) {
            return $found;
          }
        }
      }
    }
    return NULL;
  }

}
