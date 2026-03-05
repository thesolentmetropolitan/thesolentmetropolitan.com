<?php

/**
 * @file
 * Drush script to import Classy Paragraphs config for hero banner styles.
 *
 * Usage: drush php:script drush_import_classy_paragraphs.php
 *
 * This script reads the YAML config files from config/sync/ and imports
 * them as classy_paragraphs_style config entities.
 */

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;

$config_path = DRUPAL_ROOT . '/../config/sync';
$file_storage = new FileStorage($config_path);
$config_factory = \Drupal::configFactory();

$prefix = 'classy_paragraphs.classy_paragraphs_style.hero_art_style_';

// Scan config/sync for our hero art style YAML files.
$all_configs = $file_storage->listAll('classy_paragraphs.classy_paragraphs_style.hero_art_style_');

if (empty($all_configs)) {
  echo "No hero art style config files found in config/sync/.\n";
  echo "Ensure YAML files are placed in: $config_path\n";
  exit(1);
}

$imported = 0;
$skipped = 0;

foreach ($all_configs as $config_name) {
  $data = $file_storage->read($config_name);
  if (!$data) {
    echo "  SKIP: Could not read $config_name\n";
    $skipped++;
    continue;
  }

  // Check if config already exists.
  $existing = $config_factory->get($config_name);
  if (!$existing->isNew()) {
    echo "  EXISTS: {$data['label']} ({$data['id']}) - updating\n";
  }
  else {
    echo "  NEW: {$data['label']} ({$data['id']})\n";
  }

  // Write config.
  $config = $config_factory->getEditable($config_name);
  foreach ($data as $key => $value) {
    $config->set($key, $value);
  }
  $config->save();
  $imported++;
}

echo "\n=== Import complete ===\n";
echo "Imported/updated: $imported\n";
echo "Skipped: $skipped\n";
echo "\nRun 'drush cr' to clear caches.\n";