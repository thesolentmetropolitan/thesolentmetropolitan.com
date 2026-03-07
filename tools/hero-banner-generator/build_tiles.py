#!/usr/bin/env python3
"""
Build repeating SVG tile files from cached Phosphor icon SVGs.

Uses a HEXAGONAL GRID for uniform spacing between all icons.

A hex grid guarantees every icon is exactly the same distance from its
6 nearest neighbours — including across tile boundaries when CSS repeats.

Tile geometry:
  - Width  = 2 * spacing  (2 columns)
  - Height = spacing * sqrt(3)  (2 hex rows)
  - 4 icon positions per tile, arranged in hex pattern:

    Row 0:  P0(0, 0)           P1(spacing, 0)
    Row 1:       P2(spacing/2, h/2)      P3(3*spacing/2, h/2)

  All nearest-neighbour distances = spacing (including across tile edges).

  Icons at tile edges are intentional — CSS background-repeat completes
  them from adjacent tiles, creating the seamless hex pattern.

For N icon types, icons cycle across the 4 positions: A, B, C, A / A, B, A, B / A, A, A, A
"""

import sys
import os
import re
import math

ICON_CACHE_DIR = sys.argv[1]
TILE_OUTPUT_DIR = sys.argv[2]
SPACING = int(sys.argv[3])      # Distance between icon centres (e.g. 60)
ICON_SIZE = int(sys.argv[4])    # Rendered icon size in px (e.g. 28)

TILE_WIDTH = 2 * SPACING
TILE_HEIGHT = SPACING * math.sqrt(3)

# 4 hex grid positions (as px coordinates of icon centres)
HEX_POSITIONS = [
    (0, 0),                                          # P0: top-left corner
    (SPACING, 0),                                    # P1: top-centre
    (SPACING / 2, TILE_HEIGHT / 2),                  # P2: mid-left
    (SPACING + SPACING / 2, TILE_HEIGHT / 2),        # P3: mid-right
]


def extract_svg_paths(svg_file):
    """Extract the inner content of an SVG, stripping the outer <svg> tag."""
    with open(svg_file, 'r') as f:
        content = f.read()
    inner = re.sub(r'<svg[^>]*>', '', content)
    inner = re.sub(r'</svg>', '', inner).strip()
    return inner


def build_tile_svg(machine_name, icon_names):
    """Build a single tile SVG with icons on a hex grid.

    Icons placed at tile edges get DUPLICATED at wrapped positions so that
    CSS background-repeat produces seamless tiling. For example, an icon
    centred at (0, 0) extends outside the top-left corner — we render
    additional copies at (TILE_WIDTH, 0), (0, TILE_HEIGHT), and
    (TILE_WIDTH, TILE_HEIGHT) so each edge has the completing half.
    """
    half = ICON_SIZE / 2
    scale = ICON_SIZE / 256  # Phosphor icons use 256x256 viewBox
    icon_count = len(icon_names)

    parts = []
    tw = f'{TILE_WIDTH:.2f}'.rstrip('0').rstrip('.')
    th = f'{TILE_HEIGHT:.2f}'.rstrip('0').rstrip('.')
    parts.append(f'<svg xmlns="http://www.w3.org/2000/svg" '
                 f'width="{tw}" height="{th}" '
                 f'viewBox="0 0 {tw} {th}">')

    for i, (cx, cy) in enumerate(HEX_POSITIONS):
        icon_name = icon_names[i % icon_count]
        svg_file = os.path.join(ICON_CACHE_DIR, f'{icon_name}.svg')
        if not os.path.exists(svg_file):
            print(f'  WARNING: Icon {icon_name} not found, skipping')
            continue

        inner_svg = extract_svg_paths(svg_file)

        # Generate all wrapped copies of this icon position.
        # For each offset (dx, dy) in the 3x3 tile neighbourhood,
        # check if the icon would be at least partially visible
        # within the tile bounds (0,0 to TILE_WIDTH,TILE_HEIGHT).
        for dx in [-1, 0, 1]:
            for dy in [-1, 0, 1]:
                wx = cx + dx * TILE_WIDTH
                wy = cy + dy * TILE_HEIGHT

                # Icon bounding box at this wrapped position
                left = wx - half
                right = wx + half
                top = wy - half
                bottom = wy + half

                # Skip if entirely outside the tile
                if right <= 0 or left >= TILE_WIDTH:
                    continue
                if bottom <= 0 or top >= TILE_HEIGHT:
                    continue

                tx = wx - half
                ty = wy - half

                parts.append(
                    f'  <g transform="translate({tx:.2f},{ty:.2f})" '
                    f'fill="white" stroke="white" stroke-width="0" '
                    f'opacity="0.12">'
                )
                parts.append(f'    <g transform="scale({scale:.6f})">')
                parts.append(f'      {inner_svg}')
                parts.append('    </g>')
                parts.append('  </g>')

    parts.append('</svg>')
    return '\n'.join(parts)


# Page map: machine_name => [icon_names]
PAGE_MAP = {
    'culture_culture': ['palette', 'music-notes', 'masks-theater'],
    'culture_art_design': ['palette', 'paint-brush', 'pencil-ruler'],
    'culture_community': ['users-three', 'hands-clapping'],
    'culture_dance': ['person-arms-spread', 'music-notes'],
    'culture_enthusiasts': ['binoculars', 'heart', 'star'],
    'culture_faith': ['church', 'hands-praying', 'cross'],
    'culture_food_drink': ['fork-knife', 'coffee', 'wine'],
    'culture_games': ['game-controller', 'puzzle-piece', 'dice-five'],
    'culture_heritage': ['bank', 'scroll', 'castle-turret'],
    'culture_language': ['translate', 'chat-text', 'globe-simple'],
    'culture_maritime': ['anchor', 'sailboat', 'waves'],
    'culture_music': ['music-notes', 'vinyl-record', 'microphone'],
    'culture_outdoors': ['tree', 'mountains', 'compass'],
    'culture_photography': ['camera', 'aperture', 'image'],
    'culture_radio_podcast': ['microphone', 'broadcast', 'headphones'],
    'culture_science': ['atom', 'flask', 'microscope'],
    'culture_screen': ['film-strip', 'monitor-play', 'video-camera'],
    'culture_sport': ['trophy', 'football', 'medal'],
    'culture_stage': ['masks-theater', 'microphone-stage'],
    'culture_style': ['t-shirt', 'scissors', 'coat-hanger'],
    'culture_talks': ['chats', 'megaphone', 'presentation-chart'],
    'culture_technology': ['cpu', 'code', 'circuit-board'],
    'culture_writing': ['pen-nib', 'book-open', 'notebook'],
    'culture_workshops': ['wrench', 'hammer', 'lightbulb'],
    'culture_volunteering': ['hand-heart', 'handshake', 'heart'],
    'sectors_sectors': ['buildings', 'briefcase', 'chart-line-up'],
    'sectors_arts': ['palette', 'paint-brush'],
    'sectors_construction': ['hard-hat', 'crane', 'hammer'],
    'sectors_consulting': ['chart-bar', 'handshake', 'presentation-chart'],
    'sectors_creative': ['lightbulb', 'paint-brush', 'sparkle'],
    'sectors_democracy': ['bank', 'scales', 'megaphone'],
    'sectors_design': ['pen-nib', 'ruler', 'compass-tool'],
    'sectors_education': ['graduation-cap', 'book-open', 'chalkboard-teacher'],
    'sectors_engineering': ['gear', 'wrench', 'calculator'],
    'sectors_entrepreneur': ['rocket', 'lightbulb', 'chart-line-up'],
    'sectors_environment': ['leaf', 'globe-simple', 'recycle'],
    'sectors_event_venue': ['calendar', 'map-pin', 'ticket'],
    'sectors_facilities': ['building-office', 'toolbox', 'clipboard-text'],
    'sectors_farming': ['plant', 'sun', 'grain'],
    'sectors_finance': ['currency-gbp', 'chart-line', 'bank'],
    'sectors_health_care': ['heartbeat', 'stethoscope', 'first-aid-kit'],
    'sectors_hospitality': ['bed', 'fork-knife', 'bell-simple'],
    'sectors_legal': ['scales', 'gavel', 'scroll'],
    'sectors_lifestyle': ['sun', 'coffee', 'sparkle'],
    'sectors_logistics': ['truck', 'package', 'warehouse'],
    'sectors_manufacturing': ['factory', 'gear', 'conveyor-belt'],
    'sectors_maritime': ['anchor', 'boat', 'waves'],
    'sectors_marketing': ['megaphone', 'target', 'chart-bar'],
    'sectors_media': ['newspaper', 'broadcast', 'video-camera'],
    'sectors_military': ['shield', 'medal-military', 'flag'],
    'sectors_non_profit': ['hand-heart', 'globe-simple', 'users-three'],
    'sectors_property': ['house', 'key', 'buildings'],
    'sectors_public_sector': ['bank', 'users', 'shield-check'],
    'sectors_retail': ['shopping-cart', 'storefront', 'tag'],
    'sectors_science': ['atom', 'flask', 'dna'],
    'sectors_sport_fitness': ['barbell', 'trophy', 'person-simple-run'],
    'sectors_technology': ['cpu', 'code', 'monitor'],
    'sectors_tourism': ['airplane', 'map-trifold', 'camera'],
    'sectors_trades': ['wrench', 'hammer', 'plug'],
    'sectors_transport': ['train-simple', 'car', 'bus'],
    'sectors_utilities': ['lightning', 'drop', 'plug'],
    'living_living': ['house', 'heart', 'sun'],
    'living_advice': ['lightbulb', 'chat-circle', 'compass'],
    'living_education': ['graduation-cap', 'book-open', 'pencil-simple'],
    'living_family': ['users', 'baby', 'house'],
    'living_fitness': ['barbell', 'heartbeat', 'person-simple-run'],
    'living_health': ['heartbeat', 'stethoscope', 'pill'],
    'living_home_garden': ['house', 'flower-lotus', 'plant'],
    'living_housing': ['house', 'key', 'buildings'],
    'living_mental_health': ['brain', 'heart', 'chat-circle'],
    'living_outreach': ['hand-heart', 'megaphone', 'users-three'],
    'living_work': ['briefcase', 'laptop', 'clock'],
    # --- About section ---
    'about_about': ['info', 'book-open', 'question'],
    'about_why': ['question', 'lightbulb'],
    'about_editorial_policy': ['newspaper', 'shield', 'pen-nib'],
    'about_our_services': ['briefcase', 'gear', 'handshake'],
    'about_our_team': ['users', 'users-three'],
    'about_contact_us': ['envelope-simple', 'phone', 'chat-circle'],
    'about_privacy_policy': ['lock', 'shield', 'eye-slash'],
    'about_terms_of_use': ['scroll', 'file-text', 'clipboard-text'],
    # --- Explore section ---
    'explore_explore': ['compass', 'magnifying-glass', 'binoculars'],
    'explore_archive': ['archive', 'clock-countdown', 'folder'],
    'explore_articles': ['article', 'newspaper', 'book-open'],
    'explore_collaborations': ['handshake', 'users-three', 'puzzle-piece'],
    'explore_data': ['chart-bar', 'database', 'table'],
    'explore_events': ['calendar', 'map-pin', 'ticket'],
    'explore_jobs_boards': ['briefcase', 'magnifying-glass', 'user'],
    'explore_maps': ['map-trifold', 'map-pin', 'globe'],
    'explore_opinion': ['chat-circle', 'megaphone', 'pen-nib'],
    'explore_organisations': ['buildings', 'users', 'building-office'],
    'explore_themes': ['swatches', 'tag', 'grid-four'],
}


if __name__ == '__main__':
    print(f'  Tile dimensions: {TILE_WIDTH}px x {TILE_HEIGHT:.2f}px')
    print(f'  Icon spacing: {SPACING}px (uniform hex grid)')
    print(f'  Icon size: {ICON_SIZE}px')
    print()

    built = 0
    for machine_name, icon_names in PAGE_MAP.items():
        svg_content = build_tile_svg(machine_name, icon_names)
        out_path = os.path.join(TILE_OUTPUT_DIR, f'{machine_name}.svg')
        with open(out_path, 'w') as f:
            f.write(svg_content)
        print(f'  Built: {machine_name}.svg ({len(icon_names)} icon types, 4 positions)')
        built += 1
    print(f'\nBuilt {built} tile SVGs.')
