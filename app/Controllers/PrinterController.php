<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;
use FilamentManager\Core\Csrf;
use FilamentManager\Core\HttpException;
use FilamentManager\Core\Request;
use FilamentManager\Core\Response;
use FilamentManager\Core\Session;
use FilamentManager\Core\Uuid;
use FilamentManager\Core\View;
use FilamentManager\Services\AuditService;
use FilamentManager\Services\ChangeService;

final class PrinterController
{
    public function __construct(private readonly App $app) {}

    public function form(Request $request, ?string $id = null): void
    {
        $user = $this->app->auth()->requireRole('admin', 'manager');
        $printer = $id ? $this->app->db()->fetch('SELECT * FROM printers WHERE id=? AND workspace_id=? AND deleted_at IS NULL', [$id, $user['workspace_id']]) : null;
        if ($id && !$printer) throw new HttpException('Printer not found', 404);
        $slots = $id ? $this->app->db()->fetchAll('SELECT * FROM printer_slots WHERE printer_id=? AND deleted_at IS NULL ORDER BY slot_number', [$id]) : [];
        $spools = $this->app->db()->fetchAll("SELECT s.id,s.current_net_weight_g,s.status,m.material_type,m.color_name FROM spools s JOIN materials m ON m.id=s.material_id WHERE s.workspace_id=? AND s.deleted_at IS NULL AND (s.status IN ('in_stock','loaded') OR s.id IN (SELECT loaded_spool_id FROM printer_slots WHERE printer_id=?)) ORDER BY m.material_type,m.color_name", [$user['workspace_id'], $id ?? '']);
        View::render('printer_form', ['title' => $id ? View::t('edit') : View::t('add_printer'), 'printer' => $printer, 'slots' => $slots, 'spools' => $spools, 'basePath' => $request->basePath()]);
    }

    public function save(Request $request, ?string $id = null): void
    {
        Csrf::verify($request);
        $user = $this->app->auth()->requireRole('admin', 'manager');
        $name = trim((string) $request->input('name'));
        if ($name === '' || mb_strlen($name) > 120) throw new HttpException('Invalid printer name', 422);
        $status=(string)$request->input('status','active');if(!in_array($status,['active','maintenance','downtime','fault','inactive'],true))throw new HttpException('Invalid printer status',422);
        $duplicate=$this->app->db()->fetch('SELECT id FROM printers WHERE workspace_id=? AND name=? AND id<>?',[$user['workspace_id'],$name,$id??'']);
        if($duplicate){Session::flash('error',View::t('printer_name_exists',['name'=>$name]));Response::redirect($request->basePath().($id?'/printers/'.$id.'/edit':'/printers/new'));}
        $slotCount = max(1, min(16, (int) $request->input('slot_count', 1)));
        $desired = [];
        for ($number = 1; $number <= $slotCount; $number++) $desired[$number] = trim((string) $request->input('slot_' . $number)) ?: null;
        $selected = array_filter($desired);
        if (count($selected) !== count(array_unique($selected))) throw new HttpException('One spool cannot be loaded in multiple slots.', 422);

        $db = $this->app->db();
        $before = null;
        $db->transaction(function () use ($db, $request, $user, $name, $status, $slotCount, $desired, &$id, &$before): void {
            if ($id) {
                $before = $db->fetch('SELECT * FROM printers WHERE id=? AND workspace_id=? AND deleted_at IS NULL FOR UPDATE', [$id, $user['workspace_id']]);
                if (!$before) throw new HttpException('Printer not found', 404);
                if ((int) $request->input('version', 0) !== (int) $before['version']) throw new HttpException('Printer was changed by another user. Reload the form.', 409);
                $printerVersion = (int) $before['version'] + 1;
                $db->execute('UPDATE printers SET name=?,manufacturer=?,model=?,description=?,status=?,version=? WHERE id=?', [$name, trim((string) $request->input('manufacturer')) ?: null, trim((string) $request->input('model')) ?: null, trim((string) $request->input('description')) ?: null, $status, $printerVersion, $id]);
            } else {
                $id = Uuid::v4();
                $printerVersion = 1;
                $db->execute('INSERT INTO printers (id,workspace_id,name,manufacturer,model,description,status,sort_order) VALUES (?,?,?,?,?,?,?,COALESCE((SELECT MAX(p.sort_order)+1 FROM printers p WHERE p.workspace_id=?),0))', [$id, $user['workspace_id'], $name, trim((string) $request->input('manufacturer')) ?: null, trim((string) $request->input('model')) ?: null, trim((string) $request->input('description')) ?: null, $status, $user['workspace_id']]);
            }

            $existing = $db->fetchAll('SELECT * FROM printer_slots WHERE printer_id=? AND deleted_at IS NULL ORDER BY slot_number FOR UPDATE', [$id]);
            $byNumber = [];
            foreach ($existing as $slot) $byNumber[(int) $slot['slot_number']] = $slot;
            $affectedSlots = [];
            $affectedSpools = [];

            foreach ($desired as $targetNumber => $spoolId) {
                if (!$spoolId) continue;
                if (!$db->fetch('SELECT id FROM spools WHERE id=? AND workspace_id=? AND deleted_at IS NULL', [$spoolId, $user['workspace_id']])) throw new HttpException('Invalid spool selection', 422);
                $other = $db->fetch('SELECT * FROM printer_slots WHERE loaded_spool_id=? AND deleted_at IS NULL FOR UPDATE', [$spoolId]);
                if ($other && ($other['printer_id'] !== $id || (int) $other['slot_number'] !== $targetNumber)) {
                    $db->execute('UPDATE printer_slots SET loaded_spool_id=NULL,version=version+1 WHERE id=?', [$other['id']]);
                    $affectedSlots[$other['id']] = true;
                    if ($other['printer_id'] === $id && isset($byNumber[(int) $other['slot_number']])) $byNumber[(int) $other['slot_number']]['loaded_spool_id'] = null;
                    $this->movement($db, $user, $spoolId, 'unloaded', $other['printer_id'], $other['id']);
                }
            }

            for ($number = 1; $number <= $slotCount; $number++) {
                $spoolId = $desired[$number];
                $slot = $byNumber[$number] ?? null;
                $oldSpool = $slot['loaded_spool_id'] ?? null;
                if ($slot && $oldSpool === $spoolId) continue;
                if ($oldSpool) {
                    $db->execute("UPDATE spools SET status='in_stock',version=version+1 WHERE id=?", [$oldSpool]);
                    $affectedSpools[$oldSpool] = true;
                    $this->movement($db, $user, $oldSpool, 'unloaded', $id, $slot['id']);
                }
                if ($slot) {
                    $db->execute('UPDATE printer_slots SET loaded_spool_id=?,version=version+1 WHERE id=?', [$spoolId, $slot['id']]);
                    $slotId = $slot['id'];
                } else {
                    $slotId = Uuid::v4();
                    $db->execute('INSERT INTO printer_slots (id,workspace_id,printer_id,slot_number,loaded_spool_id) VALUES (?,?,?,?,?)', [$slotId, $user['workspace_id'], $id, $number, $spoolId]);
                }
                $affectedSlots[$slotId] = true;
                if ($spoolId) {
                    $db->execute("UPDATE spools SET status='loaded',version=version+1 WHERE id=?", [$spoolId]);
                    $affectedSpools[$spoolId] = true;
                    $this->movement($db, $user, $spoolId, 'loaded', $id, $slotId);
                }
            }

            foreach ($byNumber as $number => $slot) if ($number > $slotCount) {
                if ($slot['loaded_spool_id']) {
                    $db->execute("UPDATE spools SET status='in_stock',version=version+1 WHERE id=?", [$slot['loaded_spool_id']]);
                    $affectedSpools[$slot['loaded_spool_id']] = true;
                    $this->movement($db, $user, $slot['loaded_spool_id'], 'unloaded', $id, $slot['id']);
                }
                $db->execute('UPDATE printer_slots SET deleted_at=UTC_TIMESTAMP(6),loaded_spool_id=NULL,version=version+1 WHERE id=?', [$slot['id']]);
                $affectedSlots[$slot['id']] = true;
            }

            $changes = new ChangeService($this->app);
            $changes->record($user['workspace_id'], 'printer', $id, 'upsert', $printerVersion, $user['id']);
            foreach (array_keys($affectedSlots) as $slotId) { $row = $db->fetch('SELECT version,deleted_at FROM printer_slots WHERE id=?', [$slotId]); $changes->record($user['workspace_id'], 'printer_slot', $slotId, $row['deleted_at'] ? 'delete' : 'upsert', (int) $row['version'], $user['id']); }
            foreach (array_keys($affectedSpools) as $spoolId) { $row = $db->fetch('SELECT version FROM spools WHERE id=?', [$spoolId]); $changes->record($user['workspace_id'], 'spool', $spoolId, 'upsert', (int) $row['version'], $user['id']); }
        });

        $after = $db->fetch('SELECT * FROM printers WHERE id=?', [$id]);
        (new AuditService($this->app))->record($before ? 'printer.updated' : 'printer.created', 'printer', $id, $before, $after);
        Session::flash('success', $this->app->translator()->get('saved'));
        Response::redirect($request->basePath() . '/');
    }

    public function delete(Request $request, string $id): void
    {
        Csrf::verify($request);
        $user = $this->app->auth()->requireRole('admin', 'manager');
        $printer = $this->app->db()->fetch('SELECT * FROM printers WHERE id=? AND workspace_id=? AND deleted_at IS NULL', [$id, $user['workspace_id']]);
        if (!$printer) throw new HttpException('Printer not found', 404);
        $this->app->db()->transaction(function ($db) use ($id, $user, $printer): void {
            $changes = new ChangeService($this->app);
            $slots = $db->fetchAll('SELECT * FROM printer_slots WHERE printer_id=? AND deleted_at IS NULL FOR UPDATE', [$id]);
            foreach ($slots as $slot) {
                if ($slot['loaded_spool_id']) {
                    $db->execute("UPDATE spools SET status='in_stock',version=version+1 WHERE id=?", [$slot['loaded_spool_id']]);
                    $spool = $db->fetch('SELECT version FROM spools WHERE id=?', [$slot['loaded_spool_id']]);
                    $changes->record($user['workspace_id'], 'spool', $slot['loaded_spool_id'], 'upsert', (int) $spool['version'], $user['id']);
                    $this->movement($db, $user, $slot['loaded_spool_id'], 'unloaded', $id, $slot['id']);
                }
                $db->execute('UPDATE printer_slots SET loaded_spool_id=NULL,deleted_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=?', [$slot['id']]);
                $changes->record($user['workspace_id'], 'printer_slot', $slot['id'], 'delete', (int) $slot['version'] + 1, $user['id']);
            }
            $version = (int) $printer['version'] + 1;
            $db->execute('UPDATE printers SET deleted_at=UTC_TIMESTAMP(6),version=? WHERE id=?', [$version, $id]);
            $changes->record($user['workspace_id'], 'printer', $id, 'delete', $version, $user['id']);
        });
        (new AuditService($this->app))->record('printer.deleted', 'printer', $id, $printer, null);
        Response::redirect($request->basePath() . '/');
    }

    private function movement($db, array $user, string $spoolId, string $type, string $printerId, string $slotId): void
    {
        $db->execute('INSERT INTO spool_movements(id,workspace_id,spool_id,movement_type,printer_id,printer_slot_id,source,user_id) VALUES(?,?,?,?,?,?,?,?)', [Uuid::v4(), $user['workspace_id'], $spoolId, $type, $printerId, $slotId, 'web', $user['id']]);
    }
}
