<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d         = json_input();
$id        = (int)($d['id']        ?? 0);
$direction = (string)($d['direction'] ?? '');

if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) fail('Invalid.');

$current = db()->prepare("SELECT id, sort_order FROM periods WHERE id=?");
$current->execute([$id]);
$row = $current->fetch();
if (!$row) fail('Period not found.');

$curSort = (int)$row['sort_order'];

// Find the immediate neighbour in the requested direction
if ($direction === 'up') {
    $stmt = db()->prepare(
        "SELECT id, sort_order FROM periods WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1"
    );
} else {
    $stmt = db()->prepare(
        "SELECT id, sort_order FROM periods WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1"
    );
}
$stmt->execute([$curSort]);
$neighbour = $stmt->fetch();
if (!$neighbour) ok(['message' => 'Already at boundary.']); // nothing to swap

// Swap sort_order values
$upd = db()->prepare("UPDATE periods SET sort_order=? WHERE id=?");
$upd->execute([(int)$neighbour['sort_order'], $id]);
$upd->execute([$curSort, (int)$neighbour['id']]);

ok();
