<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #199 (Expediente RH — Hijo A). CURP, RFC, NSS, salario y domicilio son datos
 * personales sensibles: ver el expediente exige un permiso PROPIO, distinto de 'talento.view'
 * (que solo da acceso a la ficha/perfil normal del colaborador). Nadie debe leer el sueldo de
 * otro solo por entrar a su ficha. Aditiva/idempotente: crea el permiso y lo asigna a los roles
 * base (super-administrator + DESARROLLADOR), igual que el resto de permisos de Talento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.expediente.view', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.expediente.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
