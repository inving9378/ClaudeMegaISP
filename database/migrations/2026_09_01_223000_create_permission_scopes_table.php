<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #851 (Fase A) — catálogo de permisos que declaran un criterio de
 * "alcance propio" (scope). Puramente declarativo: que un permiso tenga fila
 * aquí NO filtra nada todavía (eso lo aplica el Eloquent Global Scope de una
 * fase posterior del mismo item) — solo dice "este permiso admite distinguir
 * entre ver TODO vs ver SOLO lo propio" y explica en criterio_propios en qué
 * se basa ese "propio" (ej. responsable de almacén). Se llena desde
 * module.json (clave opcional `scope_propios` por permiso) vía
 * PermissionSyncService, igual patrón que `permissions.description` (#858).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permission_scopes')) {
            return;
        }

        Schema::create('permission_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('criterio_propios');
            $table->timestamps();

            $table->unique('permission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_scopes');
    }
};
