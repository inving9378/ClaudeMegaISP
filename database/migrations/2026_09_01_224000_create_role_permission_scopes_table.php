<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #865 (Fase B) — asignación real de alcance por rol. `permission_scopes`
 * (Fase A, #851) solo CATALOGA qué permisos admiten distinguir "propios" vs
 * "todos"; esta tabla dice, por cada (rol, permiso), cuál de los dos aplica.
 * Default 'todos' preserva el comportamiento actual para TODO rol hasta que
 * alguien inserte una fila explícita con 'propios' — sin fila, el Global
 * Scope (OwnScopeFilter) no filtra nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_permission_scopes')) {
            return;
        }

        Schema::create('role_permission_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->enum('scope', ['propios', 'todos'])->default('todos');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_scopes');
    }
};
