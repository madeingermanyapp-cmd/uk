<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Request,Response,Auth,Database as DB};
/** The specialized venue workflow is the only write surface in this deployment.
 * Existing hotel/API write surfaces stay available in source for future integration. */
final class Guard
{
    public function handle(Request $r,callable $next): Response
    {
        $path=$r->path();
        if(!DB::tableExists('manor_units') || !DB::query("SELECT id FROM tenants WHERE slug='wonham-manor' LIMIT 1"))return $next($r);
        if(str_starts_with($path,'/manor') || str_starts_with($path,'/admin/manor') || str_starts_with($path,'/admin/login') || str_starts_with($path,'/page/') || $path==='/admin' || $path==='/') { Auth::startSession();Language::apply(); }
        if(str_starts_with($path,'/admin/settings')&&$r->method()==='GET')return Response::redirect('/admin/manor/settings/general');
        if($path==='/admin'&&$r->method()==='GET'&&Auth::isOperator())return Response::redirect('/admin/manor'.(Settings::get()['setup_complete']?'':'/settings/setup'));
        if(!in_array($r->method(),['GET','OPTIONS'],true) && !str_starts_with($path,'/manor') && !str_starts_with($path,'/admin/manor') && !str_starts_with($path,'/admin/login') && !str_starts_with($path,'/auth/') && !str_starts_with($path,'/install') && !str_starts_with($path,'/cron/')) return Response::html('Use the Wonham management console for venue changes.',403);
        return $next($r);
    }
}
