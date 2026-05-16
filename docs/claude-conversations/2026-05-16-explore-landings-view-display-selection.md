# /explore/* landings — View Display selection matters — 2026-05-16

A quick reference note. Discovered while investigating "topic sub-term filter isn't filtering events" on `/explore/events`.

## The gotcha

The `events_listing` View has multiple displays, and **only some accept the topic as a contextual argument**:

| Display | Contextual filter | Effect when given a topic |
|---|---|---|
| `view_display_events_page` | **None** (`arguments: {}`) | Ignores the topic — always shows all events |
| `view_display_primary_topic` | `field_primary_topic_target_id` | Filters to events whose primary topic matches |
| `view_display_related_topics` | `field_related_topics_target_id` | Filters to events whose related topics include it |
| `view_display_primary_and_related` | (virtual — renders both above) | Filters by primary OR related |

The "events page" display was originally a no-argument list of all events for the `/explore/events` landing — fine for showing everything, but when the topic filter pills got added, this display silently ignores the topic ID. Result: filter pills appear to do nothing, all events show regardless.

## The fix

Edit the composite page (e.g. `/explore/events`), find the View Display paragraph, change the **Display** dropdown from `View Display Events Page` to **`View Display Primary and Related Topics`**. Save and reload.

After the change:
- No filter → preprocess hands the View the all-topics scope → every content-tagged event matches → see all events as before.
- `?topic=34` (Art & Design) → filters to events whose primary topic is Art & Design *or* whose related topics include it.
- Cross-section events that appear via related topics get the "from \<Section\>" kicker automatically.

## Same pattern applies to the other Explore landings

If the topic pills don't filter on `/explore/articles` or `/explore/orgs-directories`, check the View Display paragraph on those pages too. The relevant displays are:

| Landing | Switch to display |
|---|---|
| `/explore/events` | `view_display_primary_and_related` on `events_listing` |
| `/explore/articles` | `view_display_primary_and_related` on `articles_listing` (note: this placeholder needs to exist — currently only `organisations_listing` and `links_listing` have it. If `articles_listing` doesn't yet, add it the same way `events_listing` got it — see commit `e983db1`) |
| `/explore/orgs-directories` | `view_display_primary_and_related` on `organisations_listing` (and possibly a separate paragraph for `links_listing`) |

## Open question for future iterations

Two ways to make this less of a footgun for editors:

1. **Train editors** to always pick *Primary and Related* on /explore/* landings.
2. **Add the contextual argument** (`field_primary_topic_target_id`) to the page-style displays (`view_display_events_page`, `view_display_orgs_directories_page`, ...) so they accept a topic too. Lets editors pick any display and have filtering work. Trade-off: it also changes the default behaviour of those displays — they'd now respect any contextual arg passed in, which might bite somewhere else.

Defer the decision until another mismatch surfaces.
