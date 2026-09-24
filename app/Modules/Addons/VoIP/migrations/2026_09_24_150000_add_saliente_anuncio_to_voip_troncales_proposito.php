<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MegaVoz Fase 7 — completa el enum `proposito` con el 4º valor. Se declaró
 * `registro_ucm/saliente_cobranza/saliente_avisos/saliente_corte` desde
 * Fase 1 (23-sep), pero el plan de Fase 7 habla de 4 tipos de campaña
 * (cobranza/aviso/anuncio/corte) — faltaba el propósito de troncal para
 * "anuncio". MySQL no tiene ALTER de enum vía Schema::enum() para agregar un
 * valor sin redeclarar toda la columna; se hace con SQL crudo, ADITIVO (los
 * 4 valores existentes se preservan igual).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE voip_troncales
            MODIFY COLUMN proposito ENUM('registro_ucm', 'saliente_cobranza', 'saliente_avisos', 'saliente_anuncio', 'saliente_corte')
            NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE voip_troncales
            MODIFY COLUMN proposito ENUM('registro_ucm', 'saliente_cobranza', 'saliente_avisos', 'saliente_corte')
            NULL
        ");
    }
};
