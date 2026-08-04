<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;

/**
 * Item #21 — Infra de relación Padre-Hijo (modelo + asociación + permiso).
 *
 * Decisión de Irving (preguntas estructuradas del item, 2026-07-15):
 *  - Modelo: tabla pivote `user_relationships` con tipo (padre/tutor/apoderado),
 *    GENÉRICA a nivel `users` (NO extiende el esquema parental_accounts de MegaFamilia).
 *  - Permisos: explícitos por relación, SIN herencia automática — crear la relación
 *    nunca copia roles/permisos del hijo al padre.
 *  - Alcance: solo lectura (el padre ve al hijo, no actúa en su nombre).
 *
 * Consumidores futuros (MegaFamilia, Vista Hijo APK, Panel Padre APK, Control
 * Parental) se conectan a esta tabla; ninguno se construye en este item.
 */
return new class extends Migration
{
    private string $permission = 'family.parent_view_child';

    public function up(): void
    {
        Schema::create('user_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('child_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['padre', 'tutor', 'apoderado'])->default('padre');
            $table->enum('status', ['activa', 'inactiva'])->default('activa');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['parent_user_id', 'child_user_id']);
            $table->index('status');
        });

        // Permiso explícito de lectura (solo super-administrator + DESARROLLADOR por
        // defecto — no termina en ".view" a propósito para NO auto-repartirse a los
        // demás roles base; se otorga a mano cuando exista un consumidor real).
        $permission = Permission::where('name', $this->permission)->where('guard_name', 'web')->first();
        if (! $permission) {
            try {
                Permission::create([
                    'name'        => $this->permission,
                    'guard_name'  => 'web',
                    'description' => 'Ver información de una cuenta hijo/a vinculada (solo lectura, requiere relación activa en user_relationships)',
                ]);
            } catch (PermissionAlreadyExists $e) {
                // carrera/duplicado: ya existe, continuar.
            }
        }

        app(PermissionSyncService::class)->syncPermissionToBaseRoles($this->permission);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_relationships');
        // keep_data:true — no eliminamos el permiso Spatie al revertir.
    }
};
