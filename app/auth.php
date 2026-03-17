<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function require_auth(): void {
  if (!current_user()) {
    header('Location: ' . BASE_URL . '/index.php?page=login');
    exit;
  }
}

function require_role(array $roles): void {
  require_auth();
  $u = current_user();
  if (!$u || !in_array($u['role'], $roles, true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

function login_user(string $email, string $password): bool {
  $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email=? LIMIT 1');
  $stmt->execute([$email]);
  $u = $stmt->fetch();
  if (!$u || (int)$u['is_active'] !== 1) return false;
  if (!password_verify($password, $u['password_hash'])) return false;

  $_SESSION['user'] = [
    'id' => (int)$u['id'],
    'full_name' => $u['full_name'],
    'email' => $u['email'],
    'role' => $u['role'],
  ];
  return true;
}

function logout_user(): void {
  $_SESSION = [];
  if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

function csrf_token(): string {
  if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf'];
}

function csrf_verify(?string $token): void {
  $t = $token ?? '';
  if (!hash_equals($_SESSION['csrf'] ?? '', $t)) {
    http_response_code(419);
    echo json_encode(['ok'=>false,'error'=>'Invalid CSRF token']);
    exit;
  }
}
