<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Ulid,BookingService};
final class Inventory
{
    /** Lock the stable tenant row before reading requests or inventory, including empty calendars. */
    public static function run(callable $fn): mixed
    {
        return DB::transaction(function()use($fn){
            $tenant=DB::query("SELECT * FROM tenants WHERE slug='wonham-manor' FOR UPDATE")[0]??null;
            if (!$tenant) throw new \DomainException('Wonham is not initialized.');
            return $fn($tenant);
        });
    }
    public static function resources(string $unitId): array
    {
        $units=DB::query('SELECT * FROM manor_units'); $ids=[]; $seen=[];
        $walk=function($id)use(&$walk,&$ids,&$seen,$units){
            if(isset($seen[$id])) throw new \DomainException('Inventory hierarchy contains a cycle.');
            $seen[$id]=true;
            foreach($units as $u) if($u['id']===$id) {
                if($u['resource_id']) $ids[]=$u['resource_id'];
                foreach($units as $child) if($child['parent_id']===$id) $walk($child['id']);
            }
        }; $walk($unitId);
        if(!$ids) throw new \DomainException('No physical inventory is assigned to this unit.');
        sort($ids); return array_unique($ids);
    }
    public static function reserve(array $r, ?string $expiry, bool $confirm, array $tenant): void
    {
        $ids=self::resources($r['unit_id']);
        foreach($ids as $id){
            DB::query('SELECT id FROM resources WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$tenant['id']]);
            $conflict=DB::query('SELECT id FROM manor_allocations WHERE resource_id=? AND request_id<>? AND starts_at<? AND ends_at>? AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) LIMIT 1',[$id,$r['id'],$r['ends_at'],$r['starts_at']]);
            // Legacy VoxelBooking records are checked too. Manor booking ids are excluded for rescheduling.
            $legacy=DB::query("SELECT b.id FROM bookings b WHERE b.tenant_id=? AND b.resource_id=? AND b.status IN ('confirmed','rescheduled') AND (b.status<>'rescheduled' OR NULLIF(b.rescheduled_to_id,'') IS NULL) AND b.start_datetime<? AND b.end_datetime>? AND b.id NOT IN (SELECT booking_id FROM manor_allocations WHERE request_id=? AND booking_id IS NOT NULL) LIMIT 1",[$tenant['id'],$id,self::local($r['ends_at']),self::local($r['starts_at']),$r['id']]);
            if($conflict || $legacy) throw new \DomainException('The selected interval conflicts with a reservation or active hold.');
        }
        self::release($r['id']);
        foreach($ids as $id){
            $bookingId=null;
            if($confirm){
                $bookingId=BookingService::createBooking(['tenant_id'=>$tenant['id'],'customer_id'=>$r['customer_id'],'booking_pattern'=>'resource','resource_id'=>$id,'start_datetime'=>self::local($r['starts_at']),'end_datetime'=>self::local($r['ends_at']),'party_size'=>$r['guests'],'source'=>'admin','status'=>'confirmed','notes'=>'Wonham request '.$r['id']],$tenant,false)['id'];
            }
            DB::execute('INSERT INTO manor_allocations(id,request_id,resource_id,booking_id,starts_at,ends_at,expires_at) VALUES(?,?,?,?,?,?,?)',[Ulid::generate(),$r['id'],$id,$bookingId,$r['starts_at'],$r['ends_at'],$expiry]);
        }
    }
    public static function release(string $id): void
    {
        DB::execute("UPDATE bookings SET status='cancelled',cancelled_at=UTC_TIMESTAMP() WHERE id IN(SELECT booking_id FROM manor_allocations WHERE request_id=? AND booking_id IS NOT NULL)",[$id]);
        DB::execute('DELETE FROM manor_allocations WHERE request_id=?',[$id]);
    }
    public static function local(string $utc): string
    {
        return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/London'))->format('Y-m-d H:i:s');
    }
}
