<?php
declare(strict_types=1);

// Redirect project root (/edutrack) to the public login entrypoint.
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/edutrack/index.php')), '/');
if ($base === '' || $base === '.') {
  $base = '/edutrack';
}
header('Location: ' . $base . '/public/index.php?page=login');
exit;
