# Brief: Update Hero Banner SVG Tiles

## Context

The Solent Metropolitan website uses repeating SVG icon pattern tiles overlaid on gradient backgrounds as hero banners. These are built from Phosphor Icons (Bold weight) using a hexagonal grid for uniform spacing.

The existing build pipeline is at:
```
scripts/generate-patterned-hero-banners/drush_scripts/
├── build_tiles.py          # Positions icons into hex-grid tile SVGs
├── fetch_and_build_tiles.sh # Downloads Phosphor Bold SVGs, calls build_tiles.py
```

The CSS is at:
```
web/themes/custom/customsolent/css/hero-art-styles.css
```

The SVG tiles output to:
```
web/themes/custom/customsolent/images/hero-tiles/
```

Classy Paragraphs YAML configs are in:
```
config/sync/classy_paragraphs.classy_paragraphs_style.hero_art_style_*.yml
```

The Drush insertion script is at:
```
scripts/generate-patterned-hero-banners/drush_scripts/drush_insert_hero_paragraphs.php
```

## How the tile system works

### Hex grid geometry
- SPACING = 60px (distance between icon centres)
- Tile width = 2 × SPACING = 120px
- Tile height = SPACING × √3 ≈ 103.92px
- 4 icon positions per tile in hex pattern
- Icons at tile edges are DUPLICATED at wrapped positions for seamless CSS repeat
- CSS `background-size: 120px 103.92px`

### Icon rendering
- Source: Phosphor Icons Bold weight
- SVG URL pattern: `https://raw.githubusercontent.com/phosphor-icons/core/main/assets/bold/{name}-bold.svg`
- Rendered at 28px within the tile
- White fill, 12% opacity
- Phosphor Bold SVGs use 256×256 viewBox

### Build process
1. `fetch_and_build_tiles.sh` downloads icons to `phosphor_icons_cache/`
2. `build_tiles.py` reads cached SVGs and generates tile SVGs
3. Icon names are defined in `PAGE_MAP` dict in `build_tiles.py`
4. If an icon can't be found, the script prints a WARNING — **check for these**

### CSS structure
Each class follows this pattern:
```css
.hero-art-style--{section}-{page} {
  background:
    url('../images/hero-tiles/{section}_{page}.svg') repeat,
    linear-gradient(135deg, {start} 0%, {mid} 50%, {end} 100%);
  background-size: 120px 103.92px, cover;
}
```

### Gradient colours by section
| Section  | Start   | Mid     | End     | Description |
|----------|---------|---------|---------|-------------|
| Culture  | #6B21A8 | #7C3AED | #A855F7 | Purple      |
| Sectors  | #1D4ED8 | #2563EB | #3B82F6 | Blue        |
| Living   | #047857 | #059669 | #10B981 | Green       |
| About    | #334155 | #475569 | #64748B | Slate       |
| Explore  | #B45309 | #D97706 | #F59E0B | Amber       |

### YAML config structure
```yaml
langcode: en
status: true
dependencies: {}
id: hero_art_style_{section}_{page}
label: {Section} - {Page Name}
classes: hero-art-style--{section}-{page}
```

### Drush insertion script
The `$page_map` array maps URL aliases to classy paragraph config IDs:
```php
'/culture/music' => 'hero_art_style_culture_music',
```

---

## Task: Update existing tiles

For each item below, update the `PAGE_MAP` entry in `build_tiles.py` with the new icon names. Then regenerate only the affected tiles.

**IMPORTANT: Before building, verify each icon exists.** Run:
```bash
curl -sI "https://raw.githubusercontent.com/phosphor-icons/core/main/assets/bold/{icon-name}-bold.svg" | head -1
```
If you get a 404, find an alternative from Phosphor's bold set. The `fetch_and_build_tiles.sh` script also prints WARNING for any icon it can't download.

---

### 1. Culture — Faith

**Current icons:** `church`, `hands-praying`, `cross`
**Problem:** Only represents Christianity. Needs multi-faith representation.
**New icons:** `hands-praying`, `cross`, `star-of-david`

The hands-praying icon is universally spiritual. Cross represents Christianity. Star-of-david for Judaism.

**Fallback if `star-of-david` doesn't exist:** Try `star-four-pointed` or `star`. For Islamic representation, try `moon` (crescent moon is an Islamic symbol) — if using moon, go with `hands-praying`, `cross`, `moon`.

**Note on approach:** The editorial intent is to represent interfaith inclusivity without being tokenistic. Three recognisable symbols from different traditions is better than trying to squeeze more in.

**Hex arrangement:** Currently fine — no geometry fix needed, just icon swap.

---

### 2. Culture — Festivals (NEW)

**This is a new entry.** Festivals is a new Culture submenu item. It previously used Music's tile.
**Machine name:** `culture_festivals`
**CSS class:** `hero-art-style--culture-festivals`
**URL alias:** `/culture/festivals`
**Gradient:** Culture purple (#6B21A8 → #7C3AED → #A855F7)

**Icons:** `confetti`, `tent`, `flag-pennant`

These represent the breadth of festivals — literary, science, music, food — without being genre-specific. Confetti = celebration. Tent = outdoor festival venue. Flag-pennant = bunting/festival decoration.

**Fallbacks:** If `confetti` doesn't exist, try `party-popper` or `sparkle`. If `tent` doesn't exist, try `campfire` or `star`. If `flag-pennant` doesn't exist, try `flag-banner` or `flag`.

**Requires:**
- New entry in `PAGE_MAP` in `build_tiles.py`
- New CSS class in `hero-art-styles.css`
- New YAML config: `classy_paragraphs.classy_paragraphs_style.hero_art_style_culture_festivals.yml`
- New entry in `$page_map` in `drush_insert_hero_paragraphs.php`
- New icon names added to `ICONS` array in `fetch_and_build_tiles.sh`

---

### 3. Culture — Identity (NEW)

**This is a new entry.** Identity covers the Solent concept — regional identity, how citizens of Portsmouth and Southampton see themselves.
**Machine name:** `culture_identity`
**CSS class:** `hero-art-style--culture-identity`
**URL alias:** `/culture/identity`
**Gradient:** Culture purple (#6B21A8 → #7C3AED → #A855F7)

**Icons:** `fingerprint`, `compass`, `flag-banner`

**Rationale:** Fingerprint = unique identity, individuality. Compass = finding your bearing, orientation, sense of place. Flag-banner = belonging, community, pride.

This is deliberately abstract. The editorial intent is to NOT represent specific identity categories (race, sexuality, subculture) explicitly — that would risk stereotyping. These icons suggest "who you are" and "where you belong" without narrowing it.

**Fallbacks:** If `flag-banner` doesn't exist, try `flag`, `flag-pennant`, or `shield`. If `fingerprint` doesn't exist, try `identification-badge` or `identification-card`.

**Requires:**
- New entry in `PAGE_MAP` in `build_tiles.py`
- New CSS class in `hero-art-styles.css`
- New YAML config: `classy_paragraphs.classy_paragraphs_style.hero_art_style_culture_identity.yml`
- New entry in `$page_map` in `drush_insert_hero_paragraphs.php`
- New icon names added to `ICONS` array in `fetch_and_build_tiles.sh`

---

### 4. Culture — Outdoor & Active

**Current entry key:** `culture_outdoors` — icons: `tree`, `mountains`, `compass`
**Rename to:** `culture_outdoor_active`
**New CSS class:** `hero-art-style--culture-outdoor-active`
**New URL alias:** `/culture/outdoor-active`

**New icons:** `tree`, `mountains`, `person-simple-run`

Swap `compass` for `person-simple-run` to reflect the "Active" addition. Tree and mountains stay — they represent outdoors well. The running figure adds the active/sport dimension.

**Fallback:** If `person-simple-run` doesn't exist, try `person-simple-walk`, `bicycle`, or `sneaker`.

**Requires:**
- Update `PAGE_MAP` key from `culture_outdoors` to `culture_outdoor_active`
- Update CSS: remove old `.hero-art-style--culture-outdoors` class, add new `.hero-art-style--culture-outdoor-active`
- New YAML config (and remove old one): `hero_art_style_culture_outdoor_active`
- Update `$page_map` in Drush script: change alias and config ID

---

### 5. Culture — Stage

**Current icons:** `masks-theater`, `microphone-stage` (only 2 icons)
**New icons:** `masks-theater`, `microphone-stage`, `ticket`

Adding `ticket` gives a third icon for better hex distribution. Ticket represents going to see a show — universal to theatre, comedy, concerts.

**Fallback:** If `ticket` doesn't exist, try `receipt` or `star`.

**Hex arrangement:** Adding a 3rd icon fixes the 2-icon limitation. The hex grid cycles 3 icons across 4 positions as A, B, C, A.

---

### 6. Culture — Style

**Current icons:** `t-shirt`, `scissors`, `coat-hanger`
**Problem:** Too focused on sewing/tailoring. Style is broader — fashion, personal expression, aesthetics.
**New icons:** `t-shirt`, `sunglasses`, `sneaker`, `coat-hanger`

T-shirt = clothing. Sunglasses = style/cool/fashion. Sneaker = streetwear/contemporary. Coat-hanger = wardrobe/fashion retail.

4 icons maps perfectly to the hex grid's 4 positions — each position gets a unique icon with no cycling. This is the cleanest possible arrangement.

**Fallbacks:** If `sunglasses` doesn't exist, try `eye-glasses` or `diamond`. If `sneaker` doesn't exist, try `shoe` or `crown`.

**Hex arrangement:** Currently fine — just icon swap.

---

### 7. Culture — Technology

**Current icons:** `cpu`, `code`, `circuit-board` (3 icons but possibly only 2 rendering)
**Problem:** `circuit-board` may not exist in Phosphor Bold, causing only 2 icons to render, breaking hex uniformity.
**New icons:** `cpu`, `code`, `laptop`

`laptop` is a safe, widely available icon. Represents everyday technology well for the Culture context (people using tech, attending tech events).

**Fallback:** If `laptop` doesn't exist, try `desktop`, `monitor`, or `desktop-tower`.

---

### 8. Sectors — Arts

**Current icons:** `palette`, `paint-brush` (only 2 icons)
**New icons:** `palette`, `paint-brush`, `frame-corners`

Adding `frame-corners` (picture frame) gives a 3rd icon and broadens from just painting to exhibiting/gallery arts.

**Fallback:** If `frame-corners` doesn't exist, try `image`, `pencil`, or `easel`.

---

### 9. Sectors — Farming

**Current icons:** `plant`, `sun`, `grain` (3 in config but likely only 2 rendering)
**Problem:** `grain` probably doesn't exist in Phosphor Bold.
**New icons:** `plant`, `sun`, `leaf`

`leaf` is a reliable Phosphor icon and works well for farming/agriculture.

**Fallback:** `tree` if `leaf` doesn't exist (unlikely — `leaf` is core Phosphor).

---

### 10. Sectors — Manufacturing

**Current icons:** `factory`, `gear`, `conveyor-belt` (3 in config but likely only 2 rendering)
**Problem:** `conveyor-belt` probably doesn't exist in Phosphor Bold.
**New icons:** `factory`, `gear`, `package`

`package` represents manufactured output/product. Completes the production story: factory (where), gear (how), package (what comes out).

**Fallback:** `cube` or `wrench` if `package` doesn't exist.

---

### 11. About — Accessibility (NEW)

**This is a new entry.** Currently using Outreach's tile as a placeholder.
**Machine name:** `about_accessibility`
**CSS class:** `hero-art-style--about-accessibility`
**URL alias:** `/about/accessibility`
**Gradient:** About slate (#334155 → #475569 → #64748B)

**Icons:** `wheelchair`, `eye`, `ear`

These represent three key dimensions of accessibility: mobility, vision, hearing. Clean, recognisable, respectful.

**Fallbacks:** If `wheelchair` doesn't exist, try `person-arms-spread`. If `ear` doesn't exist, try `speaker-high`. If `eye` doesn't exist (unlikely), try `magnifying-glass`.

**Requires:**
- New entry in `PAGE_MAP` in `build_tiles.py`
- New CSS class in `hero-art-styles.css`
- New YAML config: `classy_paragraphs.classy_paragraphs_style.hero_art_style_about_accessibility.yml`
- New entry in `$page_map` in `drush_insert_hero_paragraphs.php`
- New icon names added to `ICONS` array in `fetch_and_build_tiles.sh`

---

## Summary of changes

### Updates to existing entries (8)
| # | Section  | Page          | Change type        |
|---|----------|---------------|--------------------|
| 1 | Culture  | Faith         | Icon swap          |
| 4 | Culture  | Outdoor & Active | Rename + icon swap |
| 5 | Culture  | Stage         | Add 3rd icon       |
| 6 | Culture  | Style         | Icon swap          |
| 7 | Culture  | Technology    | Fix broken icon    |
| 8 | Sectors  | Arts          | Add 3rd icon       |
| 9 | Sectors  | Farming       | Fix broken icon    |
| 10| Sectors  | Manufacturing | Fix broken icon    |

### New entries (3)
| # | Section | Page          | Machine name          |
|---|---------|---------------|-----------------------|
| 2 | Culture | Festivals     | culture_festivals     |
| 3 | Culture | Identity      | culture_identity      |
| 11| About   | Accessibility | about_accessibility   |

### Files to modify
1. `build_tiles.py` — Update/add PAGE_MAP entries
2. `fetch_and_build_tiles.sh` — Add new icon names to ICONS array
3. `hero-art-styles.css` — Add 3 new classes, update 1 renamed class
4. `drush_insert_hero_paragraphs.php` — Add 3 new entries, update 1 renamed entry
5. Create 3 new YAML configs in `config/sync/`
6. Remove 1 old YAML config (culture_outdoors → culture_outdoor_active)

### After changes
1. Run `fetch_and_build_tiles.sh` to download any new icons and regenerate all tiles
2. Check output for any WARNING messages about missing icons
3. Copy generated tiles to `web/themes/custom/customsolent/images/hero-tiles/`
4. Run `drush php:script drush_insert_hero_paragraphs.php` for new entries
5. `drush cex` → commit → deploy → `drush cim` → `drush cr`

### Verification
After deployment, visually check each updated/new banner:
- All icons should be complete (not cropped at edges)
- Icons should be evenly spaced in a honeycomb pattern
- 3-icon tiles cycle A, B, C, A across the 4 hex positions
