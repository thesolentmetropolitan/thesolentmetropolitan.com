# Note: section pages need a section_filter paragraph to get sub-sub-term expansion — 2026-05-16

A reminder for next time, parked at the end of the 2026-05-16 expandable_filtering session.

## What was fixed today

Taxonomy term parents corrected — `tid 163` (*Local Government*) and `tid 162` (*Acting Schools*) had `parent` references pointing at the wrong sibling, so filter queries (`loadTree` from a parent's tid) returned no descendants. With those parents fixed, `/sectors?topic=84` (Public Sector) now correctly lists the 9 council org nodes.

## What still doesn't expand on regular section pages

`/sectors`, `/culture`, `/living` show a flat list of *direct children* of the page's primary topic — for `/sectors` that's *Arts, Construction, …, Public Sector, …*. Clicking *Public Sector* filters correctly (the orgs now appear), but the sidebar doesn't reveal *Public Sector's children* (Local Government, etc.) the way `/explore/events` does.

Why: those pages render the **view_display paragraph's own filter sidebar** (no `section_filter` paragraph present). The flat behaviour is the fallback. The `field_show_subterms` boolean field that drives the auto-expand only exists on the `section_filter` paragraph type.

## How to enable sub-sub-term expansion on a section page

For each section page where you want it (e.g. `/sectors`, `/culture`, `/living`):

1. Edit the page (e.g. `/sectors/edit`).
2. Add a **Section Filter** paragraph at the top of `field_content_component` (or inside the same enclosure as the existing view_display paragraph — both work).
3. Leave **field_topic** empty (the paragraph inherits the page's primary topic).
4. Tick **Show sub-topics**.
5. Save.

After cache rebuild:

- The view_display paragraph stops rendering its own filter (suppressed because a `section_filter` is now on the page).
- The section_filter paragraph renders the same children list as before, *plus* one level of grandchildren when a parent is the active filter.
- Example: `/sectors?topic=84` (Public Sector) → Public Sector pill becomes branch-active (magenta-dark text + underline), *All in Public Sector* appears italic, *Local Government* indented under it. Click *Local Government* → URL becomes `?topic=163`, filters to just Local Government orgs.

## Trade-offs

- Editorial cost: one extra paragraph per section page, plus the field tick.
- Visual cost: none — the section_filter paragraph replaces the view_display's own sidebar 1:1 visually when present.
- Maintenance cost: same field on every section page; if the design of the section filter ever changes, all pages pick it up automatically (single source of truth in the section_filter paragraph template/CSS).

## Alternative — extend field_show_subterms to view_display

Instead of adding a section_filter paragraph to every section page, we could put the **Show sub-topics** field on the `view_display` paragraph type too, and read it in `customsolent_preprocess_paragraph__view_display()` the same way the section_filter preprocess does. That way, section pages keep using their existing view_display paragraph's filter and just gain the expand behaviour.

Pros:
- Zero editorial burden when retrofitting existing section pages (one field tick instead of adding a paragraph).
- Same data model across both filter paragraph types.

Cons:
- Field duplicated across two paragraph types (small config maintenance overhead).
- Slightly more code paths to keep in sync.

If we go this route, the implementation is small: a new `field.storage.paragraph.field_show_subterms` instance for the `view_display` bundle (or reuse — the storage already exists), plus the preprocess change.

## Tag for follow-up

If you decide on **Alternative B**, it's roughly the same effort as the original `field_show_subterms` addition on `section_filter` — half an hour with the editor task being one tick per section page. Either path is fine; the choice is about editorial-vs-code surface area.
