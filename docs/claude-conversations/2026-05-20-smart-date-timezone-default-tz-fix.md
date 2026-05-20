# Smart Date timezone: form-widget bug + the rules that finally make sense — 2026-05-20

After the [UTC → Europe/London site migration on 2026-05-16](2026-05-16-event-times-tz-migration-runbook.md), event times started displaying 1 hour later than what editors typed in the admin form. Type `12:00`, see `1pm`. Despite the site timezone being set to Europe/London, despite a per-row "default: Europe/London" dropdown on the form, despite the formatter being told Europe/London at display time, the editor's input ended up stored as if it were UTC.

This document explains why, what the fix is, the exhaustive list of places where the timezone is configured (and which one *actually wins*), and feedback to record about the Smart Date editor experience.

## The bug, exactly

`smart_date` 4.2.5's `smartdate_timezone` widget has a setting called **"Default timezone"** at the form-display level with three options:

| Option (UI label) | Stored value | What the widget actually does |
|---|---|---|
| **Site default (ignores any user override)** | `''` (empty string) | The per-row TZ dropdown pre-selects empty. On save, `massageFormValues()` sees no timezone and stores `DrupalDateTime->getTimestamp()` with no remap — **the typed hours go in as UTC**. The "site default" label is misleading; it does NOT fall through to `date_default_timezone_get()`. |
| User's timezone, defaulting to site (always saved) | `'user'` | Pre-selects `date_default_timezone_get()` (= site default if user has no per-profile override). Always stores a TZ. Works. |
| A custom timezone (always saved) | `'custom'` | Pre-selects the `custom_tz` setting (a hard-coded TZ). Always stores a TZ. Ignores user-profile overrides. Works. |

The default is the broken-by-design "Site default (empty)" option. The empty value bypasses the timezone conversion entirely.

Source: `web/modules/contrib/smart_date/src/Plugin/Field/FieldWidget/SmartDateTimezoneWidget.php` lines 44–53, and `SmartDateWidgetBase::massageFormValues()` lines 318–365.

## The fix on dev

Three config changes:

1. **Form display widget** (`core.entity_form_display.node.event.default.yml`):
   ```yaml
   default_tz: custom        # was ''
   custom_tz:  Europe/London # was ''
   ```
2. **Teaser view display** (`core.entity_view_display.node.event.teaser.yml`):
   ```yaml
   timezone: Europe/London   # was ''
   ```
3. **Default (full page) view display** (`core.entity_view_display.node.event.default.yml`):
   ```yaml
   timezone: Europe/London   # was ''
   ```

(The `compact` view display was already set to Europe/London during yesterday's work.)

Plus a one-off data fix on `nid 247` (Palmerston Park Boogie Down 3) — its stored `value` and `end_value` were shifted back by 3600 seconds so the BST display reads `12 – 8pm` as intended.

After: editor types `12:00 – 20:00`, Drupal stores `11:00 – 19:00 UTC` (which IS `12:00 – 20:00 BST`), display renders `12 – 8pm`. ✓

## Hierarchy of timezone settings — which wins?

For event times specifically, in order of *which one drives storage* (top wins):

1. **Per-row TZ dropdown on the node edit form** (`/node/N/edit` → "when" → "Time zone")
   * If the editor picks an explicit timezone, that one wins. Stored alongside the timestamp.
   * If the editor picks "- default: Europe/London -" (label says default, value is empty string), the widget falls to setting 2.
2. **Form widget `default_tz` setting** (`/admin/structure/types/manage/event/form-display` → gear on "when")
   * `custom` → uses `custom_tz` (a hard-coded TZ) → **stores it explicitly**. ← we now use this.
   * `user` → uses `date_default_timezone_get()` (= site default unless the editor has a per-user TZ) → stores it explicitly.
   * `''` (Site default label) → **does NOT fall through**, stores no TZ, treats input as UTC. ← the bug.
3. **Site default** (`/admin/config/regional/settings`) → `Europe/London`
   * Used as the value resolved by `date_default_timezone_get()` when setting 2 is `user` and the editor has no per-profile override.
4. **User profile TZ** (`/user/N/edit`)
   * Overrides setting 3 when setting 2 is `user`.
   * Ignored when setting 2 is `custom`.

For display (which one drives what the page shows):

5. **View-display formatter "Time zone"** (per view mode: compact / teaser / default / etc.)
   * Each view mode's formatter has its own dropdown. Each must be set to **Europe/London** explicitly, OR the stored TZ is used. We now have all three set explicitly.
   * Display NEVER affects storage. A correct display rule cannot fix a wrongly-stored value.

### The simple version

For an editor to **type `20:00`, see `8pm`** on this site, the chain has to be:

* Form widget `default_tz` = `custom`, `custom_tz` = `Europe/London` (1, 2 — drives storage)
* Per-row dropdown left at the default (1 falls to 2)
* Each view mode's formatter `timezone` = `Europe/London` (5 — drives display)

Settings 3 and 4 don't need to match; with `default_tz=custom` they're ignored.

## Editor UX feedback (for ourselves and Smart Date)

* **The "Site default" label on `default_tz` is misleading**: it implies fall-through to the site TZ, but actually disables timezone handling. A non-trivial editor or site-builder would assume this is the safe choice. Either it should fall through, or it should be labelled honestly as "Treat input as UTC (no timezone saved)".
* **Three places to set a timezone on one field config screen** (`default_tz` dropdown, `custom_tz` dropdown, `allowed_timezones` multi-select) without any clear "what supersedes what" guidance. Add to that the per-row dropdown on every edit form, the site default, and one timezone per view-display formatter — **six layers** to align before an editor sees the time they typed.
* **The per-row dropdown defaults to a sentinel ("- default: X -") that visually claims to mean X but, when default_tz is empty, actually means "no timezone"**. The dropdown label and the dropdown behaviour disagree.
* For a content editorial site, the practical workflow needs to be: configure once at site-build, then editors only ever see and use local time. The current Smart Date defaults force editors and site-builders to assume timezone literacy at every layer.

Not raising an upstream issue ([smart_date](https://drupal.org/project/smart_date)) for now. Tracking in this repo at [#282](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/282) so we have a single place to land follow-ups (e.g. if it bites us again after a contrib update, or if the `smartdate_custom` formatter ever gets a config schema and we have to revisit settings).

## Deploying to live

The three config changes deploy as normal:

```bash
git pull
drush cim -y
drush cr
```

**That's all the config side needs.** New events created on live after this deploy will be stored correctly.

## Events on live created/edited since the May 16 migration

Any event whose `node.changed` is after **2026-05-16 13:31 BST** (the migration commit) is potentially affected by the bug — saved via the broken form widget, so its stored UTC is 1 hour ahead of what the editor intended.

Suspect events from this dev environment (use as a guide for what to spot-check on live):

| nid | changed | start (BST) | title |
|---|---|---|---|
| 247 | 2026-05-20 02:13 | 24 May 12:00 → 20:00 | Palmerston Park Boogie Down 3 — **already fixed locally; need same shift on live** |
| 226 | 2026-05-19 23:56 | 19 May 17:30 → 17:30 | Southern Creative Catalyst \| MEET |
| 233 | 2026-05-19 12:20 | 23 May 14:00 → 15:30 | People's Emergency Briefing (Film Screening …) |
| 221 | 2026-05-17 16:53 | 20 May 10:30 → 10:30 | Mayor Making Ceremony |
| 232 | 2026-05-16 16:15 | 23 May 01:00 → 00:59 | Wanderlust Festival, Southampton — explicit `tz=UTC` on the row, looks wrong |

(Events with `changed=2026-05-16 13:56 BST` were saved a few minutes before the migration ran and are likely correct; the migration shift handled them. Verify any that look off.)

### One-off shift command for live (per node)

For each event that displays 1 hour late, run on live:

```bash
ddev drush php:eval "
\$n = \Drupal\node\Entity\Node::load(NID_HERE);
foreach (\$n->get('field_when') as \$item) {
  \$item->value     -= 3600;
  \$item->end_value -= 3600;
  \$item->timezone   = 'Europe/London';
}
\$n->save();
"
```

(Replace `NID_HERE` with the node id. Don't run on the same node twice — it shifts again.)

### Bulk option (not recommended without spot-checking first)

A bulk shift of every event whose `changed >= 2026-05-16 13:31 BST` is **risky** because all-day events and pre-migration events would also be touched. Spot-check the suspect list above first, then shift only the ones that are wrong.

## Files

```
M  config/sync/core.entity_form_display.node.event.default.yml
   - default_tz: '' -> custom
   - custom_tz:  '' -> Europe/London
M  config/sync/core.entity_view_display.node.event.teaser.yml
   - timezone: '' -> Europe/London
M  config/sync/core.entity_view_display.node.event.default.yml
   - timezone: '' -> Europe/London
A  docs/claude-conversations/2026-05-20-smart-date-timezone-default-tz-fix.md
```

## Related

* [#282](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/282) — issue we're filing in this repo to track the Smart Date editor-UX feedback.
* `2026-05-16-event-times-tz-migration-runbook.md` — the original UTC → Europe/London migration. This bug only became visible *after* that migration started shifting display times into BST.
* Earlier `customsolent_helpers` work for site-specific Drupal-internals fixes — same general pattern of "small module/config touch hides a Drupal-core quirk so editors don't have to know about it".
