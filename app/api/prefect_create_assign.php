<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../school_email.php';
csrf_check_from_header();
$me = require_api_role(['admin']);

function generate_password(int $len = 10): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  $special = '!@#$%';
  $out = '';
  for ($i = 0; $i < $len - 1; $i++) {
    $out .= $chars[random_int(0, strlen($chars) - 1)];
  }
  $out .= $special[random_int(0, strlen($special) - 1)];
  return $out;
}

$d = json_input();
$full = trim((string)($d['full_name'] ?? ''));
$classId = (int)($d['class_id'] ?? 0);

if ($full === '' || $classId <= 0) fail('Full name and class are required.');

$classStmt = db()->prepare("SELECT id, name FROM classes WHERE id=? LIMIT 1");
$classStmt->execute([$classId]);
$class = $classStmt->fetch();
if (!$class) fail('Class not found.', 404);

$email = school_email_generate($full);

$plainPassword = generate_password(11);
$hash = password_hash($plainPassword, PASSWORD_BCRYPT);

db()->beginTransaction();
try {
  db()->exec("
    CREATE TABLE IF NOT EXISTS prefect_password_audit (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      prefect_user_id INT NOT NULL,
      class_id INT NOT NULL,
      email VARCHAR(190) NOT NULL,
      plain_password VARCHAR(120) NOT NULL,
      created_by_user_id INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_prefect_user (prefect_user_id),
      INDEX idx_class (class_id)
    )
  ");

  $ins = db()->prepare("INSERT INTO users(full_name,email,password_hash,role,is_active) VALUES(?,?,?,?,1)");
  $ins->execute([$full, $email, $hash, 'prefect']);
  $userId = (int)db()->lastInsertId();

  $upd = db()->prepare("UPDATE classes SET prefect_user_id=? WHERE id=?");
  $upd->execute([$userId, $classId]);

  $audit = db()->prepare("
    INSERT INTO prefect_password_audit(prefect_user_id, class_id, email, plain_password, created_by_user_id)
    VALUES(?,?,?,?,?)
  ");
  $audit->execute([$userId, $classId, $email, $plainPassword, (int)$me['id']]);

  db()->commit();
  ok([
    'id' => $userId,
    'email' => $email,
    'password' => $plainPassword,
    'class_id' => $classId,
    'class_name' => $class['name'],
    'full_name' => $full
  ]);
} catch (Throwable $e) {
  db()->rollBack();
  fail('Could not create prefect.', 500);
}
