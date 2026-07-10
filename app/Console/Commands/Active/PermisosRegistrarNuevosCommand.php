<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reforma de permisos A2 — registrar los permisos que el código YA referencia
 * pero no existen (gap duro "genuinamente ausente"), para restaurar sus gates y
 * hacerlos asignables en la pantalla de Roles.
 *
 * REGLAS DURAS:
 *  - context = 'panel' para todos (ninguno es self-service del colaborador).
 *  - SIN auto-asignar a roles base: se atacan SOLO super-administrator y DESARROLLADOR.
 *  - NO corre permissions:sync-roles (dispararía el auto-grant de .view a roles base).
 *  - Idempotente (firstOrCreate + givePermissionTo). Dry-run por defecto; --apply escribe.
 *
 *   php artisan permisos:registrar-nuevos           # dry-run (no escribe)
 *   php artisan permisos:registrar-nuevos --apply   # crea + ataca a los 2 roles admin
 */
class PermisosRegistrarNuevosCommand extends Command
{
    protected $signature = 'permisos:registrar-nuevos {--apply : Ejecuta los cambios (por defecto dry-run)}';

    protected $description = 'Reforma A2: registra permisos ausentes (context=panel) y los attacha SOLO a super-administrator/DESARROLLADOR';

    private const ADMIN_ROLES = ['super-administrator', 'DESARROLLADOR'];

    /** nombre => módulo (para la tabla de evidencia). */
    private const PERMS = [
        // Marketing (dotted, 14)
        'marketing.leads.view'          => 'Marketing',
        'marketing.leads.create'        => 'Marketing',
        'marketing.leads.update'        => 'Marketing',
        'marketing.leads.delete'        => 'Marketing',
        'marketing.leads.assign'        => 'Marketing',
        'marketing.leads.score'         => 'Marketing',
        'marketing.forms.view'          => 'Marketing',
        'marketing.forms.create'        => 'Marketing',
        'marketing.forms.update'        => 'Marketing',
        'marketing.forms.delete'        => 'Marketing',
        'marketing.brand_kit.configure' => 'Marketing',
        'marketing.conversations.view'  => 'Marketing',
        'marketing.campaigns.view'      => 'Marketing',
        'marketing.templates.manage'    => 'Marketing',
        // Config (snake, 4)
        'recurring_debts_payments_custom_view'    => 'Configuracion',
        'recurring_debts_payments_custom_add'     => 'Configuracion',
        'recurring_debts_payments_custom_edit'    => 'Configuracion',
        'recurring_debts_payments_custom_destroy' => 'Configuracion',
        // Clientes (snake, 1)
        'client_statistics_view_tab_client' => 'Clientes',
        // Talento (dotted, 2)
        'talento.catalog.view'   => 'Talento',
        'talento.employees.view' => 'Talento',
        // Falta-sync (materializar, Config)
        'config_view_messages' => 'Configuracion',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line('');
        $this->info('=== Permisos A2 — ' . ($apply ? 'APPLY (escribe)' : 'DRY-RUN (no escribe)') . ' ===');

        // Verificar que existan los 2 roles admin
        $adminRoles = [];
        foreach (self::ADMIN_ROLES as $rn) {
            $r = Role::where('name', $rn)->where('guard_name', 'web')->first();
            if (!$r) {
                $this->error("Rol admin no encontrado: {$rn} — abortando.");
                return self::FAILURE;
            }
            $adminRoles[] = $r;
        }

        $rows = [];
        if ($apply) {
            DB::beginTransaction();
        }
        try {
            foreach (self::PERMS as $name => $modulo) {
                $existed = Permission::where('name', $name)->where('guard_name', 'web')->exists();

                if ($apply) {
                    $perm = Permission::firstOrCreate(
                        ['name' => $name, 'guard_name' => 'web'],
                        ['context' => 'panel']
                    );
                    // Forzar context=panel explícito (bypass mass-assignment / idempotencia)
                    DB::table('permissions')->where('id', $perm->id)->update(['context' => 'panel']);
                    // Atacar SOLO a los 2 roles admin (additive)
                    foreach ($adminRoles as $r) {
                        $r->givePermissionTo($perm);
                    }
                }

                $rows[] = [
                    $name,
                    $modulo,
                    'panel',
                    $existed ? 'ya-existía' : ($apply ? 'CREADO' : 'se crearía'),
                    implode(' + ', self::ADMIN_ROLES),
                ];
            }

            if ($apply) {
                DB::commit();
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        } catch (\Throwable $e) {
            if ($apply) {
                DB::rollBack();
            }
            $this->error('ERROR — rollback: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->table(['nombre', 'módulo', 'context', 'estado', 'roles atacados'], $rows);
        $this->line('Total: ' . count($rows) . ' permisos.');
        $this->warn('Regla: NO se corrió permissions:sync-roles. NO se atacó ningún rol base.');

        if (!$apply) {
            $this->line('');
            $this->warn('DRY-RUN: no se escribió nada. Ejecuta con --apply tras el OK.');
        } else {
            $this->info('APPLY completo. Verifica: roles base sin estos permisos; los 2 admin sí.');
        }
        return self::SUCCESS;
    }
}
