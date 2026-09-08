<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Seeders;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcDiaFestivo;
use Illuminate\Database\Seeder;

/**
 * Siembra los festivos oficiales de México (art. 74 LFT) que quedan de 2026
 * en adelante desde la fecha en que se corre este seeder. IDEMPOTENTE
 * (`firstOrCreate` por fecha) — correrlo de nuevo no duplica filas. El
 * catálogo queda editable por un admin después (Fase posterior, UI propia).
 *
 * Ejecutar manualmente: php artisan db:seed --class="App\Modules\Addons\DocumentacionCorporativa\Seeders\DiasFestivosSeeder"
 */
class DiasFestivosSeeder extends Seeder
{
    public function run(): void
    {
        $festivos = [
            '2026-09-16' => 'Día de la Independencia',
            '2026-11-16' => 'Aniversario de la Revolución Mexicana (3er lunes de noviembre)',
            '2026-12-25' => 'Navidad',
        ];

        foreach ($festivos as $fecha => $descripcion) {
            DcDiaFestivo::firstOrCreate(
                ['fecha' => $fecha],
                ['descripcion' => $descripcion]
            );
        }
    }
}
