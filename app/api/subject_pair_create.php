<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

db()->exec("
  CREATE TABLE IF NOT EXISTS subject_pairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id_1 INT NOT NULL,
    subject_id_2 INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_subject_pair (subject_id_1, subject_id_2),
    FOREIGN KEY (subject_id_1) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id_2) REFERENCES subjects(id) ON DELETE CASCADE
  )
");

$d = json_input();
$subject1 = (int)($d['subject_id_1'] ?? 0);
$subject2 = (int)($d['subject_id_2'] ?? 0);
$classId = isset($d['class_id']) && (int)$d['class_id'] > 0 ? (int)$d['class_id'] : null;
$active = (int)($d['is_active'] ?? 1) === 1 ? 1 : 0;

if ($subject1 <= 0 || $subject2 <= 0 || $subject1 === $subject2) fail('Choose two different subjects.');
if ($subject1 > $subject2) {
  [$subject1, $subject2] = [$subject2, $subject1];
}

$stmt = db()->prepare("INSERT INTO subject_pairs(subject_id_1, subject_id_2, class_id, is_active) VALUES(?,?,?,?)");
try {
  $stmt->execute([$subject1, $subject2, $classId, $active]);
  ok(['id' => (int)db()->lastInsertId()]);
} catch (Throwable $e) {
  fail('Subject pair already exists for this class.');
}
