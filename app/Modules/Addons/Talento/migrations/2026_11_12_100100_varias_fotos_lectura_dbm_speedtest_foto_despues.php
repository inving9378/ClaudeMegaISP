<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #1286: Lectura dBm, Speedtest y Foto después aceptan varias fotos en la misma orden (hasta
 * ahora solo Tendido/cableado, Empalme/fusión y Otro). Al cerrar, el candado de potencia ya toma la
 * lectura más reciente. Por nombre, porque los ids del catálogo pueden no coincidir entre entornos.
 */
return new class extends Migration
{
    private const TIPOS = ['Lectura dBm', 'Speedtest', 'Foto después'];

    public function up(): void
    {
        DB::table('talento_evidence_types')->whereIn('name', self::TIPOS)
            ->update(['permite_varias' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('talento_evidence_types')->whereIn('name', self::TIPOS)
            ->update(['permite_varias' => false, 'updated_at' => now()]);
    }
};
