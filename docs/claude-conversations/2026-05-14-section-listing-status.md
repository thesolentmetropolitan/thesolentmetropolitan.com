# Section listing — current status / what's next — 2026-05-14

A short prompt-for-future-Rob. Drop this into a new Claude conversation
when you come back to this work and it should have everything it needs
to resume without re-reading the entire transcript.

## Everything from the brief plus the iteration items is in:

- **field_topic** priority + all-topics fallback ✓
- **Section Filter paragraph** + sidebar layout via `:has()` ✓
- **Primary-topic kicker** on cross-section teasers ✓
- **Combined primary + related** virtual display now on **organisations**, **links**, AND **events** ✓
- **Pagination** generalised to all views ✓
- **Date exposed filter** (today / this weekend / this week / this month) ✓
- **Pill styling** with rectangular shape, matches topic-item height + colour palette ✓
- **Combined topic + date filtering** — pill URLs and form preserve each other's state ✓
- **Auto-submit JS** so pill clicks don't need an Apply button ✓
- **"All" relabel** of `- Any -` ✓

## Open issues for the future

- **[#257](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/257)** — verify date-filter boundary handling once "Next month onwards" is added; also covers slug-keyed group ids in the URL.
- **[#258](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/258)** — clean URLs via custom controller (`/culture/community/this-month`).

## Editor task for the events primary+related

When you want to use it, on any section page (Culture, Sectors, Living, or a leaf like Culture/Technology):

1. Add a **View Display** paragraph.
2. **View**: `Events Listing`
3. **Display**: `View Display Primary and Related Topics` (this is the new option that just appeared in the dropdown).
4. Save. The page now shows events whose `field_primary_topic` matches OR whose `field_related_topics` includes the page's topic. The "from Sectors / Technology" kicker appears automatically on cross-section events.

## Workflow reminder

**Always `drush cex` before `drush cim`** — any Views/config change made via the admin UI lives in the DB only, and re-importing stale YAML will revert it silently. Bit me on 2026-05-14 (the pill widget reverted from Radios to Select after a routine `drush cim`).

## Read the implementation logs for full context

- `2026-05-13-section-listing-enhancements-brief.md` — the design brief that started it all.
- `2026-05-13-section-listing-enhancements-implementation.md` — first round (the original brief).
- `2026-05-14-section-listing-iteration-implementation.md` — the date filter + pills + combined filtering iteration.
