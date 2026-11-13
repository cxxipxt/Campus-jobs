<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/functions.php';
function expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $fn): void { try { $fn(); } catch (InvalidArgumentException $ex) { return; } throw new RuntimeException('Expected validation rejection'); }
foreach (['-1','1e2','NaN','1.234','100000000',''] as $value) rejects(fn()=>money($value));
expect(money('0.00')==='0.00','Zero wage is valid');
expect(money('99999999.99')==='99999999.99','Decimal maximum');
foreach (['0','-1','abc','1 OR 1=1','4294967296'] as $value) rejects(fn()=>positive_id($value));
expect(positive_id('42')===42,'Valid ID');
expect(optional_text('',20,'phone')===null,'Optional empty values must become NULL');
rejects(fn()=>required_text(str_repeat('ก',151),150,'name'));
expect(!str_contains(e('<script>"&'),'<' ),'Stored output must be escaped');
$_SESSION=['auth'=>['role'=>'employer','id'=>7]];
expect(identity('student')===null,'Employer cannot read student history');
expect(identity('employer')===7,'Employer session ID');
$_SESSION=['auth'=>['role'=>'student','id'=>['7']]];
expect(identity('student')===null,'Reject malformed session identity');
expect(thai_time('2026-10-13 03:00:00')==='13/10/2026 10:00','UTC to Bangkok');
expect(array_keys(statuses())===['Pending','Reviewing','Accepted','Rejected'],'Shared status contract');
echo "PASS: validation, role isolation, output escaping and UTC conversion\n";
