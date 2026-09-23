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

COMPOSITE tiles (see COMPOSITE_TILES below) use the same hex geometry but a
WIDER tile: still 2 hex rows tall (so the whole tile is visible inside a
~110-150px hero), but as many columns as needed to give every icon its own
position. A tile with C columns is C * spacing wide and holds 2 * C icons;
CSS background-size must match (the build prints it). Icons are defined once
as <symbol>s and placed with <use>, so a 160-icon tile stays small.

Usage:
  build_tiles.py CACHE_DIR OUT_DIR SPACING ICON_SIZE [tile_name ...]
  With no tile names every tile is built; with names only those are.
"""
import random

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


def extract_svg_paths(svg_file):
    """Extract the inner content of an SVG, stripping the outer <svg> tag."""
    with open(svg_file, 'r') as f:
        content = f.read()
    inner = re.sub(r'<svg[^>]*>', '', content)
    inner = re.sub(r'</svg>', '', inner).strip()
    return inner


def hex_positions(cols):
    """Icon-centre coordinates for a 2-row hex tile with `cols` columns.

    Row 0 sits on the tile's top edge, row 1 half a spacing lower and half
    a spacing to the right. Width = cols * SPACING, height = SPACING * sqrt(3).
    Every icon is SPACING from its six neighbours, including across the
    tile edges when CSS repeats it. cols = 2 gives the classic 4-position tile.
    """
    positions = []
    for c in range(cols):
        positions.append((c * SPACING, 0))
    for c in range(cols):
        positions.append((c * SPACING + SPACING / 2, TILE_HEIGHT / 2))
    return positions


def build_tile_svg(machine_name, icon_names, cols=2):
    """Build a single tile SVG with icons on a hex grid.

    Each distinct icon becomes one <symbol>; every placement is a <use>.
    Icons placed at tile edges get DUPLICATED at wrapped positions so that
    CSS background-repeat produces seamless tiling. For example, an icon
    centred at (0, 0) extends outside the top-left corner — we render
    additional copies at (TILE_WIDTH, 0), (0, TILE_HEIGHT), and
    (TILE_WIDTH, TILE_HEIGHT) so each edge has the completing half.
    """
    half = ICON_SIZE / 2
    tile_width = cols * SPACING
    positions = hex_positions(cols)
    icon_count = len(icon_names)

    # Icons at each position. A composite list that is one short of the
    # grid fills the spare slot from the middle of the list rather than
    # the start, so the filler never lands next to its twin (the last
    # position of row 1 wraps round to touch position 0).
    placed = []
    for i in range(len(positions)):
        if i < icon_count or icon_count <= 4:
            placed.append(icon_names[i % icon_count])
        else:
            placed.append(icon_names[(icon_count // 2 + i) % icon_count])

    def fmt(v):
        return f'{v:.2f}'.rstrip('0').rstrip('.')

    tw, th = fmt(tile_width), fmt(TILE_HEIGHT)
    parts = [f'<svg xmlns="http://www.w3.org/2000/svg" '
             f'xmlns:xlink="http://www.w3.org/1999/xlink" '
             f'width="{tw}" height="{th}" viewBox="0 0 {tw} {th}">']

    # One <symbol> per distinct icon (Phosphor icons use a 256x256 viewBox).
    parts.append('  <defs>')
    missing = set()
    for icon_name in dict.fromkeys(placed):
        svg_file = os.path.join(ICON_CACHE_DIR, f'{icon_name}.svg')
        if not os.path.exists(svg_file):
            print(f'  WARNING: Icon {icon_name} not found, skipping')
            missing.add(icon_name)
            continue
        inner_svg = extract_svg_paths(svg_file)
        if '<path' not in inner_svg:
            # A failed download saved as "404: Not Found" is not an icon.
            print(f'  WARNING: Icon {icon_name} cache file has no <path>, skipping')
            missing.add(icon_name)
            continue
        parts.append(f'    <symbol id="i-{icon_name}" viewBox="0 0 256 256">{inner_svg}</symbol>')
    parts.append('  </defs>')

    parts.append('  <g fill="white" opacity="0.12">')
    for (cx, cy), icon_name in zip(positions, placed):
        if icon_name in missing:
            continue
        # Generate all wrapped copies of this icon position.
        # For each offset (dx, dy) in the 3x3 tile neighbourhood,
        # check if the icon would be at least partially visible
        # within the tile bounds (0,0 to tile_width,TILE_HEIGHT).
        for dx in [-1, 0, 1]:
            for dy in [-1, 0, 1]:
                wx = cx + dx * tile_width
                wy = cy + dy * TILE_HEIGHT
                if wx + half <= 0 or wx - half >= tile_width:
                    continue
                if wy + half <= 0 or wy - half >= TILE_HEIGHT:
                    continue
                parts.append(
                    f'    <use href="#i-{icon_name}" xlink:href="#i-{icon_name}" '
                    f'x="{fmt(wx - half)}" y="{fmt(wy - half)}" '
                    f'width="{ICON_SIZE}" height="{ICON_SIZE}"/>'
                )
    parts.append('  </g>')
    parts.append('</svg>')
    return '\n'.join(parts), tile_width


# Page map: machine_name => [icon_names]
PAGE_MAP = {
    'culture_culture': ['palette', 'music-notes', 'mask-happy'],
    'culture_art_design': ['palette', 'paint-brush', 'pencil-ruler'],
    'culture_community': ['users-three', 'hands-clapping'],
    'culture_dance': ['person-arms-spread', 'music-notes'],
    'culture_enthusiasts': ['binoculars', 'heart', 'star'],
    'culture_faith': ['hands-praying'],
    'culture_festivals': ['confetti', 'tent', 'flag-pennant'],
    'culture_food_drink': ['fork-knife', 'coffee', 'wine'],
    'culture_games': ['game-controller', 'puzzle-piece', 'dice-five'],
    'culture_heritage': ['bank', 'scroll', 'castle-turret'],
    'culture_identity': ['fingerprint', 'compass', 'flag-banner'],
    'culture_language': ['translate', 'chat-text', 'globe-simple'],
    'culture_maritime': ['anchor', 'sailboat', 'waves'],
    'culture_music': ['music-notes', 'vinyl-record', 'microphone'],
    'culture_outdoor_active': ['tree', 'mountains', 'person-simple-run'],
    'culture_photography': ['camera', 'aperture', 'image'],
    'culture_radio_podcast': ['microphone', 'broadcast', 'headphones'],
    'culture_science': ['atom', 'flask', 'microscope'],
    'culture_screen': ['film-strip', 'monitor-play', 'video-camera'],
    'culture_sport': ['trophy', 'football', 'medal'],
    'culture_stage': ['mask-happy', 'microphone-stage', 'ticket'],
    'culture_style': ['t-shirt', 'sunglasses', 'sneaker', 'coat-hanger'],
    'culture_talks': ['chats', 'megaphone', 'presentation-chart'],
    'culture_technology': ['cpu', 'code', 'laptop', 'device-mobile'],
    'culture_writing': ['pen-nib', 'book-open', 'notebook'],
    'culture_workshops': ['wrench', 'hammer', 'lightbulb'],
    'culture_volunteering': ['hand-heart', 'handshake', 'heart'],
    'sectors_sectors': ['buildings', 'briefcase', 'chart-line-up'],
    'sectors_arts': ['palette', 'paint-brush', 'frame-corners'],
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
    'sectors_farming': ['plant', 'sun', 'leaf', 'barn'],
    'sectors_finance': ['currency-gbp', 'chart-line', 'bank'],
    'sectors_health_care': ['heartbeat', 'stethoscope', 'first-aid-kit'],
    'sectors_hospitality': ['bed', 'fork-knife', 'bell-simple'],
    'sectors_legal': ['scales', 'gavel', 'scroll'],
    'sectors_lifestyle': ['sun', 'coffee', 'sparkle'],
    'sectors_logistics': ['truck', 'package', 'warehouse'],
    'sectors_manufacturing': ['factory', 'gear', 'package', 'cube'],
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
    'about_accessibility': ['wheelchair', 'eye', 'ear'],
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
    # --- Search page ---
    'search': ['magnifying-glass', 'binoculars', 'file-text'],
}


# Composite tiles: every icon used by the tiles whose name starts with
# `prefix` ('' = all of them), each placed once, in a seeded shuffle so
# the build is repeatable. Two hex rows tall, ceil(N / 2) columns wide.
#   culture_culture  -> the /culture landing page: all Culture topic icons.
#   home             -> the front page: every icon on the site.
COMPOSITE_TILES = {
    'culture_culture': {'prefix': 'culture_', 'seed': 7},
    'home': {'prefix': '', 'seed': 11},
}


def composite_icons(prefix):
    """Union of PAGE_MAP icons for tiles starting with prefix, first-seen order."""
    seen = {}
    for name, icons in PAGE_MAP.items():
        if name in COMPOSITE_TILES or not name.startswith(prefix):
            continue
        for icon in icons:
            seen.setdefault(icon, True)
    return list(seen)


def tile_plan():
    """Yield (machine_name, icon_names, cols, note) for every tile."""
    for machine_name, icon_names in PAGE_MAP.items():
        if machine_name in COMPOSITE_TILES:
            continue
        yield machine_name, icon_names, 2, f'{len(icon_names)} icon types, 4 positions'
    for machine_name, spec in COMPOSITE_TILES.items():
        icons = composite_icons(spec['prefix'])
        random.Random(spec['seed']).shuffle(icons)
        cols = math.ceil(len(icons) / 2)
        yield machine_name, icons, cols, f'composite: {len(icons)} icons, {cols} columns'


if __name__ == '__main__':
    only = set(sys.argv[5:])
    print(f'  Row height: {TILE_HEIGHT:.2f}px (2 hex rows per tile)')
    print(f'  Icon spacing: {SPACING}px (uniform hex grid)')
    print(f'  Icon size: {ICON_SIZE}px')
    print()

    built = 0
    for machine_name, icon_names, cols, note in tile_plan():
        if only and machine_name not in only:
            continue
        svg_content, tile_width = build_tile_svg(machine_name, icon_names, cols)
        out_path = os.path.join(TILE_OUTPUT_DIR, f'{machine_name}.svg')
        with open(out_path, 'w') as f:
            f.write(svg_content)
        print(f'  Built: {machine_name}.svg ({note}) '
              f'-> CSS background-size: {tile_width:g}px {TILE_HEIGHT:.2f}px')
        built += 1
    print(f'\nBuilt {built} tile SVGs.')
