<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const TEACHER_STAMP_OTP_INTERVAL = 60;

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

  teacher_stamp_seed_credentials();
  $done = true;
}

function teacher_stamp_seed_credentials(): void {
  $pdo = db();

  $teacherUsers = $pdo->query("
    SELECT u.id AS user_id, t.user_id AS teacher_row_user_id, t.stamp_code, t.stamp_secret
    FROM users u
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE u.role = 'teacher'
    ORDER BY u.id
  ")->fetchAll();

  $insert = $pdo->prepare("
    INSERT INTO teachers(user_id, salary_type, hourly_rate, fixed_salary, phone, stamp_code, stamp_secret, active)
    VALUES(?, 'hourly', NULL, NULL, NULL, ?, ?, 1)
  ");
  $update = $pdo->prepare("
    UPDATE teachers
    SET stamp_code = ?, stamp_secret = ?
    WHERE user_id = ?
  ");

  foreach ($teacherUsers as $teacher) {
    $userId = (int)$teacher['user_id'];
    $stampCode = trim((string)($teacher['stamp_code'] ?? ''));
    $stampSecret = trim((string)($teacher['stamp_secret'] ?? ''));

    if ($stampCode === '') {
      $stampCode = teacher_stamp_generate_unique_code();
    }
    if ($stampSecret === '') {
      $stampSecret = teacher_stamp_generate_secret();
    }

    if (empty($teacher['teacher_row_user_id'])) {
      $insert->execute([$userId, $stampCode, $stampSecret]);
      continue;
    }

    if ($stampCode !== (string)$teacher['stamp_code'] || $stampSecret !== (string)$teacher['stamp_secret']) {
      $update->execute([$stampCode, $stampSecret, $userId]);
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

function teacher_stamp_current_otp(string $secret, ?int $timestamp = null): string {
  $time = $timestamp ?? time();
  $counter = intdiv($time, TEACHER_STAMP_OTP_INTERVAL);
  $hash = hash_hmac('sha1', (string)$counter, $secret, true);
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
      t.stamp_secret
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
      t.stamp_secret
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
    $payload[] = [
      'teacher_user_id' => (int)$row['teacher_user_id'],
      'full_name' => $row['full_name'],
      'email' => $row['email'],
      'phone' => $row['phone'],
      'stamp_code' => $row['stamp_code'],
      'current_otp' => teacher_stamp_current_otp((string)$row['stamp_secret']),
      'arrived_at' => $status['arrived_at'],
      'departed_at' => $status['departed_at'],
      'is_in' => $status['is_in'],
      'expires_in' => TEACHER_STAMP_OTP_INTERVAL - (time() % TEACHER_STAMP_OTP_INTERVAL),
    ];
  }

  return $payload;
}
