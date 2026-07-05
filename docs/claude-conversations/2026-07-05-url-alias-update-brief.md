# The Solent Metropolitan — Retrospective URL Alias Update

## Overview

Two pieces of work:

1. **Custom token module** — provides a reliable `[node:event_date_formatted]` token for Pathauto, working around Smart Date's token compatibility issues with Pathauto's validation.
2. **Batch alias regeneration** — update all published Event, Organisation, and Link nodes with new URL patterns using the custom token.

**Drupal 11 compatible.**

---

## Prerequisites

Before running the alias regeneration script:

1. **Custom token module must be created and enabled** (see Part 1 below)
2. **Pathauto patterns must be configured and exported** for all three content types:
   - Event: `events/[node:title]-[node:event_date_formatted]`
   - Organisation: the pattern Rob has defined
   - Link: the pattern Rob has defined
3. **Verify the custom token works** by editing and saving a single Event node, then checking its URL alias includes the formatted date.
4. **Back up the database** before running the batch.

---

## Part 1: Custom Token Module — event_date_formatted

### Why a custom module

Smart Date's built-in token support does not work reliably with Pathauto's token validation in current Drupal 11 / Token module versions. The `[node:field_when:value-custom:Y-m-d-H-i]` token and its variants are rejected as invalid. A small custom module provides a reliable, validated token that Pathauto accepts.

### Module structure

Create a minimal custom module:

```
web/modules/custom/customsolent_tokens/
├── customsolent_tokens.info.yml
├── customsolent_tokens.tokens.inc
└── customsolent_tokens.module
```

### customsolent_tokens.info.yml

```yaml
name: 'Custom Solent Tokens'
type: module
description: 'Provides custom tokens for The Solent Metropolitan, including formatted event dates for Pathauto.'
core_version_requirement: ^10.3 || ^11
package: 'Custom'
dependencies:
  - drupal:node
  - smart_date:smart_date
```

### customsolent_tokens.tokens.inc

```php
<?php

/**
 * @file
 * Token definitions and replacements for Custom Solent Tokens.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function customsolent_tokens_token_info() {
  $info = [];

  $info['tokens']['node']['event_date_formatted'] = [
    'name' => t('Event date formatted'),
    'description' => t('The event start date from field_when, formatted as YYYY-MM-DD-HH-MM (e.g. 2026-05-24-19-30). Only available on Event nodes with a field_when value.'),
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function customsolent_tokens_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'node' && !empty($data['node'])) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $data['node'];

    foreach ($tokens as $name => $original) {
      if ($name === 'event_date_formatted') {
        if ($node->hasField('field_when') && !$node->get('field_when')->isEmpty()) {
          $field_item = $node->get('field_when')->first();
          // Smart Date stores the start timestamp in the 'value' property.
          $timestamp = $field_item->value;
          if ($timestamp) {
            // Format as YYYY-MM-DD-HH-MM
            $replacements[$original] = date('Y-m-d-H-i', $timestamp);
            // Add cache dependency on the node
            $bubbleable_metadata->addCacheableDependency($node);
          }
        }

        // If no field_when value, return empty string (Pathauto will skip it)
        if (!isset($replacements[$original])) {
          $replacements[$original] = '';
        }
      }
    }
  }

  return $replacements;
}
```

### customsolent_tokens.module

```php
<?php

/**
 * @file
 * Custom Solent Tokens module.
 *
 * Provides custom tokens including formatted event dates.
 * Token definitions are in customsolent_tokens.tokens.inc.
 */
```

### Enable the module

```bash
drush en customsolent_tokens
drush cr
```

### Verify

After enabling, go to the Pathauto pattern edit page for Events. Click "Browse available tokens" → Node. You should see **"Event date formatted"** with token `[node:event_date_formatted]`. Set the Event pattern to:

```
events/[node:title]-[node:event_date_formatted]
```

This will produce URLs like: `/events/jazz-on-the-seafront-2026-05-24-19-30`

Save the pattern — it should validate without errors.

---

## Part 2: Retrospective URL Alias Update

Create a drush-executable PHP script that:

1. Loads all published nodes of types: `event`, `organisation`, `link`
2. For each node, deletes the existing URL alias (if any)
3. Triggers Pathauto to generate a new alias based on the current pattern
4. Reports what changed

### Script

Place in: `scripts/regenerate-url-aliases.php`

Run via: `drush php:script scripts/regenerate-url-aliases.php`

```php
<?php

/**
 * @file
 * Regenerate URL aliases for Event, Organisation, and Link nodes.
 *
 * Run with: drush php:script scripts/regenerate-url-aliases.php
 *
 * Prerequisites:
 *   - Pathauto patterns configured for event, organisation, link
 *   - Smart Date token verified working for events
 */

use Drupal\pathauto\PathautoState;

$entity_type_manager = \Drupal::entityTypeManager();
$pathauto_generator = \Drupal::service('pathauto.generator');
$path_alias_storage = $entity_type_manager->getStorage('path_alias');

$content_types = ['event', 'organisation', 'link'];
$total_updated = 0;
$total_skipped = 0;
$errors = [];

foreach ($content_types as $bundle) {
  echo "\n--- Processing: $bundle ---\n";

  $nids = $entity_type_manager->getStorage('node')->getQuery()
    ->condition('type', $bundle)
    ->condition('status', 1)
    ->accessCheck(FALSE)
    ->execute();

  if (empty($nids)) {
    echo "  No published $bundle nodes found.\n";
    continue;
  }

  $nodes = $entity_type_manager->getStorage('node')->loadMultiple($nids);
  $bundle_updated = 0;
  $bundle_skipped = 0;

  foreach ($nodes as $node) {
    $nid = $node->id();
    $title = $node->getTitle();
    $old_alias = \Drupal::service('path_alias.manager')
      ->getAliasByPath('/node/' . $nid);

    try {
      // Delete existing aliases for this node
      $existing_aliases = $path_alias_storage->loadByProperties([
        'path' => '/node/' . $nid,
      ]);
      foreach ($existing_aliases as $alias_entity) {
        $alias_entity->delete();
      }

      // Set pathauto state to generate a new alias
      $node->path->pathauto = PathautoState::CREATE;

      // Trigger pathauto to generate the new alias
      $result = $pathauto_generator->updateEntityAlias($node, 'bulkupdate');

      // Get the new alias
      $new_alias = \Drupal::service('path_alias.manager')
        ->getAliasByPath('/node/' . $nid);

      if ($new_alias !== '/node/' . $nid) {
        echo "  [$bundle] NID $nid: \"$title\"\n";
        echo "    Old: $old_alias\n";
        echo "    New: $new_alias\n";
        $bundle_updated++;
      } else {
        echo "  [$bundle] NID $nid: \"$title\" — no alias generated (check pattern)\n";
        $bundle_skipped++;
      }
    } catch (\Exception $e) {
      $error_msg = "  [$bundle] NID $nid: \"$title\" — ERROR: " . $e->getMessage();
      echo "$error_msg\n";
      $errors[] = $error_msg;
    }
  }

  echo "  $bundle: $bundle_updated updated, $bundle_skipped skipped\n";
  $total_updated += $bundle_updated;
  $total_skipped += $bundle_skipped;
}

// Clear caches after all updates
echo "\n--- Rebuilding caches ---\n";
drupal_flush_all_caches();

echo "\n=== Summary ===\n";
echo "Total updated: $total_updated\n";
echo "Total skipped: $total_skipped\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
  echo "\nErrors encountered:\n";
  foreach ($errors as $error) {
    echo "  $error\n";
  }
}

echo "\nDone. Run 'drush cr' if needed.\n";
```

---

## Alternative: Drush + Pathauto built-in

Pathauto has a built-in bulk update feature accessible at `/admin/config/search/path/update_bulk`. However, it updates ALL content types at once and doesn't give per-node reporting. The drush script above gives more control and visibility.

If you prefer the built-in approach:

```bash
# Regenerate aliases for specific content types
drush pathauto:aliases-generate node event
drush pathauto:aliases-generate node organisation
drush pathauto:aliases-generate node link
```

Check if `drush pathauto:aliases-generate` is available in your version. If not, the PHP script is the reliable approach.

---

## Post-run verification

1. Check a sample of events — verify the URL includes the date in `YYYY-MM-DD-HH-MM` format
2. Check organisations — verify the URL follows the new pattern
3. Check links — verify the URL follows the new pattern
4. Visit old URLs — they should 404 (old aliases are deleted). If you need redirects from old to new, install the Redirect module and configure it to create redirects on alias change. **Consider installing Redirect before running the script** if you want automatic redirects.
5. Check search results — search index may need rebuilding to reflect new URLs

---

## Redirect consideration

If any of these nodes have been shared externally (social media, other websites linking to them), deleting old aliases without redirects will break those links. Options:

- **Install the Redirect module** (`drupal/redirect`) before running the script. When Pathauto generates a new alias, Redirect automatically creates a 301 redirect from the old URL to the new one.
- **Or accept broken old links** if the content hasn't been widely shared yet (likely the case for a site in soft launch).

---

## Testing

1. **Custom token visible:** After enabling the module, browse available tokens in the Pathauto pattern page. `[node:event_date_formatted]` appears under Node tokens with description "The event start date from field_when, formatted as YYYY-MM-DD-HH-MM."
2. **Token validates in pattern:** Save the Event Pathauto pattern with `events/[node:title]-[node:event_date_formatted]` — no validation error.
3. **Single node test:** Edit and save one event. Verify the URL alias includes the correct date (e.g. `/events/jazz-on-the-seafront-2026-05-24-19-30`).
4. **Event without field_when:** If any event lacks a date, the token returns empty string. Verify the URL still generates (just without the date suffix) and doesn't error.
5. **Non-event content types:** The token only applies to nodes with `field_when`. Verify Organisation and Link nodes are unaffected (the token isn't in their patterns).
6. **Run the batch script:** Execute via drush, review the output for all three content types.
7. **Spot-check:** Visit 3–4 nodes of each type via their new URLs.
8. **Search:** Search for an event, organisation, or link. Click the result. Verify it lands on the correct page (not a 404).
9. **Redirects (if Redirect module installed):** Visit an old URL. Verify it 301 redirects to the new URL.
