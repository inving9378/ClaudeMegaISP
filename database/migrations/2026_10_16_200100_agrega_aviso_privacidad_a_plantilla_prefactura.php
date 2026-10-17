<?php

use App\Services\Finance\Billing\PrefacturaPlantillaUpdater;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * FAC-03 (item #508) — agrega ${data.aviso_privacidad} al pie legal de la plantilla «Prefactura.»
 * (cambio 6 de PrefacturaPlantillaUpdater). Mismo criterio que 2026_10_16_170000: solo toca un
 * HTML conocido (original, versión 1-4 o 1-5) y solo si el resultado da HASH_APLICADA; la
 * escritura va condicionada al hash leído. Si el HTML es otro (alguien la personalizó) NO falla
 * ni lo toca: deja Log::warning y el ajuste se revisa a mano con
 *   php artisan prefactura:actualizar-plantilla --dry-run   /   --force
 * Sin la variable en la plantilla simplemente no aparece la línea del aviso.
 *
 * En un entorno que aún tiene el original, la migración 170000 ya deja los 6 cambios y esta
 * encuentra «ya_aplicada».
 *
 * down(): quita solo el cambio 6, y solo si la plantilla está exactamente como la dejó up().
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $r = (new PrefacturaPlantillaUpdater())->aplicar();
            $nivel = ($r['escrito'] || $r['situacion'] === PrefacturaPlantillaUpdater::SITUACION_APLICADA) ? 'info' : 'warning';
            Log::{$nivel}('[Prefactura] Aviso de privacidad en la plantilla: ' . $r['motivo'], [
                'situacion' => $r['situacion'],
                'faltan'    => $r['faltan'],
                'id'        => $r['id'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Prefactura] Error al agregar el aviso de privacidad (sin cambios): ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            $updater = new PrefacturaPlantillaUpdater();
            $plantilla = $updater->plantilla();
            if (! $plantilla || sha1((string) $plantilla->html) !== PrefacturaPlantillaUpdater::HASH_APLICADA) {
                Log::warning('[Prefactura] down() aviso de privacidad: la plantilla no está como la dejó up(); no se toca.');

                return;
            }

            $html = str_replace('${data.leyenda_legal}${data.aviso_privacidad}', '${data.leyenda_legal}', (string) $plantilla->html);
            if (sha1($html) !== PrefacturaPlantillaUpdater::HASH_APLICADA_V2) {
                Log::warning('[Prefactura] down() aviso de privacidad: el resultado no es la versión 1-5; no se toca.');

                return;
            }

            \Illuminate\Support\Facades\DB::table('document_templates')
                ->where('id', $plantilla->id)
                ->whereRaw('SHA1(html) = ?', [PrefacturaPlantillaUpdater::HASH_APLICADA])
                ->update(['html' => $html, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('[Prefactura] down() aviso de privacidad: error (sin cambios): ' . $e->getMessage());
        }
    }
};
