<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use App\Modules\Addons\Talento\Services\CorreccionEvidenciasService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Item #1285 — todo lo que documenta una orden, para revisarla desde Talento › Órdenes de trabajo ›
 * «Ver»: evidencias obligatorias (cumplidas o faltantes), fotos, lecturas de dBm, nota del técnico,
 * incidencias y cronología.
 *
 * Acceso: el de TalentoWorkOrderController::show (permiso talento.work_orders.view y, si no es
 * personal de órdenes, que la orden sea suya o de su equipo). Las fotos además exigen
 * talento.media.view, y las sensibles talento.media.view_sensitive; la imagen la sirve la ruta que
 * ya existía (talento.media.serve), que vuelve a revisar los permisos.
 */
class TalentoOrdenDocumentacionController extends Controller
{
    /** Motivos de «No puedo completar» (TalentoMobileApiController valida estas claves). */
    private const MOTIVOS = [
        'cliente_ausente' => 'Cliente ausente',
        'sin_acceso'      => 'Sin acceso',
        'falta_material'  => 'Falta material',
        'olt_sin_senal'   => 'OLT sin señal',
        'riesgo'          => 'Riesgo',
        'otro'            => 'Otro',
    ];

    public function index($id): JsonResponse
    {
        // Misma regla de acceso y misma resolución de la referencia que el detalle: 403/404/409/422
        // salen de ahí tal cual.
        $detalle = app(TalentoWorkOrderController::class)->show($id)->getData(true);
        $esTarea = ($detalle['_source'] ?? null) === 'task';
        $numero = (int) ($detalle['id'] ?? 0);
        $fk = $esTarea ? 'tarea_id' : 'work_order_id';
        $fila = $esTarea ? DB::table('tasks')->where('id', $numero)->first() : DB::table('talento_work_orders')->where('id', $numero)->first();
        $tipoId = $esTarea ? ($fila->talento_type_id ?? null) : ($fila->type_id ?? null);

        $usuario = auth()->user();
        $verFotos = (bool) $usuario?->can('talento.media.view');
        $verSensibles = (bool) $usuario?->can('talento.media.view_sensitive');

        $media = TalentoWorkOrderMedia::where($fk, $numero)->orderBy('captured_at')->orderBy('id')->get();
        $tipos = DB::table('talento_evidence_types')->pluck('name', 'id');

        $fotos = $verFotos ? $media->map(fn (TalentoWorkOrderMedia $m) => [
            'id'             => $m->id,
            'tipo'           => $m->evidence_type_id ? ($tipos[$m->evidence_type_id] ?? null) : null,
            'tipo_interno'   => $m->type,
            'capturada'      => $this->iso($m->captured_at ?? $m->created_at),
            'potencia_dbm'   => $m->potencia_dbm,
            'justificacion'  => $m->justificacion,
            'fuera_de_lugar' => (bool) $m->location_flagged,
            'distancia_m'    => $m->location_distance_m,
            'restringida'    => $m->isSensitive() && ! $verSensibles,
            'url'            => ($m->isSensitive() && ! $verSensibles) ? null : route('talento.media.serve', ['id' => $m->id]),
            // #1286: foto de corrección (null = de cuando se hizo la orden).
            'correccion_id'  => $m->correccion_id ? (int) $m->correccion_id : null,
        ])->values() : null;

        // #1286: correcciones pedidas por el supervisor sobre la orden terminada.
        $correcciones = $esTarea ? array_map(fn ($c) => array_merge($c, [
            'pedida_at'  => $this->iso($c['pedida_at']),
            'enviada_at' => $this->iso($c['enviada_at']),
            'cerrada_at' => $this->iso($c['cerrada_at']),
        ]), app(CorreccionEvidenciasService::class)->historial($numero)) : [];

        return response()->json([
            'puede_ver_fotos' => $verFotos,
            'fotos'           => $fotos,
            // Lo obligatorio se cumple con las fotos de cuando se hizo la orden, no con las de corrección.
            'requeridas'      => $this->requeridas($tipoId, $media->filter(fn ($m) => ! $m->correccion_id)),
            'lecturas_dbm'    => $this->lecturas($media),
            'nota_tecnico'    => $this->texto($fila->nota_tecnico ?? null),
            'incidencias'     => $this->incidencias($fk, $numero),
            'correcciones'    => $correcciones,
            'cronologia'      => array_merge($this->cronologia($fila, $esTarea), $this->pasosDeCorreccion($correcciones)),
        ]);
    }

    /** #1286: pedida, enviada y cerrada de cada corrección, para la cronología. */
    private function pasosDeCorreccion(array $correcciones): array
    {
        $cierres = ['aceptada' => 'Corrección aceptada', 'cancelada' => 'Corrección cancelada', 'reemplazada' => 'Se pidió otra corrección'];
        $pasos = [];
        foreach ($correcciones as $c) {
            $pasos[] = ['paso' => 'Corrección pedida', 'fecha' => $c['pedida_at'], 'quien' => $c['pedida_por']];
            if ($c['enviada_at']) {
                $pasos[] = ['paso' => 'Corrección enviada por el técnico', 'fecha' => $c['enviada_at'], 'quien' => null];
            }
            if ($c['cerrada_at'] && $c['cierre'] !== 'reemplazada') {
                $pasos[] = ['paso' => $cierres[$c['cierre']] ?? 'Corrección cerrada', 'fecha' => $c['cerrada_at'], 'quien' => $c['cerrada_por']];
            }
        }

        return $pasos;
    }

    /** Evidencias que exige el tipo de orden y cuántas fotos de cada una hay. */
    private function requeridas(?int $tipoId, $media): array
    {
        if (! $tipoId) {
            return [];
        }
        $porTipo = $media->groupBy('evidence_type_id')->map->count();

        return DB::table('talento_ot_type_evidence_requirements as r')
            ->join('talento_evidence_types as e', 'e.id', '=', 'r.evidence_type_id')
            ->where('r.ot_type_id', $tipoId)
            ->orderBy('e.sort_order')->orderBy('e.id')
            ->get(['e.id', 'e.name', 'e.es_firma', 'e.es_lectura_dbm', 'r.condition'])
            ->map(fn ($r) => [
                'id'             => $r->id,
                'nombre'         => $r->name,
                'es_firma'       => (bool) $r->es_firma,
                'es_lectura_dbm' => (bool) $r->es_lectura_dbm,
                'condicion'      => $r->condition,
                'fotos'          => (int) ($porTipo[$r->id] ?? 0),
                'cumplida'       => ($porTipo[$r->id] ?? 0) > 0,
            ])->values()->all();
    }

    /** Lecturas de potencia de las fotos, con su categoría según talento_dbm_thresholds. */
    private function lecturas($media): array
    {
        return $media->filter(fn ($m) => $m->potencia_dbm !== null)->map(function ($m) {
            $umbral = DB::table('talento_dbm_thresholds')
                ->where(fn ($q) => $q->whereNull('dbm_min')->orWhere('dbm_min', '<=', $m->potencia_dbm))
                ->where(fn ($q) => $q->whereNull('dbm_max')->orWhere('dbm_max', '>=', $m->potencia_dbm))
                ->orderByDesc('id')->first(['categoria', 'mensaje']);

            return [
                'foto_id'   => $m->id,
                'valor'     => (float) $m->potencia_dbm,
                'categoria' => $umbral->categoria ?? null,
                'mensaje'   => $umbral->mensaje ?? null,
                'capturada' => $this->iso($m->captured_at ?? $m->created_at),
                'correccion' => (bool) $m->correccion_id,
            ];
        })->values()->all();
    }

    private function incidencias(string $fk, int $numero): array
    {
        return DB::table('talento_work_order_incidents')->where($fk, $numero)->orderBy('id')
            ->get(['motivo', 'nota', 'server_at', 'created_at', 'created_by'])
            ->map(fn ($i) => [
                'motivo' => self::MOTIVOS[$i->motivo] ?? $i->motivo,
                'nota'   => $this->texto($i->nota),
                'fecha'  => $this->iso($i->server_at ?? $i->created_at),
                'quien'  => $this->nombre($i->created_by),
            ])->values()->all();
    }

    /** Creada, terminada y validada, con quién; solo lo que exista en la orden. */
    private function cronologia(?object $f, bool $esTarea): array
    {
        if (! $f) {
            return [];
        }
        $pasos = [
            ['paso' => 'Creada', 'fecha' => $f->created_at ?? null, 'quien' => $f->created_by ?? null],
            ['paso' => 'Terminada por el técnico', 'fecha' => $esTarea ? ($f->finish_at ?? null) : ($f->completed_at ?? null), 'quien' => $f->completed_by ?? null],
            ['paso' => 'Validada', 'fecha' => $f->validated_at ?? null, 'quien' => $f->validated_by ?? null],
        ];
        if ($esTarea && ! empty($f->finish_at_first_time) && $f->finish_at_first_time !== ($f->finish_at ?? null)) {
            array_splice($pasos, 1, 0, [['paso' => 'Terminada por primera vez', 'fecha' => $f->finish_at_first_time, 'quien' => null]]);
        }

        return array_values(array_map(fn ($p) => [
            'paso'  => $p['paso'],
            'fecha' => $this->iso($p['fecha']),
            'quien' => $this->nombre($p['quien']),
        ], array_filter($pasos, fn ($p) => ! empty($p['fecha']))));
    }

    private function nombre($userId): ?string
    {
        if (! $userId || ! is_numeric($userId)) {
            return null;
        }
        $u = DB::table('users')->where('id', $userId)->first(['name', 'father_last_name']);

        return $u ? trim($u->name . ' ' . ($u->father_last_name ?? '')) : null;
    }

    private function iso($valor): ?string
    {
        if (! $valor) {
            return null;
        }

        return ($valor instanceof \DateTimeInterface ? Carbon::instance($valor) : Carbon::parse($valor))->toIso8601String();
    }

    private function texto($valor): ?string
    {
        $t = trim((string) $valor);

        return $t === '' ? null : $t;
    }
}
