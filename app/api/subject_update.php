<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);
$d=json_input();
$id=(int)($d['id']??0);
$code=strtoupper(trim((string)($d['code']??'')));
$name=trim((string)($d['name']??''));
$active=(int)($d['is_active']??1);
if($id<=0||$code===''||$name==='') fail('Invalid.');
try{
  $stmt=db()->prepare("UPDATE subjects SET code=?, name=?, is_active=? WHERE id=?");
  $stmt->execute([$code,$name,$active,$id]);
  ok();
}catch(Exception $e){ fail('Code must be unique.'); }
