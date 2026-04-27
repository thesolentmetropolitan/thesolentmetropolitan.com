<?php

/**
 * @file
 * Drush script to insert hero-with-art-style paragraphs into Composite Pages.
 *
 * Usage: drush php:script drush_insert_hero_paragraphs.php
 *
 * For each page (matched by URL alias), this script:
 *   1. Finds the node by its path alias
 *   2. Looks for an existing hero_with_art_style paragraph
 *   3a. If found: updates its field_classy to the correct style
 *   3b. If not found: creates one and prepends it to field_content_component
 *
 * IMPORTANT: Review the $page_map array below and adjust if your
 * node aliases or field names differ.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Entity\EntityInterface;

/**
 * Map of URL alias => classy paragraph config ID.
 *
 * The config ID is used to look up the classy_paragraphs_style entity,
 * which is then set as the value of field_classy on the hero paragraph.
 */
$page_map = [
  // Culture - View All Culture
  '/culture' => 'hero_art_style_culture_culture',
  // Culture - Art & Design
  '/culture/art-design' => 'hero_art_style_culture_art_design',
  // Culture - Community
  '/culture/community' => 'hero_art_style_culture_community',
  // Culture - Dance
  '/culture/dance' => 'hero_art_style_culture_dance',
  // Culture - Enthusiasts
  '/culture/enthusiasts' => 'hero_art_style_culture_enthusiasts',
  // Culture - Faith
  '/culture/faith' => 'hero_art_style_culture_faith',
  // Culture - Festivals
  '/culture/festivals' => 'hero_art_style_culture_festivals',
  // Culture - Food & Drink
  '/culture/food-drink' => 'hero_art_style_culture_food_drink',
  // Culture - Games
  '/culture/games' => 'hero_art_style_culture_games',
  // Culture - Heritage
  '/culture/heritage' => 'hero_art_style_culture_heritage',
  // Culture - Identity
  '/culture/identity' => 'hero_art_style_culture_identity',
  // Culture - Language
  '/culture/language' => 'hero_art_style_culture_language',
  // Culture - Maritime
  '/culture/maritime' => 'hero_art_style_culture_maritime',
  // Culture - Music
  '/culture/music' => 'hero_art_style_culture_music',
  // Culture - Outdoor & Active
  '/culture/outdoor-active' => 'hero_art_style_culture_outdoor_active',
  // Culture - Photography
  '/culture/photography' => 'hero_art_style_culture_photography',
  // Culture - Radio & Podcast
  '/culture/radio-podcast' => 'hero_art_style_culture_radio_podcast',
  // Culture - Science
  '/culture/science' => 'hero_art_style_culture_science',
  // Culture - Screen
  '/culture/screen' => 'hero_art_style_culture_screen',
  // Culture - Sport
  '/sport' => 'hero_art_style_culture_sport',
  // Culture - Stage
  '/culture/stage' => 'hero_art_style_culture_stage',
  // Culture - Style
  '/culture/style' => 'hero_art_style_culture_style',
  // Culture - Talks
  '/culture/talks' => 'hero_art_style_culture_talks',
  // Culture - Technology
  '/culture/technology' => 'hero_art_style_culture_technology',
  // Culture - Writing
  '/culture/writing' => 'hero_art_style_culture_writing',
  // Culture - Workshops
  '/culture/workshops' => 'hero_art_style_culture_workshops',
  // Culture - Volunteering
  '/culture/volunteering' => 'hero_art_style_culture_volunteering',
  // Sectors - View All Sectors
  '/sectors' => 'hero_art_style_sectors_sectors',
  // Sectors - Arts
  '/sectors/arts' => 'hero_art_style_sectors_arts',
  // Sectors - Construction
  '/sectors/construction' => 'hero_art_style_sectors_construction',
  // Sectors - Consulting
  '/sectors/consulting' => 'hero_art_style_sectors_consulting',
  // Sectors - Creative
  '/sectors/creative' => 'hero_art_style_sectors_creative',
  // Sectors - Democracy
  '/sectors/democracy' => 'hero_art_style_sectors_democracy',
  // Sectors - Design
  '/sectors/design' => 'hero_art_style_sectors_design',
  // Sectors - Education
  '/sectors/education' => 'hero_art_style_sectors_education',
  // Sectors - Engineering
  '/sectors/engineering' => 'hero_art_style_sectors_engineering',
  // Sectors - Entrepreneur
  '/sectors/entrepreneur' => 'hero_art_style_sectors_entrepreneur',
  // Sectors - Environment
  '/sectors/environment' => 'hero_art_style_sectors_environment',
  // Sectors - Event & Venue
  '/sectors/event-venue' => 'hero_art_style_sectors_event_venue',
  // Sectors - Facilities
  '/sectors/facilities' => 'hero_art_style_sectors_facilities',
  // Sectors - Farming
  '/sectors/farming' => 'hero_art_style_sectors_farming',
  // Sectors - Finance
  '/sectors/finance' => 'hero_art_style_sectors_finance',
  // Sectors - Health & Care
  '/sectors/health-care' => 'hero_art_style_sectors_health_care',
  // Sectors - Hospitality
  '/sectors/hospitality' => 'hero_art_style_sectors_hospitality',
  // Sectors - Legal
  '/sectors/legal' => 'hero_art_style_sectors_legal',
  // Sectors - Lifestyle
  '/sectors/lifestyle' => 'hero_art_style_sectors_lifestyle',
  // Sectors - Logistics
  '/sectors/logistics' => 'hero_art_style_sectors_logistics',
  // Sectors - Manufacturing
  '/sectors/manufacturing' => 'hero_art_style_sectors_manufacturing',
  // Sectors - Maritime
  '/sectors/maritime' => 'hero_art_style_sectors_maritime',
  // Sectors - Marketing
  '/sectors/marketing' => 'hero_art_style_sectors_marketing',
  // Sectors - Media
  '/sectors/media' => 'hero_art_style_sectors_media',
  // Sectors - Military
  '/sectors/defence-and-military' => 'hero_art_style_sectors_military',
  // Sectors - Non-profit
  '/sectors/non-profit' => 'hero_art_style_sectors_non_profit',
  // Sectors - Property
  '/sectors/property' => 'hero_art_style_sectors_property',
  // Sectors - Public Sector
  '/sectors/public-sector' => 'hero_art_style_sectors_public_sector',
  // Sectors - Retail
  '/sectors/retail' => 'hero_art_style_sectors_retail',
  // Sectors - Science
  '/sectors/science' => 'hero_art_style_sectors_science',
  // Sectors - Sport & Fitness
  '/sectors/sport-fitness' => 'hero_art_style_sectors_sport_fitness',
  // Sectors - Technology
  '/sectors/technology' => 'hero_art_style_sectors_technology',
  // Sectors - Tourism
  '/sectors/tourism' => 'hero_art_style_sectors_tourism',
  // Sectors - Trades
  '/sectors/trades' => 'hero_art_style_sectors_trades',
  // Sectors - Transport
  '/sectors/transport' => 'hero_art_style_sectors_transport',
  // Sectors - Utilities
  '/sectors/utilities' => 'hero_art_style_sectors_utilities',
  // Living - View All Living
  '/living' => 'hero_art_style_living_living',
  // Living - Advice
  '/living/advice' => 'hero_art_style_living_advice',
  // Living - Education
  '/living/education' => 'hero_art_style_living_education',
  // Living - Family
  '/living/family' => 'hero_art_style_living_family',
  // Living - Fitness
  '/living/fitness' => 'hero_art_style_living_fitness',
  // Living - Health
  '/living/health' => 'hero_art_style_living_health',
  // Living - Home & Garden
  '/living/home-garden' => 'hero_art_style_living_home_garden',
  // Living - Housing
  '/living/housing' => 'hero_art_style_living_housing',
  // Living - Mental Health
  '/living/mental-health' => 'hero_art_style_living_mental_health',
  // Living - Outreach
  '/living/outreach' => 'hero_art_style_living_outreach',
  // Living - Work
  '/living/work' => 'hero_art_style_living_work',
  // === About section ===
  // About - View All About
  '/about' => 'hero_art_style_about_about',
  // About - Accessibility
  '/about/accessibility' => 'hero_art_style_about_accessibility',
  // About - Why?
  '/about/why' => 'hero_art_style_about_why',
  // About - Editorial Policy
  '/about/editorial-policy' => 'hero_art_style_about_editorial_policy',
  // About - Our Services
  '/about/our-services' => 'hero_art_style_about_our_services',
  // About - Our team
  '/about/team' => 'hero_art_style_about_our_team',
  // About - Contact us
  '/contact' => 'hero_art_style_about_contact_us',
  // About - Privacy policy
  '/about/privacy-policy' => 'hero_art_style_about_privacy_policy',
  // About - Terms of use
  '/about/terms' => 'hero_art_style_about_terms_of_use',
  // === Explore section ===
  // Explore - View All Explore
  '/explore' => 'hero_art_style_explore_explore',
  // Explore - Archive
  '/explore/archive' => 'hero_art_style_explore_archive',
  // Explore - Articles
  '/explore/articles' => 'hero_art_style_explore_articles',
  // Explore - Collaborations
  '/explore/collaborations' => 'hero_art_style_explore_collaborations',
  // Explore - Data
  '/explore/data' => 'hero_art_style_explore_data',
  // Explore - Events
  '/explore/events' => 'hero_art_style_explore_events',
  // Explore - Jobs boards
  '/explore/jobs-boards' => 'hero_art_style_explore_jobs_boards',
  // Explore - Maps
  '/explore/maps' => 'hero_art_style_explore_maps',
  // Explore - Opinion
  '/explore/opinion' => 'hero_art_style_explore_opinion',
  // Explore - Organisations
  '/explore/organisations' => 'hero_art_style_explore_organisations',
  // Explore - Themes
  '/explore/themes' => 'hero_art_style_explore_themes',
];

$alias_manager = \Drupal::service('path_alias.manager');
$entity_type_manager = \Drupal::entityTypeManager();
$path_validator = \Drupal::service('path.validator');

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($page_map as $alias => $classy_id) {
  echo "\nProcessing: $alias => $classy_id\n";

  // Resolve alias to internal path.
  $internal_path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);

  if (!$internal_path || $internal_path === $alias) {
    echo "  ERROR: No node found for alias '$alias'. Skipping.\n";
    $errors++;
    continue;
  }

  // Extract node ID from /node/123.
  if (!preg_match('/^\/node\/(\d+)$/', $internal_path, $matches)) {
    echo "  ERROR: Internal path '$internal_path' is not a node. Skipping.\n";
    $errors++;
    continue;
  }

  $nid = $matches[1];
  $node = Node::load($nid);

  if (!$node) {
    echo "  ERROR: Could not load node $nid. Skipping.\n";
    $errors++;
    continue;
  }

  // Check this is a composite_page.
  if ($node->bundle() !== 'composite_page') {
    echo "  SKIP: Node $nid ({$node->getTitle()}) is type '{$node->bundle()}', not composite_page.\n";
    $skipped++;
    continue;
  }

  // Check if node already has a hero-with-art-style paragraph.
  $existing_hero = NULL;
  if ($node->hasField('field_content_component')) {
    $existing_paragraphs = $node->get('field_content_component')->referencedEntities();
    foreach ($existing_paragraphs as $paragraph) {
      if ($paragraph->bundle() === 'hero_with_art_style') {
        $existing_hero = $paragraph;
        break;
      }
    }
  }
  else {
    echo "  ERROR: Node $nid does not have field_content_component. Skipping.\n";
    $errors++;
    continue;
  }

  // Verify the classy paragraphs style exists.
  $classy_style = $entity_type_manager
    ->getStorage('classy_paragraphs_style')
    ->load($classy_id);

  if (!$classy_style) {
    echo "  ERROR: Classy paragraph style '$classy_id' not found. Run import script first. Skipping.\n";
    $errors++;
    continue;
  }

  if ($existing_hero) {
    // Update the existing hero paragraph's field_classy.
    $existing_hero->set('field_classy', ['target_id' => $classy_id]);
    $existing_hero->save();
    $node->save();

    echo "  UPDATED: Hero paragraph on node $nid ({$node->getTitle()}) set to style: $classy_id\n";
    $updated++;
  }
  else {
    // No existing hero — create one and prepend it.
    $hero_paragraph = Paragraph::create([
      'type' => 'hero_with_art_style',
      'field_classy' => [
        'target_id' => $classy_id,
      ],
    ]);
    $hero_paragraph->save();

    $current_values = $node->get('field_content_component')->getValue();
    $new_ref = [
      'target_id' => $hero_paragraph->id(),
      'target_revision_id' => $hero_paragraph->getRevisionId(),
    ];
    array_unshift($current_values, $new_ref);

    $node->set('field_content_component', $current_values);
    $node->save();

    echo "  INSERTED: New hero paragraph (style: $classy_id) into node $nid ({$node->getTitle()})\n";
    $inserted++;
  }
}

echo "\n=== Complete ===\n";
echo "Updated: $updated\n";
echo "Inserted (new): $inserted\n";
echo "Skipped: $skipped\n";
echo "Errors: $errors\n";
echo "\nRun 'drush cr' to clear caches.\n";