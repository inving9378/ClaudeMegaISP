<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1326: una incidencia sin atender («No puedo completar») manda la orden al supervisor. Estas
 * columnas dicen cuándo, quién y cómo la atendió (editada, reprogramada, eliminada o regresada al
 * técnico), y «El cliente canceló» se agrega a la lista de motivos. Las incidencias de antes quedan
 * atendidas como «anterior» para que no lleguen todas de golpe a la bandeja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_work_order_incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('talento_work_order_incidents', 'atendida_at')) {
                $table->timestamp('atendida_at')->nullable()->after('server_at');
                $table->unsignedBigInteger('atendida_por')->nullable()->after('atendida_at');
                $table->string('atencion', 20)->nullable()->after('atendida_por');
                $table->index(['tarea_id', 'atendida_at'], 'talento_wo_incidents_tarea_atendida_idx');
            }
        });

        DB::statement("ALTER TABLE talento_work_order_incidents MODIFY motivo ENUM('cliente_cancelo','cliente_ausente','sin_acceso','falta_material','olt_sin_senal','riesgo','otro') NOT NULL");

        DB::table('talento_work_order_incidents')->whereNull('atendida_at')
            ->update(['atendida_at' => DB::raw('created_at'), 'atencion' => 'anterior']);
    }

    public function down(): void
    {
        // Las de «El cliente canceló» pasan a «otro» para poder quitar el valor de la lista.
        DB::table('talento_work_order_incidents')->where('motivo', 'cliente_cancelo')->update(['motivo' => 'otro']);
        DB::statement("ALTER TABLE talento_work_order_incidents MODIFY motivo ENUM('cliente_ausente','sin_acceso','falta_material','olt_sin_senal','riesgo','otro') NOT NULL");

        Schema::table('talento_work_order_incidents', function (Blueprint $table) {
            if (Schema::hasColumn('talento_work_order_incidents', 'atendida_at')) {
                $table->dropIndex('talento_wo_incidents_tarea_atendida_idx');
                $table->dropColumn(['atendida_at', 'atendida_por', 'atencion']);
            }
        });
    }
};
