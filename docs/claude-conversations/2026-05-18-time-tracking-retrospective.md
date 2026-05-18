# Time spent so far + going-forward logging — 2026-05-18

A pause-and-reflect on how much time has gone into the work so far, to inform future estimation. Issue [#281](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/281).

The numbers below are reconstructed from commit timestamps — Claude can see *when* commits landed but not the work either side of them, so these are lower bounds. The real total is higher; how much higher depends on padding (see below).

## The recent intensive run — section-listing-enhancements (May 3–18 2026)

~16 calendar days, mostly late evening / past-midnight sessions. ~12 of those had substantial work; the rest were touch-ups or quiet days.

| Theme | Dates | Rough hours (commit-adjacent) |
|---|---|---|
| Filter infrastructure: separate listing views per content type, section filter paragraph, primary-topic kicker scaffolding | May 3–4 | 12–16 |
| Event / link / organisation teaser polish (icons, colours, CTA, external-link affordances) | May 4–7 | 10–14 |
| Smart Date upgrade + front-page compact event grid + heading paragraph gradients | May 8–10 | 10–14 |
| Multi-day event date display, taxonomy reorg | May 12 | 4–6 |
| Section listing enhancements composite commit + primary_and_related virtual display | May 13–14 | 6–8 |
| Grouped date filter pills (today / weekend / week / month), per-page suppression on front page | May 14–16 | 6–8 |
| Timezone fix + sub-term expansion + filter UI tweaks + dedupe + Explore guard | May 16 | 6–8 |
| In/from kicker implementation + drush it diagnosis + term position drama | May 17 | 10–12 |
| Pagination filter-preservation + mobile pager regression | May 17 | 2–3 |
| Kicker flip-flop diagnosis + lazy_builder fix + custom module + writeup | May 17–18 | 5–7 |

**Subtotal: ~70–95 hours of active collaboration over the 16 days**, across ~12 working sessions.

## Earlier work

First commits trailing `Co-Authored-By: Claude` land on 2026-01-25. Between Jan 25 and early May the cadence was lighter — menu styling, mobile-menu, sundry tweaks. Estimated **15–25 hours** across that earlier window. Work intensified noticeably from early May with the section-listing-enhancements push.

## Total to date

Commit-adjacent: **~85–120 hours**.

With the padding adjustment Rob noted, real total is reasonably higher — closer to **120–160 hours**.

## Why commit time underestimates real time

Commits are breadcrumbs. The actual work envelopes them on both sides:

- **Before**: loading context, re-reading prior work, scanning the code, deciding what to do, manual exploration in the Drupal admin UI.
- **After**: visual verification, testing edge cases, writing notes, cleaning up, the "do I commit this now or iterate one more time" pause.

Rob's first session of a day costs about 20 minutes of context-reload before the first commit lands. Wrap-up at end of session adds 30+ minutes that often doesn't produce a commit at all (just verification + cleanup).

## Patterns worth noting for future estimation

- **Diagnosis often takes longer than fixing.** The lazy_builder fix today was a small code change. The hours went into empirically proving *why* three earlier defenses didn't work — and the actual unlock (theme alters silently dead in Drupal 11) came late in the session. When a bug reappears after a "fix", budget for diagnosis time, not just code time.
- **CSS / visual iteration eats hours.** Several commits per day in May 4–7 and May 14–16 are sub-30-minute polish commits in sequence. Each individually small but they add up. Bigger feature work tends to land as one larger commit after longer thinking.
- **Late-night sessions skew the rhythm.** Many commits are 01:00–04:00 local — useful for planning around energy/focus rather than calendar slots.
- **Diagnosis sessions have outsized leverage.** Today's diagnosis (Drupal 11 `ModuleHandler::alter` skips themes) is a finding that explains a previously-mysterious class of bugs and informs future architecture decisions. That kind of session is hard to estimate up-front because the value is "we learned something" rather than "we shipped feature X".

## Going forward — proactive time logging

Three low-friction options, picked over heavier time-tracking tools (which tend to get skipped):

### 1. Start / end note in the conversation

Rob drops `"started 21:30"` at the start of a session and Claude captures it. `"stopping"` at the end logs the delta. Zero tooling. Just a habit.

### 2. Session footer in commit messages

On the *last* commit of a session, append a trailer:

```
Session: 21:30–00:45
```

Then `git log --grep="Session:"` gives an accurate timeline retroactively. The footer survives in git forever. Less invasive than per-commit timestamps.

### 3. Claude prompt at session start

A persistent memory note so Claude asks at session start: "Want me to log a start time for this session?" — removes the burden from Rob to remember.

Rob is going to try one or more of these and see what sticks. The most important property is friction — anything that takes more than 5 seconds will be skipped.

## Action

Save a memory: at the start of future sessions, prompt Rob about logging a start time. Will be added to memory after this writeup is committed.
