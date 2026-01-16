<?php

/**
 * @file
 * Drush script to create content nodes from menu items in structure_sync.data.yml.
 *
 * Usage:
 *   drush php:script scripts/create_menu_content.php
 *   drush php:script scripts/create_menu_content.php -- --dry-run
 *   drush php:script scripts/create_menu_content.php -- --limit=5
 *   drush php:script scripts/create_menu_content.php -- --dry-run --limit=5
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\pathauto\PathautoState;
use Symfony\Component\Yaml\Yaml;

/**
 * Descriptions for each menu item title.
 * Edit these descriptions to customise the content for each page.
 */
$title_descriptions = [
  // Main categories
  'Culture' => 'Discover the vibrant cultural scene of the Solent region, from arts and entertainment to community events and local traditions.',
  'Sectors' => 'Explore the diverse industries and business sectors that drive the Solent economy and create opportunities for growth.',
  'Living' => 'Everything you need to know about life in the Solent region, from health and wellbeing to family and community resources.',
  'Explore' => 'Find events, articles, organisations and more across the Solent metropolitan area.',
  'About' => 'Learn more about The Solent Metropolitan, our mission, team and services.',

  // Culture subcategories
  'Community' => 'Connect with local community groups, neighbourhood initiatives and grassroots organisations making a difference.',
  'Sport' => 'From grassroots clubs to professional teams, discover the sporting life of the Solent region.',
  'Art & Design' => 'Galleries, studios, exhibitions and creative spaces showcasing local and international artistic talent.',
  'Music' => 'Live venues, festivals, local artists and the rich musical heritage of the Solent area.',
  'Theatre & Stage' => 'Professional and amateur theatre, performing arts venues and stage productions across the region.',
  'Dance' => 'Dance schools, performances, and the diverse dance community of the Solent region.',
  'Writing & Blogs' => 'Local writers, literary events, book clubs and the written word in our community.',
  'Heritage' => 'Museums, historical sites and the rich heritage that shaped the Solent region.',
  'Podcasts & Radio' => 'Local radio stations, podcasts and audio content from across the Solent area.',
  'Food & Drink' => 'Restaurants, cafes, local producers and the culinary culture of the region.',
  'Fashion' => 'Local designers, boutiques and the fashion scene of the Solent area.',
  'Science & Tech Forums' => 'Technology meetups, science events and innovation discussions in the region.',
  'Makers & Crafts' => 'Artisans, craft workshops and the maker community of the Solent area.',
  'Film, TV & Streaming' => 'Local film productions, cinemas and screen culture in the Solent region.',
  'Enthusiasts\' Clubs' => 'Hobby groups, special interest clubs and enthusiast communities.',
  'Video Games' => 'Gaming communities, esports and video game culture in the region.',
  'Board Games' => 'Tabletop gaming groups, board game cafes and the local gaming community.',
  'Faith' => 'Religious communities, places of worship and interfaith initiatives.',
  'Language' => 'Language learning, multicultural communities and linguistic diversity.',
  'All Culture' => 'Browse all cultural categories and discover what the Solent region has to offer.',

  // Living subcategories
  'Health' => 'Healthcare services, wellness resources and health information for Solent residents.',
  'Fitness' => 'Gyms, sports facilities, fitness classes and active lifestyle resources.',
  'Learning & Education' => 'Schools, colleges, universities and lifelong learning opportunities.',
  'Mental Health' => 'Mental health support, counselling services and wellbeing resources.',
  'Family' => 'Family services, parenting resources and support for families in the region.',
  'Home & Garden' => 'Home improvement, gardening and domestic life in the Solent area.',
  'Advice' => 'Guidance and support services for residents of the Solent region.',
  'Outreach' => 'Community outreach programmes and support services.',
  'Work' => 'Employment, careers and workplace resources in the Solent region.',
  'All Living' => 'Browse all living categories for life in the Solent region.',

  // Sectors subcategories
  'Maritime' => 'The maritime industry, ports, shipping and marine businesses of the Solent.',
  'Farming' => 'Agriculture, farming communities and rural businesses in the region.',
  'Science' => 'Scientific research, laboratories and science-based industries.',
  'Engineering' => 'Engineering firms, technical services and the engineering sector.',
  'Infrastructure' => 'Transport, utilities and infrastructure development in the region.',
  'Construction' => 'Building, construction companies and development projects.',
  'Marketing & PR' => 'Marketing agencies, PR firms and communications businesses.',
  'Crafts' => 'Craft businesses, artisan producers and skilled trades.',
  'Education' => 'Educational institutions, training providers and the education sector.',
  'Health & Care' => 'Healthcare providers, care services and the health sector.',
  'Hospitality' => 'Hotels, restaurants and the hospitality industry.',
  'Lifestyle' => 'Lifestyle businesses and services in the Solent region.',
  'Finance' => 'Financial services, banking and the finance sector.',
  'Professional Services' => 'Legal, accounting and professional service firms.',
  'Property & Trades' => 'Estate agents, property developers and trade services.',
  'Transport' => 'Transport companies, logistics and mobility services.',
  'Creative & Performance' => 'Creative industries, performers and entertainment businesses.',
  'Environment & Nature' => 'Environmental organisations, conservation and green businesses.',
  'Media' => 'News outlets, media companies and journalism in the region.',
  'Charity & Volunteering' => 'Charitable organisations, volunteering opportunities and the third sector.',
  'Event & Venue' => 'Event organisers, venues and the events industry.',
  'Retail' => 'Shops, retail businesses and the local high street.',
  'Vehicle & Machinery' => 'Automotive, machinery and vehicle-related businesses.',
  'Tourism & Travel' => 'Tourism, travel agencies and visitor attractions.',
  'Policing' => 'Police services, community safety and law enforcement.',
  'Military' => 'Armed forces, defence sector and military heritage.',
  'Animal Care' => 'Veterinary services, animal welfare and pet businesses.',
  'Architecture' => 'Architectural firms, design practices and the built environment.',
  'Aerospace' => 'Aviation, aerospace companies and the aerospace industry.',
  'Manufacturing' => 'Manufacturers, factories and industrial production.',
  'Logistics' => 'Supply chain, warehousing and logistics businesses.',
  'Legal' => 'Law firms, legal services and the justice sector.',
  'Utilities' => 'Energy, water and utility companies.',
  'Rescue' => 'Emergency services, rescue organisations and first responders.',
  'Democracy' => 'Local government, civic participation and democratic institutions.',
  'Style and Fashion' => 'Fashion industry, stylists and clothing businesses.',
  'Sport & Fitness' => 'Sports businesses, fitness industry and athletic services.',
  'Technology' => 'Tech companies, digital businesses and the technology sector.',
  'Government' => 'Public sector, government services and civic administration.',
  'Facilities' => 'Facilities management, building services and maintenance.',
  'Security' => 'Security firms, protective services and safety businesses.',
  'All Sectors' => 'Browse all business sectors in the Solent region.',

  // Explore subcategories
  'Events' => 'Upcoming events, festivals and activities across the Solent region.',
  'Articles' => 'News, features and articles about life in the Solent area.',
  'Organisations' => 'Directory of organisations, businesses and groups in the region.',
  'Themes' => 'Explore content organised by themes and topics.',
  'Collaborations' => 'Partnership projects and collaborative initiatives.',
  'Jobs boards' => 'Employment opportunities and job listings in the Solent region.',
  'Explore All' => 'Browse all content and discover the Solent region.',

  // About subcategories
  'Our team' => 'Meet the team behind The Solent Metropolitan.',
  'Contact us' => 'Get in touch with The Solent Metropolitan team.',
  'Why?' => 'Learn about our mission and why we created The Solent Metropolitan.',
  'Our Services' => 'Discover the services we offer to the Solent community.',
  'All About' => 'Learn everything about The Solent Metropolitan.',
];

/**
 * Get description for a title, with fallback to generic description.
 */
function get_description($title, $descriptions) {
  if (isset($descriptions[$title])) {
    return $descriptions[$title];
  }
  // Fallback description
  return "Discover more about $title in the Solent metropolitan region.";
}

/**
 * Output a message.
 */
function output($message) {
  echo $message . PHP_EOL;
}

// Parse command line arguments
$dry_run = false;
$limit = null;

if (isset($extra) && is_array($extra)) {
  foreach ($extra as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
      $dry_run = true;
    }
    if (strpos($arg, '--limit=') === 0) {
      $limit = (int) substr($arg, 8);
    }
  }
}

// Path to the YML file
$yml_path = DRUPAL_ROOT . '/../config/sync/structure_sync.data.yml';

// Alternative path if running from web root
if (!file_exists($yml_path)) {
  $yml_path = DRUPAL_ROOT . '/config/sync/structure_sync.data.yml';
}

// Try relative to script location
if (!file_exists($yml_path)) {
  $yml_path = dirname(__DIR__) . '/config/sync/structure_sync.data.yml';
}

if (!file_exists($yml_path)) {
  output("Error: YML file not found. Tried:");
  output("  - " . DRUPAL_ROOT . '/../config/sync/structure_sync.data.yml');
  output("  - " . DRUPAL_ROOT . '/config/sync/structure_sync.data.yml');
  output("  - " . dirname(__DIR__) . '/config/sync/structure_sync.data.yml');
  exit(1);
}

output("Reading YML file: $yml_path");

// Parse the YML file
$data = Yaml::parseFile($yml_path);

if (empty($data['menus'])) {
  output("Error: No menus found in YML file.");
  exit(1);
}

$menus = $data['menus'];
$count = 0;
$created = 0;
$skipped = 0;

output("Found " . count($menus) . " menu items.");
if ($dry_run) {
  output("DRY RUN MODE - No changes will be made.\n");
}
if ($limit) {
  output("Limiting to $limit items.\n");
}

foreach ($menus as $menu_item) {
  // Check limit
  if ($limit && $created >= $limit) {
    output("\nReached limit of $limit items.");
    break;
  }

  $count++;

  // Only process main menu items
  if ($menu_item['menu_name'] !== 'main') {
    continue;
  }

  $title = $menu_item['title'];
  $uri = $menu_item['uri'];

  // Extract path from uri (remove 'internal:' prefix)
  $path = str_replace('internal:', '', $uri);

  // Skip if path is empty
  if (empty($path) || $path === '/') {
    output("[$count] Skipping '$title' - empty path");
    $skipped++;
    continue;
  }

  // Check if a node with this path alias already exists
  try {
    $existing_path = \Drupal::service('path_alias.manager')->getPathByAlias($path);
    if ($existing_path !== $path) {
      output("[$count] Skipping '$title' - path '$path' already exists (points to $existing_path)");
      $skipped++;
      continue;
    }
  } catch (\Exception $e) {
    // Path doesn't exist, which is fine
  }

  output("[$count] Processing: $title (path: $path)");

  if ($dry_run) {
    $description = get_description($title, $title_descriptions);
    output("  Would create composite_page node with:");
    output("    - Title: $title");
    output("    - Path: $path");
    output("    - Paragraph 1: heading");
    output("        field_heading: '$title'");
    output("        field_heading_align: 'left'");
    output("        field_heading_size: 'h2'");
    output("        field_color_text: 'black'");
    output("    - Paragraph 2: text");
    output("        description: '$description'");
    $created++;
    continue;
  }

  try {
    // Load the "black" color taxonomy term
    $color_terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'color', 'name' => 'black']);
    $black_term = reset($color_terms);

    if (!$black_term) {
      output("  WARNING: Could not find 'black' color term, skipping color field");
    }

    // Create the heading paragraph
    $heading_data = [
      'type' => 'heading',
      'field_heading' => $title,
      'field_heading_align' => 'left',
      'field_heading_size' => 'h2',
    ];
    if ($black_term) {
      $heading_data['field_color_text'] = ['target_id' => $black_term->id()];
    }
    $heading_paragraph = Paragraph::create($heading_data);
    $heading_paragraph->save();

    // Create the text paragraph with description
    $description = get_description($title, $title_descriptions);
    $text_paragraph = Paragraph::create([
      'type' => 'text',
      'field_text' => [
        'value' => "<p>$description</p>",
        'format' => 'basic_html',
      ],
    ]);
    $text_paragraph->save();

    // Create the node (using composite_page content type)
    $node = Node::create([
      'type' => 'composite_page',
      'title' => $title,
      'status' => 1, // Published
      'field_content_component' => [
        [
          'target_id' => $heading_paragraph->id(),
          'target_revision_id' => $heading_paragraph->getRevisionId(),
        ],
        [
          'target_id' => $text_paragraph->id(),
          'target_revision_id' => $text_paragraph->getRevisionId(),
        ],
      ],
      'path' => [
        'alias' => $path,
        'pathauto' => PathautoState::SKIP,
      ],
    ]);
    $node->save();

    output("  Created node ID: " . $node->id() . " with path alias: $path");
    $created++;

  } catch (\Exception $e) {
    output("  ERROR: " . $e->getMessage());
    $skipped++;
  }
}

output("\n--- Summary ---");
output("Total menu items: " . count($menus));
output("Processed: $count");
output("Created: $created");
output("Skipped: $skipped");

if ($dry_run) {
  output("\nThis was a DRY RUN. Run without --dry-run to create content.");
}
