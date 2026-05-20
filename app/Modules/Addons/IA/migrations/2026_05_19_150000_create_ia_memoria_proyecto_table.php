<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Memoria persistente del proyecto: hechos, decisiones, avances y pendientes
 * extraídos automáticamente de las conversaciones IA y reinyectados como
 * system prompt al iniciar nuevas conversaciones.
 *
 * Scope GLOBAL del proyecto (compartido por todo el equipo, no por user_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_memoria_proyecto', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['hecho', 'avance', 'decision', 'pendiente', 'error_resuelto'])
                  ->default('hecho');
            $table->text('contenido');
            $table->string('modulo_relacionado', 100)->nullable();
            $table->unsignedTinyInteger('relevancia')->default(5)->comment('1-10');
            $table->boolean('obsoleto')->default(false);
            $table->unsignedBigInteger('ia_conversacion_id')->nullable()
                  ->comment('Conversación de la que se extrajo (NULL si es manual)');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['obsoleto', 'relevancia'], 'idx_memoria_vigentes');
            $table->index('tipo');
            $table->index('ia_conversacion_id');

            $table->foreign('ia_conversacion_id')
                  ->references('id')->on('ia_conversaciones')
                  ->nullOnDelete();
        });

        // Permiso ia_manage_memoria asignado a roles administrativos.
        $perm = $this->upsertPermission('ia_manage_memoria');

        $rolesObjetivo = [
            ComunConstantsController::SUPER_ADMIN_ROLE,
            ComunConstantsController::SUPER_ADMINISTRADOR_CUSTOM_ROLE,
            'Administrador',
            ComunConstantsController::DEVELOPER_ROLE,
        ];

        foreach ($rolesObjetivo as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($perm);
            }
        }
    }

    public function down(): void
    {
        $perm = Permission::where('name', 'ia_manage_memoria')->where('guard_name', 'web')->first();
        if ($perm) {
            foreach (Role::all() as $role) {
                $role->revokePermissionTo($perm);
            }
            $perm->delete();
        }
        Schema::dropIfExists('ia_memoria_proyecto');
    }

    protected function upsertPermission(string $name): Permission
    {
        try {
            return Permission::create(['name' => $name, 'guard_name' => 'web']);
        } catch (PermissionAlreadyExists $e) {
            return Permission::where('name', $name)->where('guard_name', 'web')->firstOrFail();
        }
    }
};
