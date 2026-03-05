# Hero Banner Art Styles
## The Solent Metropolitan

Generated configuration for 72 hero banner icon pattern styles.

## Contents

```
hero-banner-config/
├── config_sync/          # 72 Classy Paragraphs YAML configs
│   └── classy_paragraphs.classy_paragraphs_style.hero_art_style_*.yml
├── css/
│   └── hero-art-styles.css    # All 72 CSS classes
├── drush_scripts/
│   ├── drush_import_classy_paragraphs.php  # Step 1: Import configs
│   ├── drush_insert_hero_paragraphs.php    # Step 3: Insert paragraphs
│   ├── fetch_and_build_tiles.sh            # Step 2a: Fetch icons
│   └── build_tiles.py                      # Step 2b: Build SVG tiles
└── README.md
```

## Workflow

### Step 1: Import Classy Paragraph configs
```bash
# Copy YAML files into your config/sync directory
cp config_sync/*.yml /path/to/your/project/config/sync/

# Import via Drush
drush php:script drush_scripts/drush_import_classy_paragraphs.php
drush cr
```

### Step 2: Build SVG tile files
```bash
# On a machine with internet access
cd drush_scripts/
bash fetch_and_build_tiles.sh

# Copy output tiles to your theme
cp svg_tiles/*.svg /path/to/themes/custom/customsolent/images/hero-tiles/
```

### Step 3: Add CSS to your theme
```bash
# Copy the CSS file
cp css/hero-art-styles.css /path/to/themes/custom/customsolent/css/

# Add to your theme's library (customsolent.libraries.yml):
# hero-art-styles:
#   css:
#     theme:
#       css/hero-art-styles.css: {}
```

### Step 4: Insert hero paragraphs into pages
```bash
# Ensure all pages exist as nodes with the correct URL aliases first
drush php:script drush_scripts/drush_insert_hero_paragraphs.php
drush cr
```

## Sections & Gradients

| Section  | Gradient Start | Gradient Mid | Gradient End |
|----------|---------------|-------------|-------------|
| Culture  | #6B21A8       | #7C3AED     | #A855F7     |
| Sectors  | #1D4ED8       | #2563EB     | #3B82F6     |
| Living   | #047857       | #059669     | #10B981     |

## Icon Positioning (Hex Grid — Tile: 120px × 103.92px, Icon Size: 28px)

Uses a hexagonal grid with 4 icon positions per tile. Every icon is exactly
60px from its 6 nearest neighbours, including across tile boundaries.

```
    Row 0:  icon           icon
    Row 1:       icon            icon
    Row 0:  icon           icon        ← next tile
```

For 3 icon types (A, B, C): positions cycle A, B, C, A.
For 2 icon types (A, B): positions cycle A, B, A, B.
For 1 icon type (A): all 4 positions use A.

## Adjusting

- **Icon spacing**: Change `SPACING` in fetch_and_build_tiles.sh (default: 60px, tile = 2×spacing wide)
- **Tile size**: CSS `background-size` should match: width = 2×spacing, height = spacing×√3
- **Icon opacity**: Change `opacity` value in build_tiles.py (default: 0.12)
- **Icon size**: Change `ICON_SIZE` in fetch_and_build_tiles.sh (default: 28px)
- **Gradient colours**: Edit the CSS directly or re-run with modified GRADIENTS

## Icon Library

All icons are from [Phosphor Icons](https://phosphoricons.com/) (MIT licence).
Bold weight is used for better visibility at small sizes in the repeating pattern.
