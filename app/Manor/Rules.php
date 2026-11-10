<?php
declare(strict_types=1);
namespace App\Manor;

final class Rules
{
    public const MODES = ['ENTIRE_PROPERTY','PARTIAL_VENUE','MEETING_ROOM','EVENT_HALL','ACCOMMODATION_UNIT','BUILDING','FLOOR'];
    public const STATES = [
        'draft'=>['submitted','cancelled'], 'submitted'=>['under_review','rejected','cancelled'],
        'under_review'=>['more_information_required','quoted','rejected','cancelled'],
        'more_information_required'=>['submitted','rejected','cancelled'],
        'quoted'=>['customer_accepted','under_review','rejected','cancelled','expired'],
        'customer_accepted'=>['awaiting_payment','cancelled'], 'awaiting_payment'=>['payment_verification','cancelled','expired'],
        'payment_verification'=>['confirmed','awaiting_payment','cancelled','expired'],
        'confirmed'=>['cancelled'], 'rejected'=>[], 'cancelled'=>[], 'expired'=>[],
    ];
    public static function transition(string $from, string $to): void
    {
        if (!in_array($to, self::STATES[$from] ?? [], true)) throw new \DomainException('Invalid workflow transition.');
    }
    /** Reject nonexistent and ambiguous wall times rather than silently choosing a DST offset. */
    public static function instant(string $value): \DateTimeImmutable
    {
        $tz = new \DateTimeZone('Europe/London');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $tz);
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) throw new \DomainException('Invalid London date/time.');
        $wall = strtotime($value . ':00 UTC');
        $offsets = array_unique(array_column($tz->getTransitions($date->getTimestamp()-86400,$date->getTimestamp()+86400), 'offset'));
        $matches = 0;
        foreach ($offsets as $offset) {
            if ((new \DateTimeImmutable('@'.($wall-$offset)))->setTimezone($tz)->format('Y-m-d\TH:i') === $value) $matches++;
        }
        if ($matches !== 1) throw new \DomainException('Choose a time outside the UK clock-change hour.');
        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
    public static function pence(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,6})(\.[0-9]{1,2})?$/D', $amount)) throw new \DomainException('Use a positive GBP amount with at most two decimals.');
        [$whole,$decimal] = array_pad(explode('.', $amount),2,'');
        return (int)$whole*100 + (int)str_pad($decimal,2,'0');
    }
    public static function quote(array $items, ?array $selection = null): array
    {
        if (!$items || count($items)>100) throw new \DomainException('Quotation requires line items.');
        $service=0; $deposit=0; $tax=0; $base=false; $lines=[];
        foreach ($items as $i=>$item) {
            if(!is_array($item))throw new \DomainException('Invalid quotation line.');
            foreach(['mandatory','refundable','base','visible'] as $flag)if(isset($item[$flag])&&!is_bool($item[$flag]))throw new \DomainException('Quotation flags must be true or false.');
            $qty=filter_var($item['quantity']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
            $rate=filter_var($item['tax_bps']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10000]]);
            if (!$qty || $rate===false || trim($item['name']??'')==='') throw new \DomainException('Invalid quotation item.');
            $mandatory=(bool)($item['mandatory']??false); $refundable=(bool)($item['refundable']??false);
            if (($item['base']??false) && $mandatory && !$refundable && Rules::pence((string)($item['amount']??''))>0) $base=true;
            $chosen=$mandatory || $selection===null || in_array((string)$i,$selection,true);
            $net=self::pence((string)$item['amount'])*$qty;
            $lineTax=intdiv($net*$rate+5000,10000);
            $line=['name'=>mb_substr($item['name'],0,255),'description'=>mb_substr($item['description']??'',0,2000),'amount'=>(string)$item['amount'],'quantity'=>$qty,'tax_bps'=>$rate,'mandatory'=>$mandatory,'refundable'=>$refundable,'base'=>(bool)($item['base']??false),'visible'=>(bool)($item['visible']??true),'selected'=>$chosen,'net_pence'=>$net,'tax_pence'=>$lineTax];
            if ($mandatory && !$line['visible']) throw new \DomainException('Mandatory charges must be visible on the quotation.');
            if (!$mandatory && !$line['visible']) throw new \DomainException('Optional items must be visible to customers.');
            if ($chosen) { if ($refundable) $deposit+=$net+$lineTax; else { $service+=$net; $tax+=$lineTax; } }
            $lines[]=$line;
        }
        if (!$base) throw new \DomainException('A positive mandatory non-refundable base booking charge is required.');
        return ['items'=>$lines,'service_pence'=>$service,'tax_pence'=>$tax,'deposit_pence'=>$deposit,'total_pence'=>$service+$tax+$deposit,'currency'=>'GBP'];
    }
}
