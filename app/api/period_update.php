<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);
$d=json_input();
$id=(int)($d['id']??0);
$label=trim((string)($d['label']??''));
$start=(string)($d['start_time']??'');
$end=(string)($d['end_time']??'');
$sort=(int)($d['sort_order']??10);
$teach=(int)($d['is_teaching_period']??1);
if($id<=0||$label===''||$start===''||$end==='') fail('Invalid.');
$stmt=db()->prepare("UPDATE periods SET label=?, start_time=?, end_time=?, sort_order=?, is_teaching_period=? WHERE id=?");
$stmt->execute([$label,$start,$end,$sort,$teach,$id]);
ok();
