<?php

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Services\Finance\Billing\PrefacturaPlantillaUpdater;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Item #480 — ajusta la plantilla «Prefactura.» (document_templates, POR NOMBRE) para el
 * PrefacturaDataProvider: logo → ${data.logo_pdf}, celda de IVA → ${data.iva_monto} y sin
 * «${…}» dentro de comentarios. Detalle de los cambios en PrefacturaPlantillaUpdater.
 *
 * NUNCA falla, a propósito: una migración que truena en prod frena toda la release. Si el HTML
 * en BD no es exactamente el esperado (alguien la editó), no toca nada, deja Log::warning y
 * registra un item en la Hoja de Ruta; el ajuste se aplica luego a mano con
 *   php artisan prefactura:actualizar-plantilla --dry-run   (muestra el diff)
 *   php artisan prefactura:actualizar-plantilla --force
 *
 * down() restaura el HTML previo, que va aquí abajo (gz + base64, exacto byte a byte: el
 * original usa CRLF), solo si la plantilla sigue tal cual la dejó up().
 */
return new class extends Migration
{
    private const ORIGINAL_GZ_B64 = <<<'B64'
        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
        B64;

    public function up(): void
    {
        try {
            $r = (new PrefacturaPlantillaUpdater())->aplicar();

            if ($r['escrito'] || $r['situacion'] === PrefacturaPlantillaUpdater::SITUACION_APLICADA) {
                Log::info('[Prefactura] Plantilla: ' . $r['motivo'], ['id' => $r['id']]);

                return;
            }

            Log::warning('[Prefactura] La plantilla NO se actualizó: ' . $r['motivo'], [
                'situacion' => $r['situacion'],
                'faltan'    => $r['faltan'],
                'id'        => $r['id'],
            ]);
            $this->registrarEnHojaDeRuta($r);
        } catch (\Throwable $e) {
            Log::warning('[Prefactura] Error al ajustar la plantilla (sin cambios): ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            $updater = new PrefacturaPlantillaUpdater();
            $plantilla = $updater->plantilla();
            if (! $plantilla || sha1((string) $plantilla->html) !== PrefacturaPlantillaUpdater::HASH_APLICADA) {
                Log::warning('[Prefactura] down(): la plantilla no está como la dejó up(); no se restaura.');

                return;
            }

            $original = gzuncompress(base64_decode(preg_replace('/\s+/', '', self::ORIGINAL_GZ_B64)));
            if (! is_string($original) || sha1($original) !== PrefacturaPlantillaUpdater::HASH_ORIGINAL) {
                Log::warning('[Prefactura] down(): el respaldo embebido no coincide con su hash; no se restaura.');

                return;
            }

            DB::table('document_templates')->where('id', $plantilla->id)->update([
                'html'       => $original,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Prefactura] down(): error al restaurar (sin cambios): ' . $e->getMessage());
        }
    }

    private function registrarEnHojaDeRuta(array $r): void
    {
        if (! Schema::hasTable('roadmap_items') || ! class_exists(RoadmapItem::class)) {
            return;
        }

        $titulo = 'Prefactura: la plantilla «Prefactura.» no se ajustó sola al desplegar (HTML distinto al esperado)';
        $abierto = RoadmapItem::where('title', $titulo)
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado'])
            ->exists();
        if ($abierto) {
            return;
        }

        RoadmapItem::create([
            'title'                   => $titulo,
            'modulo'                  => 'Facturación',
            'description'             => 'La migración 2026_10_16_170000 (item #480) no tocó la plantilla: '
                . $r['motivo'] . ' Situación: ' . $r['situacion'] . '.'
                . ($r['faltan'] ? ' Anclas faltantes: ' . implode('; ', $r['faltan']) . '.' : '')
                . ' Mientras no se ajuste, el botón «Prefactura actualizada» puede pedir variables que la plantilla no tiene.',
            'prompt'                  => 'En el servidor afectado: php artisan prefactura:actualizar-plantilla --dry-run, '
                . 'revisar el diff y, si es correcto, aplicar con --force (respalda el HTML previo en storage/app/prefactura/).',
            'status'                  => 'pending',
            'priority'                => 'alta',
            'nivel_riesgo'            => 'B',
            'nivel_riesgo_origen'     => 'interno',
            'estado_aprobacion'       => 'requiere_irving',
            'tipo'                    => 'manual',
            'excluir_pool_automatico' => true,
        ]);
    }
};
