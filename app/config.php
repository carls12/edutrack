<?php
declare(strict_types=1);

define('APP_NAME', 'EduTrack');

// Auto-detect base URL from the current script path, e.g. /edutrack/public
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/edutrack/public/index.php';
$basePath = str_replace('\\', '/', dirname($scriptName));
$basePath = preg_replace('#/app/api$#', '', $basePath) ?? $basePath;
$basePath = rtrim($basePath, '/');
if ($basePath === '' || $basePath === '.') {
  $basePath = '/edutrack/public';
}
define('BASE_URL', $basePath);

define('DB_HOST', 'localhost');
define('DB_NAME', 'edutrack');
define('DB_USER', 'root');
define('DB_PASS', ''); // set your password

// Security
define('CSRF_KEY', 'change-this-to-a-long-random-string');
