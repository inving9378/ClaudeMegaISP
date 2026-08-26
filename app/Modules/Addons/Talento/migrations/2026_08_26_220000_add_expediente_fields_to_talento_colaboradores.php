<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #199 (Expediente RH — Hijo A). Campos que piden las 11 plantillas de RH y
 * hoy no existen en el colaborador. NO se duplican telefono/correo/domicilio/RFC: esos ya
 * viven en `users` (phone/email/address/city_municipality/state_country/code_postal/colony/rfc)
 * y el expediente los reutiliza desde ahi. Tampoco se duplican puesto->department (ya existe,
 * "area o cuadrilla"), jefe inmediato->supervisor_id, fecha de ingreso->hire_date ni
 * salario->base_salary (ya existian). Aditiva/idempotente (guard hasColumn), nunca migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_colaboradores', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('hire_date');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'curp')) {
                $table->string('curp', 18)->nullable()->after('birth_date');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'nss')) {
                $table->string('nss', 11)->nullable()->after('curp');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'emergency_contact_name')) {
                $table->string('emergency_contact_name', 150)->nullable()->after('nss');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'job_title')) {
                $table->string('job_title', 100)->nullable()->after('emergency_contact_phone');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'relation_type')) {
                $table->enum('relation_type', ['indeterminada', 'determinada', 'obra'])->nullable()->after('job_title');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'relation_end_date')) {
                $table->date('relation_end_date')->nullable()->after('relation_type');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'pay_frequency')) {
                $table->enum('pay_frequency', ['semanal', 'quincenal', 'mensual'])->nullable()->after('relation_end_date');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'work_schedule')) {
                $table->string('work_schedule', 150)->nullable()->after('pay_frequency');
            }
            if (!Schema::hasColumn('talento_colaboradores', 'work_location')) {
                $table->string('work_location', 150)->nullable()->after('work_schedule');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            foreach ([
                'birth_date', 'curp', 'nss', 'emergency_contact_name', 'emergency_contact_phone',
                'job_title', 'relation_type', 'relation_end_date', 'pay_frequency',
                'work_schedule', 'work_location',
            ] as $column) {
                if (Schema::hasColumn('talento_colaboradores', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
