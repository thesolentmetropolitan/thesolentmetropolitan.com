<?php

declare(strict_types=1);

/**
 * @file
 * Theme settings form for slnt theme.
 */

use Drupal\Core\Form\FormState;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function slnt_form_system_theme_settings_alter(array &$form, FormState $form_state): void {

  $form['slnt'] = [
    '#type' => 'details',
    '#title' => t('slnt'),
    '#open' => TRUE,
  ];

  $form['slnt']['example'] = [
    '#type' => 'textfield',
    '#title' => t('Example'),
    '#default_value' => theme_get_setting('example'),
  ];

}
