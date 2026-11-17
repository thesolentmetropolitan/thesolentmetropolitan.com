<?php

namespace Drupal\customsolent_helpers\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\views\Plugin\EntityReferenceSelection\ViewsSelection;

/**
 * The core Views selection handler, able to accept newly created entities.
 *
 * Core's "views" handler does not implement SelectionWithAutocreateInterface,
 * so an entity created inline (Inline Entity Form's "Add new") against a
 * field that uses a View for its autocomplete fails validation with "This
 * entity cannot be referenced". This subclass, swapped in for the "views"
 * plugin by customsolent_helpers_entity_reference_selection_alter(), accepts
 * a new entity when its bundle is one of the handler's target bundles (or
 * any bundle when none are set), as the default handler does.
 *
 * Not a plugin of its own: no annotation, so discovery does not list it.
 */
class ViewsSelectionWithNew extends ViewsSelection implements SelectionWithAutocreateInterface {

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $values = [$entity_type->getKey('label') => $label];
    if ($bundle_key = $entity_type->getKey('bundle')) {
      $values[$bundle_key] = $bundle;
    }
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($values);
    if ($entity instanceof \Drupal\user\EntityOwnerInterface) {
      $entity->setOwnerId($uid);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $target_bundles = $this->getConfiguration()['target_bundles'] ?? NULL;
    return array_filter($entities, function ($entity) use ($target_bundles) {
      return $target_bundles === NULL || in_array($entity->bundle(), (array) $target_bundles, TRUE);
    });
  }

}
