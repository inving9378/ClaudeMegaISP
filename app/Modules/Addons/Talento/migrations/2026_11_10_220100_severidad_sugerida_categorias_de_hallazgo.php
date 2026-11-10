<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #1316 (decisión 3 de Irving, 2026-11-10): severidad sugerida de las categorías iniciales de
 * hallazgo. Solo llena las que siguen vacías: no pisa lo que ya se haya editado en el catálogo.
 * Las demás categorías quedan sin sugerencia (se editan en Talento › Recorridos › Categorías).
 */
return new class extends Migration
{
    private const SUGERIDAS = [
        'Robo o vandalismo'        => 'critica',
        'Riesgo por terceros'      => 'critica',
        'Poste dañado o inclinado' => 'alta',
        'Fibra expuesta o dañada'  => 'alta',
        'Vegetación sobre la fibra' => 'media',
    ];

    public function up(): void
    {
        foreach (self::SUGERIDAS as $nombre => $severidad) {
            DB::table('talento_hallazgo_categorias')->where('nombre', $nombre)->whereNull('severidad_sugerida')
                ->update(['severidad_sugerida' => $severidad, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::SUGERIDAS as $nombre => $severidad) {
            DB::table('talento_hallazgo_categorias')->where('nombre', $nombre)->where('severidad_sugerida', $severidad)
                ->update(['severidad_sugerida' => null, 'updated_at' => now()]);
        }
    }
};
