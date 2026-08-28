<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #199 (Expediente RH — Hijo A), bloque "De la empresa" (config global, UNA vez,
 * no por persona). Reutiliza `company_information` (ya existe, fila singleton id=1) en vez de
 * crear una tabla propia: razon_social ya vive en `company_name`, domicilio/RFC operativos ya
 * existen. Solo se agregan los 2 campos que no tenian equivalente: representante que firma y
 * el domicilio para asuntos de datos personales (puede diferir del domicilio fiscal/operativo).
 * Aditiva/idempotente (guard hasColumn), nunca migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            if (!Schema::hasColumn('company_information', 'legal_representative')) {
                $table->string('legal_representative', 150)->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('company_information', 'data_privacy_address')) {
                $table->text('data_privacy_address')->nullable()->after('legal_representative');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            foreach (['legal_representative', 'data_privacy_address'] as $column) {
                if (Schema::hasColumn('company_information', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
