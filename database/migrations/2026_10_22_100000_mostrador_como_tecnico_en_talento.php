<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mostrador en Talento = como un técnico (decisión 2026-10-22; reemplaza la de administrador del
 * 2026-10-21): entra a su propio dashboard y solo ve lo suyo. Conserva crear órdenes de trabajo
 * para los técnicos (28-sep) y ve cuánto cobra cada quien en Liquidaciones, sin desglose ni
 * cálculo (permiso nuevo talento.liquidation.totales).
 *
 * - Rol Mostrador: sus permisos talento.* quedan exactamente en la lista de abajo.
 * - Quien tiene el rol Mostrador pierde los permisos talento.* que tenía dados a él directamente
 *   (se conservan los de ver y firmar sus propios documentos). Lo que se quita queda en el log.
 * - talento.liquidation.totales también se da a los roles que ya veían todas las liquidaciones,
 *   para que les salga el enlace del menú.
 * Roles por nombre (dev y prod no coinciden en ids).
 */
return new class extends Migration
{
    private const MOSTRADOR = [
        // Lo mismo que el rol TECNICO.
        'talento.view', 'talento.dashboard.view', 'talento.academy.view', 'talento.attendance.view',
        'talento.custody.view', 'talento.devices.view', 'talento.embajadores.view',
        'talento.health_bonus.view', 'talento.location.view', 'talento.media.view',
        'talento.signatures.view', 'talento.warranty.view', 'talento.work_orders.view',
        'talento.work_sites.view',
        // Lo propio del Mostrador.
        'talento.work_orders.manage', 'talento.comisiones.view', 'talento.liquidation.totales',
    ];

    private const PERSONALES_QUE_SE_QUEDAN = ['talento.documentos.ver-propios', 'talento.documentos.firmar-propios'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $totales = Permission::firstOrCreate(['name' => 'talento.liquidation.totales', 'guard_name' => 'web']);
        $vista = Permission::where('name', 'talento.liquidation.view')->where('guard_name', 'web')->first();
        foreach ($vista?->roles ?? [] as $rol) {
            if (! $rol->hasPermissionTo($totales)) {
                $rol->givePermissionTo($totales);
            }
        }

        $mostrador = Role::where('name', 'Mostrador')->where('guard_name', 'web')->first();
        if (! $mostrador) {
            return;
        }

        foreach ($mostrador->permissions->filter(fn ($p) => str_starts_with($p->name, 'talento.')) as $p) {
            if (! in_array($p->name, self::MOSTRADOR, true)) {
                $mostrador->revokePermissionTo($p);
            }
        }
        foreach (self::MOSTRADOR as $nombre) {
            $p = Permission::where('name', $nombre)->where('guard_name', 'web')->first();
            if ($p && ! $mostrador->hasPermissionTo($p)) {
                $mostrador->givePermissionTo($p);
            }
        }

        foreach ($mostrador->users()->get() as $usuario) {
            /** @var User $usuario */
            $quitar = $usuario->getDirectPermissions()
                ->filter(fn ($p) => str_starts_with($p->name, 'talento.') && ! in_array($p->name, self::PERSONALES_QUE_SE_QUEDAN, true));
            foreach ($quitar as $p) {
                $usuario->revokePermissionTo($p);
            }
            if ($quitar->isNotEmpty()) {
                Log::info('Mostrador como técnico en Talento: se quitan permisos personales', [
                    'login_user' => $usuario->login_user,
                    'permisos'   => $quitar->pluck('name')->values()->all(),
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No se revierte solo: devolver permisos de administrador (al rol o a personas) es una
        // decisión aparte. Lo quitado a cada persona quedó en el log.
    }
};
