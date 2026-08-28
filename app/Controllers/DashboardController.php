<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;
use FilamentManager\Core\Request;
use FilamentManager\Core\Session;
use FilamentManager\Core\View;
use FilamentManager\Services\UpdateService;

final class DashboardController
{
    public function __construct(private readonly App $app) {}
    public function index(Request $request): void
    {
        $user = $this->app->auth()->requireUser();
        $workspace=$this->app->db()->fetch('SELECT printer_sort_mode FROM workspaces WHERE id=?',[$user['workspace_id']]);$sortMode=(string)($workspace['printer_sort_mode']??'az');
        $printers = $this->app->db()->fetchAll('SELECT * FROM printers WHERE workspace_id=? AND deleted_at IS NULL ORDER BY sort_order,name', [$user['workspace_id']]);
        if($sortMode!=='custom')usort($printers,static function(array $a,array $b)use($sortMode):int{$result=strnatcasecmp((string)$a['name'],(string)$b['name']);return $sortMode==='za'?-$result:$result;});
        $slots = $this->app->db()->fetchAll("SELECT ps.*,s.current_net_weight_g,m.material_type,m.color_name,m.color_hex,m.commercial_name,mf.name manufacturer_name FROM printer_slots ps LEFT JOIN spools s ON s.id=ps.loaded_spool_id LEFT JOIN materials m ON m.id=s.material_id LEFT JOIN manufacturers mf ON mf.id=m.manufacturer_id WHERE ps.workspace_id=? AND ps.deleted_at IS NULL ORDER BY ps.printer_id,ps.slot_number", [$user['workspace_id']]);
        $byPrinter = [];
        foreach ($slots as $slot) $byPrinter[$slot['printer_id']][] = $slot;
        $update = null;
        if ($user['role'] === 'admin') {
            $force=Session::get('check_updates_after_login')===true;Session::forget('check_updates_after_login');$updates=new UpdateService($this->app);
            try { $update = $updates->check($force); } catch (\Throwable) { try{$update=$updates->check(false);}catch(\Throwable){$update=null;} }
        } else {
            Session::forget('check_updates_after_login');
        }
        View::render('dashboard', ['title' => View::t('dashboard'), 'printers' => $printers, 'slotsByPrinter' => $byPrinter, 'printerSortMode'=>$sortMode, 'update' => $update, 'basePath' => $request->basePath()]);
    }
}
