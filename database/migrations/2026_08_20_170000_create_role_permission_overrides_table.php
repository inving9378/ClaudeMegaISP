<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #542 — marca de "override manual" por rol+permiso.
 *
 * Cuando la pantalla de edición de rol (PermissionController::update) revoca
 * un permiso, se registra aquí. PermissionSyncService::giveToAllOtherRoles()
 * respeta esta marca y NO vuelve a otorgar automáticamente ese permiso a ese
 * rol (aunque auto_grant_view_base_roles esté en true), para que una edición
 * manual del rol no quede pisada por un sync posterior. Si el permiso se
 * vuelve a agregar manualmente, la marca se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_overrides');
    }
};
