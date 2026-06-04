<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-paso 1: Definición de niveles
        Schema::create('talento_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);                  // junior, senior, expert, etc.
            $table->unsignedTinyInteger('rank');          // 1=más bajo, mayor=más alto
            $table->json('required_certifications');      // {count: N} o [{course_id: X}, ...]
            $table->decimal('base_salary', 10, 2);        // sueldo estándar del nivel
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('rank');
            $table->index('active');
        });

        // Sub-paso 2a: Nivel actual del colaborador (FK aditiva)
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            $table->unsignedBigInteger('level_id')->nullable()->after('supervisor_id');
            $table->foreign('level_id')->references('id')->on('talento_levels')->onDelete('set null');
        });

        // Sub-paso 2b: Historial de asignaciones de nivel
        Schema::create('talento_level_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('level_id');
            $table->enum('reason', ['promotion', 'manual'])->default('promotion');
            $table->json('certifications_snapshot');      // snapshot del progreso al momento
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('cascade');
            $table->foreign('level_id')->references('id')->on('talento_levels')->onDelete('restrict');
            $table->index(['colaborador_id', 'assigned_at'], 'tla_col_time_idx');
        });

        // Sub-paso 4a: Gating en tipos de orden de trabajo
        Schema::table('talento_work_order_types', function (Blueprint $table) {
            $table->unsignedBigInteger('required_level_id')->nullable()->after('active');
            $table->foreign('required_level_id')->references('id')->on('talento_levels')->onDelete('set null');
        });

        // Sub-paso 4b: Gating en tipos de actividad de proyectos
        Schema::table('talento_activity_types', function (Blueprint $table) {
            $table->unsignedBigInteger('required_level_id')->nullable()->after('active');
            $table->foreign('required_level_id')->references('id')->on('talento_levels')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('talento_activity_types',    fn($t) => $t->dropForeign(['required_level_id']));
        Schema::table('talento_activity_types',    fn($t) => $t->dropColumn('required_level_id'));
        Schema::table('talento_work_order_types',  fn($t) => $t->dropForeign(['required_level_id']));
        Schema::table('talento_work_order_types',  fn($t) => $t->dropColumn('required_level_id'));
        Schema::dropIfExists('talento_level_assignments');
        Schema::table('talento_colaboradores',     fn($t) => $t->dropForeign(['level_id']));
        Schema::table('talento_colaboradores',     fn($t) => $t->dropColumn('level_id'));
        Schema::dropIfExists('talento_levels');
    }
};
