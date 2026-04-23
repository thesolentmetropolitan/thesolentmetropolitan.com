<?php

/**
 * @file
 * Drush script to populate the Topic taxonomy with a hierarchical term
 * structure mirroring the "Main navigation" menu.
 *
 * Idempotent: terms are keyed on (name, parent) within the `topic` vocabulary,
 * so re-running will skip anything that already exists.
 *
 * Usage:
 *   drush php:script scripts/generate_topic_taxonomy.php
 *   drush php:script scripts/generate_topic_taxonomy.php -- --dry-run
 *
 * See docs/claude-conversations/2026-04-23-topic-taxonomy-generation-brief.md
 */

use Drupal\taxonomy\Entity\Term;

const VOCABULARY = 'topic';
const SUMMARY_FIELD = 'field_reader_summary';
const SUMMARY_MAX = 200;

/**
 * Taxonomy definition.
 *
 * Keyed by parent section name in display order (parent weight = index).
 * Each parent has a 'summary' and an ordered 'children' list. Child weight
 * is the index within that list. Child 'name' is stored verbatim as the
 * term name (including the "Parent / " prefix, per brief).
 */
function topic_taxonomy_definition() {
  return [
    'Culture' => [
      'summary' => 'The arts, heritage, food, sport, community life and creative energy of the Greater Solent.',
      'children' => [
        ['name' => 'Culture / Art & Design', 'summary' => 'Galleries, studios, exhibitions and the visual artists and designers shaping Solent culture.'],
        ['name' => 'Culture / Community', 'summary' => 'Neighbourhood groups, local initiatives and the people building community life across the Solent.'],
        ['name' => 'Culture / Dance', 'summary' => "Dance performances, schools, clubs and the movers shaping the Solent's dance scene."],
        ['name' => 'Culture / Faith', 'summary' => 'Places of worship, faith communities and interfaith life across the Greater Solent.'],
        ['name' => 'Culture / Festivals', 'summary' => "Festivals, carnivals and celebrations that mark the Solent's cultural calendar."],
        ['name' => 'Culture / Food & Drink', 'summary' => 'Restaurants, pubs, producers and the flavours of the Greater Solent.'],
        ['name' => 'Culture / Games', 'summary' => "Board games, tabletop clubs, video gaming and the Solent's wider gaming community."],
        ['name' => 'Culture / Heritage', 'summary' => 'Museums, historical sites and the stories that shaped the Solent region.'],
        ['name' => 'Culture / Language', 'summary' => 'Languages spoken in the Solent, learning opportunities and multicultural communities.'],
        ['name' => 'Culture / Maritime', 'summary' => "The Solent's maritime heritage, seafaring traditions and culture shaped by the water."],
        ['name' => 'Culture / Music', 'summary' => "Gigs, venues, festivals and the musicians making up the Solent's music scene."],
        ['name' => 'Culture / Outdoor & Active', 'summary' => 'Walks, rides, open water and the active outdoor life of the Solent coast and countryside.'],
        ['name' => 'Culture / Photography', 'summary' => "Photographers, exhibitions and images that capture the Solent's people and places."],
        ['name' => 'Culture / Radio & Podcast', 'summary' => 'Local stations, podcasters and audio storytelling from around the Solent.'],
        ['name' => 'Culture / Science', 'summary' => 'Science communication, public talks, citizen science and curiosity across the Solent.'],
        ['name' => 'Culture / Screen', 'summary' => 'Film, TV, streaming and the screen culture being made and watched in the Solent.'],
        ['name' => 'Culture / Sport', 'summary' => 'Grassroots clubs, match days and the sporting life followed across the Solent.'],
        ['name' => 'Culture / Stage', 'summary' => 'Theatres, performances and the stage arts on show across the Solent region.'],
        ['name' => 'Culture / Style', 'summary' => 'Fashion, local designers and the style shaping Solent life.'],
        ['name' => 'Culture / Talks', 'summary' => 'Lectures, panels and public talks happening across the Solent.'],
        ['name' => 'Culture / Technology', 'summary' => 'How technology shapes culture and creativity across the Solent.'],
        ['name' => 'Culture / Writing', 'summary' => 'Writers, literary events, zines and the written word in the Solent region.'],
        ['name' => 'Culture / Workshops', 'summary' => 'Hands-on workshops, courses and skill-sharing sessions open to the Solent public.'],
        ['name' => 'Culture / Volunteering', 'summary' => 'Ways to get involved — volunteering projects and community contributions across the Solent.'],
      ],
    ],
    'Sectors' => [
      'summary' => 'The industries, businesses and organisations driving the Solent economy.',
      'children' => [
        ['name' => 'Sectors / Arts', 'summary' => 'Arts organisations, funded venues and the creative arts sector in the Solent economy.'],
        ['name' => 'Sectors / Construction', 'summary' => 'Builders, contractors and construction firms working across the Solent.'],
        ['name' => 'Sectors / Consulting', 'summary' => 'Consulting firms and advisors serving organisations in the Solent region.'],
        ['name' => 'Sectors / Creative', 'summary' => 'Creative agencies, studios and freelancers working across the Solent.'],
        ['name' => 'Sectors / Defence & Military', 'summary' => 'Armed forces, defence contractors and the military industry in the Solent region.'],
        ['name' => 'Sectors / Democracy', 'summary' => 'Councils, civic institutions and the democratic organisations of the Solent.'],
        ['name' => 'Sectors / Design', 'summary' => 'Design studios, practices and independents shaping how the Solent looks and feels.'],
        ['name' => 'Sectors / Education', 'summary' => 'Schools, colleges, universities and training providers in the Solent education sector.'],
        ['name' => 'Sectors / Engineering', 'summary' => 'Engineering firms, specialists and technical services based across the Solent.'],
        ['name' => 'Sectors / Entrepreneur', 'summary' => 'Founders, startups and the entrepreneurs building new ventures in the Solent.'],
        ['name' => 'Sectors / Environment', 'summary' => 'Environmental organisations, conservation groups and green businesses in the Solent.'],
        ['name' => 'Sectors / Event & Venue', 'summary' => 'Event companies, venues and the events industry active across the Solent.'],
        ['name' => 'Sectors / Facilities', 'summary' => 'Facilities management, maintenance and building services providers in the Solent.'],
        ['name' => 'Sectors / Farming', 'summary' => "Farms, growers and agricultural businesses across the Solent's rural hinterland."],
        ['name' => 'Sectors / Finance', 'summary' => 'Banks, insurers, advisors and the finance sector serving the Solent economy.'],
        ['name' => 'Sectors / Health & Care', 'summary' => 'Hospitals, clinics, care providers and the health and social care sector.'],
        ['name' => 'Sectors / Hospitality', 'summary' => 'Hotels, pubs, restaurants and the hospitality industry across the Solent.'],
        ['name' => 'Sectors / Legal', 'summary' => 'Law firms, barristers and legal services based across the Solent region.'],
        ['name' => 'Sectors / Lifestyle', 'summary' => 'Lifestyle businesses, wellbeing brands and consumer services in the Solent.'],
        ['name' => 'Sectors / Logistics', 'summary' => 'Logistics providers, warehousing and distribution businesses across the Solent.'],
        ['name' => 'Sectors / Manufacturing', 'summary' => 'Manufacturers, factories and industrial producers based in the Solent.'],
        ['name' => 'Sectors / Maritime', 'summary' => "Shipping, ports, marine engineering and the Solent's maritime industries."],
        ['name' => 'Sectors / Marketing', 'summary' => 'Marketing, PR and communications agencies based around the Solent.'],
        ['name' => 'Sectors / Media', 'summary' => 'News outlets, publishers and media businesses covering the Solent region.'],
        ['name' => 'Sectors / Non-profit', 'summary' => 'Charities, community interest companies and the third sector across the Solent.'],
        ['name' => 'Sectors / Property', 'summary' => 'Estate agents, developers and property businesses in the Solent region.'],
        ['name' => 'Sectors / Public Sector', 'summary' => 'Councils, public bodies and public sector organisations serving the Solent.'],
        ['name' => 'Sectors / Retail', 'summary' => 'Shops, retailers and the high street businesses of the Solent.'],
        ['name' => 'Sectors / Science', 'summary' => 'Research organisations, labs and science-based industries in the Solent economy.'],
        ['name' => 'Sectors / Sport & Fitness', 'summary' => 'Sports clubs, fitness operators and the sport and fitness industry in the Solent.'],
        ['name' => 'Sectors / Technology', 'summary' => 'Technology companies, startups and digital innovation in the Solent economy.'],
        ['name' => 'Sectors / Tourism', 'summary' => 'Tourism operators, attractions and the visitor economy of the Solent.'],
        ['name' => 'Sectors / Trades', 'summary' => 'Tradespeople, skilled crafts and trade businesses serving the Solent.'],
        ['name' => 'Sectors / Transport', 'summary' => 'Bus, rail, ferry and transport operators moving people around the Solent.'],
        ['name' => 'Sectors / Utilities', 'summary' => 'Energy, water and utility providers serving Solent homes and businesses.'],
      ],
    ],
    'Living' => [
      'summary' => 'Health, housing, family and everyday life across the Greater Solent region.',
      'children' => [
        ['name' => 'Living / Advice', 'summary' => 'Practical advice and guidance services for people living in the Solent.'],
        ['name' => 'Living / Education', 'summary' => 'Learning opportunities, schools, courses and educational life in the Solent.'],
        ['name' => 'Living / Family', 'summary' => 'Parenting, childcare, family services and support for Solent families.'],
        ['name' => 'Living / Fitness', 'summary' => 'Gyms, classes and ways to stay active while living in the Solent.'],
        ['name' => 'Living / Health', 'summary' => 'Healthcare, wellbeing and health services for Solent residents.'],
        ['name' => 'Living / Home & Garden', 'summary' => 'Homes, gardens and domestic life across the Solent region.'],
        ['name' => 'Living / Housing', 'summary' => 'Housing options, tenancy advice and support for Solent residents.'],
        ['name' => 'Living / Mental Health', 'summary' => 'Mental health support, counselling and wellbeing resources across the Solent.'],
        ['name' => 'Living / Outreach', 'summary' => 'Community outreach, support projects and help for people across the Solent.'],
        ['name' => 'Living / Work', 'summary' => 'Careers, jobs and working life for people based in the Solent.'],
      ],
    ],
    'Explore' => [
      'summary' => 'Ways to navigate the site — events, articles, data, maps and more from across the Solent.',
      'children' => [
        ['name' => 'Explore / Archive', 'summary' => 'Archived content and older material from across The Solent Metropolitan.'],
        ['name' => 'Explore / Articles', 'summary' => 'News, features and long reads exploring life across the Solent.'],
        ['name' => 'Explore / Collaborations', 'summary' => 'Partnership projects and collaborative work happening around the Solent.'],
        ['name' => 'Explore / Data', 'summary' => 'Data, statistics and open information about the Solent region.'],
        ['name' => 'Explore / Events', 'summary' => "Events happening across the Solent — what's on, where and when."],
        ['name' => 'Explore / Jobs Boards', 'summary' => 'Job listings and employment opportunities from around the Solent.'],
        ['name' => 'Explore / Maps', 'summary' => 'Maps of the Solent — places, routes and visual ways to explore the region.'],
        ['name' => 'Explore / Opinion', 'summary' => 'Comment, columns and opinion pieces on Solent life.'],
        ['name' => 'Explore / Organisations', 'summary' => 'Directory of organisations, businesses and groups active in the Solent.'],
        ['name' => 'Explore / Themes', 'summary' => 'Cross-cutting themes connecting content from across the Solent.'],
      ],
    ],
    'About' => [
      'summary' => 'About The Solent Metropolitan — our purpose, team, policies and how to get in touch.',
      'children' => [
        ['name' => 'About / Why?', 'summary' => 'Why The Solent Metropolitan exists — the purpose behind the project.'],
        ['name' => 'About / Editorial Policy', 'summary' => 'Our editorial standards and how we decide what to publish.'],
        ['name' => 'About / Our Services', 'summary' => 'Services The Solent Metropolitan offers to partners and the community.'],
        ['name' => 'About / Our Team', 'summary' => 'The people behind The Solent Metropolitan.'],
        ['name' => 'About / Contact Us', 'summary' => 'How to get in touch with The Solent Metropolitan team.'],
        ['name' => 'About / Accessibility', 'summary' => 'Our approach to making The Solent Metropolitan accessible to everyone.'],
        ['name' => 'About / Privacy Policy', 'summary' => 'How we handle personal data and protect visitor privacy.'],
        ['name' => 'About / Terms of Use', 'summary' => 'Terms governing use of The Solent Metropolitan website.'],
      ],
    ],
  ];
}

/**
 * Write a message to stdout.
 */
function tt_output($message) {
  echo $message . PHP_EOL;
}

/**
 * Find an existing term in VOCABULARY by name and parent tid.
 *
 * Parent tid 0 means "top-level" in Drupal's taxonomy hierarchy.
 *
 * @return int|null
 *   The term id, or NULL if no match.
 */
function tt_find_term($name, $parent_tid) {
  $query = \Drupal::entityQuery('taxonomy_term')
    ->accessCheck(FALSE)
    ->condition('vid', VOCABULARY)
    ->condition('name', $name)
    ->condition('parent', $parent_tid);
  $tids = $query->execute();
  if (empty($tids)) {
    return NULL;
  }
  return (int) reset($tids);
}

// ---------------------------------------------------------------------------
// Entry point.
// ---------------------------------------------------------------------------

$dry_run = FALSE;
if (isset($extra) && is_array($extra)) {
  foreach ($extra as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
      $dry_run = TRUE;
    }
  }
}

// Confirm the vocabulary exists.
$vocab = \Drupal::entityTypeManager()
  ->getStorage('taxonomy_vocabulary')
  ->load(VOCABULARY);
if (!$vocab) {
  tt_output("ERROR: Vocabulary '" . VOCABULARY . "' does not exist. Aborting.");
  exit(1);
}

// Detect whether field_reader_summary is present on the topic bundle.
$field_definitions = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('taxonomy_term', VOCABULARY);
$has_summary_field = isset($field_definitions[SUMMARY_FIELD]);
if (!$has_summary_field) {
  tt_output("WARNING: Field '" . SUMMARY_FIELD . "' not found on vocabulary '" . VOCABULARY . "'. Terms will be created without reader summaries.");
}

$definition = topic_taxonomy_definition();

tt_output('Generating Topic taxonomy from definition ('
  . count($definition) . ' parents).');
if ($dry_run) {
  tt_output('DRY RUN MODE — no changes will be written.');
}
tt_output('');

$created_parents = 0;
$skipped_parents = 0;
$created_children = 0;
$skipped_children = 0;

$parent_weight = 0;
foreach ($definition as $parent_name => $parent_data) {
  $existing_tid = tt_find_term($parent_name, 0);
  if ($existing_tid !== NULL) {
    tt_output("[parent] '$parent_name' — already exists (tid $existing_tid), skipping.");
    $skipped_parents++;
    $parent_tid = $existing_tid;
  }
  else {
    tt_output("[parent] '$parent_name' — creating (weight $parent_weight).");
    if ($dry_run) {
      // Use a sentinel tid for dry-run child lookups.
      $parent_tid = 0;
    }
    else {
      $values = [
        'vid' => VOCABULARY,
        'name' => $parent_name,
        'weight' => $parent_weight,
        'parent' => [0],
      ];
      if ($has_summary_field) {
        $values[SUMMARY_FIELD] = mb_substr($parent_data['summary'], 0, SUMMARY_MAX);
      }
      $term = Term::create($values);
      $term->save();
      $parent_tid = (int) $term->id();
      tt_output("           created tid $parent_tid.");
    }
    $created_parents++;
  }

  $child_weight = 0;
  foreach ($parent_data['children'] as $child) {
    $child_name = $child['name'];
    // In dry-run mode we don't have a real parent tid for fresh parents, so
    // skip the child existence check in that case — we're only reporting
    // what would happen.
    $existing_child_tid = ($dry_run && $parent_tid === 0)
      ? NULL
      : tt_find_term($child_name, $parent_tid);

    if ($existing_child_tid !== NULL) {
      tt_output("  [child]  '$child_name' — already exists (tid $existing_child_tid), skipping.");
      $skipped_children++;
    }
    else {
      tt_output("  [child]  '$child_name' — creating (weight $child_weight).");
      if (!$dry_run) {
        $values = [
          'vid' => VOCABULARY,
          'name' => $child_name,
          'weight' => $child_weight,
          'parent' => [$parent_tid],
        ];
        if ($has_summary_field) {
          $values[SUMMARY_FIELD] = mb_substr($child['summary'], 0, SUMMARY_MAX);
        }
        $term = Term::create($values);
        $term->save();
      }
      $created_children++;
    }
    $child_weight++;
  }

  $parent_weight++;
  tt_output('');
}

tt_output('--- Summary ---');
tt_output("Parents created: $created_parents");
tt_output("Parents skipped (already existed): $skipped_parents");
tt_output("Children created: $created_children");
tt_output("Children skipped (already existed): $skipped_children");

if ($dry_run) {
  tt_output('');
  tt_output('DRY RUN complete. Re-run without --dry-run to apply.');
  return;
}

tt_output('');
tt_output('Rebuilding caches...');
drupal_flush_all_caches();
tt_output('Done.');
