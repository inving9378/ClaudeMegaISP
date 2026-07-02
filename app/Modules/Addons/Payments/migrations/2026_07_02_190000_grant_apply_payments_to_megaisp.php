<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * FASE 4 (F4.1) — MEGAISP puede APLICAR pagos.
 *
 * El bot MEGAISP (usuario de sistema id 4844) nacía con 0 permisos. Aquí se le
 * da EXCLUSIVAMENTE el permiso de aplicar pagos (payments_capture_manage), el
 * mismo que autoriza la captura de mostrador. Nada más.
 *
 * Idempotente y aditiva. No crea permisos nuevos ni roles.
 */
return new class extends Migration
{
    private const PERMISSION = 'payments_capture_manage';

    public function up(): void
    {
        $megaisp = User::systemBot();
        $perm    = Permission::where('name', self::PERMISSION)->first();

        if ($megaisp && $perm && !$megaisp->hasPermissionTo(self::PERMISSION)) {
            $megaisp->givePermissionTo($perm);
        }
    }

    public function down(): void
    {
        $megaisp = User::systemBot();
        if ($megaisp && $megaisp->hasPermissionTo(self::PERMISSION)) {
            $megaisp->revokePermissionTo(self::PERMISSION);
        }
    }
};
