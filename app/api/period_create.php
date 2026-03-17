<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);
$d=json_input();
$label=trim((string)($d['label']??''));
$start=(string)($d['start_time']??'');
$end=(string)($d['end_time']??'');
$sort=(int)($d['sort_order']??10);
$teach=(int)($d['is_teaching_period']??1);
if($label===''||$start===''||$end==='') fail('Invalid.');
$stmt=db()->prepare("INSERT INTO periods(label,start_time,end_time,sort_order,is_teaching_period) VALUES(?,?,?,?,?)");
$stmt->execute([$label,$start,$end,$sort,$teach]);
ok(['id'=>(int)db()->lastInsertId()]);
