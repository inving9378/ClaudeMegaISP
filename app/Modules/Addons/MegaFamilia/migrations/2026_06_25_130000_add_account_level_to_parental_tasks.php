<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas a nivel de CUENTA (aditivo). NO se dropa profile_id (lo siguen usando el
 * panel admin, ReportesController y la API móvil). Se agrega:
 *   - account_id (nullable, backfilleado desde el perfil) → tarea de la cuenta.
 *   - assignment_type ('cada_uno'|'solo_uno') → cómo se reparten las asignaciones.
 *
 * La verdad de "a quién está asignada" vive en parental_task_assignments. Las
 * tareas account-level del portal se distinguen porque TIENEN assignments (has()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('parental_tasks', 'account_id')) {
            Schema::table('parental_tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('profile_id');
                $table->foreign('account_id')->references('id')->on('parental_accounts')->onDelete('cascade');
            });
        }
        if (! Schema::hasColumn('parental_tasks', 'assignment_type')) {
            Schema::table('parental_tasks', function (Blueprint $table) {
                $table->enum('assignment_type', ['cada_uno', 'solo_uno'])->default('cada_uno')->after('points');
            });
        }

        // Backfill account_id desde el perfil (las filas existentes son profile-level).
        DB::statement('
            UPDATE parental_tasks t
            INNER JOIN parental_profiles p ON t.profile_id = p.id
            SET t.account_id = p.account_id
            WHERE t.account_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('parental_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('parental_tasks', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
            if (Schema::hasColumn('parental_tasks', 'assignment_type')) {
                $table->dropColumn('assignment_type');
            }
        });
    }
};
