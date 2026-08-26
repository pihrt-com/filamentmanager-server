<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;use FilamentManager\Core\Request;use FilamentManager\Core\View;

final class AuditController
{
    public function __construct(private readonly App $app){}
    public function index(Request $r):void{$u=$this->app->auth()->requireRole('admin');$rows=$this->app->db()->fetchAll('SELECT a.*,COALESCE(us.display_name,us.username,\'System\') actor FROM audit_log a LEFT JOIN users us ON us.id=a.user_id WHERE a.workspace_id=? ORDER BY a.id DESC LIMIT 500',[$u['workspace_id']]);View::render('audit',['title'=>View::t('audit'),'rows'=>$rows,'basePath'=>$r->basePath()]);}
}
