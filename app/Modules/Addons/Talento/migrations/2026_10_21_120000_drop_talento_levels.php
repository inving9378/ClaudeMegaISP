<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira los niveles de colaborador (Fase 7b, 2026-06-04): niveles con sueldo, promociones y
 * "gating" de órdenes/actividades por nivel. Nadie los usaba (0 colaboradores con nivel,
 * 0 promociones, 0 tipos con nivel requerido) y duplicaban lo que ya se maneja en
 * Administración. Decisión del 2026-10-21; respaldo de las filas en la bitácora de ese día.
 */
return new class extends Migration
{
    private const PERMISOS = ['talento.levels.view', 'talento.levels.manage'];

    public function up(): void
    {
        foreach (['talento_activity_types' => 'required_level_id', 'talento_work_order_types' => 'required_level_id', 'talento_colaboradores' => 'level_id'] as $tabla => $columna) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $t) use ($columna) {
                    $t->dropForeign([$columna]);
                    $t->dropColumn($columna);
                });
            }
        }

        Schema::dropIfExists('talento_level_assignments');
        Schema::dropIfExists('talento_levels');

        // Spatie borra en cascada sus asignaciones a roles y usuarios.
        DB::table('permissions')->whereIn('name', self::PERMISOS)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        if (Schema::hasTable('manual_sections')) {
            DB::table('manual_sections')->where('module_slug', 'talento-niveles')->delete();
        }
    }

    public function down(): void
    {
        // Solo la estructura; las filas viven en el respaldo de la bitácora del 2026-10-21.
        if (!Schema::hasTable('talento_levels')) {
            Schema::create('talento_levels', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80);
                $table->unsignedTinyInteger('rank');
                $table->json('required_certifications');
                $table->decimal('base_salary', 10, 2);
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique('rank');
                $table->index('active');
            });
        }
        if (!Schema::hasTable('talento_level_assignments')) {
            Schema::create('talento_level_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('colaborador_id');
                $table->unsignedBigInteger('level_id');
                $table->enum('reason', ['promotion', 'manual'])->default('promotion');
                $table->json('certifications_snapshot');
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('cascade');
                $table->foreign('level_id')->references('id')->on('talento_levels')->onDelete('restrict');
                $table->index(['colaborador_id', 'assigned_at'], 'tla_col_time_idx');
            });
        }
        foreach (['talento_colaboradores' => ['level_id', 'supervisor_id'], 'talento_work_order_types' => ['required_level_id', 'active'], 'talento_activity_types' => ['required_level_id', 'active']] as $tabla => [$columna, $despues]) {
            if (!Schema::hasColumn($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $t) use ($columna, $despues) {
                    $t->unsignedBigInteger($columna)->nullable()->after($despues);
                    $t->foreign($columna)->references('id')->on('talento_levels')->onDelete('set null');
                });
            }
        }
        foreach (self::PERMISOS as $nombre) {
            DB::table('permissions')->insertOrIgnore(['name' => $nombre, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
