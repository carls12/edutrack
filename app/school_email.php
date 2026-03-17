<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function school_email_school_name(): string {
  $stmt = db()->query("SELECT school_name FROM school_settings WHERE id=1");
  $row = $stmt->fetch();
  $name = trim((string)($row['school_name'] ?? 'EduTrack School'));
  return $name !== '' ? $name : 'EduTrack School';
}

function school_email_acronym(string $schoolName): string {
  $parts = preg_split('/[^a-z0-9]+/i', strtolower(trim($schoolName))) ?: [];
  $parts = array_values(array_filter($parts, static fn($part) => $part !== ''));
  if (!$parts) {
    return 'school';
  }

  $letters = '';
  foreach ($parts as $part) {
    $letters .= $part[0];
  }

  if (strlen($letters) < 2) {
    $letters = substr(implode('', $parts), 0, 6);
  }

  return strtolower($letters !== '' ? $letters : 'school');
}

function school_email_local_part(string $fullName): string {
  $name = strtolower(trim($fullName));
  $name = preg_replace('/[^a-z0-9]+/', '.', $name) ?? '';
  $name = trim($name, '.');
  return $name !== '' ? $name : 'user';
}

function school_email_domain(string $schoolName): string {
  return school_email_acronym($schoolName) . '.school.local';
}

function school_email_generate(string $fullName, int $userId = 0): string {
  $localBase = school_email_local_part($fullName);
  $domain = school_email_domain(school_email_school_name());

  $suffix = 0;
  do {
    $local = $localBase . ($suffix > 0 ? (string)($suffix + 1) : '');
    $email = $local . '@' . $domain;
    $stmt = db()->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $stmt->execute([$email, $userId]);
    $exists = $stmt->fetch();
    $suffix++;
  } while ($exists);

  return $email;
}

function school_email_sync_non_admin_users(): void {
  $users = db()->query("SELECT id, full_name FROM users WHERE role <> 'admin' ORDER BY id")->fetchAll();
  $update = db()->prepare("UPDATE users SET email=? WHERE id=?");

  foreach ($users as $user) {
    $email = school_email_generate((string)$user['full_name'], (int)$user['id']);
    $update->execute([$email, (int)$user['id']]);
  }
}
