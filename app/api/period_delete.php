<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);
$d=json_input();
$id=(int)($d['id']??0);
if($id<=0) fail('Invalid.');
$stmt=db()->prepare("DELETE FROM periods WHERE id=?");
$stmt->execute([$id]);
ok();
