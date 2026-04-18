<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);
$d=json_input();
$id=(int)($d['id']??0);
$code=strtoupper(trim((string)($d['code']??'')));
$name=trim((string)($d['name']??''));
$active=(int)($d['is_active']??1);
$isPractical=(int)($d['is_practical']??0);
$parentId=$d['parent_subject_id']??null; $parentId=$parentId!==null&&$parentId!==''?(int)$parentId:null;
if($id<=0||$code===''||$name==='') fail('Invalid.');
try{
  $stmt=db()->prepare("UPDATE subjects SET code=?, name=?, is_active=?, is_practical=?, parent_subject_id=? WHERE id=?");
  $stmt->execute([$code,$name,$active,$isPractical,$parentId,$id]);
  ok();
}catch(Exception $e){ fail('Code must be unique.'); }
