<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

/**
 * Human-logic timetable generator
 * ─────────────────────────────────────────────────────────────────────────────
 * Rules applied in order:
 *  1. Never double-book a teacher or a class slot
 *  2. Respect teacher availability
 *  3. Max 3 periods of the same subject per class per day
 *  4. Prefer CONSECUTIVE double periods (2 back-to-back) over isolated singles
 *     → lessons land in natural blocks, not scattered one per day
 *  5. Day selection: prefer the day where the teacher has the MOST free slots
 *     (fills the teacher's open windows first), class-load as tiebreaker
 *  6. After each block is placed the day-sort refreshes so the next block
 *     naturally lands on a different day (even spread emerges organically)
 *  7. Paired subjects share the exact same slot (parallel groups)
 *  8. Manual / locked entries are never touched
 *  9. Any unplaced hours are reported in the response
 */

// One-time schema migration: drop unique-per-slot key so paired subjects can share a slot
try { db()->exec("ALTER TABLE timetable_entries DROP INDEX uniq_slot"); } catch (Throwable $e) {}

// ── Base data ────────────────────────────────────────────────────────────────
$days = [1, 2, 3, 4, 5]; // Mon–Fri

// Teaching periods in sort order (used for consecutive-slot detection)
$periods = db()->query(
    "SELECT id FROM periods WHERE is_teaching_period=1 ORDER BY sort_order"
)->fetchAll(PDO::FETCH_COLUMN);   // array of period IDs in order
$periods = array_map('intval', $periods);
if (!$periods) fail('No teaching periods configured.');

$assignments = db()->query(
    "SELECT teacher_user_id, subject_id, class_id, hours_per_week
     FROM teacher_assignments ORDER BY class_id, subject_id"
)->fetchAll();
if (!$assignments) fail('No teacher assignments configured.');

// ── Availability ─────────────────────────────────────────────────────────────
$availMap = [];
foreach (db()->query(
    "SELECT teacher_user_id, day_of_week, period_id, is_available FROM teacher_availability"
)->fetchAll() as $r) {
    $availMap["{$r['teacher_user_id']}-{$r['day_of_week']}-{$r['period_id']}"] = ((int)$r['is_available'] === 1);
}
$avail = fn(int $t, int $d, int $p): bool => $availMap["$t-$d-$p"] ?? true;

// ── Subject pairs ─────────────────────────────────────────────────────────────
$paired = [];
try {
    foreach (db()->query(
        "SELECT subject_id_1, subject_id_2, class_id FROM subject_pairs WHERE is_active=1 AND class_id IS NOT NULL"
    )->fetchAll() as $r) {
        $c = (int)$r['class_id'];
        $paired[$c][(int)$r['subject_id_1']] = (int)$r['subject_id_2'];
        $paired[$c][(int)$r['subject_id_2']] = (int)$r['subject_id_1'];
    }
} catch (Throwable $e) {}

$aIdx = [];
foreach ($assignments as $a) $aIdx[(int)$a['class_id']][(int)$a['subject_id']] = $a;

// ── Transaction ───────────────────────────────────────────────────────────────
db()->beginTransaction();
try {
    db()->exec("DELETE FROM timetable_entries WHERE source='auto' AND is_locked=0");

    // Live booking state — updated as we insert
    $teacherBusy = [];   // "t-d-p"   => true
    $classSlot   = [];   // "c-d-p"   => [subject_ids]
    $subjDay     = [];   // "c-s-d"   => count  (max-3 rule)
    $classDay    = [];   // "c-d"     => count  (spread tiebreaker)

    foreach (db()->query(
        "SELECT class_id,subject_id,teacher_user_id,day_of_week,period_id FROM timetable_entries"
    )->fetchAll() as $e) {
        $teacherBusy["{$e['teacher_user_id']}-{$e['day_of_week']}-{$e['period_id']}"] = true;
        $classSlot["{$e['class_id']}-{$e['day_of_week']}-{$e['period_id']}"][] = (int)$e['subject_id'];
        $k = "{$e['class_id']}-{$e['subject_id']}-{$e['day_of_week']}";
        $subjDay[$k] = ($subjDay[$k] ?? 0) + 1;
        $dk = "{$e['class_id']}-{$e['day_of_week']}";
        $classDay[$dk] = ($classDay[$dk] ?? 0) + 1;
    }

    $ins = db()->prepare(
        "INSERT INTO timetable_entries
             (class_id,subject_id,teacher_user_id,day_of_week,period_id,source,is_locked)
         VALUES(?,?,?,?,?,'auto',0)"
    );

    // Commit one slot and update all booking maps
    $commit = function(int $c, int $s, int $t, int $d, int $pid)
        use ($ins, &$teacherBusy, &$classSlot, &$subjDay, &$classDay): void {
        $ins->execute([$c, $s, $t, $d, $pid]);
        $teacherBusy["$t-$d-$pid"] = true;
        $classSlot["$c-$d-$pid"][] = $s;
        $k = "$c-$s-$d"; $subjDay[$k]  = ($subjDay[$k]  ?? 0) + 1;
        $dk = "$c-$d";   $classDay[$dk] = ($classDay[$dk] ?? 0) + 1;
    };

    // Days sorted: most teacher-free-slots first, fewest class-lessons as tiebreaker
    $sortedDays = function(int $c, int $t) use ($days, $periods, &$classDay, &$teacherBusy, $avail): array {
        $d = $days;
        usort($d, function($a, $b) use ($c, $t, $periods, &$classDay, &$teacherBusy, $avail) {
            $fA = $fB = 0;
            foreach ($periods as $pid) {
                if ($avail($t, $a, $pid) && !isset($teacherBusy["$t-$a-$pid"])) $fA++;
                if ($avail($t, $b, $pid) && !isset($teacherBusy["$t-$b-$pid"])) $fB++;
            }
            return $fA !== $fB
                ? $fB - $fA  // more free slots → comes first
                : ($classDay["$c-$a"] ?? 0) <=> ($classDay["$c-$b"] ?? 0); // fewer class lessons → comes first
        });
        return $d;
    };

    /**
     * Find up to $want consecutive period IDs on $day for teacher $t in class $c
     * (subject $s — checked against the max-3 cap).
     * Falls back to non-consecutive slots if no consecutive block exists.
     * Returns the period IDs to use (may be fewer than $want if not enough room).
     */
    $findSlots = function(int $c, int $s, int $t, int $day, int $want)
        use ($periods, &$teacherBusy, &$classSlot, &$subjDay, $avail): array {

        $alreadyToday = $subjDay["$c-$s-$day"] ?? 0;
        $canPlace     = min($want, 3 - $alreadyToday); // honour max-3 cap
        if ($canPlace <= 0) return [];

        // Collect individually usable period IDs (preserving sort order)
        $usable = [];
        foreach ($periods as $pid) {
            if (!$avail($t, $day, $pid))            continue;
            if (isset($teacherBusy["$t-$day-$pid"])) continue;
            if (!empty($classSlot["$c-$day-$pid"]))  continue;
            $usable[] = $pid;
        }
        if (count($usable) < $canPlace) $canPlace = count($usable);
        if ($canPlace <= 0) return [];

        // Try to find $canPlace consecutive period IDs
        if ($canPlace > 1) {
            $usableSet = array_flip($usable);
            $n         = count($periods);
            for ($i = 0; $i <= $n - $canPlace; $i++) {
                $run = array_slice($periods, $i, $canPlace);
                $ok  = true;
                foreach ($run as $p) { if (!isset($usableSet[$p])) { $ok = false; break; } }
                if ($ok) return $run; // ✓ consecutive block found
            }
        }

        // No consecutive block — return the first $canPlace available (non-consecutive is fine)
        return array_slice($usable, 0, $canPlace);
    };

    // Same as $findSlots but requires BOTH teachers to be free simultaneously (for pairs)
    $findPairedSlots = function(int $c, int $s, int $ps, int $t, int $pt, int $day, int $want)
        use ($periods, &$teacherBusy, &$classSlot, &$subjDay, $avail): array {

        $cap = min($want, 3 - ($subjDay["$c-$s-$day"] ?? 0), 3 - ($subjDay["$c-$ps-$day"] ?? 0));
        if ($cap <= 0) return [];

        $usable = [];
        foreach ($periods as $pid) {
            if (!$avail($t,  $day, $pid))             continue;
            if (!$avail($pt, $day, $pid))             continue;
            if (isset($teacherBusy["$t-$day-$pid"]))  continue;
            if (isset($teacherBusy["$pt-$day-$pid"])) continue;
            if (!empty($classSlot["$c-$day-$pid"]))   continue;
            $usable[] = $pid;
        }
        if (count($usable) < $cap) $cap = count($usable);
        if ($cap <= 0) return [];

        if ($cap > 1) {
            $usableSet = array_flip($usable);
            $n         = count($periods);
            for ($i = 0; $i <= $n - $cap; $i++) {
                $run = array_slice($periods, $i, $cap);
                $ok  = true;
                foreach ($run as $p) { if (!isset($usableSet[$p])) { $ok = false; break; } }
                if ($ok) return $run;
            }
        }
        return array_slice($usable, 0, $cap);
    };

    /**
     * Core placement loop.
     * Tries to place $remaining lessons in blocks of 2 (prefer consecutive).
     * After each successful block the day-sort refreshes → next block naturally
     * lands on a different day (even spread without a hard rule).
     */
    $placeAll = function(int $c, int $s, int $t, int $remaining, callable $slotFinder) use (&$commit, $sortedDays): int {
        $placed = 0;
        $bail   = 0; // safety: break if a full pass through all days yields nothing

        while ($remaining > 0 && $bail < 3) {
            $want     = min(2, $remaining); // prefer double period
            $placed_block = false;

            foreach ($sortedDays($c, $t) as $day) {
                $pids = $slotFinder($day, $want);

                // If no double available, try single
                if (empty($pids) && $want > 1) $pids = $slotFinder($day, 1);

                if (!empty($pids)) {
                    foreach ($pids as $pid) { $commit($c, $s, $t, $day, $pid); $placed++; $remaining--; }
                    $placed_block = true;
                    break; // block placed — refresh day order for the next block
                }
            }

            if (!$placed_block) $bail++;
        }
        return $placed;
    };

    $placed    = [];
    $donePairs = [];

    // ═══════════════════════════════════════════════════════════════════════════
    // PASS 1 — Paired subjects (both teachers must be free at the same slot)
    // ═══════════════════════════════════════════════════════════════════════════
    foreach ($assignments as $a) {
        $c = (int)$a['class_id'];  $s = (int)$a['subject_id'];
        $t = (int)$a['teacher_user_id']; $need = (int)$a['hours_per_week'];

        $partner = $paired[$c][$s] ?? null;
        if ($partner === null) continue;

        $pKey = "$c-".min($s,$partner).'-'.max($s,$partner);
        if (isset($donePairs[$pKey])) continue;
        $donePairs[$pKey] = true;

        if (!isset($aIdx[$c][$partner])) continue;
        $pa = $aIdx[$c][$partner]; $pt = (int)$pa['teacher_user_id'];
        $remaining = min($need, (int)$pa['hours_per_week']);

        $n = 0; $bail = 0;
        while ($remaining > 0 && $bail < 3) {
            $want = min(2, $remaining);
            $placed_block = false;

            foreach ($sortedDays($c, $t) as $day) {
                $pids = $findPairedSlots($c, $s, $partner, $t, $pt, $day, $want);
                if (empty($pids) && $want > 1)
                    $pids = $findPairedSlots($c, $s, $partner, $t, $pt, $day, 1);

                if (!empty($pids)) {
                    foreach ($pids as $pid) {
                        $commit($c, $s,       $t,  $day, $pid);
                        $commit($c, $partner, $pt, $day, $pid);
                        $n++; $remaining--;
                    }
                    $placed_block = true;
                    break;
                }
            }
            if (!$placed_block) $bail++;
        }

        $placed["$c-$s"]       = ($placed["$c-$s"]       ?? 0) + $n;
        $placed["$c-$partner"] = ($placed["$c-$partner"] ?? 0) + $n;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PASS 2 — All remaining (unpaired, or hours beyond the paired quota)
    // ═══════════════════════════════════════════════════════════════════════════
    foreach ($assignments as $a) {
        $c = (int)$a['class_id'];  $s = (int)$a['subject_id'];
        $t = (int)$a['teacher_user_id']; $need = (int)$a['hours_per_week'];

        $remaining = $need - ($placed["$c-$s"] ?? 0);
        if ($remaining <= 0) continue;

        // Capture $s and $t for the closure
        $sf = fn(int $day, int $want) => $findSlots($c, $s, $t, $day, $want);
        $n  = $placeAll($c, $s, $t, $remaining, $sf);

        $placed["$c-$s"] = ($placed["$c-$s"] ?? 0) + $n;
    }

    db()->commit();

    // ── Build warnings for any assignment that couldn't be fully placed ────────
    $warnings = [];
    foreach ($assignments as $a) {
        $c = (int)$a['class_id']; $s = (int)$a['subject_id']; $need = (int)$a['hours_per_week'];
        $got = $placed["$c-$s"] ?? 0;
        if ($got < $need) $warnings[] = "Class $c / Subject $s: placed $got of $need h/wk";
    }

    ok([
        'message'  => 'Timetable generated.' . ($warnings ? ' Warnings: ' . implode('; ', $warnings) : ''),
        'warnings' => $warnings,
    ]);

} catch (Exception $e) {
    db()->rollBack();
    fail('Generation failed: ' . $e->getMessage(), 500);
}
