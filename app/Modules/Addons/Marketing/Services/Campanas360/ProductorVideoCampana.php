<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Jobs\RenderVideoJob;
use App\Modules\Addons\Marketing\Models\Empresa;
use Illuminate\Support\Facades\DB;

/**
 * «Producir en Video MVM» (§2.6): crea el trabajo en el motor existente con la plantilla «Guion por escenas», las
 * escenas, la voz y la música del guion, ligado a la campaña (`source_campaign_id`, `pieza_origen_id`) y lo despacha a
 * la cola `video-render`. El video aparece también en Video → Cola. Un guion no se produce dos veces a la vez
 * (candado de fila sobre el guion + 409).
 */
class ProductorVideoCampana
{
    public const PLANTILLAS = ['reel_guion' => 'guion-escenas-9-16', 'video_guion' => 'guion-escenas-16-9'];

    public function __construct(
        private readonly ValidadorPiezas $validador,
        private readonly GuionAMvm $traductor,
    ) {}

    /**
     * @return array{ok: true, video: GeneratedContent}|array{ok: false, error: string, mensaje: string, http: int, extra?: array}
     */
    public function producir(Campaign $campana, GeneratedContent $guion, ?string $voz = null, ?string $musica = null): array
    {
        if (! in_array($guion->pieza, CatalogosCampana360::GUIONES, true)) {
            return self::rechazo('no_es_guion', 'Solo los guiones de Reel y de Video se producen en Video MVM.', 422);
        }

        if ($guion->status !== 'ready') {
            return self::rechazo('pieza_no_lista', 'El guion todavía no está listo.', 422);
        }

        $v = $this->validador->validar($guion->pieza, $guion->contenido);
        if (! $v['ok']) {
            return self::rechazo('guion_invalido', 'El guion no cumple los límites del video. Corrígelo antes de producir.', 422, ['errores' => $v['errores']]);
        }

        $plantilla = VideoTemplate::query()
            ->where('company_id', Empresa::PRINCIPAL_ID)
            ->where('slug', self::PLANTILLAS[$guion->pieza])
            ->where('active', true)
            ->first();

        if (! $plantilla) {
            return self::rechazo('plantilla_no_disponible', 'La plantilla «Guion por escenas» no está disponible. Avísale a Irving.', 422);
        }

        $formato = CatalogosCampana360::formatoDeGuion($guion->pieza);
        $trabajo = $this->traductor->traducir($v['contenido'], $formato, $voz, $musica, $campana->publico['nichos'] ?? []);

        $resultado = DB::transaction(function () use ($guion, $campana, $plantilla, $trabajo, $formato, $v) {
            GeneratedContent::query()->whereKey($guion->id)->lockForUpdate()->first();

            $enCurso = GeneratedContent::query()
                ->where('pieza_origen_id', $guion->id)
                ->whereIn('pieza', CatalogosCampana360::PIEZAS_VIDEO)
                ->whereIn('status', ['pending', 'generating'])
                ->exists();

            if ($enCurso) {
                return self::rechazo('produccion_en_curso', 'Ese guion ya se está produciendo.', 409);
            }

            $video = new GeneratedContent();
            $video->forceFill([
                'company_id'          => Empresa::PRINCIPAL_ID,
                'type'                => 'video',
                'pieza'               => CatalogosCampana360::piezaVideoDeGuion($guion->pieza),
                'pieza_origen_id'     => $guion->id,
                'source_campaign_id'  => $campana->id,
                'template_id'         => $plantilla->id,
                'template_type'       => VideoTemplate::class,
                'status'              => 'pending',
                'render_stage'        => 'queued',
                'render_progress'     => 0,
                'render_log'          => [],
                'generation_engine'   => 'mvm_ffmpeg',
                'generation_cost_usd' => 0,
                'input_variables'     => $trabajo['variables'],
                'generation_metadata' => [
                    'template_slug' => $plantilla->slug,
                    'aspect_ratio'  => $formato,
                    'guion_hash'    => sha1(json_encode($v['contenido'], JSON_UNESCAPED_UNICODE)),
                ],
            ])->save();

            return ['ok' => true, 'video' => $video];
        });

        if (! $resultado['ok']) {
            return $resultado;
        }

        // Fuera de la transacción (con la cola `sync` el render corre aquí y debe ver la fila ya guardada).
        RenderVideoJob::dispatch(
            $resultado['video']->id,
            $plantilla->id,
            $trabajo['variables'],
            $trabajo['options'],
            Empresa::PRINCIPAL_ID,
        )->onQueue('video-render');

        return $resultado;
    }

    private static function rechazo(string $error, string $mensaje, int $http, array $extra = []): array
    {
        return ['ok' => false, 'error' => $error, 'mensaje' => $mensaje, 'http' => $http, 'extra' => $extra];
    }
}
