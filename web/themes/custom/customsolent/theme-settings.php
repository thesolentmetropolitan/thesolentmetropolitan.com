<?php

declare(strict_types=1);

/**
 * @file
 * Theme settings form for customsolent theme.
 */

use Drupal\Core\Form\FormState;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function customsolent_form_system_theme_settings_alter(array &$form, FormState $form_state): void {

  $form['customsolent'] = [
    '#type' => 'details',
    '#title' => t('customsolent'),
    '#open' => TRUE,
  ];

  $form['customsolent']['example'] = [
    '#type' => 'textfield',
    '#title' => t('Example'),
    '#default_value' => theme_get_setting('example'),
  ];

}
