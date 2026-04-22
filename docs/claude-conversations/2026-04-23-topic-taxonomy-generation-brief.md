# The Solent Metropolitan — Topic Taxonomy Generation Brief

## Overview

Write a drush-executable PHP script to populate the existing **Topic** taxonomy vocabulary with a hierarchical term structure that mirrors the "Main navigation" menu. The Topic vocabulary already exists and is empty.

---

## Prerequisites

Before running the script, manually add this field to the Topic vocabulary via the Drupal admin UI:

- **field_reader_summary** — Plain text field, max 200 characters. This is a reader-facing summary of the term (separate from the built-in description field which is editor-facing). The script will populate this with a generated sentence for each term.

---

## What the script does

1. Creates **top-level parent terms** for each of the five main menu sections: Culture, Sectors, Living, Explore, About
2. Creates **child terms** for each submenu item under those sections, with the correct parent-child relationship
3. **Excludes** submenu items that begin with "View All"
4. Sets the **term weight** on each term to match the menu item display order (first submenu item = weight 0, second = weight 1, etc.)
5. Populates **field_reader_summary** with a short reader-facing sentence for each term (see guidelines below)
6. Is **idempotent** — safe to re-run without creating duplicates. Before creating a term, check if a term with the same name and parent already exists in the Topic vocabulary. If it does, skip it.

---

## Term naming convention

- **Parent terms** use the section name in title case: `Culture`, `Sectors`, `Living`, `Explore`, `About`
- **Child terms** include the parent name, a space-slash-space separator, and the submenu item name in title case: `Sectors / Education`, `Culture / Technology`, `Living / Mental Health`
- This naming convention disambiguates terms that share a submenu name under different parents (e.g. `Culture / Technology` vs `Sectors / Technology`, `Culture / Science` vs `Sectors / Science`)

---

## Complete term list

### Culture (parent, weight 0)
| Weight | Term name |
|--------|-----------|
| 0 | Culture / Art & Design |
| 1 | Culture / Community |
| 2 | Culture / Dance |
| 3 | Culture / Faith |
| 4 | Culture / Festivals |
| 5 | Culture / Food & Drink |
| 6 | Culture / Games |
| 7 | Culture / Heritage |
| 8 | Culture / Language |
| 9 | Culture / Maritime |
| 10 | Culture / Music |
| 11 | Culture / Outdoor & Active |
| 12 | Culture / Photography |
| 13 | Culture / Radio & Podcast |
| 14 | Culture / Science |
| 15 | Culture / Screen |
| 16 | Culture / Sport |
| 17 | Culture / Stage |
| 18 | Culture / Style |
| 19 | Culture / Talks |
| 20 | Culture / Technology |
| 21 | Culture / Writing |
| 22 | Culture / Workshops |
| 23 | Culture / Volunteering |

### Sectors (parent, weight 1)
| Weight | Term name |
|--------|-----------|
| 0 | Sectors / Arts |
| 1 | Sectors / Construction |
| 2 | Sectors / Consulting |
| 3 | Sectors / Creative |
| 4 | Sectors / Defence & Military |
| 5 | Sectors / Democracy |
| 6 | Sectors / Design |
| 7 | Sectors / Education |
| 8 | Sectors / Engineering |
| 9 | Sectors / Entrepreneur |
| 10 | Sectors / Environment |
| 11 | Sectors / Event & Venue |
| 12 | Sectors / Facilities |
| 13 | Sectors / Farming |
| 14 | Sectors / Finance |
| 15 | Sectors / Health & Care |
| 16 | Sectors / Hospitality |
| 17 | Sectors / Legal |
| 18 | Sectors / Lifestyle |
| 19 | Sectors / Logistics |
| 20 | Sectors / Manufacturing |
| 21 | Sectors / Maritime |
| 22 | Sectors / Marketing |
| 23 | Sectors / Media |
| 24 | Sectors / Non-profit |
| 25 | Sectors / Property |
| 26 | Sectors / Public Sector |
| 27 | Sectors / Retail |
| 28 | Sectors / Science |
| 29 | Sectors / Sport & Fitness |
| 30 | Sectors / Technology |
| 31 | Sectors / Tourism |
| 32 | Sectors / Trades |
| 33 | Sectors / Transport |
| 34 | Sectors / Utilities |

### Living (parent, weight 2)
| Weight | Term name |
|--------|-----------|
| 0 | Living / Advice |
| 1 | Living / Education |
| 2 | Living / Family |
| 3 | Living / Fitness |
| 4 | Living / Health |
| 5 | Living / Home & Garden |
| 6 | Living / Housing |
| 7 | Living / Mental Health |
| 8 | Living / Outreach |
| 9 | Living / Work |

### Explore (parent, weight 3)
| Weight | Term name |
|--------|-----------|
| 0 | Explore / Archive |
| 1 | Explore / Articles |
| 2 | Explore / Collaborations |
| 3 | Explore / Data |
| 4 | Explore / Events |
| 5 | Explore / Jobs Boards |
| 6 | Explore / Maps |
| 7 | Explore / Opinion |
| 8 | Explore / Organisations |
| 9 | Explore / Themes |

### About (parent, weight 4)
| Weight | Term name |
|--------|-----------|
| 0 | About / Why? |
| 1 | About / Editorial Policy |
| 2 | About / Our Services |
| 3 | About / Our Team |
| 4 | About / Contact Us |
| 5 | About / Accessibility |
| 6 | About / Privacy Policy |
| 7 | About / Terms of Use |

---

## Technical requirements

- **Vocabulary machine name:** `topic` (verify by checking the existing empty vocabulary)
- **Script location:** Place in a sensible location such as `scripts/` or `drush/` in the project root
- **Execution:** The script should be runnable via `drush php:script <path-to-script>`
- **Parent-child relationships:** Set explicitly using the `parent` field on each term — do not rely on the naming convention to imply hierarchy. The term name includes the parent prefix for display/disambiguation purposes; the actual hierarchy must be set structurally.
- **field_reader_summary:** Populate with a generated sentence for each term (see guidelines below). If the field doesn't exist yet (because the admin hasn't added it), the script should not fail — log a warning and continue without populating.
- **Built-in description field:** Leave empty (will be used for editor-facing notes later).
- **After completion:** The script should output a summary of what was created and what was skipped (already existed).
- **Run `drush cr`** (cache rebuild) after the script completes, or include it in the script.

---

## field_reader_summary guidelines

Generate a short reader-facing sentence (under 200 characters) for each term. These summaries will be displayed to readers to explain what content they can expect to find under each topic.

### Tone and framing
- **Descriptive and inviting**, not definitional or dry
- Frame as what the reader will discover, not a dictionary definition
- Should work naturally in a display context like: "In Culture: [summary]"
- Use language that reflects the Greater Solent regional focus where appropriate

### Parent terms
- Summaries should describe the broad scope of the section
- Example for Culture: "The arts, heritage, food, sport, community life and creative energy of the Greater Solent"
- Example for Sectors: "The industries, businesses and organisations driving the Solent economy"

### Child terms
- Summaries should describe what content the reader will find, in the context of the parent section
- **For terms that exist under multiple parents, the summaries must reflect the different contextual meaning of each.** This is critical.
- Example — Culture / Technology: "How technology shapes culture and creativity across the Solent"
- Example — Sectors / Technology: "Technology companies, startups and digital innovation in the Solent economy"
- Example — Culture / Maritime: "The maritime heritage, traditions and seafaring culture of the Solent"
- Example — Sectors / Maritime: "Shipping, ports, marine engineering and the Solent's maritime industries"
- Example — Living / Education: "Learning opportunities, schools, courses and educational life in the Solent"
- Example — Sectors / Education: "Educational institutions, training providers and the education sector"

### What to avoid
- Don't repeat the term name verbatim as the first words ("Technology is...")
- Don't use generic phrasing that could apply to any region — ground it in the Solent where it feels natural
- Don't exceed 200 characters — these need to be concise
- Don't make them sound like marketing copy — keep them informative and genuine

---

## What this taxonomy enables (context for Claude Code)

This taxonomy is the structural backbone of the site's content organisation. Content types (Article, Event, Organisation) will have a **primary topic** field (single value, entity reference to Topic) that determines where content appears. A **related topics** field (multi-value) enables content to surface on other section pages as related content. The taxonomy hierarchy will also drive the navigation menu's visual state (highlighting the current section and page), though that is a separate piece of work.

The term names include the parent prefix (e.g. "Sectors / Education") deliberately — this disambiguates terms in the editorial UI where editors choose terms from autocomplete or select lists. Terms like "Technology" exist under both Culture and Sectors with different contextual meanings.
