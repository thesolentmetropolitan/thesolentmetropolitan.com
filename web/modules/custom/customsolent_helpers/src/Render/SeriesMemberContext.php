<?php

declare(strict_types=1);

namespace Drupal\customsolent_helpers\Render;

use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Marks "this card is being rendered on its series' own page".
 *
 * A series page renders each member in the compact view mode. The member's
 * preprocess (in the theme) asks which series contain it, and on the
 * series' own page leaves that series out of the "Part of the series" line
 * because the h1 already says it. The member build carries a distinct
 * cache key for this, and these two callbacks set and clear the request
 * static around the render so only that build sees the context.
 *
 * Trusted callbacks must live in a module: themes cannot register them.
 */
class SeriesMemberContext implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['enter', 'leave'];
  }

  /**
   * #pre_render: note the series id from the build's cache key.
   */
  public static function enter(array $build): array {
    foreach ($build['#cache']['keys'] ?? [] as $key) {
      if (is_string($key) && str_starts_with($key, 'in_series_')) {
        _customsolent_rendering_members_of_series((int) substr($key, strlen('in_series_')));
        break;
      }
    }
    return $build;
  }

  /**
   * #post_render: clear the context again.
   */
  public static function leave($markup, array $build) {
    _customsolent_rendering_members_of_series(0);
    return $markup instanceof Markup ? $markup : Markup::create((string) $markup);
  }

}
