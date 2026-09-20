<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\PathProcessor;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Maps /{topic page path}/{events|organisations} to the listing route.
 *
 * One route serves every topic, instead of a node per topic per type.
 *
 *   /culture/music/events  →  /topic-listing/24/events
 *
 * A REAL PAGE ALWAYS WINS: if the full path is itself a path alias (an
 * editor has built a bespoke page at /culture/music/jazz/events) this
 * processor leaves it alone and the node renders as normal.
 */
class TopicListingPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  public function __construct(
    protected AliasManagerInterface $aliasManager,
    protected AliasRepositoryInterface $aliasRepository,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if (!preg_match('#^(/.+)/(' . implode('|', customsolent_helpers_listing_route_words()) . ')$#', $path, $m)) {
      return $path;
    }
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    // A real page at this exact address wins.
    if ($this->aliasRepository->lookupByAlias($path, $langcode)) {
      return $path;
    }
    $internal = $this->aliasManager->getPathByAlias($m[1], $langcode);
    if (preg_match('#^/node/(\d+)$#', $internal, $n)) {
      return '/topic-listing/' . $n[1] . '/' . $m[2];
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (preg_match('#^/topic-listing/(\d+)/([a-z]+)$#', $path, $m)) {
      $alias = $this->aliasManager->getAliasByPath('/node/' . $m[1]);
      if ($alias !== '/node/' . $m[1]) {
        return rtrim($alias, '/') . '/' . $m[2];
      }
    }
    return $path;
  }

}
