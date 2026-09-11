<?php

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9990813 (sub-item de #9990807). Permisos del Portal de Colaborador para SUS
 * PROPIOS documentos de expediente (distintos de 'talento.expediente.view'/'...gestionar', que
 * gatean la ficha ADMIN sobre cualquier colaborador). Se auto-asignan/revocan igual que
 * 'portal.colaborador' vía TalentoColaboradorObserver (aditivo, givePermissionTo/revokePermissionTo
 * según status del colaborador) — no dependen de asignación manual por rol, aunque también se
 * sincronizan a los roles base por consistencia con el resto de permisos de Talento. Mismo patrón
 * de backfill que 2026_07_02_100000_create_portal_colaborador_permission.php: los colaboradores
 * ACTIVOS ya existentes reciben el permiso directo aquí mismo (el observer solo cubre altas/cambios
 * de status futuros, no retroactivo).
 */
return new class extends Migration
{
    private const PERMISOS = [
        'talento.documentos.ver-propios',
        'talento.documentos.firmar-propios',
    ];

    public function up(): void
    {
        $syncService = app(PermissionSyncService::class);

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            $syncService->syncPermissionToBaseRoles($permiso);
        }

        TalentoColaborador::where('status', 'active')->get()->each(function (TalentoColaborador $c) {
            $user = User::find($c->user_id);
            if (! $user) {
                return;
            }
            foreach (self::PERMISOS as $permiso) {
                if (! $user->hasDirectPermission($permiso)) {
                    $user->givePermissionTo($permiso);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
