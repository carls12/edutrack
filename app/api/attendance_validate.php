<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
$me=require_api_role(['admin','principal']);

$d=json_input();
$id=(int)($d['id']??0);
if($id<=0) fail('Invalid id.');

$stmt=db()->prepare("UPDATE attendance SET validation_status='validated', validated_by_user_id=?, validated_at=NOW() WHERE id=? AND validation_status='pending'");
$stmt->execute([(int)$me['id'],$id]);
ok();
