<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Render;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Lazy-builder callbacks for topic kickers on node teasers.
 *
 * The topic kicker that sits above (in) or below (from) a teaser
 * title depends on the host page's topic scope. The teaser is
 * rendered with entity render cache keyed only by node id + view
 * mode, so its HTML is shared across every page that renders it.
 * Baking the kicker into that cache would carry one page's "in
 * Technology" label onto a neighbouring page where the same content
 * should display "from Sectors / Technology" — or no kicker at all.
 *
 * These callbacks are invoked via a #lazy_builder placeholder set on
 * the teaser build in customsolent_preprocess_node. The placeholder
 * lives inside the cached teaser HTML but is resolved per request —
 * after the cache lookup — so the kicker always reflects the current
 * page's URL, regardless of which page first warmed the teaser cache.
 *
 * The page's topic scope is resolved from the URL (not from request
 * attributes), because by the time the placeholder is executed,
 * earlier per-paragraph attributes may have been overwritten by
 * later paragraphs on the same page.
 */
class KickerLazyBuilder implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['inKicker', 'fromKicker'];
  }

  /**
   * Lazy-builder for the "in" kicker (content within page scope).
   *
   * @param int $nid
   *   The node ID whose teaser the kicker belongs to.
   *
   * @return array
   *   A render array (using the in_kicker theme hook) or an empty
   *   render array when no kicker should display.
   */
  public static function inKicker(int $nid): array {
    $ctx = self::resolvePageContext();
    $node = Node::load($nid);
    if (!$node || !$node->hasField('field_primary_topic') || $node->get('field_primary_topic')->isEmpty()) {
      return self::emptyBuild($nid);
    }
    $primary_term = $node->get('field_primary_topic')->entity;
    if (!$primary_term) {
      return self::emptyBuild($nid);
    }
    $primary_tid = (int) $primary_term->id();

    // In-scope check.
    if (!$ctx['scope_ids'] || !in_array($primary_tid, $ctx['scope_ids'], TRUE)) {
      return self::emptyBuild($nid);
    }
    // Exact match — no kicker.
    if ($ctx['tid'] !== NULL && $ctx['tid'] === $primary_tid) {
      return self::emptyBuild($nid);
    }
    $items = _customsolent_build_in_kicker($primary_term, $ctx['depth']);
    if (!$items) {
      return self::emptyBuild($nid);
    }
    $top = _customsolent_topic_top_level($primary_term);
    return [
      '#theme' => 'in_kicker',
      '#show_in_kicker' => TRUE,
      '#in_kicker_items' => $items,
      '#in_kicker_section' => $top ? strtolower($top->getName()) : '',
      '#cache' => [
        'contexts' => ['url.path', 'url.query_args:topic'],
        'tags' => ['node:' . $nid],
      ],
    ];
  }

  /**
   * Lazy-builder for the "from" kicker (content outside page scope).
   */
  public static function fromKicker(int $nid): array {
    $ctx = self::resolvePageContext();
    $node = Node::load($nid);
    if (!$node || !$node->hasField('field_primary_topic') || $node->get('field_primary_topic')->isEmpty()) {
      return self::emptyBuild($nid);
    }
    $primary_term = $node->get('field_primary_topic')->entity;
    if (!$primary_term) {
      return self::emptyBuild($nid);
    }
    $primary_tid = (int) $primary_term->id();

    // "from" only when the content's primary topic is OUTSIDE the
    // page's scope. If there's no scope at all (e.g. /explore in
    // all-topics mode) we still show "from" with the full chain.
    if ($ctx['scope_ids'] && in_array($primary_tid, $ctx['scope_ids'], TRUE)) {
      return self::emptyBuild($nid);
    }
    $top = _customsolent_topic_top_level($primary_term);
    return [
      '#theme' => 'from_kicker',
      '#show_primary_kicker' => TRUE,
      '#primary_kicker_ancestors' => _customsolent_build_from_kicker($primary_term),
      '#primary_kicker_section' => $top ? strtolower($top->getName()) : '',
      '#cache' => [
        'contexts' => ['url.path', 'url.query_args:topic'],
        'tags' => ['node:' . $nid],
      ],
    ];
  }

  /**
   * Empty placeholder render with cache metadata so misses are still
   * cached per-URL (avoids reprocessing on every request).
   */
  protected static function emptyBuild(int $nid): array {
    return [
      '#cache' => [
        'contexts' => ['url.path', 'url.query_args:topic'],
        'tags' => ['node:' . $nid],
      ],
    ];
  }

  /**
   * Resolves the page's topic context from the current request URL.
   *
   * Mirrors the resolution logic used in the customsolent theme's
   * paragraph__view_display preprocess, but starts from the page
   * URL rather than the paragraph. Cached statically for the
   * request — the URL doesn't change mid-request.
   *
   * @return array{tid: ?int, depth: int, scope_ids: ?array<int>}
   *   tid: the page's resolved Topic term id (NULL in all-topics mode).
   *   depth: the term's depth (0 = top-level Topic, -1 = no term).
   *   scope_ids: term id + all descendants (NULL in all-topics mode).
   */
  protected static function resolvePageContext(): array {
    static $cache = NULL;
    if ($cache !== NULL) {
      return $cache;
    }
    $default = ['tid' => NULL, 'depth' => -1, 'scope_ids' => NULL];

    $request = \Drupal::request();
    $path_info = $request->getPathInfo();
    $alias_manager = \Drupal::service('path_alias.manager');
    $internal = $alias_manager->getPathByAlias($path_info);

    if (!preg_match('|^/node/(\d+)$|', $internal, $m)) {
      return $cache = $default;
    }
    $page_node = Node::load((int) $m[1]);
    if (!$page_node || $page_node->bundle() !== 'composite_page') {
      return $cache = $default;
    }

    // Topic anchor: a section_filter paragraph with field_topic, or
    // the page node's field_primary_topic as fallback.
    $term = NULL;
    if ($page_node->hasField('field_content')) {
      foreach ($page_node->get('field_content')->referencedEntities() as $para) {
        if ($para->bundle() === 'section_filter'
          && $para->hasField('field_topic')
          && !$para->get('field_topic')->isEmpty()) {
          $term = $para->get('field_topic')->entity;
          break;
        }
      }
    }
    if (!$term && $page_node->hasField('field_primary_topic') && !$page_node->get('field_primary_topic')->isEmpty()) {
      $term = $page_node->get('field_primary_topic')->entity;
    }
    if (!$term) {
      return $cache = $default;
    }

    // Explore / About guard — these are all-topics landing groups,
    // not section pages.
    $top = _customsolent_topic_top_level($term);
    $top_name = $top ? $top->getName() : '';
    if (in_array($top_name, ['Explore', 'About'], TRUE)) {
      return $cache = $default;
    }

    $tid = (int) $term->id();
    $ctx = [
      'tid' => $tid,
      'depth' => _customsolent_get_term_depth($term),
      'scope_ids' => _customsolent_get_term_with_descendants($tid),
    ];

    // ?topic= narrows the active scope when valid.
    $query_topic = $request->query->get('topic');
    if ($query_topic !== NULL && is_numeric($query_topic) && (int) $query_topic > 0) {
      $candidate_tid = (int) $query_topic;
      if (in_array($candidate_tid, $ctx['scope_ids'], TRUE)) {
        $candidate_term = Term::load($candidate_tid);
        if ($candidate_term) {
          $ctx = [
            'tid' => $candidate_tid,
            'depth' => _customsolent_get_term_depth($candidate_term),
            'scope_ids' => _customsolent_get_term_with_descendants($candidate_tid),
          ];
        }
      }
    }

    return $cache = $ctx;
  }

}
