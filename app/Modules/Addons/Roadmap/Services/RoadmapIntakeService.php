<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * TORRE V2 — PUNTO ÚNICO de alta de items en la Hoja de Ruta.
 *
 * Lo usan las tres vías que ahora pueden crear trabajo, para que las tres pasen por los MISMOS
 * candados:
 *   - la API externa (Cowork define el qué),
 *   - las terminales (sub-items de seguimiento cuando un item resulta ser multi-fase),
 *   - Jarvis (descomposición de un item grande al repartir).
 *
 * CANDADO CENTRAL: un item creado NACE SIN APROBAR (`pendiente_revision`) pase lo que pase. Crear
 * y aprobar son dos actos separados: quien crea no puede darse permiso de ejecución en el mismo
 * lazo. El triaje sigue siendo del revisor/autopilot, exactamente como con cualquier item.
 */
class RoadmapIntakeService
{
    public function __construct(private RoadmapReportService $reportes)
    {
    }

    /**
     * Crea un item. Devuelve el item ya persistido.
     *
     * @param  array   $datos   title (req), description, prompt, modulo, nivel_riesgo, priority,
     *                          origen_item_id, target_version
     * @param  string  $autor   quién lo crea: 'claude-cowork' | 'wt-3' | 'jarvis' | 'claude-code'
     * @param  bool    $interno true si el autor corre ON-BOX (terminal/Jarvis/CC). Decide cómo se
     *                          sella `nivel_riesgo_origen`, del que depende el guard #260: un nivel
     *                          A de origen externo nunca habilita `aprobado_claude`.
     */
    public function crear(array $datos, string $autor, bool $interno): RoadmapItem
    {
        $title = trim((string) ($datos['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('El item necesita un título.');
        }

        // CIRC-05 pieza A (#9990946) — idempotencia: un reintento con la MISMA clave_externa
        // devuelve el item ya existente tal cual (sin `save()`, `wasRecentlyCreated` queda en
        // false) en vez de duplicarlo. El llamador (ej. RoadmapExternalController) distingue
        // hallazgo de alta nueva revisando `$item->wasRecentlyCreated`.
        $claveExterna = $this->recorta($datos['clave_externa'] ?? null, 191);
        if ($claveExterna !== null) {
            $existente = RoadmapItem::where('clave_externa', $claveExterna)->first();
            if ($existente) {
                return $existente;
            }
        }

        $padre = null;
        if (! empty($datos['origen_item_id'])) {
            $padre = RoadmapItem::find((int) $datos['origen_item_id']);
            if (! $padre) {
                throw new InvalidArgumentException(
                    "El item padre #{$datos['origen_item_id']} no existe."
                );
            }

            // CIRC-05 pieza B (#9990947) — freno contra cadenas de sub-items de seguimiento
            // sin fin: si el padre ya está a la profundidad máxima permitida desde su raíz,
            // no se persiste nada.
            $maxProfundidad = (int) config('roadmap_externo.max_profundidad_creacion', 3);
            $profundidadPadre = $this->profundidadDesdeRaiz($padre);
            if ($profundidadPadre >= $maxProfundidad) {
                throw new InvalidArgumentException(
                    "No se puede crear: el padre #{$padre->id} ya está a profundidad máxima "
                    . "({$maxProfundidad}) desde su raíz."
                );
            }
        }

        // El valor tal cual lo mandó quien crea (se conserva para auditoría más abajo, se aplique
        // o no). CIRC-05 pieza C (#9990948, decisión de Irving q1=Opción1 "ignorar silenciosamente
        // y recalcular siempre server-side"): la vía EXTERNA nunca puede fijar su propio
        // nivel_riesgo — se descarta en silencio (sin 422; el clasificador de triaje-null #419 lo
        // calcula después) y solo la vía INTERNA (terminal/Jarvis/CC, la que ya confía el guard
        // #260) puede declararlo directo.
        $nivelDeclarado = $datos['nivel_riesgo'] ?? null;
        $nivel          = $interno ? $nivelDeclarado : null;
        if ($nivel !== null && ! in_array($nivel, RoadmapItem::NIVELES_RIESGO, true)) {
            throw new InvalidArgumentException("nivel_riesgo inválido '{$nivel}' (A, B o C).");
        }

        $prioridad = $datos['priority'] ?? null;
        if ($prioridad !== null && ! in_array($prioridad, ['alta', 'media', 'baja'], true)) {
            throw new InvalidArgumentException("priority inválida '{$prioridad}' (alta, media o baja).");
        }

        $item = new RoadmapItem();
        $item->title       = mb_substr($title, 0, 255);
        $item->description = $this->recorta($datos['description'] ?? null, 20000);
        $item->prompt      = $this->recorta($datos['prompt'] ?? null, 20000);
        // `modulo` es texto libre y con drift conocido (#526): se hereda del padre cuando el que
        // crea no lo declara, que es lo correcto para un sub-item de seguimiento.
        $item->modulo         = $this->recorta($datos['modulo'] ?? $padre?->modulo, 100);
        $item->target_version = $this->recorta($datos['target_version'] ?? null, 20);
        // #214 — mismo patrón que `modulo`: si quien crea no declara priority, hereda la del padre
        // (un sub-item de un item `alta` no debe degradar a NULL en la cola solo por descomponerse).
        $item->priority       = $prioridad ?? $padre?->priority;
        $item->origen_item_id = $padre?->id;
        $item->clave_externa  = $claveExterna;

        // El nivel solo puede venir declarado por la vía interna (ver arriba): si se aplicó, su
        // origen siempre es 'interno' (la externa nunca llega a este punto con $nivel !== null).
        $item->nivel_riesgo = $nivel;
        if ($nivel !== null) {
            $item->nivel_riesgo_origen = 'interno';
        }

        // #9990960 (CIRC-05 pieza B 3/4) — la alta EXTERNA (Cowork) siempre debe llevar el bloque
        // "Canal de respuesta (obligatorio)" al final del prompt (regla permanente del proyecto,
        // spec de #9990947). Las altas internas (terminales/Jarvis/CC) no lo requieren: ya operan
        // dentro del propio circuito y no cambian su comportamiento actual. Idempotente: si el
        // prompt ya trae el bloque (ej. un reintento), no se duplica.
        if (! $interno) {
            $item->prompt = RoadmapItem::conCanalDeRespuesta((string) $item->prompt);
        }

        // CANDADO: nace sin aprobar, siempre. No hay parámetro que lo cambie.
        $item->estado_aprobacion = 'pendiente_revision';
        $item->status            = 'pending';

        $item->log = [[
            'ts'      => now()->toIso8601String(),
            'por'     => $autor,
            'evento'  => 'item_creado',
            'via'     => $interno ? 'interno' : 'externo',
            'padre'   => $padre?->id,
        ]];

        $item->save();

        $this->reportes->append(
            $item,
            $autor,
            'nota',
            $padre
                ? "Item creado como sub-item de seguimiento de #{$padre->id}."
                : 'Item creado en la Hoja de Ruta.',
            null,
            ['via' => $interno ? 'interno' : 'externo', 'padre' => $padre?->id, 'nivel_declarado' => $nivelDeclarado, 'nivel_aplicado' => $nivel]
        );

        Log::channel('roadmap_externo')->info('item-creado', [
            'id' => $item->id, 'por' => $autor, 'via' => $interno ? 'interno' : 'externo',
            'padre' => $padre?->id, 'modulo' => $item->modulo, 'nivel_declarado' => $nivelDeclarado, 'nivel_aplicado' => $nivel,
        ]);

        return $item->refresh();
    }

    /**
     * Profundidad de $item contando desde su raíz (raíz sin origen_item_id = 1, cada
     * sub-item de seguimiento suma un nivel). El tope de 50 iteraciones es SOLO una guarda
     * anti-bucle-infinito por datos corruptos (cadena cíclica), NO el límite de negocio
     * (ese es `max_profundidad_creacion`).
     */
    private function profundidadDesdeRaiz(RoadmapItem $item): int
    {
        $profundidad = 1;
        $actual = $item;

        while ($actual->origen_item_id && $profundidad < 50) {
            $siguiente = RoadmapItem::find($actual->origen_item_id);
            if (! $siguiente) {
                break;
            }
            $actual = $siguiente;
            $profundidad++;
        }

        return $profundidad;
    }

    private function recorta($valor, int $max): ?string
    {
        $valor = $valor === null ? null : trim((string) $valor);

        return ($valor === null || $valor === '') ? null : mb_substr($valor, 0, $max);
    }
}
