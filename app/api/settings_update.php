<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../school_email.php';
csrf_check_from_header();
require_api_role(['admin']);
$d = json_input();
$name = trim((string)($d['school_name'] ?? ''));
$currency = trim((string)($d['currency'] ?? 'XAF'));
$tz = trim((string)($d['timezone'] ?? 'Africa/Douala'));
if($name==='') fail('School name required.');
db()->beginTransaction();
try {
  $stmt = db()->prepare("UPDATE school_settings SET school_name=?, currency=?, timezone=? WHERE id=1");
  $stmt->execute([$name,$currency,$tz]);
  school_email_sync_non_admin_users();
  db()->commit();
} catch (Throwable $e) {
  db()->rollBack();
  fail('Could not update school settings.', 500);
}
ok();
