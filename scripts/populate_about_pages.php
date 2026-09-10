<?php

/**
 * @file
 * Populates the About section pages with first-draft content and finishes
 * the article -> composite page restructuring.
 *
 * Run with: drush php:script scripts/populate_about_pages.php
 *
 * What it does:
 *   1. /about (87)              — intro + table of contents (absorbs Overview).
 *   2. /about/our-services (101)— services content based on the mission.
 *   3. /about/editorial-policy (108) — impartiality, accuracy, independence.
 *   4. /about/accessibility (112)    — font, contrast, keyboard navigation.
 *   5. /about/privacy-policy (106)   — minimal-data policy, contact form.
 *   6. /about/terms (107)            — GPL v3 code, content/brand copyright.
 *   7. Creates a "Our team" composite page (banner: hero_art_style_about_our_team)
 *      from the Team article's content, moves the /about/team alias to it,
 *      and unpublishes the Team article (node 15).
 *   8. Retires Overview: unpublishes node 1, disables its main-menu item,
 *      removes its alias and 301-redirects /about/overview -> /about (node 87).
 *
 * Node IDs are shared between environments (local DB is refreshed from prod),
 * so this runs identically on both. The team-page creation and Overview
 * retirement are idempotent. The text updates (1-6) are deterministic
 * overwrites: re-running is safe, but DO NOT re-run after editing those
 * pages in the admin UI — it would clobber the edits.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\redirect\Entity\Redirect;

/**
 * Replaces the first text paragraph inside a node's first enclosure.
 */
function slnt_update_first_text(int $nid, string $html): void {
  $node = Node::load($nid);
  if (!$node) {
    echo "SKIP: node $nid not found.\n";
    return;
  }
  foreach ($node->get('field_content_component') as $item) {
    $enclosure = $item->entity;
    if (!$enclosure || $enclosure->bundle() !== 'enclosure') {
      continue;
    }
    foreach ($enclosure->get('field_content_component') as $inner) {
      $text = $inner->entity;
      if ($text && $text->bundle() === 'text') {
        $text->set('field_text', ['value' => $html, 'format' => 'basic_html']);
        // In-place update: keeps every existing revision reference valid.
        $text->setNewRevision(FALSE);
        $text->save();
        echo "updated: node $nid ({$node->getTitle()}) text paragraph {$text->id()}\n";
        return;
      }
    }
  }
  echo "SKIP: node $nid — no text paragraph found in an enclosure.\n";
}

// ---------------------------------------------------------------------------
// 1. /about — intro + table of contents (absorbs the Overview page).
// ---------------------------------------------------------------------------
slnt_update_first_text(87, <<<'HTML'
<p>The Solent Metropolitan is an independent, grass-roots media platform for the Greater Solent &mdash; Southampton, Portsmouth, the Isle of Wight, and the towns and villages connected by the Solent water on the central southern coast of the UK.</p>
<p>We champion the whole region, and both its cities equally: success happens when you're in good company. We're apolitical, self-funded and independent &mdash; not connected to any public sector organisation, and not a clone of a platform used elsewhere. We publish slow content: events, organisation listings, articles and ideas that take a deeper look over time, connecting people, places and projects across the region rather than chasing the news cycle.</p>
<p>The site is made and built here in the Solent, and its source code is open source &mdash; like the public interest sites it takes inspiration from.</p>
<h2>In this section</h2>
<ul>
<li><a href="/about/team">Our team</a> &mdash; who is behind The Solent Metropolitan.</li>
<li><a href="/about/our-services">Our services</a> &mdash; what we offer: an events guide, organisation listings, articles and the connections between them.</li>
<li><a href="/about/editorial-policy">Editorial policy</a> &mdash; how we stay impartial, accurate and independent.</li>
<li><a href="/about/accessibility">Accessibility</a> &mdash; how this site is designed to be usable by everyone.</li>
<li><a href="/about/privacy-policy">Privacy policy</a> &mdash; what data we collect (very little) and how we look after it.</li>
<li><a href="/about/terms">Terms of use</a> &mdash; open source code, copyright and using the site.</li>
<li><a href="/about/contact">Contact us</a> &mdash; get in touch.</li>
</ul>
HTML);

// ---------------------------------------------------------------------------
// 2. /about/our-services — grounded in the front-page mission.
// ---------------------------------------------------------------------------
slnt_update_first_text(101, <<<'HTML'
<p>The Solent Metropolitan champions the Greater Solent in three dimensions &mdash; Culture, Sectors and Living &mdash; the triple helix that makes up the region's DNA. Everything we offer serves that mission: giving visibility to what's happening here, and connecting the people and organisations making it happen.</p>
<h2>What we offer</h2>
<ul>
<li><strong>An events guide</strong> &mdash; curated listings of what's on across the region, in arts, science, technology, business and more. Trusted local organisations can be given a login to publish and update their own events.</li>
<li><strong>Organisation listings</strong> &mdash; a growing directory of the organisations that make up the region's cultural and industrial scenes.</li>
<li><strong>Articles and slow content</strong> &mdash; a deeper look over time, not a fast-moving news feed: positive but thought-provoking coverage of the region and its people.</li>
<li><strong>Curated links</strong> &mdash; signposts to the best of what others are doing; we'd rather point to good work than duplicate it.</li>
<li><strong>Connections</strong> &mdash; cross-promotion between events, organisations, articles and places, linking related things together so each is more visible than it would be alone.</li>
<li><strong>A think tank</strong> &mdash; a home for grand ideas about the region's future, from public infrastructure and transport to the hubs that uplift what's already going on.</li>
</ul>
<h2>What we're not</h2>
<p>We're not a site full of adverts &mdash; that market is already well served. We're independent and self-funded, which means our priority is the quality and usefulness of what we publish, not selling space around it.</p>
<h2>Work with us</h2>
<p>If you run events or an organisation in the Greater Solent and would like to be listed &mdash; or you have an idea we should hear &mdash; please <a href="/about/contact">get in touch</a>.</p>
HTML);

// ---------------------------------------------------------------------------
// 3. /about/editorial-policy — impartiality, accuracy, independence.
// ---------------------------------------------------------------------------
slnt_update_first_text(108, <<<'HTML'
<p>The Solent Metropolitan is an independent, self-funded media platform. These are the principles we hold ourselves to.</p>
<h2>Impartiality</h2>
<p>We are apolitical. We don't campaign for parties or candidates, and we don't take political sides. Where a story touches on politics or public policy, we aim to cover it fairly and let readers form their own view.</p>
<p>Impartiality also applies to place. We champion Southampton and Portsmouth equally, along with the Isle of Wight, Fareham, Gosport, Eastleigh and every community around the Solent. We believe the region succeeds together: we celebrate each place on its own merits, never one at another's expense.</p>
<h2>Accuracy</h2>
<p>We aim to get things right: to check facts before publishing, to be clear about what we know and what we don't, and to attribute information to its source where we can. When we get something wrong, we correct it promptly and transparently.</p>
<h2>Independence</h2>
<p>We are self-funded and grass-roots: not owned by, funded by or connected to any public sector organisation, political group or commercial interest. Coverage cannot be bought, and inclusion on the site is always an editorial decision.</p>
<h2>Opinion</h2>
<p>We do publish opinion &mdash; positive but thought-provoking &mdash; and when we do, it is clearly presented as opinion and kept distinct from factual reporting.</p>
<h2>Event and organisation listings</h2>
<p>Some listings are provided by the organisations themselves, and trusted organisations can publish their own events. We may edit listings for clarity or remove ones that don't meet our standards. Details can change after publication, so please check with the organiser before travelling.</p>
<h2>Feedback and complaints</h2>
<p>If you think we've fallen short of any of this, please <a href="/about/contact">contact us</a>. We'll listen, and we'll put right anything we've got wrong.</p>
HTML);

// ---------------------------------------------------------------------------
// 4. /about/accessibility — deliberately no hero banner on this page.
// ---------------------------------------------------------------------------
slnt_update_first_text(112, <<<'HTML'
<p>We want this site to be usable by everyone, and accessibility is considered in the design and development of every part of it.</p>
<h2>Typeface</h2>
<p>The site is set in Atkinson Hyperlegible Next, a typeface designed by the Braille Institute to maximise legibility. Its letterforms are deliberately distinct from one another, which helps readers with low vision &mdash; and makes reading more comfortable for everyone.</p>
<h2>Colour and contrast</h2>
<p>Our colour palette is chosen and checked for contrast, so text remains readable against the site's backgrounds, including the deep blue used in the navigation and footer.</p>
<h2>Keyboard navigation</h2>
<p>The site can be navigated fully by keyboard. Interactive elements show a clearly visible focus indicator, so you can always see where you are, and menus can be tabbed through in a logical order.</p>
<h2>Works without JavaScript</h2>
<p>Core parts of the site, including the navigation, have fallbacks that work with JavaScript switched off.</p>
<h2>Responsive design</h2>
<p>Pages adapt to your screen, from small phones to large desktop displays, without loss of content or horizontal scrolling.</p>
<h2>Always improving</h2>
<p>Accessibility is never finished. If you find any part of the site difficult to use, please <a href="/about/contact">tell us</a> &mdash; it genuinely helps, and we'll do our best to fix it.</p>
HTML);

// The Accessibility page deliberately has no hero banner; its heading
// paragraph is the page title, so it should be an h1 (it was h2, leaving
// the page without an h1 — poor heading hierarchy, of all pages this one).
$acc = Node::load(112);
if ($acc) {
  foreach ($acc->get('field_content_component') as $item) {
    $enclosure = $item->entity;
    if (!$enclosure || $enclosure->bundle() !== 'enclosure') {
      continue;
    }
    foreach ($enclosure->get('field_content_component') as $inner) {
      $h = $inner->entity;
      if ($h && $h->bundle() === 'heading' && $h->get('field_heading_size')->value !== 'h1') {
        $h->set('field_heading_size', 'h1');
        $h->setNewRevision(FALSE);
        $h->save();
        echo "updated: Accessibility title heading {$h->id()} h2 -> h1.\n";
      }
    }
  }
}

// ---------------------------------------------------------------------------
// 5. /about/privacy-policy — minimal-data stance, contact form, dormant Klaro.
// ---------------------------------------------------------------------------
slnt_update_first_text(106, <<<'HTML'
<p>We keep data collection to a minimum. In short: no advertising, no tracking, and we only hold what you choose to send us.</p>
<h2>Cookies</h2>
<p>We don't set advertising, analytics or tracking cookies. The site includes a consent manager, but it stays hidden because there is currently nothing requiring consent &mdash; no optional third-party services are active. If that ever changes, you'll be asked first before anything optional is switched on.</p>
<h2>The contact form</h2>
<p>If you use our <a href="/about/contact">contact form</a>, we receive the details you enter &mdash; your name, email address and message. We use them only to read and respond to your message. We don't add you to mailing lists, share your details with anyone else, or sell them. Messages are kept only as long as needed to deal with your enquiry.</p>
<h2>Server logs</h2>
<p>Like almost every website, our server keeps standard technical logs (such as IP addresses and pages requested) for security and troubleshooting. These are routinely rotated and are not used to profile visitors.</p>
<h2>Your rights</h2>
<p>Under UK data protection law you have rights over personal data we hold about you, including access, correction and erasure. Given how little we collect, exercising them is usually as simple as <a href="/about/contact">contacting us</a> and asking.</p>
<h2>Changes</h2>
<p>If the way the site handles data changes &mdash; for example, if an optional service requiring consent is introduced &mdash; this page will be updated to describe it.</p>
HTML);

// ---------------------------------------------------------------------------
// 6. /about/terms — open source code; copyright over content and brand.
// ---------------------------------------------------------------------------
slnt_update_first_text(107, <<<'HTML'
<h2>Open source</h2>
<p>This site's source code is open source, released under the GNU General Public License v3 and available on <a href="https://github.com/thesolentmetropolitan/thesolentmetropolitan.com">GitHub</a>. It's built on <a href="https://new.drupal.org/home">Drupal</a>, which is itself free software under the GNU GPL. You're welcome to study the code, learn from it and reuse it under the terms of that licence.</p>
<h2>Copyright</h2>
<p>The open licence covers the code, not the content. Unless otherwise stated, the content of this site &mdash; articles, images and other editorial material &mdash; is copyright &copy; The Solent Metropolitan. So are The Solent Metropolitan name and brand, the tagline "The broader perspective, for a distinct region", and the three dimensions / triple helix concept of Culture, Sectors and Living. Please ask before reusing any of these.</p>
<h2>Listings and links</h2>
<p>Event and organisation listings are published in good faith, and some details come from the organisers themselves. Details can change after publication, so always check with the organiser before travelling. Links to other websites are provided as signposts, not endorsements; we're not responsible for their content.</p>
<h2>Using the site</h2>
<p>The site is provided in good faith and as-is: we work hard on accuracy but can't warrant that everything is complete or error-free. Please use the site fairly &mdash; don't misrepresent our content as your own, harvest it wholesale, or use it to impersonate us or anyone listed here.</p>
<h2>Changes</h2>
<p>These terms may evolve as the site grows; this page always carries the current version.</p>
HTML);

// ---------------------------------------------------------------------------
// 7. "Our team" composite page from the Team article (node 15).
//
// An unpublished placeholder composite page ("Our team", node 88, with the
// hero_art_style_about_our_team banner) already exists from an earlier
// batch — reuse and populate it if present, create the page otherwise.
// ---------------------------------------------------------------------------
$team_html = <<<'HTML'
<p>The Solent Metropolitan was founded by me, <a href="https://www.linkedin.com/in/therobyouknow/">Rob Davis</a>.</p>
<p>It was borne out of a love for both Solent cities, Southampton and Portsmouth, and the areas around them &mdash; I have roots in both, from growing up, working, living, studying and playing across the region.</p>
<p>This one-off, long-term project is a reincarnation of my previous community media life: I presented and produced speech radio shows about volunteering and community across the Solent, on Unity 101.1 FM in Southampton, Skyline 102.5 FM in Hedge End and 93.7 Express FM in Portsmouth.</p>
<p>Professionally, I build online digital experiences and collaboration platforms with <a href="https://new.drupal.org/home">Drupal</a> &mdash; most recently for the United Nations, and before that for the region's universities, the Natural History Museum, government and public sector websites, and charities and social purpose campaigns including Kew, Anthony Nolan, End Child Food Poverty and The Elders.</p>
<p>The Solent Metropolitan brings those strands together. Its main drive is to curate and surface events and create the spaces around them; alongside that it's a think tank for grand ideas about the region &mdash; particularly transport and the public infrastructure and hubs that would uplift the great things already going on. Those may be a while coming, so in the meantime this is a virtual platform connecting people, ideas and events across the Solent.</p>
<p>Are we yet another account documenting the region? There's room for several platforms, and we're not here to compete or step on anyone's toes &mdash; but we do feel we have a unique offer. The project is open to collaboration with anyone who has an affinity for the region: being local has no minimum length of stay, and fresh perspectives are as welcome as deep roots. <a href="/about/contact">Get in touch</a>.</p>
HTML;

$existing = \Drupal::entityTypeManager()->getStorage('node')
  ->loadByProperties(['type' => 'composite_page', 'title' => 'Our team']);
if ($existing) {
  $team = reset($existing);
  echo "exists:  'Our team' composite page (node {$team->id()}), reusing it.\n";
}
else {
  $enclosure = Paragraph::create([
    'type' => 'enclosure',
    'field_padding' => '2em',
    'field_content_component' => [],
  ]);
  $enclosure->save();

  $hero = Paragraph::create([
    'type' => 'hero_with_art_style',
    'field_classy' => ['target_id' => 'hero_art_style_about_our_team'],
    'field_title' => 'Our team',
  ]);
  $hero->save();

  $team = Node::create([
    'type' => 'composite_page',
    'title' => 'Our team',
    'field_primary_topic' => ['target_id' => 116],
    'field_content_component' => [
      ['target_id' => $hero->id(), 'target_revision_id' => $hero->getRevisionId()],
      ['target_id' => $enclosure->id(), 'target_revision_id' => $enclosure->getRevisionId()],
    ],
  ]);
  $team->save();
  echo "created: 'Our team' composite page (node {$team->id()}).\n";
}

// Populate the team content (works for both the placeholder and a fresh
// page: replaces the first text paragraph, or adds one to the enclosure).
$team_enclosure = NULL;
$team_text = NULL;
foreach ($team->get('field_content_component') as $item) {
  if ($item->entity && $item->entity->bundle() === 'enclosure') {
    $team_enclosure = $item->entity;
    foreach ($team_enclosure->get('field_content_component') as $inner) {
      if ($inner->entity && $inner->entity->bundle() === 'text') {
        $team_text = $inner->entity;
        break;
      }
    }
    break;
  }
}
if ($team_text) {
  $team_text->set('field_text', ['value' => $team_html, 'format' => 'basic_html']);
  $team_text->setNewRevision(FALSE);
  $team_text->save();
  echo "updated: 'Our team' text paragraph {$team_text->id()}.\n";
}
elseif ($team_enclosure) {
  $team_text = Paragraph::create([
    'type' => 'text',
    'field_text' => ['value' => $team_html, 'format' => 'basic_html'],
  ]);
  $team_text->save();
  $team_enclosure->get('field_content_component')->appendItem([
    'target_id' => $team_text->id(),
    'target_revision_id' => $team_text->getRevisionId(),
  ]);
  $team_enclosure->setNewRevision(FALSE);
  $team_enclosure->save();
  echo "added: text paragraph {$team_text->id()} to 'Our team' enclosure.\n";
}
else {
  echo "WARNING: 'Our team' page has no enclosure — content not placed.\n";
}

if (!$team->isPublished()) {
  $team->setPublished()->save();
  echo "published: 'Our team' (node {$team->id()}).\n";
}

// Move the /about/team alias from the article to the composite page.
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$old_aliases = $alias_storage->loadByProperties(['path' => '/node/15', 'alias' => '/about/team']);
if ($old_aliases) {
  $alias_storage->delete($old_aliases);
  echo "deleted: /about/team alias for the Team article (/node/15).\n";
}
if (!$alias_storage->loadByProperties(['path' => '/node/' . $team->id(), 'alias' => '/about/team'])) {
  $alias_storage->create([
    'path' => '/node/' . $team->id(),
    'alias' => '/about/team',
    'langcode' => 'en',
  ])->save();
  echo "created: /about/team alias for node {$team->id()}.\n";
}

// Unpublish the Team article.
$team_article = Node::load(15);
if ($team_article && $team_article->isPublished()) {
  $team_article->setUnpublished()->save();
  echo "unpublished: Team article (node 15).\n";
}

// ---------------------------------------------------------------------------
// 8. Retire Overview: fold into /about.
// ---------------------------------------------------------------------------
$overview = Node::load(1);
if ($overview && $overview->isPublished()) {
  $overview->setUnpublished()->save();
  echo "unpublished: Overview article (node 1).\n";
}

$menu_links = \Drupal::entityTypeManager()->getStorage('menu_link_content')
  ->loadByProperties(['menu_name' => 'main', 'title' => 'Overview']);
foreach ($menu_links as $link) {
  if ($link->isEnabled()) {
    $link->set('enabled', FALSE)->save();
    echo "disabled: 'Overview' main-menu item ({$link->id()}).\n";
  }
}

$overview_aliases = $alias_storage->loadByProperties(['path' => '/node/1', 'alias' => '/about/overview']);
if ($overview_aliases) {
  $alias_storage->delete($overview_aliases);
  echo "deleted: /about/overview alias for /node/1.\n";
}

$redirect_storage = \Drupal::entityTypeManager()->getStorage('redirect');
if (!$redirect_storage->loadByProperties(['redirect_source__path' => 'about/overview'])) {
  Redirect::create([
    'redirect_source' => ['path' => 'about/overview', 'query' => []],
    'redirect_redirect' => ['uri' => 'internal:/node/87'],
    'status_code' => 301,
    'language' => 'und',
  ])->save();
  echo "created: /about/overview -> /node/87 (301, resolves to /about).\n";
}

echo "Done.\n";
