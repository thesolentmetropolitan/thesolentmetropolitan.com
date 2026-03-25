Good morning Claude Code

Fixing positions of things in the footer. Please use the web browser as necessary to troubleshoot and provide a solution.

Desktop and mobile: Footer: The 3 SVG icons inside "field field--name-field-content-component field--type-entity-reference-revisions field--label-hidden field__items"
are vertically stacked. Can you write CSS to make them inline side by side.

Also in footer, "clearfix text-formatted field field--name-field-copyright-message field--type-text field--label-hidden field__item" - inside this, the words: "Copyright (c) The Solent Metropolitan 2026" are not white.

The "paragraph paragraph--type--icon-with-link paragraph--view-mode--default", the "Drupal Drop_White.svg" is missing and needs to be there and white.

Related to alignment issues is, in web/themes/custom/customsolent/templates/paragraphs/paragraph--enclosure.html.twig lines 59-64 some padding calculations - I need these - but only for the first outermost enclosure - not for any nested enclosures. For nested enclosures I just need to use the padding_value_incr on its own.

We're now on a branch "2026-03-25-fix-footer-issues" for troubleshooting and fixing issues then testing.