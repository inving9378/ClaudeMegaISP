<?php

namespace App\Modules\Addons\Roadmap\Services\JarvisHerramientas;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\ColaCircuitoService;
use App\Modules\Addons\Roadmap\Services\JarvisResumenService;
use App\Modules\Addons\Roadmap\Services\RitmoCircuitoService;
use App\Modules\Addons\Roadmap\Services\SupervisorService;
use App\Modules\Addons\Roadmap\Support\GastoIa;
use App\Modules\Addons\Roadmap\Support\JarvisVigilia;
use App\Services\Core\SaludIA;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #1106 — herramientas de SOLO LECTURA que Jarvis llama bajo demanda (tool use). Cada una
 * devuelve datos ya resumidos y con tope de filas; reusa los cálculos de la Torre y la Panorama.
 * Ninguna escribe, y ninguna lee llaves, Integraciones ni datos de clientes: esa frontera es
 * la LISTA BLANCA de fuentes de abajo (solo `roadmap_items`, `settings` de pausa/paralelismo,
 * logs de gasto y el estado de la vigilia). Toda salida pasa por {@see Redactor}.
 */
class RegistroJarvis
{
    private const TOPE = 15;

    /** Fuentes que las herramientas pueden tocar. Las pruebas verifican que nada más aparece. */
    public const FUENTES = ['roadmap_items', 'settings', 'jarvis_visitas', 'log claude (gasto)', 'jarvis vigilia'];

    private const CERRADOS = ['completado', 'cancelado', 'rechazado'];

    public function __construct(
        private JarvisResumenService $resumen,
        private SupervisorService $supervisor,
        private ColaCircuitoService $cola,
        private RitmoCircuitoService $ritmo,
    ) {
    }

    /** Esquemas en el formato neutro del adaptador de IA. */
    public function esquemas(): array
    {
        $vacio = ['type' => 'object', 'properties' => new \stdClass()];

        return [
            ['nombre' => 'estado_circuito', 'descripcion' => 'Terminales (qué item trabaja cada una y desde cuándo), pausa, por qué está ociosa cada terminal, la cola (tomables, bloqueados por causa, en curso, lo que solo Irving puede destrabar) y el ritmo según la cuota del plan.', 'parametros' => $vacio],
            ['nombre' => 'bandeja_irving', 'descripcion' => 'Items que esperan una decisión o un insumo de Irving, con su motivo.', 'parametros' => $vacio],
            ['nombre' => 'merges_pendientes', 'descripcion' => 'Items que esperan el merge de Irving (mismo conteo que la Panorama).', 'parametros' => $vacio],
            ['nombre' => 'cambios_para_probar', 'descripcion' => 'Cambios ya integrados que esperan que Irving los pruebe.', 'parametros' => $vacio],
            ['nombre' => 'item', 'descripcion' => 'Detalle de un item por su número: título, estado, nivel, módulo, rama, merge, padre/hijos, de qué depende y su historial reciente.',
                'parametros' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
            ['nombre' => 'items_por_modulo', 'descripcion' => 'Lista corta de items de un módulo, opcionalmente por estado.',
                'parametros' => ['type' => 'object', 'properties' => ['modulo' => ['type' => 'string'], 'estado' => ['type' => 'string']], 'required' => ['modulo']]],
            ['nombre' => 'actividad_reciente', 'descripcion' => 'Merges, cierres y escalaciones desde una fecha (Y-m-d o Y-m-d H:i); sin fecha, las últimas 24 horas.',
                'parametros' => ['type' => 'object', 'properties' => ['desde' => ['type' => 'string']]]],
            ['nombre' => 'gasto_ia', 'descripcion' => 'Gasto de IA del supervisor por pieza, en pesos. Rango: hoy, semana o mes.',
                'parametros' => ['type' => 'object', 'properties' => ['rango' => ['type' => 'string', 'enum' => ['hoy', 'semana', 'mes']]]]],
            ['nombre' => 'alertas', 'descripcion' => 'Alertas vivas del sistema: vigilia de Jarvis e IA sin acceso.', 'parametros' => $vacio],
        ];
    }

    public function nombres(): array
    {
        return array_column($this->esquemas(), 'nombre');
    }

    /** Ejecuta una herramienta; devuelve el JSON ya redactado y acotado. Nunca lanza. */
    public function ejecutar(string $nombre, array $args): string
    {
        try {
            $r = match ($nombre) {
                'estado_circuito'    => $this->estadoCircuito(),
                'bandeja_irving'     => $this->bandeja(),
                'merges_pendientes'  => $this->merges(),
                'cambios_para_probar' => $this->paraProbar(),
                'item'               => $this->item((int) ($args['id'] ?? 0)),
                'items_por_modulo'   => $this->porModulo((string) ($args['modulo'] ?? ''), $args['estado'] ?? null),
                'actividad_reciente' => $this->actividad($args['desde'] ?? null),
                'gasto_ia'           => $this->gasto((string) ($args['rango'] ?? 'hoy')),
                'alertas'            => $this->alertas(),
                default              => ['no_lo_se' => true, 'motivo' => 'Esa herramienta no existe.'],
            };
        } catch (Throwable) {
            $r = ['no_lo_se' => true, 'motivo' => 'No pude leer ese dato ahora.'];
        }

        return mb_substr((string) json_encode(Redactor::datos($r), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 6000);
    }

    private function vivos()
    {
        return RoadmapItem::query()->whereNull('archivado_at');
    }

    private function fila($i): array
    {
        return ['id' => $i->id, 'titulo' => mb_substr((string) $i->title, 0, 90), 'estado' => $i->estado_aprobacion];
    }

    private function estadoCircuito(): array
    {
        $terminales = $this->vivos()->where('estado_aprobacion', 'en_progreso')->whereNotNull('worker_sid')
            ->orderBy('worker_sid')->limit(self::TOPE)->get(['id', 'title', 'worker_sid', 'claimed_at', 'estado_aprobacion'])
            ->map(fn ($i) => ['terminal' => $i->worker_sid, 'item' => $this->fila($i), 'desde' => $i->claimed_at?->format('d-m-Y H:i')])->all();

        $ociosas = [];
        try {
            $ociosas = array_map(fn ($o) => ['terminal' => $o['sid'], 'motivo' => $o['motivo']], $this->supervisor->motivosOciosasAhora());
        } catch (Throwable) {
        }

        return [
            'ahora'              => now()->format('d-m-Y H:i'),
            'en_pausa'           => (bool) (DB::table('settings')->where('key', 'circuito_pausado')->value('value') ?? false),
            'terminales_activas' => (int) (DB::table('settings')->where('key', 'circuito_paralelismo')->value('value') ?? 0),
            'terminales_trabajando' => $terminales,
            'terminales_ociosas' => $ociosas,
            // Limpieza de la Hoja de ruta — la MISMA cola que usa el despachador y pinta la Torre.
            'cola'               => $this->cola->resumen(),
            'ritmo'              => $this->ritmo->ultimo() ?? $this->ritmo->limite(),
        ];
    }

    private function bandeja(): array
    {
        $arma = fn ($q) => ['total' => (clone $q)->count(), 'items' => (clone $q)->limit(self::TOPE)
            ->get(['id', 'title', 'estado_aprobacion', 'motivo_espera', 'motivo_bloqueo'])
            ->map(fn ($i) => $this->fila($i) + ['motivo' => $i->motivo_espera ?: $i->motivo_bloqueo])->all()];

        return ['decisiones' => $arma(RoadmapItem::query()->bandejaDecision()), 'insumos' => $arma(RoadmapItem::query()->bandejaInsumo())];
    }

    private function merges(): array
    {
        $q = $this->vivos()->where('esperando_merge_irving', true)->whereNull('merge_commit')->whereNotIn('estado_aprobacion', self::CERRADOS);

        return ['total' => (clone $q)->count(), 'items' => $q->limit(self::TOPE)->get(['id', 'title', 'estado_aprobacion', 'branch'])
            ->map(fn ($i) => $this->fila($i) + ['rama' => $i->branch])->all()];
    }

    private function paraProbar(): array
    {
        $q = RoadmapItem::query()->pendienteValidacion();

        return ['total' => (clone $q)->count(), 'items' => $q->limit(self::TOPE)->get(['id', 'title', 'estado_aprobacion', 'enlace_revision'])
            ->map(fn ($i) => $this->fila($i) + ['donde_verlo' => $i->enlace_revision])->all()];
    }

    private function item(int $id): array
    {
        $i = $id > 0 ? RoadmapItem::find($id) : null;
        if (! $i) {
            return ['no_lo_se' => true, 'motivo' => "No encuentro el item #{$id}."];
        }

        $deps = collect((array) $i->depende_de)->map(fn ($d) => (int) $d)->filter()->take(self::TOPE)->values();
        $espera = $deps->isEmpty() ? [] : RoadmapItem::whereIn('id', $deps)->whereNotIn('estado_aprobacion', self::CERRADOS)->pluck('id')->all();
        $hijos = RoadmapItem::where('origen_item_id', $i->id)->limit(self::TOPE)->get(['id', 'title', 'estado_aprobacion'])->map(fn ($h) => $this->fila($h))->all();
        $historial = collect((array) $i->log)->take(-6)->map(fn ($e) => array_filter([
            'cuando' => isset($e['ts']) ? substr((string) $e['ts'], 0, 16) : null,
            'quien'  => $e['por'] ?? null,
            'que'    => $e['evento'] ?? $e['decision'] ?? $e['tipo'] ?? null,
            'nota'   => isset($e['comentario']) ? mb_substr((string) $e['comentario'], 0, 160) : null,
        ]))->values()->all();

        return [
            'id' => $i->id, 'titulo' => $i->title, 'estado' => $i->estado_aprobacion, 'nivel' => $i->nivel_riesgo,
            'modulo' => $i->modulo, 'rama' => $i->branch, 'merge_commit' => $i->merge_commit ? substr((string) $i->merge_commit, 0, 10) : null,
            'terminal' => $i->worker_sid, 'espera_merge_de_irving' => (bool) $i->esperando_merge_irving,
            'motivo_espera' => $i->motivo_espera ?: $i->motivo_bloqueo,
            'padre' => $i->origen_item_id, 'hijos' => $hijos,
            'depende_de' => $deps->all(), 'espera_a_que_cierren' => $espera,
            'comentarios' => mb_substr((string) $i->comentarios_claude, -500),
            'historial_reciente' => $historial,
        ];
    }

    private function porModulo(string $modulo, ?string $estado): array
    {
        $modulo = trim($modulo);
        if ($modulo === '') {
            return ['no_lo_se' => true, 'motivo' => 'Falta el módulo.'];
        }
        $q = $this->vivos()->where(fn ($w) => $w->where('modulo', 'like', '%' . addcslashes($modulo, '%_\\') . '%')->orWhere('modulo_slug', $modulo));
        if ($estado) {
            $q->where('estado_aprobacion', $estado);
        }

        return ['total' => (clone $q)->count(), 'items' => $q->orderByDesc('id')->limit(self::TOPE)->get(['id', 'title', 'estado_aprobacion'])->map(fn ($i) => $this->fila($i))->all()];
    }

    private function actividad(?string $desde): array
    {
        try {
            $d = $desde ? Carbon::parse($desde) : now()->subDay();
        } catch (Throwable) {
            return ['no_lo_se' => true, 'motivo' => 'No entendí esa fecha.'];
        }
        $lista = fn ($q) => ['total' => (clone $q)->count(), 'items' => $q->orderByDesc('updated_at')->limit(self::TOPE)->get(['id', 'title', 'estado_aprobacion'])->map(fn ($i) => $this->fila($i))->all()];

        return [
            'desde'       => $d->format('d-m-Y H:i'),
            'cierres'     => $lista($this->vivos()->where('estado_aprobacion', 'completado')->where('updated_at', '>=', $d)),
            'merges'      => $lista($this->vivos()->whereNotNull('merge_commit')->where('updated_at', '>=', $d)),
            'escalaciones' => $lista($this->vivos()->where('estado_aprobacion', 'requiere_irving')->where('updated_at', '>=', $d)),
        ];
    }

    private function gasto(string $rango): array
    {
        $dias = ['hoy' => 0, 'semana' => 6, 'mes' => 29][$rango] ?? 0;
        $filas = GastoIa::sumar(GastoIa::lineas(storage_path('logs'), now()->subDays($dias)->format('Y-m-d'), now()->format('Y-m-d')));
        $porPieza = [];
        foreach ($filas as $f) {
            $porPieza[$f['pieza']] = ($porPieza[$f['pieza']] ?? 0) + $f['mxn'];
        }
        arsort($porPieza);

        return ['rango' => $rango, 'total_mxn' => round(array_sum($porPieza), 2), 'por_pieza_mxn' => array_map(fn ($v) => round($v, 2), array_slice($porPieza, 0, 12, true))];
    }

    private function alertas(): array
    {
        $vig = JarvisVigilia::estado();
        $ciega = [];
        try {
            $ciega = array_values(array_map(fn ($f) => $f['texto'], SaludIA::ciegas()));
        } catch (Throwable) {
        }

        return ['vigilia' => array_slice((array) ($vig['alertas'] ?? []), 0, self::TOPE), 'ia_sin_acceso' => $ciega];
    }
}
