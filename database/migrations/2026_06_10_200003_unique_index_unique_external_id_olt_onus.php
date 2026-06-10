<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índice UNIQUE en olt_onus.unique_external_id (nullable).
     *
     * NULL no viola la restricción UNIQUE en MySQL (cada NULL es único),
     * por lo que las ONUs sin unique_external_id siguen usando (sn, olt_id)
     * como clave de upsert y no son afectadas.
     *
     * Pre-requisito GR-3c SP1+SP2: upsert ya usa este campo como clave
     * y los 2 duplicados fantasma fueron eliminados.
     */
    public function up(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->unique('unique_external_id', 'olt_onus_unique_external_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->dropUnique('olt_onus_unique_external_id_unique');
        });
    }
};
