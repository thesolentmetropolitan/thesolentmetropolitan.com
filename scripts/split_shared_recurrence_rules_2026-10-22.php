<?php

/**
 * @file
 * Gives every recurring event its own recurrence rule (issue 462).
 *
 * Cloned events shared one smart_date_rule (see
 * customsolent_helpers_cloned_node_alter()), so editing the days on one
 * changed the rule the others display and would regenerate from. For each
 * rule referenced by more than one node:
 *   - the lowest nid keeps the rule; every other node gets a copy;
 *   - for a WEEKLY rule, the days (BYDAY) of each node's rule are set from
 *     the weekdays of the instances that node actually holds — its
 *     instances were generated when IT was last saved, so they are the
 *     truth for that node even where the shared rule has since been
 *     rewritten by another node's edit;
 *   - the rule's start/end are set from the node's first instance.
 * Events with a rule of their own are reported and left alone. Any node
 * whose days could not be derived (not weekly) is listed for a manual
 * check: open it, confirm the days, save.
 *
 * Idempotent; --dry-run.
 *
 * Usage:
 *   drush php:script scripts/split_shared_recurrence_rules_2026-10-22.php
 *   drush php:script scripts/split_shared_recurrence_rules_2026-10-22.php -- --dry-run
 */

use Drupal\node\Entity\Node;
use Drupal\smart_date_recur\Entity\SmartDateRule;

$dry = isset($extra) && is_array($extra) && (in_array('--dry-run', $extra, TRUE) || in_array('-n', $extra, TRUE));
$say = static function (string $line) { echo $line, PHP_EOL; };
$act = $dry ? 'WOULD ' : '';
$field = 'field_when';
$day_codes = ['Mon' => 'MO', 'Tue' => 'TU', 'Wed' => 'WE', 'Thu' => 'TH', 'Fri' => 'FR', 'Sat' => 'SA', 'Sun' => 'SU'];

$db = \Drupal::database();
$rows = $db->query("SELECT {$field}_rrule AS rid, GROUP_CONCAT(DISTINCT entity_id ORDER BY entity_id) AS nids
  FROM {node__{$field}} WHERE {$field}_rrule IS NOT NULL GROUP BY {$field}_rrule ORDER BY rid")->fetchAll();

/** The weekdays (MO..SU, in week order) of a node's instances for a rule. */
$days_from_instances = static function (Node $node, int $rid) use ($field, $day_codes): array {
  $found = [];
  foreach ($node->get($field) as $item) {
    if ((int) $item->rrule !== $rid || empty($item->value)) {
      continue;
    }
    $tz = new \DateTimeZone($item->timezone ?: 'Europe/London');
    $found[$day_codes[(new \DateTimeImmutable('@' . (int) $item->value))->setTimezone($tz)->format('D')]] = TRUE;
  }
  return array_values(array_filter($day_codes, static fn($code) => isset($found[$code])));
};

/** Points the node's items for $old_rid at $new_rid and aligns the rule with the node. */
$align = static function (Node $node, SmartDateRule $rule, int $old_rid) use ($field, $days_from_instances, $say, $act, $dry): void {
  $first = NULL;
  foreach ($node->get($field) as $item) {
    if ((int) $item->rrule === $old_rid) {
      $first ??= $item;
      if (!$dry) {
        $item->rrule = $rule->id() ?: $old_rid;
      }
    }
  }
  $changes = [];
  if ((string) $rule->get('freq')->getString() === 'WEEKLY') {
    $days = $days_from_instances($node, $old_rid);
    $params = $rule->get('parameters')->getString();
    $current = preg_match('/BYDAY=([A-Z,]+)/', $params, $m) ? $m[1] : '';
    $wanted = implode(',', $days);
    if ($days && $wanted !== $current) {
      $params = $current === '' ? trim($params . ';BYDAY=' . $wanted, ';') : str_replace('BYDAY=' . $current, 'BYDAY=' . $wanted, $params);
      $rule->set('parameters', $params);
      $changes[] = "days " . ($current ?: '(none)') . " -> $wanted (from its instances)";
    }
  }
  else {
    $changes[] = 'not weekly: CHECK the days by hand';
  }
  if ($first) {
    $rule->set('start', (int) $first->value);
    $rule->set('end', (int) $first->end_value);
  }
  if (!$dry) {
    $rule->set('instances', ['data' => $rule->getRuleInstances()]);
    $rule->save();
    foreach ($node->get($field) as $item) {
      if ((int) $item->rrule === $old_rid) {
        $item->rrule = $rule->id();
      }
    }
    $node->save();
  }
  $say("    node/{$node->id()} \"{$node->label()}\" -> rule " . ($rule->id() ?: 'new') . ($changes ? ': ' . implode('; ', $changes) : ''));
};

$split = 0;
foreach ($rows as $row) {
  $rid = (int) $row->rid;
  $nids = array_map('intval', explode(',', $row->nids));
  $rule = SmartDateRule::load($rid);
  if (!$rule) {
    $say("  !!    rule $rid is referenced by node(s) " . implode(', ', $nids) . " but does not exist");
    continue;
  }
  if (count($nids) === 1) {
    $say("  OK    rule $rid ({$rule->getRule()}) belongs to node/{$nids[0]} only");
    continue;
  }
  $say("  {$act}SPLIT rule $rid ({$rule->getRule()}) shared by nodes " . implode(', ', $nids));
  $keeper = array_shift($nids);
  $align(Node::load($keeper), $rule, $rid);
  foreach ($nids as $nid) {
    $copy = $rule->createDuplicate();
    $align(Node::load($nid), $copy, $rid);
    $split++;
  }
}
$say("== done: $split rule copies made" . ($dry ? ' [dry run]' : ''));
