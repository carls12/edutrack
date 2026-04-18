<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

/**
 * Improved timetable generator:
 *  1. Respects teacher availability
 *  2. Spreads lessons evenly across days (least-loaded day first)
 *  3. Max 1 lesson of the same subject per class per day
 *  4. Paired subjects (subject_pairs) are placed at the SAME slot (parallel groups)
 *  5. Manual / locked entries are never touched
 *  6. Reports any hours that could not be placed
 */

// ── Schema migration: drop per-slot unique key so paired subjects can share a slot ──
try {
    db()->exec("ALTER TABLE timetable_entries DROP INDEX uniq_slot");
} catch (Throwable $e) { /* already removed or doesn't exist */ }

// ── Base data ────────────────────────────────────────────────────────────────────────
$days = [1, 2, 3, 4, 5];

$periods = db()->query(
    "SELECT id, sort_order FROM periods WHERE is_teaching_period=1 ORDER BY sort_order"
)->fetchAll();
if (!$periods) fail('No teaching periods configured.');

$assignments = db()->query(
    "SELECT teacher_user_id, subject_id, class_id, hours_per_week
     FROM teacher_assignments ORDER BY class_id, subject_id"
)->fetchAll();
if (!$assignments) fail('No teacher assignments configured.');

// ── Availability map:  "teacher-day-period" => bool  (absent key = available) ───────
$availMap = [];
foreach (db()->query(
    "SELECT teacher_user_id, day_of_week, period_id, is_available FROM teacher_availability"
)->fetchAll() as $r) {
    $availMap["{$r['teacher_user_id']}-{$r['day_of_week']}-{$r['period_id']}"] = ((int)$r['is_available'] === 1);
}
$avail = fn(int $t, int $d, int $p): bool => $availMap["$t-$d-$p"] ?? true;

// ── Subject pairs:  $paired[class_id][subject_id] = partner_subject_id ───────────────
$paired = [];
try {
    foreach (db()->query(
        "SELECT subject_id_1, subject_id_2, class_id
         FROM subject_pairs WHERE is_active=1 AND class_id IS NOT NULL"
    )->fetchAll() as $r) {
        $c  = (int)$r['class_id'];
        $s1 = (int)$r['subject_id_1'];
        $s2 = (int)$r['subject_id_2'];
        $paired[$c][$s1] = $s2;
        $paired[$c][$s2] = $s1;
    }
} catch (Throwable $e) { /* subject_pairs table may not exist yet */ }

// ── Assignment index for quick look-up ───────────────────────────────────────────────
$aIdx = [];
foreach ($assignments as $a) {
    $aIdx[(int)$a['class_id']][(int)$a['subject_id']] = $a;
}

// ── Transaction ──────────────────────────────────────────────────────────────────────
db()->beginTransaction();
try {
    db()->exec("DELETE FROM timetable_entries WHERE source='auto' AND is_locked=0");

    // Booking state — rebuilt as we insert
    $teacherBusy   = [];   // "teacher-day-period"      => true
    $classSlot     = [];   // "class-day-period"        => [subject_id, ...]
    $subjDayCount  = [];   // "class-subject-day"       => int   (max-1-per-day rule)
    $classDayCount = [];   // "class-day"               => int   (for even-spread sort)

    foreach (db()->query(
        "SELECT class_id, subject_id, teacher_user_id, day_of_week, period_id FROM timetable_entries"
    )->fetchAll() as $e) {
        $teacherBusy["{$e['teacher_user_id']}-{$e['day_of_week']}-{$e['period_id']}"] = true;
        $classSlot["{$e['class_id']}-{$e['day_of_week']}-{$e['period_id']}"][]        = (int)$e['subject_id'];
        $k  = "{$e['class_id']}-{$e['subject_id']}-{$e['day_of_week']}";
        $dk = "{$e['class_id']}-{$e['day_of_week']}";
        $subjDayCount[$k]  = ($subjDayCount[$k]  ?? 0) + 1;
        $classDayCount[$dk] = ($classDayCount[$dk] ?? 0) + 1;
    }

    $ins = db()->prepare(
        "INSERT INTO timetable_entries
             (class_id, subject_id, teacher_user_id, day_of_week, period_id, source, is_locked)
         VALUES (?, ?, ?, ?, ?, 'auto', 0)"
    );

    // ── Commit one slot and update booking state ──────────────────────────────────
    $commit = function (int $c, int $s, int $t, int $d, int $pid)
        use ($ins, &$teacherBusy, &$classSlot, &$subjDayCount, &$classDayCount): void {
        $ins->execute([$c, $s, $t, $d, $pid]);
        $teacherBusy["$t-$d-$pid"]  = true;
        $classSlot["$c-$d-$pid"][]  = $s;
        $k  = "$c-$s-$d"; $subjDayCount[$k]  = ($subjDayCount[$k]  ?? 0) + 1;
        $dk = "$c-$d";    $classDayCount[$dk] = ($classDayCount[$dk] ?? 0) + 1;
    };

    // ── Days sorted by fewest lessons for a class (even-spread heuristic) ────────
    $sortedDays = function (int $c) use ($days, &$classDayCount): array {
        $d = $days;
        usort($d, fn($a, $b) => ($classDayCount["$c-$a"] ?? 0) <=> ($classDayCount["$c-$b"] ?? 0));
        return $d;
    };

    // ── Can a regular (unpaired) lesson be placed here? ───────────────────────────
    $canPlace = function (int $c, int $s, int $t, int $d, int $pid)
        use (&$teacherBusy, &$classSlot, &$subjDayCount, $avail): bool {
        if (isset($teacherBusy["$t-$d-$pid"]))       return false; // teacher double-booked
        if (!empty($classSlot["$c-$d-$pid"]))         return false; // class slot occupied
        if (!$avail($t, $d, $pid))                    return false; // teacher unavailable
        if (($subjDayCount["$c-$s-$d"] ?? 0) >= 1)   return false; // already 1 that subject today
        return true;
    };

    $placed    = []; // "class-subject" => int
    $donePairs = []; // pair key        => true

    // ═══════════════════════════════════════════════════════════════════════════════
    // PASS 1 — Paired subjects: find slots where BOTH teachers are free, insert both
    // ═══════════════════════════════════════════════════════════════════════════════
    foreach ($assignments as $a) {
        $c    = (int)$a['class_id'];
        $s    = (int)$a['subject_id'];
        $t    = (int)$a['teacher_user_id'];
        $need = (int)$a['hours_per_week'];

        $partner = $paired[$c][$s] ?? null;
        if ($partner === null) continue;

        $pKey = "$c-" . min($s, $partner) . '-' . max($s, $partner);
        if (isset($donePairs[$pKey])) continue;
        $donePairs[$pKey] = true;

        if (!isset($aIdx[$c][$partner])) continue;
        $pa = $aIdx[$c][$partner];
        $pt = (int)$pa['teacher_user_id'];

        $slots = min($need, (int)$pa['hours_per_week']);
        $n     = 0;

        for ($pass = 0; $pass < 3 && $n < $slots; $pass++) {
            foreach ($sortedDays($c) as $day) {
                foreach ($periods as $p) {
                    if ($n >= $slots) break 2;
                    $pid = (int)$p['id'];

                    if (isset($teacherBusy["$t-$day-$pid"]))       continue;
                    if (isset($teacherBusy["$pt-$day-$pid"]))      continue;
                    if (!$avail($t, $day, $pid))                   continue;
                    if (!$avail($pt, $day, $pid))                  continue;
                    if (!empty($classSlot["$c-$day-$pid"]))        continue;
                    if (($subjDayCount["$c-$s-$day"]       ?? 0) >= 1) continue;
                    if (($subjDayCount["$c-$partner-$day"] ?? 0) >= 1) continue;

                    $commit($c, $s,       $t,  $day, $pid);
                    $commit($c, $partner, $pt, $day, $pid);
                    $n++;
                }
            }
        }

        $placed["$c-$s"]       = ($placed["$c-$s"]       ?? 0) + $n;
        $placed["$c-$partner"] = ($placed["$c-$partner"] ?? 0) + $n;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // PASS 2 — All remaining assignments (unpaired, or leftover hours beyond pair quota)
    // ═══════════════════════════════════════════════════════════════════════════════
    foreach ($assignments as $a) {
        $c    = (int)$a['class_id'];
        $s    = (int)$a['subject_id'];
        $t    = (int)$a['teacher_user_id'];
        $need = (int)$a['hours_per_week'];

        $remaining = $need - ($placed["$c-$s"] ?? 0);
        if ($remaining <= 0) continue;

        $n = 0;
        for ($pass = 0; $pass < 3 && $n < $remaining; $pass++) {
            foreach ($sortedDays($c) as $day) {
                foreach ($periods as $p) {
                    if ($n >= $remaining) break 2;
                    $pid = (int)$p['id'];
                    if (!$canPlace($c, $s, $t, $day, $pid)) continue;
                    $commit($c, $s, $t, $day, $pid);
                    $n++;
                }
            }
        }

        $placed["$c-$s"] = ($placed["$c-$s"] ?? 0) + $n;
    }

    db()->commit();

    // ── Build warning list for any assignment that couldn't be fully scheduled ────
    $warnings = [];
    foreach ($assignments as $a) {
        $c    = (int)$a['class_id'];
        $s    = (int)$a['subject_id'];
        $need = (int)$a['hours_per_week'];
        $got  = $placed["$c-$s"] ?? 0;
        if ($got < $need) {
            $warnings[] = "Class $c / Subject $s: placed $got of $need h/wk";
        }
    }

    $msg = 'Timetable generated successfully.';
    if ($warnings) {
        $msg .= ' Could not fully place: ' . implode('; ', $warnings);
    }
    ok(['message' => $msg, 'warnings' => $warnings]);

} catch (Exception $e) {
    db()->rollBack();
    fail('Generation failed: ' . $e->getMessage(), 500);
}
