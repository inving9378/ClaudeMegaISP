<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 4 (F4.2) — Trazabilidad del pago conciliado por WhatsApp.
 *
 * Registra AMBOS actores en el reported_payment que genera la conciliación:
 *   - identified_by_user_id = quién IDENTIFICÓ al cliente (siempre MEGAISP).
 *   - confirmed_by_user_id  = quién CONFIRMÓ la aplicación (Tere, en el flujo
 *                             'proposed'; NULL en el automático 'exact' MEG).
 *
 * Columnas explícitas y dedicadas (no se reusa verified_by, que es de la
 * conciliación bancaria existente). Aditivas, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reported_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('reported_payments', 'identified_by_user_id')) {
                $table->unsignedBigInteger('identified_by_user_id')->nullable()->after('tecnico_id');
            }
            if (!Schema::hasColumn('reported_payments', 'confirmed_by_user_id')) {
                $table->unsignedBigInteger('confirmed_by_user_id')->nullable()->after('identified_by_user_id');
            }
            // Vínculo con la sesión de identificación (traza + anti-duplicado).
            if (!Schema::hasColumn('reported_payments', 'identification_session_id')) {
                $table->unsignedBigInteger('identification_session_id')->nullable()->after('confirmed_by_user_id');
                $table->index('identification_session_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reported_payments', function (Blueprint $table) {
            foreach (['identified_by_user_id', 'confirmed_by_user_id', 'identification_session_id'] as $col) {
                if (Schema::hasColumn('reported_payments', $col)) {
                    if ($col === 'identification_session_id') {
                        $table->dropIndex(['identification_session_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
