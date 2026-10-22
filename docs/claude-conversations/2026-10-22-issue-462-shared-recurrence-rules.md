# Issue 462: editing one regular event's days changed others (22 October 2026)

Session: morning 22 Oct 2026 (about 09:40 to 10:00). Branch
`issue-462-shared-recurrence-rules`. Release
`scripts/release-2026-10-22-issue-462-recurrence-rules.sh` (git pull, cr, run
the split script). No composer or config change.

## Rob's report

"When I edit one of the nodes' days in recurring (e.g. Wed Thu), the other
nodes' days are changed as well." Nodes 914, 916, 918 on prod.

## Cause

Smart Date Recur keeps a recurring event's rule in its own `smart_date_rule`
entity and stores only the rule id on every `field_when` item
(`field_when_rrule`). Quick Node Clone copies the field values, id included,
so a cloned event and its original point at one rule. On save the widget
does "existing rrule, so retrieve and update values"
(`smart_date_recur.module` ~line 657) and rewrites that rule in place, then
regenerates only the saved node's instances. So the other events' rule text
changes at once (the "Every …" phrase is built from the rule) and their
instances would follow on their next save. Locally 914 and 915 shared rule 6;
on prod it is 914, 916, 918 (916 and 918 were made after my DB copy).

A shared rule also breaks the module's own cron: `getParentEntity()` finds
the first node referencing the rule, so only that one gets new instances
extended.

## Fix (customsolent_helpers.module)

- `customsolent_helpers_cloned_node_alter()` (Quick Node Clone's alter hook):
  before the clone form is built, every rule referenced by the clone is
  duplicated (`createDuplicate()`) and the clone's items point at the copies.
  The form's hidden `rrule` element is a `#value`, so this has to happen on
  the form build, and on every request (the POST rebuilds the clone too).
- `customsolent_helpers_node_presave()`: a new node arriving with a rule id
  that another node already references (an import, another clone path) gets
  copies too. One query per rule, new nodes only.
- `customsolent_helpers_cron()`: copies are recorded in State
  (`customsolent_helpers.recurrence_rule_copies`); a day later any copy no
  node references is deleted. The clone form copies on every build, so an
  abandoned form, and the GET before the POST, each leave one behind.
  Smart Date Recur only skips such rules.

Tested: cloning 917 gave the form rule 9 then the save rule 10 (copies of 7);
917 stayed on 7. Aged the State entry and ran cron: the unreferenced copy
deleted, logged. Test clone deleted afterwards.

## Repair (scripts/split_shared_recurrence_rules_2026-10-22.php)

For each rule referenced by more than one node: lowest nid keeps it, the
others get a copy. For a WEEKLY rule the days of each node's rule are
re-derived from the weekdays of that node's own instances (generated when it
was last saved, so the truth for that node even after the shared rule was
rewritten); start/end from the node's first instance; instances regenerated
and the node saved. Non-weekly rules are copied and listed "CHECK the days by
hand". Idempotent, `--dry-run`. Locally: 915 moved to rule 8, both still
Wed/Thu; second run "0 rule copies made".

## For Rob

- Run the release; read the split report; check 914, 916, 918 show their own
  days, and re-save any the report flags.
- The rrule-sharing is a contrib gap (Quick Node Clone + Smart Date Recur);
  worth an issue upstream if you have a moment, our hook is the workaround.
