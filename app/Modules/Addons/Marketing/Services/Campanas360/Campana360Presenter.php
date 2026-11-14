<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Formas JSON de las Campañas 360 (§2.4 de docs/marketing/fase-2-paquetes.md): Campana360, resumen, Pieza, Video,
 * `generacion` y `videos_resumen`. El estado de la generación no se guarda: se calcula aquí de `estrategia_estado`
 * y de los estados de las filas de piezas.
 */
class Campana360Presenter
{
    public const ESTADO_PIEZA = ['pending' => 'pendiente', 'generating' => 'generando', 'ready' => 'lista', 'failed' => 'error',
                                 'completed' => 'lista', 'used' => 'lista'];

    public const ESTADO_VIDEO = ['pending' => 'en_cola', 'generating' => 'produciendo', 'ready' => 'listo', 'completed' => 'listo',
                                 'used' => 'listo', 'failed' => 'error'];

    public const ETIQUETA_VIDEO = ['en_cola' => 'En cola', 'produciendo' => 'Produciendo', 'listo' => 'Listo', 'error' => 'Error'];

    public const MINUTOS_DEMORA_COLA = 10;

    /** F-1: `budget` es NOT NULL; esta marca en `config` dice si el presupuesto ya se capturó (0 = orgánica). */
    public const MARCA_PRESUPUESTO = 'campanas360_presupuesto_definido';

    /** F-1: lo que una campaña debe tener para generar con IA o activarse (campo → qué falta, en español). */
    public const REQUISITOS = [
        'nombre'              => 'el nombre',
        'objetivo'            => 'el objetivo',
        'publico.descripcion' => 'la descripción del público',
        'oferta.descripcion'  => 'la descripción de la oferta',
        'canales'             => 'al menos un canal',
        'presupuesto_mxn'     => 'el presupuesto',
        'fecha_inicio'        => 'la fecha de inicio',
        'fecha_fin'           => 'la fecha de fin',
    ];

    public const MINUTOS_DEMORA_PRODUCCION = 15;

    /** Campana360 completa (sin piezas ni videos). */
    public function campana(Campaign $c, ?Collection $filas = null): array
    {
        $filas ??= $this->filas($c);
        [$piezas, $videos] = $this->separar($filas);

        return [
            'id'                     => (int) $c->id,
            'nombre'                 => $c->name,
            'estado'                 => $c->status,
            'estado_etiqueta'        => CatalogosCampana360::ESTADOS_CAMPANA[$c->status] ?? $c->status,
            'objetivo'               => $c->objetivo,
            'objetivo_etiqueta'      => CatalogosCampana360::OBJETIVOS[$c->objetivo] ?? null,
            'publico'                => $c->publico,
            'oferta'                 => $c->oferta,
            'canales'                => $c->canales ?? [],
            'presupuesto_mxn'        => self::presupuestoDefinido($c) ? RepartoPresupuesto::dinero((string) $c->budget) : null,
            'moneda'                 => 'MXN',
            'fecha_inicio'           => $c->start_date?->format('Y-m-d'),
            'fecha_fin'              => $c->end_date?->format('Y-m-d'),
            'estrategia'             => $c->estrategia,
            'estrategia_estado'      => $c->estrategia_estado ?? 'sin_generar',
            'estrategia_error'       => $c->estrategia_error,
            'estrategia_generada_at' => self::fecha($c->estrategia_generada_at),
            'generacion'             => $this->generacion($c, $piezas),
            'videos_resumen'         => $this->videosResumen($videos),
            'creada_at'              => self::fecha($c->created_at),
            'actualizada_at'         => self::fecha($c->updated_at),
            'faltantes'              => self::faltantes($c),
        ];
    }

    /** Campos que le faltan a la campaña para generar o activarse (vacío = completa). F-1. */
    public static function faltantes(Campaign $c): array
    {
        $vacio = fn ($v) => $v === null || (is_string($v) && trim($v) === '') || $v === [];

        $valores = [
            'nombre'              => $c->name,
            'objetivo'            => $c->objetivo,
            'publico.descripcion' => $c->publico['descripcion'] ?? null,
            'oferta.descripcion'  => $c->oferta['descripcion'] ?? null,
            'canales'             => $c->canales ?? [],
            'presupuesto_mxn'     => self::presupuestoDefinido($c) ? (string) $c->budget : null,
            'fecha_inicio'        => $c->start_date,
            'fecha_fin'           => $c->end_date,
        ];

        return array_keys(array_filter($valores, $vacio));
    }

    public static function mensajeFaltantes(array $faltantes): string
    {
        $partes = array_map(fn ($f) => self::REQUISITOS[$f] ?? $f, $faltantes);

        return 'Completa la campaña antes de seguir. Falta: ' . implode(', ', $partes) . '.';
    }

    public static function presupuestoDefinido(Campaign $c): bool
    {
        // Sin la marca (campañas creadas fuera del asistente) el `budget` guardado vale; solo `false` explícito = sin capturar.
        return ($c->config[self::MARCA_PRESUPUESTO] ?? true) === true;
    }

    /** Resumen del índice. */
    public function resumen(Campaign $c, ?Collection $filas = null): array
    {
        $completa = $this->campana($c, $filas);

        return array_intersect_key($completa, array_flip([
            'id', 'nombre', 'estado', 'estado_etiqueta', 'objetivo_etiqueta', 'canales', 'presupuesto_mxn',
            'fecha_inicio', 'fecha_fin', 'generacion', 'videos_resumen',
        ]));
    }

    /** Ficha: Campana360 + piezas por tipo + videos. */
    public function ficha(Campaign $c): array
    {
        $filas = $this->filas($c);
        [$piezas, $videos] = $this->separar($filas);

        $porTipo = array_fill_keys(CatalogosCampana360::PIEZAS_TEXTO, []);
        foreach ($piezas->sortBy(fn ($p) => [$p->pieza, $p->variante, $p->id]) as $p) {
            if (array_key_exists($p->pieza, $porTipo)) {
                $porTipo[$p->pieza][] = $this->pieza($p, $videos);
            }
        }

        return $this->campana($c, $filas) + [
            'piezas' => $porTipo,
            'videos' => $videos->sortByDesc('id')->values()->map(fn ($v) => $this->video($v, (int) $c->id))->all(),
        ];
    }

    /** GET progreso (ligero). */
    public function progreso(Campaign $c): array
    {
        $filas = $this->filas($c);
        [$piezas, $videos] = $this->separar($filas);

        return [
            'ok'                => true,
            'estrategia_estado' => $c->estrategia_estado ?? 'sin_generar',
            'estrategia_error'  => $c->estrategia_error,
            'generacion'        => $this->generacion($c, $piezas),
            'piezas'            => $piezas->sortBy(fn ($p) => [$p->pieza, $p->variante, $p->id])->values()->map(fn ($p) => [
                'id'       => (int) $p->id,
                'pieza'    => $p->pieza,
                'variante' => $p->variante,
                'estado'   => self::ESTADO_PIEZA[$p->status] ?? 'pendiente',
                'error'    => $p->status === 'failed' ? $p->failure_reason : null,
            ])->all(),
            'videos'            => $videos->sortByDesc('id')->values()->map(fn ($v) => $this->video($v, (int) $c->id))->all(),
        ];
    }

    public function pieza(GeneratedContent $p, ?Collection $videos = null): array
    {
        $videos ??= GeneratedContent::query()->where('pieza_origen_id', $p->id)->whereIn('pieza', CatalogosCampana360::PIEZAS_VIDEO)->get(['id', 'pieza_origen_id']);

        return [
            'id'             => (int) $p->id,
            'pieza'          => $p->pieza,
            'pieza_etiqueta' => CatalogosCampana360::ETIQUETAS_PIEZA[$p->pieza] ?? $p->pieza,
            'variante'       => $p->variante,
            'estado'         => self::ESTADO_PIEZA[$p->status] ?? 'pendiente',
            'error'          => $p->status === 'failed' ? $p->failure_reason : null,
            'contenido'      => $p->contenido,
            'texto_plano'    => $p->output_text,
            'editada'        => $p->editado_at !== null,
            'editada_at'     => self::fecha($p->editado_at),
            'regeneraciones' => (int) $p->regeneraciones,
            'actualizada_at' => self::fecha($p->updated_at),
            'video_ids'      => $videos->where('pieza_origen_id', $p->id)->pluck('id')->map(fn ($id) => (int) $id)->sortDesc()->values()->all(),
        ];
    }

    public function video(GeneratedContent $v, int $campanaId): array
    {
        $estado = self::ESTADO_VIDEO[$v->status] ?? 'en_cola';
        $meta   = $v->generation_metadata ?? [];
        $listo  = $estado === 'listo';
        $dur    = $meta['duration_sec'] ?? null;

        return [
            'id'              => (int) $v->id,
            'pieza'           => $v->pieza,
            'guion_id'        => $v->pieza_origen_id ? (int) $v->pieza_origen_id : null,
            'formato'         => $meta['aspect_ratio'] ?? ($v->pieza === 'video' ? '16:9' : '9:16'),
            'estado'          => $estado,
            'estado_etiqueta' => self::ETIQUETA_VIDEO[$estado],
            'error'           => $estado === 'error' ? self::errorVideo($v->failure_reason) : null,
            'sin_voz'         => ($meta['voz_generada'] ?? null) === false,
            'duracion_s'      => is_numeric($dur) && $listo ? number_format((float) $dur, 1, '.', '') : null,
            'url_ver'         => $listo ? "/api/marketing/campanas-360/{$campanaId}/videos/{$v->id}/ver" : null,
            'url_miniatura'   => $listo ? "/api/marketing/campanas-360/{$campanaId}/videos/{$v->id}/miniatura" : null,
            'en_cola_desde'   => self::fecha($v->created_at),
            'demorado'        => $this->demorado($estado, $v),
            'creado_at'       => self::fecha($v->created_at),
        ];
    }

    public function generacion(Campaign $c, Collection $piezas): array
    {
        $total     = $piezas->count();
        $listas    = $piezas->whereIn('status', ['ready', 'completed', 'used'])->count();
        $generando = $piezas->whereIn('status', ['pending', 'generating'])->count();
        $error     = $piezas->where('status', 'failed')->count();
        $estrategia = $c->estrategia_estado ?? 'sin_generar';

        $estado = match (true) {
            $estrategia === 'generando' || $generando > 0       => 'generando',
            $estrategia === 'sin_generar' && $total === 0        => 'sin_generar',
            $estrategia === 'error' || $error > 0                => 'con_errores',
            default                                              => 'lista',
        };

        return [
            'estado'           => $estado,
            'piezas_total'     => $total,
            'piezas_listas'    => $listas,
            'piezas_generando' => $generando,
            'piezas_error'     => $error,
        ];
    }

    public function videosResumen(Collection $videos): array
    {
        $estados = $videos->map(fn ($v) => self::ESTADO_VIDEO[$v->status] ?? 'en_cola');

        return [
            'total'      => $videos->count(),
            'listos'     => $estados->filter(fn ($e) => $e === 'listo')->count(),
            'en_proceso' => $estados->filter(fn ($e) => in_array($e, ['en_cola', 'produciendo'], true))->count(),
            'error'      => $estados->filter(fn ($e) => $e === 'error')->count(),
        ];
    }

    /** Filas vivas de la campaña (piezas de texto y videos), sin las borradas. */
    public function filas(Campaign $c): Collection
    {
        return GeneratedContent::query()
            ->where('source_campaign_id', $c->id)
            ->whereIn('pieza', array_merge(CatalogosCampana360::PIEZAS_TEXTO, CatalogosCampana360::PIEZAS_VIDEO))
            ->get();
    }

    /** @return array{0: Collection, 1: Collection} */
    private function separar(Collection $filas): array
    {
        return [
            $filas->whereIn('pieza', CatalogosCampana360::PIEZAS_TEXTO)->values(),
            $filas->whereIn('pieza', CatalogosCampana360::PIEZAS_VIDEO)->values(),
        ];
    }

    private function demorado(string $estado, GeneratedContent $v): bool
    {
        if ($estado === 'en_cola') {
            return $v->created_at !== null && $v->created_at->lt(now()->subMinutes(self::MINUTOS_DEMORA_COLA));
        }
        if ($estado === 'produciendo') {
            return $v->updated_at !== null && $v->updated_at->lt(now()->subMinutes(self::MINUTOS_DEMORA_PRODUCCION));
        }

        return false;
    }

    /** Mensaje corto en español para el error de un video (nunca la traza). */
    public static function errorVideo(?string $motivo): string
    {
        $m = (string) $motivo;

        return match (true) {
            $m === ''                                   => 'No se pudo producir el video.',
            str_contains($m, 'escenas')                 => 'El guion no trae escenas que el video pueda dibujar.',
            str_contains($m, 'not found') || str_contains($m, 'no existe') => 'La plantilla del video ya no está disponible.',
            str_contains($m, 'Scene') || str_contains($m, 'concat') || str_contains($m, 'encode')
                                                        => 'El motor de video falló al dibujar una escena. Intenta de nuevo.',
            str_contains($m, 'timed out') || str_contains($m, 'tiempo') => 'El video tardó demasiado en producirse. Intenta de nuevo.',
            default                                     => 'No se pudo producir el video. Intenta de nuevo o avísale a Irving.',
        };
    }

    public static function fecha(?CarbonInterface $f): ?string
    {
        return $f?->copy()->setTimezone('America/Mexico_City')->toIso8601String();
    }
}
