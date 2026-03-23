<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const TEACHER_STAMP_OTP_INTERVAL = 30;
const TEACHER_STAMP_TEMP_CODE_MINUTES = 15;

function teacher_stamp_ensure_schema(): void {
  static $done = false;
  if ($done) {
    return;
  }

  $pdo = db();

  $teacherColumns = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers'
  ");
  $teacherColumns->execute();
  $teacherColumnMap = array_fill_keys(array_column($teacherColumns->fetchAll(), 'COLUMN_NAME'), true);

  if (!isset($teacherColumnMap['stamp_code'])) {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN stamp_code VARCHAR(20) DEFAULT NULL UNIQUE AFTER phone");
  }
  if (!isset($teacherColumnMap['stamp_secret'])) {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN stamp_secret VARCHAR(80) DEFAULT NULL AFTER stamp_code");
  }
  if (!isset($teacherColumnMap['auth_app_secret'])) {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN auth_app_secret VARCHAR(64) DEFAULT NULL AFTER stamp_secret");
  }
  if (!isset($teacherColumnMap['auth_app_configured_at'])) {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN auth_app_configured_at DATETIME DEFAULT NULL AFTER auth_app_secret");
  }

  $attendanceColumns = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance'
  ");
  $attendanceColumns->execute();
  $attendanceColumnMap = array_fill_keys(array_column($attendanceColumns->fetchAll(), 'COLUMN_NAME'), true);

  if (!isset($attendanceColumnMap['source'])) {
    $pdo->exec("
      ALTER TABLE attendance
      ADD COLUMN source ENUM('manual','prefect_card','teacher_stamp') NOT NULL DEFAULT 'manual' AFTER worked_minutes
    ");
  }

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS teacher_stamp_temp_codes (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      teacher_user_id INT NOT NULL,
      code_value VARCHAR(12) NOT NULL,
      expires_at DATETIME NOT NULL,
      used_at DATETIME DEFAULT NULL,
      created_by_user_id INT DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY idx_temp_teacher (teacher_user_id, expires_at, used_at),
      FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    )
  ");

  teacher_stamp_seed_credentials();
  $done = true;
}

function teacher_stamp_seed_credentials(): void {
  $pdo = db();

  $teacherUsers = $pdo->query("
    SELECT u.id AS user_id, t.user_id AS teacher_row_user_id, t.stamp_code, t.stamp_secret, t.auth_app_secret
    FROM users u
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE u.role = 'teacher'
    ORDER BY u.id
  ")->fetchAll();

  $insert = $pdo->prepare("
    INSERT INTO teachers(user_id, salary_type, hourly_rate, fixed_salary, phone, stamp_code, stamp_secret, auth_app_secret, active)
    VALUES(?, 'hourly', NULL, NULL, NULL, ?, ?, ?, 1)
  ");
  $update = $pdo->prepare("
    UPDATE teachers
    SET stamp_code = ?, stamp_secret = ?, auth_app_secret = ?
    WHERE user_id = ?
  ");

  foreach ($teacherUsers as $teacher) {
    $userId = (int)$teacher['user_id'];
    $stampCode = trim((string)($teacher['stamp_code'] ?? ''));
    $stampSecret = trim((string)($teacher['stamp_secret'] ?? ''));
    $authAppSecret = trim((string)($teacher['auth_app_secret'] ?? ''));

    if ($stampCode === '') {
      $stampCode = teacher_stamp_generate_unique_code();
    }
    if ($stampSecret === '') {
      $stampSecret = teacher_stamp_generate_secret();
    }
    if ($authAppSecret === '') {
      $authAppSecret = teacher_stamp_generate_auth_app_secret();
    }

    if (empty($teacher['teacher_row_user_id'])) {
      $insert->execute([$userId, $stampCode, $stampSecret, $authAppSecret]);
      continue;
    }

    if (
      $stampCode !== (string)$teacher['stamp_code'] ||
      $stampSecret !== (string)$teacher['stamp_secret'] ||
      $authAppSecret !== (string)$teacher['auth_app_secret']
    ) {
      $update->execute([$stampCode, $stampSecret, $authAppSecret, $userId]);
    }
  }
}

function teacher_stamp_generate_unique_code(): string {
  $stmt = db()->prepare("SELECT 1 FROM teachers WHERE stamp_code = ? LIMIT 1");

  do {
    $candidate = 'TR' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt->execute([$candidate]);
  } while ($stmt->fetch());

  return $candidate;
}

function teacher_stamp_generate_secret(): string {
  return bin2hex(random_bytes(20));
}

function teacher_stamp_generate_auth_app_secret(int $length = 32): string {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $max = strlen($alphabet) - 1;
  $secret = '';
  for ($i = 0; $i < $length; $i++) {
    $secret .= $alphabet[random_int(0, $max)];
  }
  return $secret;
}

function teacher_stamp_base32_decode(string $secret): string {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $clean = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
  if ($clean === '') {
    return '';
  }

  $bits = '';
  $len = strlen($clean);
  for ($i = 0; $i < $len; $i++) {
    $pos = strpos($alphabet, $clean[$i]);
    if ($pos === false) {
      return '';
    }
    $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
  }

  $binary = '';
  $bitLen = strlen($bits);
  for ($i = 0; $i + 8 <= $bitLen; $i += 8) {
    $binary .= chr(bindec(substr($bits, $i, 8)));
  }

  return $binary;
}

function teacher_stamp_current_otp(string $secret, ?int $timestamp = null): string {
  $binarySecret = teacher_stamp_base32_decode($secret);
  if ($binarySecret === '') {
    return '000000';
  }

  $time = $timestamp ?? time();
  $counter = intdiv($time, TEACHER_STAMP_OTP_INTERVAL);
  $counterBytes = pack('N*', 0) . pack('N*', $counter);
  $hash = hash_hmac('sha1', $counterBytes, $binarySecret, true);
  $offset = ord(substr($hash, -1)) & 0x0F;
  $part = substr($hash, $offset, 4);
  $value = (
    ((ord($part[0]) & 0x7F) << 24) |
    (ord($part[1]) << 16) |
    (ord($part[2]) << 8) |
    ord($part[3])
  ) % 1000000;

  return str_pad((string)$value, 6, '0', STR_PAD_LEFT);
}

function teacher_stamp_verify_otp(string $secret, string $otp, int $window = 1): bool {
  $otp = preg_replace('/\D+/', '', $otp ?? '');
  if ($otp === null || strlen($otp) !== 6) {
    return false;
  }

  $now = time();
  for ($offset = -$window; $offset <= $window; $offset++) {
    if (hash_equals(teacher_stamp_current_otp($secret, $now + ($offset * TEACHER_STAMP_OTP_INTERVAL)), $otp)) {
      return true;
    }
  }

  return false;
}

function teacher_stamp_auth_app_setup_payload(int $teacherUserId): ?array {
  teacher_stamp_ensure_schema();

  $stmt = db()->prepare("
    SELECT u.id AS teacher_user_id, u.full_name, u.email, t.auth_app_secret
    FROM users u
    JOIN teachers t ON t.user_id = u.id
    WHERE u.id = ? AND u.role = 'teacher'
    LIMIT 1
  ");
  $stmt->execute([$teacherUserId]);
  $teacher = $stmt->fetch();
  if (!$teacher) {
    return null;
  }

  $settings = db()->query("SELECT school_name FROM school_settings WHERE id=1")->fetch() ?: ['school_name' => APP_NAME];
  $issuer = (string)($settings['school_name'] ?? APP_NAME);
  $label = $issuer . ':' . (string)$teacher['email'];
  $secret = (string)$teacher['auth_app_secret'];

  return [
    'teacher_user_id' => (int)$teacher['teacher_user_id'],
    'teacher_name' => $teacher['full_name'],
    'teacher_email' => $teacher['email'],
    'secret' => $secret,
    'issuer' => $issuer,
    'otpauth_uri' => 'otpauth://totp/' . rawurlencode($label)
      . '?secret=' . rawurlencode($secret)
      . '&issuer=' . rawurlencode($issuer)
      . '&digits=6&period=' . TEACHER_STAMP_OTP_INTERVAL,
  ];
}

function teacher_stamp_reset_auth_app_secret(int $teacherUserId): ?array {
  teacher_stamp_ensure_schema();
  $newSecret = teacher_stamp_generate_auth_app_secret();

  $stmt = db()->prepare("
    UPDATE teachers
    SET auth_app_secret = ?, auth_app_configured_at = NOW()
    WHERE user_id = ?
  ");
  $stmt->execute([$newSecret, $teacherUserId]);

  return teacher_stamp_auth_app_setup_payload($teacherUserId);
}

function teacher_stamp_generate_temp_code(): string {
  return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function teacher_stamp_issue_temp_code(int $teacherUserId, ?int $createdByUserId = null, int $minutes = TEACHER_STAMP_TEMP_CODE_MINUTES): array {
  teacher_stamp_ensure_schema();

  $code = teacher_stamp_generate_temp_code();
  $expiresAt = date('Y-m-d H:i:s', time() + ($minutes * 60));

  $clear = db()->prepare("
    UPDATE teacher_stamp_temp_codes
    SET used_at = COALESCE(used_at, NOW())
    WHERE teacher_user_id = ?
      AND used_at IS NULL
      AND expires_at >= NOW()
  ");
  $clear->execute([$teacherUserId]);

  $insert = db()->prepare("
    INSERT INTO teacher_stamp_temp_codes(teacher_user_id, code_value, expires_at, used_at, created_by_user_id)
    VALUES(?, ?, ?, NULL, ?)
  ");
  $insert->execute([$teacherUserId, $code, $expiresAt, $createdByUserId]);

  return [
    'code_value' => $code,
    'expires_at' => $expiresAt,
    'expires_in_seconds' => max(0, strtotime($expiresAt) - time()),
  ];
}

function teacher_stamp_active_temp_code(int $teacherUserId): ?array {
  teacher_stamp_ensure_schema();

  $stmt = db()->prepare("
    SELECT code_value, expires_at
    FROM teacher_stamp_temp_codes
    WHERE teacher_user_id = ?
      AND used_at IS NULL
      AND expires_at >= NOW()
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmt->execute([$teacherUserId]);
  $row = $stmt->fetch();
  if (!$row) {
    return null;
  }

  return [
    'code_value' => $row['code_value'],
    'expires_at' => $row['expires_at'],
    'expires_in_seconds' => max(0, strtotime((string)$row['expires_at']) - time()),
  ];
}

function teacher_stamp_temp_code_valid(int $teacherUserId, string $code): bool {
  teacher_stamp_ensure_schema();
  $cleanCode = preg_replace('/\D+/', '', $code) ?? '';
  if (strlen($cleanCode) !== 6) {
    return false;
  }

  $stmt = db()->prepare("
    SELECT id
    FROM teacher_stamp_temp_codes
    WHERE teacher_user_id = ?
      AND code_value = ?
      AND used_at IS NULL
      AND expires_at >= NOW()
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmt->execute([$teacherUserId, $cleanCode]);
  return (bool)$stmt->fetch();
}

function teacher_stamp_consume_temp_code(int $teacherUserId, string $code): void {
  teacher_stamp_ensure_schema();
  $cleanCode = preg_replace('/\D+/', '', $code) ?? '';
  if (strlen($cleanCode) !== 6) {
    return;
  }

  $stmt = db()->prepare("
    UPDATE teacher_stamp_temp_codes
    SET used_at = NOW()
    WHERE teacher_user_id = ?
      AND code_value = ?
      AND used_at IS NULL
      AND expires_at >= NOW()
  ");
  $stmt->execute([$teacherUserId, $cleanCode]);
}

function teacher_stamp_find_teacher_by_code(string $stampCode): ?array {
  teacher_stamp_ensure_schema();

  $stmt = db()->prepare("
    SELECT
      u.id AS teacher_user_id,
      u.full_name,
      u.email,
      u.is_active,
      t.active AS teacher_active,
      t.stamp_code,
      t.stamp_secret,
      t.auth_app_secret
    FROM teachers t
    JOIN users u ON u.id = t.user_id
    WHERE t.stamp_code = ?
      AND u.role = 'teacher'
    LIMIT 1
  ");
  $stmt->execute([strtoupper(trim($stampCode))]);
  $teacher = $stmt->fetch();

  return $teacher ?: null;
}

function teacher_stamp_today_status(int $teacherUserId): array {
  teacher_stamp_ensure_schema();

  $stmt = db()->prepare("
    SELECT status, event_time
    FROM attendance
    WHERE teacher_user_id = ?
      AND source = 'teacher_stamp'
      AND DATE(event_time) = CURDATE()
    ORDER BY event_time ASC, id ASC
  ");
  $stmt->execute([$teacherUserId]);
  $rows = $stmt->fetchAll();

  $arrivedAt = null;
  $departedAt = null;
  foreach ($rows as $row) {
    if ($row['status'] === 'arrived') {
      $arrivedAt = $row['event_time'];
    }
    if ($row['status'] === 'departed') {
      $departedAt = $row['event_time'];
    }
  }

  $isIn = $arrivedAt !== null && ($departedAt === null || strtotime((string)$departedAt) < strtotime((string)$arrivedAt));

  return [
    'arrived_at' => $arrivedAt,
    'departed_at' => $departedAt,
    'is_in' => $isIn,
  ];
}

function teacher_stamp_record(int $teacherUserId, string $action): array {
  teacher_stamp_ensure_schema();

  if (!in_array($action, ['arrived', 'departed'], true)) {
    throw new InvalidArgumentException('Invalid stamp action.');
  }

  $status = teacher_stamp_today_status($teacherUserId);
  if ($action === 'arrived' && $status['is_in']) {
    throw new RuntimeException('Teacher is already stamped in today.');
  }
  if ($action === 'departed' && !$status['is_in']) {
    throw new RuntimeException('Teacher must stamp in before stamping out.');
  }

  $eventAt = date('Y-m-d H:i:s');
  $insert = db()->prepare("
    INSERT INTO attendance(
      teacher_user_id,
      class_id,
      status,
      reason,
      event_time,
      worked_minutes,
      source,
      created_by_user_id,
      validation_status,
      validated_by_user_id,
      validated_at
    )
    VALUES(?, NULL, ?, NULL, ?, 0, 'teacher_stamp', ?, 'validated', ?, ?)
  ");
  $insert->execute([$teacherUserId, $action, $eventAt, $teacherUserId, $teacherUserId, $eventAt]);

  return [
    'status' => $action,
    'event_time' => $eventAt,
  ];
}

function teacher_stamp_codes_payload(): array {
  teacher_stamp_ensure_schema();

  $rows = db()->query("
    SELECT
      u.id AS teacher_user_id,
      u.full_name,
      u.email,
      t.phone,
      t.stamp_code,
      t.auth_app_secret
    FROM teachers t
    JOIN users u ON u.id = t.user_id
    WHERE u.role = 'teacher'
      AND u.is_active = 1
      AND t.active = 1
    ORDER BY u.full_name
  ")->fetchAll();

  $payload = [];
  foreach ($rows as $row) {
    $status = teacher_stamp_today_status((int)$row['teacher_user_id']);
    $tempCode = teacher_stamp_active_temp_code((int)$row['teacher_user_id']);
    $payload[] = [
      'teacher_user_id' => (int)$row['teacher_user_id'],
      'full_name' => $row['full_name'],
      'email' => $row['email'],
      'phone' => $row['phone'],
      'stamp_code' => $row['stamp_code'],
      'arrived_at' => $status['arrived_at'],
      'departed_at' => $status['departed_at'],
      'is_in' => $status['is_in'],
      'auth_app_enabled' => trim((string)($row['auth_app_secret'] ?? '')) !== '',
      'temp_code' => $tempCode['code_value'] ?? null,
      'temp_code_expires_at' => $tempCode['expires_at'] ?? null,
      'temp_code_expires_in' => $tempCode['expires_in_seconds'] ?? null,
    ];
  }

  return $payload;
}
