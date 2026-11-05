<?php

namespace App\Modules\Addons\Roadmap\Services\JarvisPropuestas;

use App\Modules\Addons\Roadmap\Models\JarvisPropuesta;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\FronterasService;
use App\Modules\Addons\Roadmap\Services\JarvisHerramientas\Redactor;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use App\Modules\Addons\Roadmap\Services\RoadmapDuplicadoService;
use App\Modules\Addons\Roadmap\Services\RoadmapIntakeService;
use App\Modules\Addons\Roadmap\Services\RoadmapReportService;
use App\Modules\Addons\Roadmap\Services\SeguimientoItemService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #1110 — Fase 2 de #1068: Jarvis solo PROPONE items. Aquí vive todo lo que el modelo no hace:
 * guardar el borrador con su huella, endurecer el nivel con las fronteras duras, detectar duplicados
 * y secretos, y CREAR (únicamente desde `crear()`, con la huella vigente, tras el clic de Irving).
 */
class PropuestaService
{
    public const PERMISO = 'roadmap.jarvis.crear_items';
    public const AUTOR = 'Jarvis (chat)';
    public const MAX_ITEMS = 12; // #1071: una épica se aprueba en bloque (antes 5)
    public const MAX_POR_HORA = 15;
    public const MINUTOS_DESHACER = 10;

    private const NIVELES = ['A' => 1, 'B' => 2, 'C' => 3];

    public function __construct(
        private RoadmapIntakeService $intake,
        private RoadmapDuplicadoService $duplicados,
        private FronterasService $fronteras,
        private SeguimientoItemService $seguimiento,
        private RoadmapReportService $reportes,
    ) {
    }

    /** Esquema de la ÚNICA herramienta de escritura del modelo: solo devuelve el borrador. */
    public static function esquemaProponer(): array
    {
        $item = ['type' => 'object', 'properties' => [
            'titulo' => ['type' => 'string'], 'que_cambia' => ['type' => 'string'], 'donde' => ['type' => 'string'],
            'como_se_valida' => ['type' => 'string'], 'modulo' => ['type' => 'string'],
            'nivel_riesgo' => ['type' => 'string', 'enum' => ['A', 'B', 'C']],
            'prioridad' => ['type' => 'string', 'enum' => ['alta', 'media', 'baja']],
            'al_inicio' => ['type' => 'boolean'],
            'opcion_elegida' => ['type' => 'string'],
        ], 'required' => ['titulo', 'que_cambia', 'donde', 'como_se_valida']];

        return [
            'nombre' => 'proponer_items',
            'descripcion' => 'SOLO cuando Irving te pide en su mensaje crear un item (o varios de un proyecto, máximo 5): devuelve el borrador. '
                . 'No crea nada; Irving lo revisa y pulsa el botón. Si es realmente ambiguo, manda solo `pregunta` (una).',
            'parametros' => ['type' => 'object', 'properties' => [
                'pregunta' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => $item, 'maxItems' => self::MAX_ITEMS],
            ]],
        ];
    }

    /** ¿El mensaje de Irving pide crear un item? Una propuesta solo nace de aquí, nunca de lo leído. */
    public static function pideCrear(string $mensaje): bool
    {
        return (bool) preg_match('/\b(cre[ao]s?|crear|crea(?:r|me|le)?|agrega(?:r|me)?|a[ñn]ade|a[ñn]adir|abre|abrir|registra(?:r)?|levanta(?:r)?|genera(?:r)?|mete|meter|pon(?:er|me)?|quiero)\b[^.?!]{0,80}\b(item|items|ítem|ítems|pendiente|pendientes|tarea|tareas|proyecto|[eé]pica|[eé]picas)\b/iu', $mensaje);
    }

    // ── Propuesta ──────────────────────────────────────────────────────────────────────────────

    /** Guarda lo que el modelo propuso (ya con filtros). Devuelve null si no hay nada que proponer. */
    public function registrar(int $userId, int $conversacionId, ?int $mensajeId, array $args): ?JarvisPropuesta
    {
        $pregunta = trim((string) ($args['pregunta'] ?? ''));
        $items = $pregunta !== '' ? [] : $this->normalizar((array) ($args['items'] ?? []), []);
        if ($pregunta === '' && $items === []) {
            return null;
        }

        return JarvisPropuesta::create([
            'user_id' => $userId, 'conversacion_id' => $conversacionId, 'mensaje_id' => $mensajeId,
            'borrador' => $items, 'pregunta' => $pregunta !== '' ? mb_substr($pregunta, 0, 500) : null,
            'huella' => self::huella($items), 'version' => 1, 'estado' => 'propuesta',
        ]);
    }

    public function editar(JarvisPropuesta $p, string $huella, array $items): JarvisPropuesta
    {
        $this->exigirVigente($p, $huella);
        $nuevo = $this->normalizar($items, $p->borrador);
        if ($nuevo === []) {
            throw new PropuestaException('vacia', 'La propuesta no puede quedar sin items; si ya no la quieres, descártala.', 422);
        }
        $p->historial = array_merge($p->historial ?? [], [['version' => $p->version, 'borrador' => $p->borrador, 'at' => now()->toIso8601String()]]);
        $p->fill(['borrador' => $nuevo, 'huella' => self::huella($nuevo), 'version' => $p->version + 1, 'estado' => 'editada'])->save();

        return $p;
    }

    public function descartar(JarvisPropuesta $p, string $huella): void
    {
        $this->exigirVigente($p, $huella);
        $p->update(['estado' => 'descartada']);
    }

    public function presentar(JarvisPropuesta $p): array
    {
        return [
            'id' => $p->id, 'huella' => $p->huella, 'estado' => $p->estado,
            'pregunta' => $p->pregunta,
            'avisos' => $this->avisos($p->borrador ?? []),
            'items' => array_map(fn (array $i) => array_diff_key($i, ['_secreto' => 1, '_nivel_jarvis' => 1]), $p->borrador ?? []),
        ];
    }

    // ── Crear ──────────────────────────────────────────────────────────────────────────────────

    public function crear(JarvisPropuesta $p, object $usuario, string $huella, bool $alInicio, array $confirmadosC): array
    {
        $this->exigirVigente($p, $huella);
        $confirmados = array_map('intval', $confirmadosC);

        return DB::transaction(function () use ($p, $usuario, $alInicio, $confirmados) {
            $p = JarvisPropuesta::whereKey($p->id)->lockForUpdate()->first();
            $hechos = collect($p->item_ids ?? [])->keyBy('indice');
            $porCrear = collect($p->borrador)->filter(fn ($i) => ! $hechos->has($i['indice']));

            if ($this->creadosUltimaHora((int) $usuario->id) + $porCrear->count() > self::MAX_POR_HORA) {
                throw new PropuestaException('limite', 'Ya se crearon muchos items desde el chat en la última hora (máximo ' . self::MAX_POR_HORA . '). Intenta más tarde.', 429);
            }

            $creados = $requieren = $rechazados = [];
            foreach ($porCrear as $i) {
                if (! empty($i['_secreto'])) {
                    $rechazados[] = ['indice' => $i['indice'], 'motivo' => 'El texto trae algo que parece una llave, contraseña o dato personal; no lo creé. Quítalo y vuelve a intentar.'];
                    continue;
                }
                if (! empty($i['requiere_segunda_confirmacion']) && ! in_array($i['indice'], $confirmados, true)) {
                    $requieren[] = ['indice' => $i['indice'], 'motivo' => $i['motivo_confirmacion']];
                    continue;
                }
                $item = $this->crearItem($i, $usuario, $alInicio || ! empty($i['al_inicio']), $p);
                $hechos->put($i['indice'], ['indice' => $i['indice'], 'id' => $item->id, 'creado_at' => now()->toIso8601String()]);
                $creados[] = $this->filaCreada($i['indice'], $item, $i['titulo']);
            }

            $pendientes = collect($p->borrador)->count() - $hechos->count();
            $p->item_ids = $hechos->values()->all();
            if ($pendientes === 0) {
                $p->estado = 'creada';
            }
            $p->save();

            $n = count($creados);

            return [
                'ok' => true,
                'mensaje' => $n > 0 ? ($n === 1 ? 'Creado #' . $creados[0]['id'] . '.' : 'Creados ' . implode(', ', array_map(fn ($c) => '#' . $c['id'], $creados)) . '.')
                    : ($requieren !== [] ? 'Falta tu segunda confirmación para seguir.' : 'No se creó nada.'),
                'creados' => $creados, 'requieren_confirmacion' => $requieren, 'rechazados' => $rechazados,
            ];
        });
    }

    private function crearItem(array $i, object $usuario, bool $alInicio, JarvisPropuesta $p): RoadmapItem
    {
        $sensible = ! empty($i['requiere_segunda_confirmacion']);
        $nivel = $sensible ? 'C' : $i['nivel_riesgo'];
        $cuerpo = "## Qué cambia\n{$i['que_cambia']}\n\n## Dónde\n{$i['donde']}\n\n## Cómo se valida\n{$i['como_se_valida']}\n\n"
            . "_Pedido por conversación con Jarvis (propuesta #{$p->id}, conversación #{$p->conversacion_id})._";

        $item = $this->intake->crear([
            'title' => $i['titulo'],
            'description' => $i['que_cambia'] . "\n\nDónde: " . $i['donde'],
            'prompt' => RoadmapItem::conCanalDeRespuesta($cuerpo),
            'modulo' => $i['modulo'] ?: null,
            'nivel_riesgo' => $nivel,
            'priority' => $i['prioridad'],
        ], self::AUTOR, true);

        $aprobador = 'irving:jarvis-chat:' . ($usuario->login_user ?? $usuario->id);
        $item->reporte_coloquial = mb_substr($i['titulo'] . '. ' . $i['que_cambia'], 0, 600);
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => self::AUTOR, 'evento' => 'creado_desde_jarvis_chat',
            'propuesta' => $p->id, 'conversacion' => $p->conversacion_id, 'user_id' => (int) $usuario->id, 'version' => $p->version, 'sensible' => $sensible];

        if ($sensible) {
            // C o sensible: jamás entra solo a la cola; Irving decide aparte.
            $item->estado_aprobacion = 'requiere_irving';
            if (! empty($i['opcion_elegida'])) {
                $item->opcion_elegida = mb_substr($i['opcion_elegida'], 0, 255);
                $item->decision_resumen = 'Opción elegida por Irving en la conversación: ' . mb_substr($i['opcion_elegida'], 0, 255);
            }
            $item->log = $log;
            $item->save();

            return $item->refresh();
        }

        $item->estado_aprobacion = 'aprobado_irving';
        $item->aprobado_por = $aprobador;
        $item->revisado_at = now();
        $item->log = $log;
        $item->save();

        if ($alInicio) {
            $this->seguimiento->meterAlCircuito($item->refresh(), [], $aprobador);
            try {
                if ($usuario->can('circuito.disparar')) {
                    app(RoadmapCircuitoService::class)->requestDisparo($aprobador, 'urgente', $item->id);
                }
            } catch (Throwable) {
            }
        }

        return $item->refresh();
    }

    private function filaCreada(int $indice, RoadmapItem $item, string $titulo): array
    {
        $enCola = $item->estado_aprobacion !== 'requiere_irving';

        return ['indice' => $indice, 'id' => $item->id, 'titulo' => $titulo, 'url' => self::url($item->id),
            'estado' => $item->estado_aprobacion, 'en_cola' => $enCola,
            'cancelable_hasta' => now()->addMinutes(self::MINUTOS_DESHACER)->toIso8601String()];
    }

    // ── Deshacer ───────────────────────────────────────────────────────────────────────────────

    public function deshacer(int $itemId, object $usuario): string
    {
        $item = RoadmapItem::find($itemId);
        $p = JarvisPropuesta::where('user_id', $usuario->id)->get()
            ->first(fn ($x) => collect($x->item_ids ?? [])->contains('id', $itemId));
        if (! $item || ! $p) {
            throw new PropuestaException('no_deshacible', 'Ese item no lo creaste tú desde el chat, así que no lo puedo deshacer.', 422);
        }
        $creado = \Illuminate\Support\Carbon::parse(collect($p->item_ids)->firstWhere('id', $itemId)['creado_at']);
        if ($creado->lt(now()->subMinutes(self::MINUTOS_DESHACER))) {
            throw new PropuestaException('plazo', 'Ya pasaron más de ' . self::MINUTOS_DESHACER . ' minutos; ya no se puede deshacer desde aquí.', 422);
        }
        if ($item->worker_sid || $item->branch || $item->estado_aprobacion === 'en_progreso' || $item->estado_aprobacion === 'cancelado') {
            throw new PropuestaException('tomado', 'Una terminal ya tomó ese item (o ya estaba cancelado); no lo puedo deshacer.', 422);
        }
        $item->estado_aprobacion = 'cancelado';
        $item->status = 'cancelled';
        $item->urgente = false;
        $item->log = array_merge($item->log ?: [], [['ts' => now()->toIso8601String(), 'por' => self::AUTOR, 'evento' => 'deshecho_desde_jarvis_chat', 'user_id' => (int) $usuario->id]]);
        $item->save();

        return 'Listo, el item #' . $itemId . ' quedó cancelado.';
    }

    // ── Normalización y filtros ────────────────────────────────────────────────────────────────

    /** @param array $previo borrador anterior: el nivel solo puede endurecerse respecto a él. */
    private function normalizar(array $items, array $previo): array
    {
        $out = [];
        foreach (array_slice(array_values($items), 0, self::MAX_ITEMS) as $n => $i) {
            $i = (array) $i;
            $titulo = $this->t($i['titulo'] ?? '', 200);
            if ($titulo === '') {
                continue;
            }
            $x = [
                'indice' => $n,
                'titulo' => $titulo, 'que_cambia' => $this->t($i['que_cambia'] ?? '', 3000), 'donde' => $this->t($i['donde'] ?? '', 1000),
                'como_se_valida' => $this->t($i['como_se_valida'] ?? '', 2000), 'modulo' => $this->t($i['modulo'] ?? '', 100),
                'prioridad' => in_array($i['prioridad'] ?? null, ['alta', 'media', 'baja'], true) ? $i['prioridad'] : 'media',
                'al_inicio' => (bool) ($i['al_inicio'] ?? false),
                'opcion_elegida' => $this->t($i['opcion_elegida'] ?? '', 255),
            ];
            $texto = implode("\n", [$x['titulo'], $x['que_cambia'], $x['donde'], $x['como_se_valida']]);

            $propuesto = in_array($i['nivel_riesgo'] ?? null, ['A', 'B', 'C'], true) ? $i['nivel_riesgo'] : 'B';
            $nivel = $propuesto;
            foreach ([$previo[$n]['nivel_riesgo'] ?? null, $this->fronteras->detectar($texto)['categoria'] ? 'C' : null] as $otro) {
                if ($otro && self::NIVELES[$otro] > self::NIVELES[$nivel]) {
                    $nivel = $otro;
                }
            }
            $frontera = $this->fronteras->detectar($texto)['categoria'];
            $x['nivel_riesgo'] = $nivel;

            $dup = $this->duplicados->buscar($x['titulo'], $x['que_cambia']);
            $x['duplicados'] = $dup ? [['id' => $dup['id'], 'titulo' => $dup['title'], 'url' => self::url($dup['id'])]] : [];

            $motivos = [];
            if ($nivel === 'C') {
                $motivos[] = $frontera ? "toca algo sensible ({$frontera})" : 'es de nivel C (decisión de diseño tuya)';
            }
            if ($x['duplicados']) {
                $motivos[] = 'parece repetir el #' . $x['duplicados'][0]['id'];
            }
            $x['requiere_segunda_confirmacion'] = $nivel === 'C' || $frontera !== null || $x['duplicados'] !== [];
            $x['motivo_confirmacion'] = $motivos ? 'Confirma por separado: ' . implode(' y ', $motivos) . '.' : null;
            $x['_secreto'] = Redactor::texto($texto) !== $texto;

            $out[] = $x;
        }

        return $out;
    }

    private function avisos(array $items): array
    {
        $a = [];
        foreach ($items as $i) {
            if (! empty($i['_secreto'])) {
                $a[] = "El item «{$i['titulo']}» trae algo que parece una llave, contraseña o dato personal: no se podrá crear así.";
            }
            if (! empty($i['duplicados'])) {
                $a[] = "«{$i['titulo']}» se parece al #{$i['duplicados'][0]['id']}.";
            }
        }

        return $a;
    }

    private function t(mixed $v, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/[ \t]+/', ' ', is_scalar($v) ? (string) $v : '')), 0, $max);
    }

    public static function huella(array $items): string
    {
        $limpio = array_map(fn ($i) => array_diff_key($i, ['_secreto' => 1]), $items);

        return hash('sha256', json_encode($limpio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function exigirVigente(JarvisPropuesta $p, string $huella): void
    {
        if (! hash_equals($p->huella, $huella)) {
            throw new PropuestaException('huella', 'La propuesta cambió mientras la revisabas. Vuelve a abrirla para ver la versión actual.', 409);
        }
        if (! in_array($p->estado, ['propuesta', 'editada'], true)) {
            throw new PropuestaException('estado', 'Esa propuesta ya no está abierta.', 409);
        }
    }

    private function creadosUltimaHora(int $userId): int
    {
        return JarvisPropuesta::where('user_id', $userId)->where('updated_at', '>=', now()->subHour())->get()
            ->sum(fn ($p) => collect($p->item_ids ?? [])->filter(fn ($x) => \Illuminate\Support\Carbon::parse($x['creado_at'])->gte(now()->subHour()))->count());
    }

    public static function url(int $id): string
    {
        return '/roadmap/item/' . $id;
    }
}
