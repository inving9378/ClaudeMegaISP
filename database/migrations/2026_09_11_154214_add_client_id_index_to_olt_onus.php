<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #9990815 (Fase 4 del buscador v2 de Clientes): la columna olt_onus.client_id se
 * agregó en 2026_04_17_032635 sin índice. El buscador por SN (prefijo `sn:`) correlaciona
 * whereExists contra olt_onus.client_id = clients.id; sin índice, MySQL hace table scan
 * completo de olt_onus por cada cliente evaluado (confirmado con EXPLAIN: DEPENDENT
 * SUBQUERY type=ALL, possible_keys vacío) — esto es lo que dispara p95 a >20s en vez de
 * los ~200ms de una búsqueda por nombre (criterio de aceptación 10 del item #9990803).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->index('client_id', 'olt_onus_client_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->dropIndex('olt_onus_client_id_index');
        });
    }
};
