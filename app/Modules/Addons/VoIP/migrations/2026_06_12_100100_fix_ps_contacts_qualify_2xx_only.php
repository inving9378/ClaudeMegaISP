<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna qualify_2xx_only a ps_contacts.
 *
 * La migración anterior (100000) cubrió las 7 columnas más comunes del
 * schema de Asterisk 20. El log de Asterisk reveló una columna adicional
 * requerida en el INSERT real:
 *   Unknown column 'qualify_2xx_only' in 'field list'
 *
 * qualify_2xx_only controla si el qualify (OPTIONS) solo acepta
 * respuestas 2xx como señal de que el endpoint está vivo.
 */
return new class extends Migration
{
    protected $connection = 'asterisk_rt';

    public function up(): void
    {
        Schema::connection('asterisk_rt')->table('ps_contacts', function (Blueprint $table) {
            if (! Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'qualify_2xx_only')) {
                $table->tinyInteger('qualify_2xx_only')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::connection('asterisk_rt')->table('ps_contacts', function (Blueprint $table) {
            if (Schema::connection('asterisk_rt')->hasColumn('ps_contacts', 'qualify_2xx_only')) {
                $table->dropColumn('qualify_2xx_only');
            }
        });
    }
};
