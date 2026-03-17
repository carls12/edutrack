<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
  if (!(error_reporting() & $severity)) {
    return false;
  }
  throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json');
  }
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  echo json_encode([
    'ok' => false,
    'error' => 'Server error: ' . $e->getMessage(),
  ]);
  exit;
});

function json_input(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '[]', true);
  return is_array($data) ? $data : [];
}

function ok($data=[]): void {
  echo json_encode(array_merge(['ok'=>true], $data));
  exit;
}
function fail(string $msg, int $code=400): void {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg]);
  exit;
}

function require_api_role(array $roles): array {
  require_auth();
  $u = current_user();
  if (!$u || !in_array($u['role'], $roles, true)) fail('Forbidden', 403);
  return $u;
}

function csrf_check_from_header(): void {
  $token = $_SERVER['HTTP_X_CSRF'] ?? '';
  csrf_verify($token);
}
