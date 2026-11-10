<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
use App\Manor\Rules;
$count=0;
function check(bool $ok,string $name): void {global $count;if(!$ok)throw new RuntimeException('FAIL '.$name);$count++;echo "PASS $name\n";}
function rejects(callable $f,string $name): void{try{$f();}catch(DomainException){check(true,$name);return;}check(false,$name);}
check(Rules::pence('123.45')===12345,'GBP exact minor units');
rejects(fn()=>Rules::pence('1.001'),'reject fractional pennies');
rejects(fn()=>Rules::pence('-1'),'reject negative amounts');
$items=[['name'=>'Base','amount'=>'100.00','quantity'=>2,'mandatory'=>true,'base'=>true,'tax_bps'=>2000],['name'=>'Equipment','amount'=>'25.00','mandatory'=>false],['name'=>'Security deposit','amount'=>'50.00','mandatory'=>true,'refundable'=>true]];
$q=Rules::quote($items,[]);
check($q['total_pence']===29000 && $q['deposit_pence']===5000 && $q['service_pence']===20000 && $q['tax_pence']===4000,'mandatory charges and separate deposit');
check(Rules::quote($items,['1'])['total_pence']===31500,'selected optional charge');
rejects(fn()=>Rules::quote([['name'=>'Other','amount'=>'1','mandatory'=>true]]),'mandatory base charge required');
rejects(fn()=>Rules::quote([['name'=>'Base','amount'=>'0','mandatory'=>true,'base'=>true]]),'zero base charge rejected');
rejects(fn()=>Rules::quote(array_merge($items,[['name'=>'Hidden option','amount'=>'1','visible'=>false]])),'hidden optional charge rejected');
check(Rules::instant('2027-06-01T12:00')->format('H:i')==='11:00','BST converted to UTC');
check(Rules::instant('2027-01-01T12:00')->format('H:i')==='12:00','GMT converted to UTC');
rejects(fn()=>Rules::instant('2027-03-28T01:30'),'spring nonexistent hour rejected');
rejects(fn()=>Rules::instant('2027-10-31T01:30'),'autumn ambiguous hour rejected');
rejects(fn()=>Rules::instant('2027-02-30T12:00'),'invalid calendar date rejected');
rejects(fn()=>Rules::transition('submitted','confirmed'),'enquiry cannot auto-confirm');
Rules::transition('payment_verification','confirmed');check(true,'payment verification confirmation transition');
echo "$count rule assertions passed.\n";
