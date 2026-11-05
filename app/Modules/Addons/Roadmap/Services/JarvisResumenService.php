<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Services\Core\SaludIA;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #1068 — «¿En qué nos quedamos?». Arma, SOLO con datos de la base, el estado real del circuito
 * (qué terminó desde la última visita, qué se trabaja, qué espera a Irving) y un resumen en
 * lenguaje llano de unos 20 segundos. El chat de Jarvis responde preguntas con este mismo estado:
 * nada se inventa ni se calcula fuera de aquí.
 */
class JarvisResumenService
{
    private const TOPE_LISTA = 8;

    public function __construct(private SupervisorService $supervisor, private CuotaPlanService $cuota)
    {
    }

    /** Guarda la visita de ahora y devuelve la anterior (24 h atrás si es la primera). */
    public function registrarVisita(int $userId): Carbon
    {
        $previa = DB::table('jarvis_visitas')->where('user_id', $userId)->value('visto_at');
        $previa
            ? DB::table('jarvis_visitas')->where('user_id', $userId)->update(['visto_at' => now(), 'updated_at' => now()])
            : DB::table('jarvis_visitas')->insert(['user_id' => $userId, 'visto_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return $previa ? Carbon::parse($previa) : now()->subDay();
    }

    /** Estado real del circuito, compacto, para el resumen y para las respuestas del chat. */
    public function estado(Carbon $desde): array
    {
        $vivos = fn () => RoadmapItem::query()->whereNull('archivado_at');
        $lista = fn ($q) => $q->limit(self::TOPE_LISTA)->get(['id', 'title'])
            ->map(fn ($i) => ['id' => $i->id, 'titulo' => $this->titulo($i->title)])->all();

        $terminados = $vivos()->where('estado_aprobacion', 'completado')->where('updated_at', '>=', $desde);
        $enCurso    = $vivos()->where('estado_aprobacion', 'en_progreso');
        $decisiones = RoadmapItem::query()->bandejaDecision();
        $insumos    = RoadmapItem::query()->bandejaInsumo();
        // Misma regla que la tarjeta «Esperan tu merge» de la Panorama.
        $merges     = app(MergesPendientesService::class)->listar(self::TOPE_LISTA);

        $ociosas = [];
        try {
            $ociosas = $this->supervisor->motivosOciosasAhora();
        } catch (Throwable) {
        }
        $ciega = [];
        try {
            $ciega = array_map(fn ($f) => $f['texto'], SaludIA::ciegas());
        } catch (Throwable) {
        }

        return [
            'hora_actual'    => now()->format('d-m-Y H:i'),
            'desde'          => $desde->format('d-m-Y H:i'),
            'terminados'     => ['total' => (clone $terminados)->count(), 'ejemplos' => $lista((clone $terminados)->orderByDesc('updated_at'))],
            'en_curso'       => ['total' => (clone $enCurso)->count(), 'items' => $lista((clone $enCurso)->orderBy('claimed_at'))],
            'esperan_decision' => ['total' => (clone $decisiones)->count(), 'items' => $lista((clone $decisiones)->orderByRaw("FIELD(priority, 'alta', 'media', 'baja')"))],
            'esperan_insumo' => ['total' => (clone $insumos)->count(), 'items' => $lista(clone $insumos)],
            'esperan_merge'  => ['total' => $merges['total'], 'items' => array_map(
                fn ($m) => ['id' => $m['id'], 'titulo' => $this->titulo($m['title'])], $merges['items'])],
            'terminales'     => [
                'activas'  => (int) (DB::table('settings')->where('key', 'circuito_paralelismo')->value('value') ?? 0),
                'en_pausa' => (bool) (DB::table('settings')->where('key', 'circuito_pausado')->value('value') ?? false),
                'ocupadas' => (clone $enCurso)->whereNotNull('worker_sid')->count(),
                'ociosas'  => array_map(fn ($o) => $o['sid'] . ': ' . $o['motivo'], $ociosas),
            ],
            'ia_ciega'       => array_values($ciega),
            // Uso de la suscripción de Claude de las terminales; null = no hay medición todavía.
            'cuota_plan'     => $this->cuota->leer(),
            // La misma cola del despachador: tomables, bloqueados por causa, en curso y lo que solo Irving destraba.
            'cola'           => app(ColaCircuitoService::class)->resumen(),
        ];
    }

    /** Unos 20 segundos de lectura, en lenguaje llano y con números de la base. */
    public function resumen(array $e): string
    {
        $partes = [];
        $t = $e['terminados'];
        $partes[] = $t['total'] === 0
            ? "Desde tu última visita ({$e['desde']}) no se terminó nada nuevo."
            : "Desde tu última visita ({$e['desde']}) se terminaron {$t['total']} pendientes"
                . ($t['ejemplos'] ? ', entre ellos ' . $this->nombrar(array_slice($t['ejemplos'], 0, 2)) : '') . '.';

        $c = $e['en_curso'];
        $partes[] = match (true) {
            $c['total'] > 0               => "Ahora se trabaja en {$c['total']}: " . $this->nombrar(array_slice($c['items'], 0, 3)) . '.',
            $e['terminales']['en_pausa']  => 'Ahora ninguna terminal trabaja porque el circuito está en pausa.',
            default                       => 'Ahora ninguna terminal está trabajando: no hay pendientes listos para tomar, '
                                             . 'lo que queda espera una decisión o que termine otra parte.',
        };

        $espera = array_filter([
            $e['esperan_decision']['total'] ? $e['esperan_decision']['total'] . ' ' . ($e['esperan_decision']['total'] === 1 ? 'decisión' : 'decisiones') : null,
            $e['esperan_merge']['total'] ? $e['esperan_merge']['total'] . ' por mergear' : null,
            $e['esperan_insumo']['total'] ? $e['esperan_insumo']['total'] . ' que esperan algo que solo tú puedes dar' : null,
        ]);
        $partes[] = $espera ? 'Te esperan ' . implode(', ', $espera) . '.' : 'No hay nada esperándote.';

        if ($e['ia_ciega']) {
            $partes[] = 'Ojo: la IA del sistema no está respondiendo (' . implode('; ', $e['ia_ciega']) . ').';
        }

        return implode(' ', $partes);
    }

    /** Título corto y sin prefijos internos (UNI-T B7a ·, [FAC-07], etc.). */
    private function titulo(?string $t): string
    {
        $t = trim((string) preg_replace('/^(\[[^\]]+\]\s*|[A-Z][A-Z0-9-]*(?:\s[A-Z0-9][A-Za-z0-9]*)?\s·\s)+/u', '', (string) $t));

        return mb_strimwidth($t, 0, 70, '…');
    }

    private function nombrar(array $items): string
    {
        return implode(' y ', array_map(fn ($i) => "#{$i['id']} ({$i['titulo']})", $items));
    }
}
