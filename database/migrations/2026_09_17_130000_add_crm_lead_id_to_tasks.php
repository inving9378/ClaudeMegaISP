<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Item #9991201 — vínculo opcional Orden de Trabajo (Talento) → prospecto CRM.
// Sigue el mismo patrón que olt_onu_id/caja_id (2026_06_08_205822): columna plana sin FK
// real (tasks es tabla core, crm_lead_information vive en el módulo CRM), porque las
// OTs de admin siempre se crean como Task (tipo=campo) — talento_work_orders no tiene
// escritor real en el código (0 filas en dev), así que no se le agrega la columna ahí.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('crm_lead_id')->nullable()->after('client_main_information_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('crm_lead_id');
        });
    }
};
