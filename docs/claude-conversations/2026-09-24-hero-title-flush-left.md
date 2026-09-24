# Front page hero: align "Welcome to" with the "What's on" heading

**Date:** 2026-09-24
**Branch:** `hero-title-flush-left`

## Ask

On desktop, the front page hero title "Welcome to The Solent Metropolitan"
sits further right than the "What's on across the Greater Solent" heading
below it. Rob's diagnosis: the padding on the cut-out pushes the title
right, so shift the cut-out left by setting the `.alignment-clamp`
padding-left to 0 (`css/paragraph-hero-art-style.css`, line 16). Can this
be done as a classy style rather than hard-coding it? Would the
page_specific_class module help with front-page specificity?

## What was measured

At 1440px wide, before the change:

| Element | Left edge |
|---|---|
| Hero paragraph padding (container centring) | 40.5px |
| Cut-out box, after the clamp's 1em | 56.5px |
| "Welcome to" text, after the cut-out's 0.6em | 73.8px |
| "What's on" text | 56.5px |

So the cut-out *box* already sat on the alignment line; it was the text
inside it that was 17px to the right. Dropping the clamp's 1em moves the
text to 57.8px, within 1.3px of the heading (the clamp's em is 16px, the
title's 0.6em is 17.3px because the title font is larger). The cut-out box
then overhangs the line by 16px, which is the look Rob described: text
aligned, solid block extending left.

## What was done

- **New classy style** `hero_title_flush_left`, label "Hero: Title Flush
  Left (desktop)", class `slnt-hero-title-flush-left`. Created via drush
  and exported, so it has a uuid.
- **CSS** in `paragraph-hero-art-style.css`, inside the existing
  desktop media query (min-width 800px):
  `.paragraph--type--hero-with-art-style.slnt-hero-title-flush-left > .alignment-clamp { padding-left: 0; }`
  Mobile is untouched (clamp keeps its 16px there, checked at 390px).
- The hero template already loops over every `field_classy` item and
  `field_classy` is multi-value, so this stacks with "Home - Front page".
  No template change.
- Applied the style to the Home hero paragraph (node 17, paragraph 810)
  locally for verification. On prod this is an editor step: edit Home,
  open the hero paragraph, add "Hero: Title Flush Left (desktop)" to its
  Classy styles alongside "Home - Front page".

## Why not page_specific_class

It keys on URL and adds a body class, so the rule would have to be
`body.front-page .paragraph--type--hero-with-art-style > .alignment-clamp`.
That couples the theme to a path, and it is invisible in the editor. The
classy style is chosen per paragraph, shows up in the edit form, and can
be reused on any other page whose hero is followed directly by a heading.

## Deploy

`scripts/release-2026-09-24-hero-title-flush-left.sh`, run on the prod
server after `git pull`. It imports the classy style config, then runs
`scripts/front_page_hero_flush_left.php`, which adds the style to the
front page hero paragraph, keeping the styles already on it. Both are
idempotent; the PHP script takes `--dry-run`. Tested locally on both
paths (style missing, style already present).
