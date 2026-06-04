<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-paso 1: Proyectos de planta externa
        Schema::create('talento_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->enum('status', ['planning', 'active', 'paused', 'done'])->default('planning');
            $table->unsignedBigInteger('lead_colaborador_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();          // vigencia / deadline para el bono
            $table->decimal('bonus_amount', 10, 2)->nullable(); // simple flat bonus (fallback)
            $table->json('bonus_scale')->nullable();        // [{threshold_pct: 80, amount: 500}, ...]
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('lead_colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->index(['status']);
        });

        // Sub-paso 2: Tipos de actividad configurables
        Schema::create('talento_activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('unit', 40)->default('unidad'); // metros, postes, loops, cruces, etc.
            $table->decimal('points_per_unit', 8, 4)->default(1);
            $table->decimal('money_per_unit', 10, 4)->nullable(); // for direct cash (rare)
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Sub-paso 3: Pool de actividades del proyecto (alcance/techo)
        Schema::create('talento_project_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('activity_type_id');
            $table->decimal('planned_quantity', 12, 4);  // techo — anti-encimar guard
            $table->text('location_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('project_id')->references('id')->on('talento_projects')->onDelete('cascade');
            $table->foreign('activity_type_id')->references('id')->on('talento_activity_types')->onDelete('restrict');
            $table->unique(['project_id', 'activity_type_id'], 'tpa_proj_type_unique');
        });

        // Sub-paso 4a: Reportes de actividad (cazados en campo)
        Schema::create('talento_project_activity_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_activity_id');
            $table->unsignedBigInteger('reported_by');     // FK users (quien reporta, puede ser supervisor)
            $table->decimal('quantity', 12, 4);
            $table->date('report_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'capped'])->default('pending');
            // capped = accepted but reduced because it would exceed planned_quantity
            $table->decimal('approved_quantity', 12, 4)->nullable(); // actual approved (may be < quantity)
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('project_activity_id')
                  ->references('id')->on('talento_project_activities')->onDelete('restrict');
            $table->index(['project_activity_id', 'status'], 'tpar_activity_status_idx');
            $table->index(['report_date'], 'tpar_date_idx');
        });

        // Sub-paso 4b: Participantes del reporte (para el reparto 1/N)
        Schema::create('talento_activity_report_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('colaborador_id');
            // Denormalized share — calculated at approval: approved_quantity / N × points_per_unit
            $table->decimal('quantity_share', 12, 4)->default(0);
            $table->decimal('points_earned', 10, 4)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('report_id')
                  ->references('id')->on('talento_project_activity_reports')->onDelete('cascade');
            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->unique(['report_id', 'colaborador_id'], 'tarp_report_col_unique');
        });

        // Seed: tipos de actividad base
        $now = now();
        $types = [
            ['name' => 'Tendido de fibra',   'unit' => 'metro',  'points_per_unit' => 0.1,  'money_per_unit' => null],
            ['name' => 'Subir a poste',       'unit' => 'poste',  'points_per_unit' => 1.0,  'money_per_unit' => null],
            ['name' => 'Hacer loop',          'unit' => 'unidad', 'points_per_unit' => 2.0,  'money_per_unit' => null],
            ['name' => 'Cruce por árbol',     'unit' => 'unidad', 'points_per_unit' => 1.5,  'money_per_unit' => null],
            ['name' => 'Empalme de fibra',    'unit' => 'empalme','points_per_unit' => 3.0,  'money_per_unit' => null],
        ];
        foreach ($types as $t) {
            DB::table('talento_activity_types')->insertOrIgnore(array_merge($t, [
                'active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_activity_report_participants');
        Schema::dropIfExists('talento_project_activity_reports');
        Schema::dropIfExists('talento_project_activities');
        Schema::dropIfExists('talento_activity_types');
        Schema::dropIfExists('talento_projects');
    }
};
