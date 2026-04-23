# The Solent Metropolitan — Article Styling Brief

## Overview

Style the Article content type full view mode so that articles look clean, readable, and editorially credible. This covers the Twig template, CSS, image handling with focal point, and field configuration.

---

## Prerequisites — manual admin tasks before Claude Code begins

### 1. Create field_standfirst
- **Content type:** Article
- **Field name:** field_standfirst
- **Field type:** Text (plain)
- **Max length:** 255 characters
- **Required:** No (optional for editors)
- **Help text:** "A single sentence that hooks the reader — the key point or angle of the article."
- **Widget:** Textfield

### 2. Change the body field type
- If the Article body field is currently "Text (formatted, long, with summary)", change it to **"Text (formatted, long)"** — remove the summary component. The standfirst replaces the summary function.
- **Important:** Check if any existing articles have summary data before changing. If they do, the summary data may be lost. Back up first or export any summaries.
- If the body field is already "Text (formatted, long)" without summary, no change needed.

### 3. Install focal point module
- Install and enable the **Focal Point** module (`drupal/focal_point`)
- Run: `composer require drupal/focal_point` then `drush en focal_point`
- Check compatibility with your Drupal 11 version before installing

### 4. Configure image styles
After focal point is installed, create the following image styles at `/admin/config/media/image-styles`:

**Article Hero (16:9)**
- Machine name: `article_hero`
- Effect 1: Focal Point Scale and Crop — width: 1200, height: 675 (16:9 ratio)

**Article Teaser (16:9)**
- Machine name: `article_teaser`
- Effect 1: Focal Point Scale and Crop — width: 400, height: 225 (16:9 ratio)

### 5. Configure the image field widget
- On the Article content type's form display (`/admin/structure/types/manage/article/form-display`), set the image field widget to use the **Focal Point** widget. This gives editors a crosshair to set the focal point when uploading images.

### 6. Configure the image field display
- On the Article content type's display for the "Full" view mode (`/admin/structure/types/manage/article/display/full`), set the image field to use the **article_hero** image style.
- On the "Teaser" view mode, set the image field to use the **article_teaser** image style.

---

## Template: node--article--full.html.twig

**Location:** `web/themes/custom/customsolent/templates/content/node--article--full.html.twig`

This template already exists but is sparse. Replace its content with the following structure.

### Field display order (top to bottom):
1. **Title** (node.label) — full width within 1200px container
2. **Standfirst** (field_standfirst) — full width, only if populated
3. **Date** (node.getCreatedTime) — full width
4. **Image** (field_image or equivalent media field) — full width within 1200px, 16:9, only if populated
5. **Body** (field_body) — narrower reading column (~740px), centred within the 1200px container
6. **Primary topic** — hidden for MVP
7. **Related topics** — hidden for MVP

### Template code:

```twig
{%
  set classes = [
    'node',
    'node--type-' ~ node.bundle|clean_class,
    node.isPromoted() ? 'node--promoted',
    node.isSticky() ? 'node--sticky',
    not node.isPublished() ? 'node--unpublished',
    view_mode ? 'node--view-mode-' ~ view_mode|clean_class,
    'slnt-article',
  ]
%}

<article{{ attributes.addClass(classes) }}>
  <div class="slnt-article__header">

    {# Title #}
    <h1 class="slnt-article__title">{{ label }}</h1>

    {# Standfirst — only if populated #}
    {% if content.field_standfirst|render|striptags|trim %}
      <p class="slnt-article__standfirst">{{ content.field_standfirst }}</p>
    {% endif %}

    {# Date #}
    <time class="slnt-article__date" datetime="{{ node.getCreatedTime()|date('Y-m-d') }}">
      {{ node.getCreatedTime()|date('j F Y') }}
    </time>

  </div>

  {# Image — only if populated #}
  {% if content.field_image|render|striptags|trim %}
    <div class="slnt-article__image">
      {{ content.field_image }}
    </div>
  {% endif %}

  {# Body — narrower reading column #}
  <div class="slnt-article__body">
    {{ content.field_body }}
  </div>

</article>
```

**Notes on the template:**
- Adjust `field_image` to match the actual machine name of your image/media field. If it's a media entity reference field, it might be `field_media_image` or similar — check the Article content type's field list.
- The `content.field_standfirst|render|striptags|trim` check ensures the standfirst wrapper doesn't render if the field is empty.
- Primary topic and related topics are excluded from the template for MVP. Add them later.
- The built-in node title is rendered manually as `{{ label }}` inside an `<h1>` with our custom class, rather than using Drupal's default title display. **Disable the title in the view mode display settings** (`/admin/structure/types/manage/article/display/full`) to prevent it rendering twice.
- Similarly, hide the date in the view mode display settings since we're rendering it manually in the template for formatting control.

---

## CSS: article styling

Add to `css/node.css` (already exists in the theme).

```css
/* ══════════════════════════════════════
   Article — Full view mode
   ══════════════════════════════════════ */

/* ── Outer container — matches composite page alignment ── */
.slnt-article {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--content-pad, 2rem);
}

/* ── Header area (title, standfirst, date) — full width ── */
.slnt-article__header {
  margin-bottom: 1.5rem;
}

/* ── Title ── */
.slnt-article__title {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 2.4rem;
  font-weight: 700;
  line-height: 1.15;
  color: var(--solent-blue, #2c4f6e);
  margin-bottom: 0.5rem;
}

/* ── Standfirst ── */
.slnt-article__standfirst {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.8rem;
  font-weight: 700;
  line-height: 1.25;
  color: var(--text, #1a1a1a);
  margin-bottom: 0.5rem;
}

/* Remove any nested paragraph margins from field output */
.slnt-article__standfirst p {
  margin: 0;
}
.slnt-article__standfirst .field {
  display: inline;
}

/* ── Date ── */
.slnt-article__date {
  display: block;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.9rem;
  font-weight: 400;
  color: var(--text-mid, #444);
  margin-bottom: 0;
}

/* ── Hero image — full width within 1200px container ── */
.slnt-article__image {
  margin: 1.5rem 0;
}

.slnt-article__image img {
  width: 100%;
  height: auto;
  display: block;
  border-radius: 4px;
}

/* ── Body — narrower reading column, centred ── */
.slnt-article__body {
  max-width: 740px;
  margin: 0 auto;
}

/* Body text inherits from fonts.css but ensure base styles */
.slnt-article__body p {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.65;
  color: var(--text, #1a1a1a);
  margin-bottom: 1.2rem;
}

/* Body headings within articles */
.slnt-article__body h2 {
  font-size: 1.8rem;
  font-weight: 700;
  line-height: 1.25;
  color: var(--solent-blue, #2c4f6e);
  margin-top: 2rem;
  margin-bottom: 0.8rem;
}

.slnt-article__body h3 {
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.3;
  color: var(--solent-blue, #2c4f6e);
  margin-top: 1.5rem;
  margin-bottom: 0.6rem;
}

/* Body links — magenta underline style from brief */
.slnt-article__body a {
  color: var(--text, #1a1a1a);
  font-weight: 700;
  text-decoration: underline;
  text-decoration-color: var(--magenta, #c5007a);
  text-underline-offset: 3px;
  text-decoration-thickness: 2px;
  transition: color 0.15s;
}

.slnt-article__body a:hover {
  color: var(--magenta, #c5007a);
}

/* Body lists */
.slnt-article__body ul,
.slnt-article__body ol {
  margin-bottom: 1.2rem;
  padding-left: 1.5em;
}

.slnt-article__body li {
  margin-bottom: 0.4rem;
  line-height: 1.65;
}

/* Blockquotes */
.slnt-article__body blockquote {
  border-left: 4px solid var(--solent-blue, #2c4f6e);
  margin: 1.5rem 0;
  padding: 0.8rem 1.5rem;
  font-style: italic;
  color: var(--text-mid, #444);
}

.slnt-article__body blockquote p {
  margin-bottom: 0.5rem;
}

.slnt-article__body blockquote p:last-child {
  margin-bottom: 0;
}

/* Images within body content */
.slnt-article__body img {
  max-width: 100%;
  height: auto;
  border-radius: 4px;
  margin: 1rem 0;
}

/* ── Mobile adjustments ── */
@media (max-width: 799px) {
  .slnt-article {
    padding: 0 var(--content-pad-mobile, 1.2rem);
  }

  .slnt-article__title {
    font-size: 1.75rem;
  }

  .slnt-article__standfirst {
    font-size: 1.4rem;
  }

  .slnt-article__body {
    max-width: 100%;
  }

  .slnt-article__body h2 {
    font-size: 1.4rem;
  }

  .slnt-article__body h3 {
    font-size: 1.15rem;
  }
}
```

**Notes on the CSS:**
- The `max-width: 740px` on `.slnt-article__body` creates the narrower reading column. Title, standfirst, date, and image remain at the full 1200px width. This creates the "expansive header, focused reading" layout.
- On mobile (<800px), the body `max-width` is removed (set to 100%) since the viewport is already narrow enough for comfortable reading.
- The CSS uses your established variables (`--solent-blue`, `--text`, `--text-mid`, `--magenta`, `--content-pad`) for consistency. If any of these aren't defined in your `:root`, the fallback values in the CSS will be used.
- Typography sizes match the typography brief: h1 2.4rem, h2 1.8rem, h3 1.35rem for desktop; h1 1.75rem, h2 1.4rem, h3 1.15rem for mobile.
- Link styles match the body link style from the colourway brief.
- The `border-radius: 4px` on images matches the CTA border-radius for visual consistency.

---

## Ensure node.css is in libraries.yml

Check that `css/node.css` is attached in `customsolent.libraries.yml`. If it's already there, no change needed. If not, add it under the appropriate library.

---

## Testing

Use the existing old articles to test. Check:

1. Title renders at the correct size and colour
2. Standfirst appears below title only when populated, at 1.8rem bold
3. Date displays as "23 April 2026" format
4. Image displays at full width, 16:9, with focal point crop applied
5. Articles without images show no image area (no empty space or broken placeholder)
6. Body text is centred in the narrower 740px column
7. Body links have the magenta underline style
8. Blockquotes, lists, and subheadings within the body render correctly
9. The article aligns horizontally with composite pages (same left/right padding)
10. Mobile layout looks correct below 800px — title and body sizes reduce, body goes full width
11. The node title doesn't render twice (check that it's disabled in the view mode display settings)

---

## What this does NOT cover (deferred)

- Primary topic display above the title (section indicator label)
- Related topics display below the body
- Author byline and author page link
- SVG tile pattern fallback when no image is present
- Teaser view mode template and styling (separate task)
- Image captions
