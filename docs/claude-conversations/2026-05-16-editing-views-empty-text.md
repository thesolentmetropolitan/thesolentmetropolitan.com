# How to edit a Views display's empty-area text — 2026-05-16

Two equivalent paths — Views admin UI for click-and-save edits, direct YAML for tracked changes or bulk edits.

The text rendered when a View returns no results is configured **per Views display** as a "Global: Text area" element under the display's *No results behavior* section.

Each of the four listing Views (`articles_listing`, `events_listing`, `links_listing`, `organisations_listing`) has multiple displays — `view_display_primary_topic`, `view_display_related_topics`, `view_display_events_page`, etc. — and each can carry its own empty text. They start out identical because Drupal duplicated the Default display, but they drift over time.

## Path A — Views admin UI (click-and-save)

1. **Navigate to the View**: `/admin/structure/views/view/events_listing/edit` (replace `events_listing` with the View id).
2. **Pick the display** in the left tab list (e.g. *View Display Primary Topic*).
3. Under the display's settings, find **"No results behavior"** (it usually sits below *Filter criteria* and above *Pager*).
4. Click the **"Global: Text area"** entry (or **Add** it if no empty area exists yet).
5. In the dialog:
    - **Content**: the message body. HTML is allowed depending on the chosen format.
    - **Text format**: pick `Full HTML` if your content includes `<p class="…">` or any `class` attribute — `Basic HTML` strips most class attributes silently.
6. **Apply (this display)** to keep the change scoped, or **Apply (all displays)** to push it down to every display that hasn't overridden it.
7. **Save** the View at the top right.
8. Clear cache: at `/admin/config/development/performance` click *Clear all caches*, or run `drush cr` from a terminal.
9. (Optional) `drush cex -y` to export the change to `config/sync/views.view.events_listing.yml` so it's tracked in git.

## Path B — Direct YAML edit

1. Open the View's config file: `config/sync/views.view.<view_id>.yml` (e.g. `config/sync/views.view.events_listing.yml`).
2. Search for `empty:` — each display has its own block. The structure is:

    ```yaml
    empty:
      area:
        id: area
        table: views
        field: area
        relationship: none
        group_type: group
        admin_label: ''
        plugin_id: text
        empty: true
        content:
          value: '<p class="slnt-view-empty">No events match the current filter. Try clearing the filter or check back soon.</p>'
          format: full_html
        tokenize: false
    ```

3. **Edit `value`** to change the rendered text. Use `<p class="…">` and other HTML if you want CSS hooks.
4. **Set `format` to `full_html`** if your value includes `class` attributes. Other formats (`basic_html`, `restricted_html`) strip them silently and your CSS hook won't appear in the rendered HTML.
5. Save the file.
6. Import: `drush cim -y`. Drupal will list the affected View and ask to confirm.
7. Clear cache: `drush cr`.

To update **every display** in a file at once (e.g. all displays of `events_listing` should share the same wording), use a multi-match replace — every occurrence of the old `value:` / `format:` pair gets the new text. That's how the 2026-05-16 commit updated four listing views in one go.

## Where to put the CSS hook

In this codebase, the empty text is wrapped in `<p class="slnt-view-empty">…</p>` so the theme can target it. Two CSS rules in `web/themes/custom/customsolent/css/section-listing.css` use that hook:

1. Always hide the *related* side's empty in a `view_display_primary_and_related` composition (the primary's empty represents the composition's "no matches" state).
2. Also hide the primary side's empty when the related side has results — otherwise the message would read as "no events match" above a list of related events, which is misleading.

Both rules use the `:has()` selector to detect the related side's state without JavaScript.

## Things to watch

- **`basic_html` strips class attributes.** If you set the format to `basic_html` and your CSS hook stops working after a save, that's why. Switch the format to `full_html`.
- **Per-display drift.** Updating one display doesn't update its siblings. If you want the same text on `view_display_primary_topic` and `view_display_related_topics`, edit both — or do it once on Default and Apply-to-all-displays before any display has overridden it.
- **Cache reflects the old text after a save via UI.** A `drush cr` is usually needed before the new text appears on the front-end (Drupal's Views output is render-cached).
- **In composition (`view_display_primary_and_related`)**: only one empty message shows visually thanks to the CSS rules above. Both displays still carry their own text — keep them identical when editing.

## Files involved

| What | Where |
|---|---|
| Per-listing empty text | `config/sync/views.view.<view_id>.yml` — multiple `empty.area.content.value` entries per file |
| CSS suppression of duplicate | `web/themes/custom/customsolent/css/section-listing.css` — the *Primary + Related composition* and *empty-area* rules |
| CSS hook class | `slnt-view-empty` — wrap each empty message's text in `<p class="slnt-view-empty">…</p>` |
