# The Solent Metropolitan — Menu Paragraph Brief

## Overview

Add a reusable paragraph type for rendering a Drupal menu as a vertical list, using Twig Tweak's `drupal_menu()` function. The initial use case is listing main menu items in the site footer, but the paragraph type is generic and can be reused on any page or in any block that accepts paragraphs. No Views required. No new contrib modules needed — Twig Tweak is already installed.

---

## Architecture

The site already has:
- A **custom footer block type** with an entity reference field that accepts paragraph types
- **Existing paragraph types** already used in footer block instances (e.g. for social media links)
- **Twig Tweak module** installed

The approach:
1. Create a new paragraph type for rendering a menu
2. Use Twig Tweak's `drupal_menu()` in the paragraph's Twig template to render the menu
3. For the footer use case: add an instance of this paragraph to the footer block alongside the existing social media paragraphs

---

## Step 1: Create the paragraph type

Create a new paragraph type:
- **Label:** Menu
- **Machine name:** `menu`
- **Description:** Renders a Drupal menu as a vertical list. Can be used in footers, sidebars, or any context that accepts paragraphs.

### Fields

This paragraph type needs minimal fields since the menu is rendered by Twig Tweak, not from field data. However, adding a few optional fields gives editorial flexibility:

**field_menu_aria_label**
- Type: Plain text
- Label: "Accessibility label"
- Description: "Describes this menu for screen readers, e.g. 'Footer navigation' or 'Sidebar navigation'. Required for accessibility when multiple menus appear on a page."
- Not required (defaults to "Site navigation" in the template)

**field_menu_heading** (optional)
- Type: Plain text
- Label: "Heading"
- Description: "Optional visible heading above the menu list, e.g. 'Navigation' or 'Explore'. Not displayed if left blank."
- Not required

**field_menu_name** (optional — only if you want editors to choose which menu)
- Type: List (text)
- Label: "Menu"
- Allowed values: `main|Main navigation`, `footer|Footer` (add others as needed)
- Default value: `main`
- Description: "Which menu to display"
- Not required (defaults to main)

If editorial flexibility isn't needed and it should always show the main menu, skip `field_menu_name` and hardcode `'main'` in the Twig template.

---

## Step 2: Twig template

Create the template file:
`web/themes/custom/customsolent/templates/paragraphs/paragraph--menu.html.twig`

```twig
{#
/**
 * @file
 * Theme for the Menu paragraph type.
 *
 * Renders a menu as a vertical list using Twig Tweak's drupal_menu().
 * Parameters: menu_name, min_depth, max_depth
 * Using depth 1,1 shows only top-level items (no submenu children).
 *
 * The aria-label is set from field_menu_aria_label if provided,
 * falling back to "Site navigation" for accessibility.
 */
#}

{% set menu_name = content.field_menu_name.0['#markup']|default('main') %}
{% set nav_label = content.field_menu_aria_label.0['#context'].value|default('Site navigation') %}

<div{{ attributes.addClass('paragraph-menu') }}>

  {% if content.field_menu_heading.0 %}
    <h3 class="paragraph-menu__heading">{{ content.field_menu_heading }}</h3>
  {% endif %}

  <nav class="paragraph-menu__nav" aria-label="{{ nav_label }}">
    {{ drupal_menu(menu_name, 1, 1) }}
  </nav>

</div>
```

**Note on field value access:** The `content.field_menu_aria_label.0['#context'].value` syntax may need adjusting depending on how Drupal renders the plain text field. If it doesn't resolve correctly, alternative approaches include `paragraph.field_menu_aria_label.value` (accessing the entity directly) or `content.field_menu_aria_label.0|render|striptags|trim`. Claude Code should test and use whichever works in context.

**Notes on `drupal_menu()` parameters:**
- First parameter: menu machine name (e.g. `'main'`)
- Second parameter: minimum depth (1 = top level)
- Third parameter: maximum depth (1 = top level only, no children)

If you skipped the `field_menu_name` field, simplify to:
```twig
{{ drupal_menu('main', 1, 1) }}
```

---

## Step 3: CSS

Add footer-specific menu styles to `css/footer-end.css` (or `css/footer-base.css`, whichever handles footer content styling). The menu rendered by `drupal_menu()` outputs a standard Drupal `<ul>` with `<li>` and `<a>` elements. These styles are scoped to the footer context so the paragraph can be styled differently when used elsewhere.

```css
/* Menu paragraph in footer context */
footer .paragraph-menu__heading {
  color: rgba(255, 255, 255, 0.85);
  margin-bottom: var(--space-sm, 1rem);
}

footer .paragraph-menu__nav ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

footer .paragraph-menu__nav li {
  margin-bottom: 0.4rem;
}

footer .paragraph-menu__nav a {
  color: rgba(255, 255, 255, 0.85);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 400;
  transition: color 0.15s;
}

footer .paragraph-menu__nav a:hover {
  color: white;
}
```

**Important:** The `.layout-content a` body link styles (bold, magenta underline) defined in `fonts.css` may cascade into the footer if the footer sits within `.layout-content`. Check whether a specificity override is needed, or whether the footer is outside `.layout-content` in the page structure.

Also add the menu heading font-size to `fonts.css` to keep typography centralised:
```css
/* Menu paragraph heading */
.paragraph-menu__heading {
  font-size: 1.1rem;
  font-weight: 700;
}
```

And remove the `font-size` and `font-weight` from the CSS in the footer file (keep only colour and spacing there).

---

## Step 4: Add to footer block instance

Once the paragraph type is created:

1. Go to the footer block instance in the Drupal admin (Structure → Block Layout, or Content → Blocks, depending on how custom blocks are managed)
2. Edit the footer block
3. Add a new paragraph of type **Menu** to the entity reference field
4. Set the Accessibility label to "Footer navigation"
5. Optionally set the heading (e.g. "Navigation" or "Explore the site") — or leave blank for no visible heading
6. If the `field_menu_name` field exists, select "Main navigation" (or leave as default)
7. Position it relative to the existing social media paragraph(s) — likely first or in a column alongside them
8. Save

---

## Step 5: Verify Twig Tweak is outputting correctly

After clearing the cache (`drush cr`), check:
- The footer shows the menu items as a vertical list
- Only top-level items appear (not submenu children)
- Items are in the correct menu weight order
- Links work correctly
- The `<nav>` element has `aria-label="Footer navigation"` in the rendered HTML (inspect element to verify)
- The search menu item appears (if it's in the main menu)
- The "Sign in" item either appears or is intentionally excluded (it may not be desirable in the footer — check whether it's in the main menu as a menu_link_content item or rendered separately)

---

## Step 6: Layout considerations

The footer currently has the solent blue background. The Menu paragraph should sit alongside the existing social media paragraph(s). There are a few layout options:

**Option A — Vertical stack:** Menu list above or below the social media links. Simpler.

**Option B — Columns:** Menu list in one column, social media in another. More like a traditional "fat footer." This would need a flex or grid layout on the footer region or the footer block's paragraph container.

The choice depends on how many footer elements there will be. For now, a vertical stack is simplest. Columns can be added later if more footer content is added.

---

## Notes

- **Performance:** `drupal_menu()` reads from Drupal's already-cached menu tree. No additional database queries are made beyond what Drupal already does for the header navigation. This is the most efficient approach.
- **Cache:** The menu content will be invalidated automatically when the menu structure changes (Drupal handles this via cache tags on the menu).
- **Accessibility:** The `<nav>` element needs a distinct `aria-label` to distinguish it from the header navigation for screen readers. Having two `<nav>` elements on a page without distinct labels is an accessibility issue. The template reads the label from `field_menu_aria_label`, falling back to "Site navigation" if left blank. For the footer instance, set this field to "Footer navigation". For a sidebar instance, "Sidebar navigation", etc.
- **Reusability:** The paragraph type is named "Menu" (not "Footer Menu") so it can be reused in other contexts — sidebars, landing pages, etc. The CSS is scoped to `footer .paragraph-menu__*` for the footer context. When reused elsewhere, add context-specific styles as needed.
- **No Views dependency:** This approach avoids Views entirely. The menu tree is rendered directly from Drupal's menu system cache.
- **Twig Tweak** is already installed. Confirm it's enabled with `drush pm:list --status=enabled | grep twig_tweak`.
