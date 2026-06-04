<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_compensation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('target_type', ['technician', 'seller', 'counter', 'all'])->default('all');
            $table->decimal('base_salary', 10, 2);
            $table->enum('period', ['weekly', 'biweekly', 'monthly'])->default('weekly');
            $table->unsignedSmallInteger('weekly_quota_units')->default(0);
            $table->json('monthly_bonus')->nullable();  // {threshold_units: X, bonus_amount: Y}
            $table->json('conditions')->nullable();     // extensible para fases futuras
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Immutable assignment history: collaborator ↔ rule snapshot
        Schema::create('talento_compensation_rule_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('rule_id');
            $table->json('data');   // snapshot completo de la regla al momento de asignar
            $table->timestamp('assigned_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('rule_id')->references('id')->on('talento_compensation_rules')->onDelete('restrict');
            $table->index(['colaborador_id', 'assigned_at'], 'tcrh_col_assigned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_compensation_rule_history');
        Schema::dropIfExists('talento_compensation_rules');
    }
};
