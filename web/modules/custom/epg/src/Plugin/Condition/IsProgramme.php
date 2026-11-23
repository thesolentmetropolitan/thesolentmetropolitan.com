<?php

namespace Drupal\epg\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * Whether a node is a programme: an event with the Broadcast format.
 *
 * Used by the URL pattern for programme pages (/programmes/…) and
 * available to any other config that takes a condition.
 */
#[Condition(
  id: 'epg_is_programme',
  label: new TranslatableMarkup('Node is a programme (an event with the Broadcast format)'),
  context_definitions: [
    'node' => new EntityContextDefinition(data_type: 'entity:node', label: new TranslatableMarkup('Node')),
  ],
)]
class IsProgramme extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $node = $this->getContextValue('node');
    if (!$node instanceof NodeInterface) {
      return $this->isNegated();
    }
    return epg_is_programme($node) xor $this->isNegated();
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): TranslatableMarkup {
    return $this->isNegated() ? $this->t('The node is not a programme') : $this->t('The node is a programme');
  }

}
