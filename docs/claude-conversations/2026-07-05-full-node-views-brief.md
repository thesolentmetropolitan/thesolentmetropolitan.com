# The Solent Metropolitan — Full Node Views for Event, Organisation & Link

## Overview

Enable full node view pages for Event, Organisation, and Link content types. These currently have Rabbit Hole blocking direct node access, which causes search results to land on a 404. Each content type gets a minimal full view page — enough to be a useful destination from search, shareable via URL, and indexable by search engines.

**Reference:** The Article full view mode template (`node--article--full.html.twig`) and its preprocess code should be used as the structural starting point. These new templates follow the same patterns — topic trail, title, content area within the same max-width and padding — but with content appropriate to each type.

**Drupal 11 compatible.**

---

## Part 1: Event Full Node View

### Rabbit Hole configuration

Change Event content type Rabbit Hole settings from "redirect to 404" (or equivalent) to **"Do nothing"** — allow direct node access.

Configure at: `/admin/structure/types/manage/event/edit` (Rabbit Hole tab)

### Template

Create: `web/themes/custom/customsolent/templates/content/node--event--full.html.twig`

Reference `node--article--full.html.twig` for structure — same outer container, same max-width, same padding, same topic trail include.

**Display order (top to bottom):**

1. **Past event kicker** (conditional — only if event date is in the past)
2. **Topic trail kicker** (from the existing topic trail component — "in MUSIC" or "from SECTORS / ARTS")
3. **Title** (`<h1>`)
4. **Date and time** (`field_when` — Smart Date, readable format e.g. "Saturday 26 April 2026, 7:30 PM")
5. **Location** (`field_where` — Location term name)
6. **Standfirst** (`field_standfirst` — if populated)
7. **External link CTA** (`field_url` — CTA button style with editor-defined link text, opens in new tab)

### Template code

```twig
{%
  set classes = [
    'node',
    'node--type-' ~ node.bundle|clean_class,
    node.isPromoted() ? 'node--promoted',
    not node.isPublished() ? 'node--unpublished',
    view_mode ? 'node--view-mode-' ~ view_mode|clean_class,
    'slnt-event-full',
  ]
%}

<article{{ attributes.addClass(classes) }}>
  <div class="slnt-event-full__header">

    {# Past event kicker — above topic trail #}
    {% if is_past_event is defined and is_past_event %}
      <div class="slnt-event-full__past-kicker">Past event</div>
    {% endif %}

    {# Topic trail / kicker — reuse existing component #}
    {% if show_in_kicker is defined and show_in_kicker %}
      {% include '@customsolent/components/in-kicker.html.twig' with {
        in_kicker_items: in_kicker_items,
      } %}
    {% endif %}
    {% if show_primary_kicker is defined and show_primary_kicker %}
      {% include '@customsolent/components/primary-kicker.html.twig' %}
    {% endif %}

    {# Title #}
    <h1 class="slnt-event-full__title">{{ label }}</h1>

    {# Date and time #}
    {% if content.field_when|render|striptags|trim %}
      <div class="slnt-event-full__when">
        {{ content.field_when }}
      </div>
    {% endif %}

    {# Location #}
    {% if content.field_where|render|striptags|trim %}
      <div class="slnt-event-full__where">
        {{ content.field_where }}
      </div>
    {% endif %}

  </div>

  {# Standfirst #}
  {% if content.field_standfirst|render|striptags|trim %}
    <div class="slnt-event-full__standfirst">
      {{ content.field_standfirst }}
    </div>
  {% endif %}

  {# External link CTA #}
  {% if content.field_url|render|striptags|trim %}
    <div class="slnt-event-full__cta">
      {{ content.field_url }}
    </div>
  {% endif %}

</article>
```

### Past event detection — preprocess

Add to `customsolent_preprocess_node()` for events in full view mode:

```php
// In customsolent_preprocess_node(), for event full view:
if ($node->bundle() === 'event' && ($variables['view_mode'] ?? '') === 'full') {
  // Check if the event date is in the past
  if ($node->hasField('field_when') && !$node->get('field_when')->isEmpty()) {
    $when_value = $node->get('field_when')->first();
    // Smart Date stores end_value; compare against current time
    $end_timestamp = $when_value->end_value ?? $when_value->value;
    $variables['is_past_event'] = ($end_timestamp < \Drupal::time()->getRequestTime());
  }
}
```

**Note:** Claude Code should inspect how Smart Date stores its values — the field item may use `value` and `end_value` properties, or different accessors. Check the Smart Date module's field type class.

### CSS

```css
/* ══════════════════════════════════════
   Event — Full view mode
   ══════════════════════════════════════ */

.slnt-event-full {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--content-pad, 2rem);
}

.slnt-event-full__header {
  margin-bottom: 1.5rem;
}

/* Past event kicker — above topic trail kicker */
.slnt-event-full__past-kicker {
  display: inline-block;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-mid, #444);
  background: var(--warm-grey, #f5f3f0);
  padding: 0.2em 0.6em;
  border-radius: 3px;
  margin-bottom: 0.3rem;
}

.slnt-event-full__title {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 2.4rem;
  font-weight: 700;
  line-height: 1.15;
  color: var(--solent-blue, #2c4f6e);
  margin-bottom: 0.5rem;
}

.slnt-event-full__when {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1rem;
  font-weight: 400;
  color: var(--text, #1a1a1a);
  margin-bottom: 0.3rem;
}

.slnt-event-full__when .field {
  display: inline;
  margin: 0;
}

.slnt-event-full__where {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1rem;
  font-weight: 400;
  color: var(--text-mid, #444);
  margin-bottom: 0;
}

.slnt-event-full__where .field {
  display: inline;
  margin: 0;
}

.slnt-event-full__standfirst {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.2rem;
  font-weight: 400;
  line-height: 1.5;
  color: var(--text, #1a1a1a);
  max-width: 740px;
  margin: 0 auto 1.5rem;
}

.slnt-event-full__standfirst p {
  margin: 0;
}

.slnt-event-full__standfirst .field {
  display: inline;
}

/* CTA button — reuse existing CTA styles */
.slnt-event-full__cta {
  margin-top: 1.5rem;
}

.slnt-event-full__cta a {
  display: inline-flex;
  align-items: center;
  gap: 0.5em;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  padding: 0.6em 1.4em;
  background: var(--solent-blue, #2c4f6e);
  border: 3px solid var(--solent-blue, #2c4f6e);
  border-radius: 4px;
  color: white;
  text-decoration: none;
  transition: all 0.2s ease;
}

.slnt-event-full__cta a:hover {
  background: var(--solent-blue-light, #3a6489);
  border-color: var(--solent-blue-light, #3a6489);
}

/* ── Mobile ── */
@media (max-width: 799px) {
  .slnt-event-full {
    padding: 0 var(--content-pad-mobile, 1.2rem);
  }

  .slnt-event-full__title {
    font-size: 1.75rem;
  }

  .slnt-event-full__standfirst {
    font-size: 1.05rem;
    max-width: 100%;
  }
}
```

### View mode field display

Configure the Event full view mode display at `/admin/structure/types/manage/event/display/full`:

| Field | Visible | Label | Formatter |
|-------|---------|-------|-----------|
| field_when | Yes | Hidden | Smart Date — full readable format |
| field_where | Yes | Hidden | Entity reference label |
| field_standfirst | Yes | Hidden | Plain text |
| field_url | Yes | Hidden | Link (uses editor link text) |
| field_image | No | — | Disabled (no images for MVP) |
| field_primary_topic | No | — | Disabled (handled by kicker preprocess) |
| field_related_topics | No | — | Disabled |

---

## Part 2: Organisation Full Node View

### Rabbit Hole configuration

Change Organisation Rabbit Hole settings to **"Do nothing"** — allow direct node access.

### Template

Create: `web/themes/custom/customsolent/templates/content/node--organisation--full.html.twig`

**Display order:**

1. **Topic trail kicker** (existing component)
2. **Title** (`<h1>`) — the organisation name
3. **Standfirst** (`field_standfirst` — one-line description)
4. **Website link** (`field_url` — CTA button, opens in new tab)

### Template code

```twig
{%
  set classes = [
    'node',
    'node--type-' ~ node.bundle|clean_class,
    not node.isPublished() ? 'node--unpublished',
    view_mode ? 'node--view-mode-' ~ view_mode|clean_class,
    'slnt-org-full',
  ]
%}

<article{{ attributes.addClass(classes) }}>
  <div class="slnt-org-full__header">

    {# Topic trail / kicker #}
    {% if show_in_kicker is defined and show_in_kicker %}
      {% include '@customsolent/components/in-kicker.html.twig' with {
        in_kicker_items: in_kicker_items,
      } %}
    {% endif %}
    {% if show_primary_kicker is defined and show_primary_kicker %}
      {% include '@customsolent/components/primary-kicker.html.twig' %}
    {% endif %}

    {# Title — organisation name #}
    <h1 class="slnt-org-full__title">{{ label }}</h1>

  </div>

  {# Standfirst #}
  {% if content.field_standfirst|render|striptags|trim %}
    <div class="slnt-org-full__standfirst">
      {{ content.field_standfirst }}
    </div>
  {% endif %}

  {# Website link CTA #}
  {% if content.field_url|render|striptags|trim %}
    <div class="slnt-org-full__cta">
      {{ content.field_url }}
    </div>
  {% endif %}

</article>
```

### CSS

```css
/* ══════════════════════════════════════
   Organisation — Full view mode
   ══════════════════════════════════════ */

.slnt-org-full {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--content-pad, 2rem);
}

.slnt-org-full__header {
  margin-bottom: 1.5rem;
}

.slnt-org-full__title {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 2.4rem;
  font-weight: 700;
  line-height: 1.15;
  color: var(--solent-blue, #2c4f6e);
  margin-bottom: 0.5rem;
}

.slnt-org-full__standfirst {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.2rem;
  font-weight: 400;
  line-height: 1.5;
  color: var(--text, #1a1a1a);
  max-width: 740px;
  margin: 0 auto 1.5rem;
}

.slnt-org-full__standfirst p {
  margin: 0;
}

.slnt-org-full__standfirst .field {
  display: inline;
}

.slnt-org-full__cta {
  margin-top: 1.5rem;
}

.slnt-org-full__cta a {
  display: inline-flex;
  align-items: center;
  gap: 0.5em;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  padding: 0.6em 1.4em;
  background: var(--solent-blue, #2c4f6e);
  border: 3px solid var(--solent-blue, #2c4f6e);
  border-radius: 4px;
  color: white;
  text-decoration: none;
  transition: all 0.2s ease;
}

.slnt-org-full__cta a:hover {
  background: var(--solent-blue-light, #3a6489);
  border-color: var(--solent-blue-light, #3a6489);
}

@media (max-width: 799px) {
  .slnt-org-full {
    padding: 0 var(--content-pad-mobile, 1.2rem);
  }

  .slnt-org-full__title {
    font-size: 1.75rem;
  }

  .slnt-org-full__standfirst {
    font-size: 1.05rem;
    max-width: 100%;
  }
}
```

### View mode field display

Configure at `/admin/structure/types/manage/organisation/display/full`:

| Field | Visible | Label | Formatter |
|-------|---------|-------|-----------|
| field_standfirst | Yes | Hidden | Plain text |
| field_url | Yes | Hidden | Link (uses editor link text) |
| field_primary_topic | No | — | Disabled |
| field_related_topics | No | — | Disabled |

---

## Part 3: Link Full Node View

### Rabbit Hole configuration

Change Link Rabbit Hole settings to **"Do nothing"**.

### Template

Create: `web/themes/custom/customsolent/templates/content/node--link--full.html.twig`

**Display order:**

1. **Topic trail kicker** (existing component)
2. **Title** (`<h1>`)
3. **Standfirst** (`field_standfirst` — if populated)
4. **Link** (`field_url` — CTA button, opens in new tab)

### Template code

```twig
{%
  set classes = [
    'node',
    'node--type-' ~ node.bundle|clean_class,
    not node.isPublished() ? 'node--unpublished',
    view_mode ? 'node--view-mode-' ~ view_mode|clean_class,
    'slnt-link-full',
  ]
%}

<article{{ attributes.addClass(classes) }}>
  <div class="slnt-link-full__header">

    {# Topic trail / kicker #}
    {% if show_in_kicker is defined and show_in_kicker %}
      {% include '@customsolent/components/in-kicker.html.twig' with {
        in_kicker_items: in_kicker_items,
      } %}
    {% endif %}
    {% if show_primary_kicker is defined and show_primary_kicker %}
      {% include '@customsolent/components/primary-kicker.html.twig' %}
    {% endif %}

    {# Title #}
    <h1 class="slnt-link-full__title">{{ label }}</h1>

  </div>

  {# Standfirst #}
  {% if content.field_standfirst|render|striptags|trim %}
    <div class="slnt-link-full__standfirst">
      {{ content.field_standfirst }}
    </div>
  {% endif %}

  {# Link CTA #}
  {% if content.field_url|render|striptags|trim %}
    <div class="slnt-link-full__cta">
      {{ content.field_url }}
    </div>
  {% endif %}

</article>
```

### CSS

Organisation and Link full views are visually identical. Use shared classes or duplicate the Organisation CSS with `slnt-link-full` selectors:

```css
/* ══════════════════════════════════════
   Link — Full view mode
   Visually identical to Organisation full view.
   ══════════════════════════════════════ */

.slnt-link-full {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--content-pad, 2rem);
}

.slnt-link-full__header {
  margin-bottom: 1.5rem;
}

.slnt-link-full__title {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 2.4rem;
  font-weight: 700;
  line-height: 1.15;
  color: var(--solent-blue, #2c4f6e);
  margin-bottom: 0.5rem;
}

.slnt-link-full__standfirst {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.2rem;
  font-weight: 400;
  line-height: 1.5;
  color: var(--text, #1a1a1a);
  max-width: 740px;
  margin: 0 auto 1.5rem;
}

.slnt-link-full__standfirst p {
  margin: 0;
}

.slnt-link-full__standfirst .field {
  display: inline;
}

.slnt-link-full__cta {
  margin-top: 1.5rem;
}

.slnt-link-full__cta a {
  display: inline-flex;
  align-items: center;
  gap: 0.5em;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  padding: 0.6em 1.4em;
  background: var(--solent-blue, #2c4f6e);
  border: 3px solid var(--solent-blue, #2c4f6e);
  border-radius: 4px;
  color: white;
  text-decoration: none;
  transition: all 0.2s ease;
}

.slnt-link-full__cta a:hover {
  background: var(--solent-blue-light, #3a6489);
  border-color: var(--solent-blue-light, #3a6489);
}

@media (max-width: 799px) {
  .slnt-link-full {
    padding: 0 var(--content-pad-mobile, 1.2rem);
  }

  .slnt-link-full__title {
    font-size: 1.75rem;
  }

  .slnt-link-full__standfirst {
    font-size: 1.05rem;
    max-width: 100%;
  }
}
```

**Note for Claude Code:** The Organisation and Link full view CSS is nearly identical. Consider creating a shared class (e.g. `.slnt-simple-full`) used by both templates instead of duplicating. This is a Claude Code judgement call — duplication is fine for two templates, shared class is better if more content types follow this pattern later.

### View mode field display

Configure at `/admin/structure/types/manage/link/display/full`:

| Field | Visible | Label | Formatter |
|-------|---------|-------|-----------|
| field_standfirst | Yes | Hidden | Plain text (if field exists on Link) |
| field_url | Yes | Hidden | Link (uses editor link text) |
| field_primary_topic | No | — | Disabled |
| field_related_topics | No | — | Disabled |

---

## Past event kicker — stacking order

On a past event's full page, the kickers stack as follows:

```
PAST EVENT                                   ← grey badge, always top
in MUSIC                                     ← section colour, topic kicker
Southampton Jazz Weekend                     ← h1 title
Saturday 12 October 2025, 7:30 PM            ← date
Guildhall Square, Southampton                ← location
```

The "Past event" badge sits above the topic kicker. Both sit above the title. This means a past event on a page reached from search shows immediately and clearly that the event has already happened — the reader doesn't need to scroll or check the date to know.

---

## Checklist: What Rob should configure

### Rabbit Hole changes
- [ ] Event: change to "Do nothing" (allow direct node access)
- [ ] Organisation: change to "Do nothing"
- [ ] Link: change to "Do nothing"

### View mode field display
- [ ] Event full: configure field visibility per the table above
- [ ] Organisation full: configure field visibility per the table above
- [ ] Link full: configure field visibility per the table above

### Disable title in view mode display
- [ ] For all three content types: disable the built-in title in the full view mode display settings to prevent double rendering (the template renders the title manually via `{{ label }}`)

---

## Implementation order

| Step | Task | Who |
|------|------|-----|
| 1 | Change Rabbit Hole settings for Event, Organisation, Link | Rob |
| 2 | Configure full view mode field display for all three types | Rob |
| 3 | Create `node--event--full.html.twig` with past event kicker | Claude Code |
| 4 | Create `node--organisation--full.html.twig` | Claude Code |
| 5 | Create `node--link--full.html.twig` | Claude Code |
| 6 | Add past event detection to `customsolent_preprocess_node()` | Claude Code |
| 7 | Add CSS for all three full view templates | Claude Code |
| 8 | Register CSS in `customsolent.libraries.yml` | Claude Code |
| 9 | Verify search results now land on working pages | Rob |

---

## Testing

1. **Event full page — current event:** Visit a current event node directly. Title, date, location, standfirst, and external link CTA all display. No "Past event" badge.
2. **Event full page — past event:** Visit a past event node. "PAST EVENT" badge appears above the topic kicker, above the title. Date shows the past date.
3. **Kicker stacking on past event:** Two lines of kickers — "PAST EVENT" (grey badge) then "in MUSIC" (section colour) — above the title.
4. **Organisation full page:** Visit an organisation node. Title, standfirst, and website link CTA display. Topic kicker shows.
5. **Link full page:** Visit a link node. Title, standfirst (if present), and link CTA display. Topic kicker shows.
6. **Search results:** Search for an event, organisation, or link. Click the result. Lands on a properly styled page, not a 404.
7. **External link CTA:** On event and organisation pages, the CTA button opens the external URL in a new tab.
8. **Mobile:** All three full views render correctly below 800px — title and standfirst sizes reduce, padding adjusts.
9. **Alignment:** All three full views align horizontally with the article full view (same max-width, same padding).
10. **Past event detection:** An event with today's date does NOT show the past event badge. An event from yesterday DOES show it. Boundary: the badge appears after the event's end time has passed (not after the start time).

---

## Files to create or modify

```
A  web/themes/custom/customsolent/templates/content/node--event--full.html.twig
A  web/themes/custom/customsolent/templates/content/node--organisation--full.html.twig
A  web/themes/custom/customsolent/templates/content/node--link--full.html.twig
M  web/themes/custom/customsolent/customsolent.theme (past event preprocess)
M  web/themes/custom/customsolent/css/node.css (or new CSS file for these views)
M  web/themes/custom/customsolent/customsolent.libraries.yml
```
