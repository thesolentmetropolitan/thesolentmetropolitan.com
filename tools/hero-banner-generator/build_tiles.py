#!/usr/bin/env python3
"""
Build repeating SVG tile files from cached Phosphor icon SVGs.

Icon positioning for seamless tiling:
  1 icon:  centre of tile (0.5, 0.5)
  2 icons: diagonal — (0.25, 0.25) and (0.75, 0.75)
  3 icons: triangle — (0.5, 0.2), (0.2, 0.7), (0.8, 0.7)

Icons are rendered white with reduced opacity for subtle pattern effect.
"""

import sys
import os
import re

ICON_CACHE_DIR = sys.argv[1]
TILE_OUTPUT_DIR = sys.argv[2]
TILE_SIZE = int(sys.argv[3])
ICON_SIZE = int(sys.argv[4])

# Positions as fraction of tile size (for centre of icon)
POSITIONS = {
    1: [(0.5, 0.5)],
    2: [(0.25, 0.25), (0.75, 0.75)],
    3: [(0.5, 0.22), (0.2, 0.72), (0.8, 0.72)],
}


def extract_svg_paths(svg_file):
    """Extract the inner content of an SVG, stripping the outer <svg> tag."""
    with open(svg_file, 'r') as f:
        content = f.read()
    # Remove the outer <svg ...> and </svg> tags, keep inner elements.
    inner = re.sub(r'<svg[^>]*>', '', content)
    inner = re.sub(r'</svg>', '', inner).strip()
    return inner


def build_tile_svg(machine_name, icon_names, tile_size, icon_size):
    """Build a single tile SVG with positioned icons."""
    count = len(icon_names)
    positions = POSITIONS.get(count, POSITIONS[3][:count])
    half = icon_size / 2

    parts = []
    parts.append(f'<svg xmlns="http://www.w3.org/2000/svg" '
                 f'width="{tile_size}" height="{tile_size}" '
                 f'viewBox="0 0 {tile_size} {tile_size}">')

    for i, (icon_name, (fx, fy)) in enumerate(zip(icon_names, positions)):
        svg_file = os.path.join(ICON_CACHE_DIR, f'{icon_name}.svg')
        if not os.path.exists(svg_file):
            print(f'  WARNING: Icon {icon_name} not found, skipping')
            continue

        inner_svg = extract_svg_paths(svg_file)
        cx = fx * tile_size - half
        cy = fy * tile_size - half

        # Wrap icon paths in a group with positioning and white fill.
        parts.append(
            f'  <g transform="translate({cx},{cy})" '
            f'fill="white" stroke="white" stroke-width="0" '
            f'opacity="0.12">'
        )
        # Scale the 256x256 Phosphor viewBox down to icon_size.
        scale = icon_size / 256
        parts.append(f'    <g transform="scale({scale})">')
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
}


if __name__ == '__main__':
    built = 0
    for machine_name, icon_names in PAGE_MAP.items():
        svg_content = build_tile_svg(machine_name, icon_names, TILE_SIZE, ICON_SIZE)
        out_path = os.path.join(TILE_OUTPUT_DIR, f'{machine_name}.svg')
        with open(out_path, 'w') as f:
            f.write(svg_content)
        print(f'  Built: {machine_name}.svg ({len(icon_names)} icons)')
        built += 1
    print(f'\nBuilt {built} tile SVGs.')