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
 *   - Thomas (descomposición de un item grande al repartir).
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
     * @param  string  $autor   quién lo crea: 'claude-cowork' | 'wt-3' | 'thomas' | 'claude-code'
     * @param  bool    $interno true si el autor corre ON-BOX (terminal/Thomas/CC). Decide cómo se
     *                          sella `nivel_riesgo_origen`, del que depende el guard #260: un nivel
     *                          A de origen externo nunca habilita `aprobado_claude`.
     */
    public function crear(array $datos, string $autor, bool $interno): RoadmapItem
    {
        $title = trim((string) ($datos['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('El item necesita un título.');
        }

        $padre = null;
        if (! empty($datos['origen_item_id'])) {
            $padre = RoadmapItem::find((int) $datos['origen_item_id']);
            if (! $padre) {
                throw new InvalidArgumentException(
                    "El item padre #{$datos['origen_item_id']} no existe."
                );
            }
        }

        $nivel = $datos['nivel_riesgo'] ?? null;
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
        $item->priority       = $prioridad;
        $item->origen_item_id = $padre?->id;

        // El nivel puede venir declarado, pero SIEMPRE queda sellado con su origen real.
        $item->nivel_riesgo = $nivel;
        if ($nivel !== null) {
            $item->nivel_riesgo_origen = $interno ? 'interno' : 'externo';
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
            ['via' => $interno ? 'interno' : 'externo', 'padre' => $padre?->id, 'nivel_declarado' => $nivel]
        );

        Log::channel('roadmap_externo')->info('item-creado', [
            'id' => $item->id, 'por' => $autor, 'via' => $interno ? 'interno' : 'externo',
            'padre' => $padre?->id, 'modulo' => $item->modulo, 'nivel' => $nivel,
        ]);

        return $item->refresh();
    }

    private function recorta($valor, int $max): ?string
    {
        $valor = $valor === null ? null : trim((string) $valor);

        return ($valor === null || $valor === '') ? null : mb_substr($valor, 0, $max);
    }
}
