<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoWorkOrderChecklistItem;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderData;
use App\Modules\Addons\Talento\Support\EsquemaCamposOrden;
use Illuminate\Support\Facades\DB;

/**
 * #1202 (Fase D de planta externa) — lo que el técnico captura al cerrar una orden con plantilla:
 * los campos «al cerrar» (potencia, fibra, coordenadas, tablas…) y cuánto usó de cada material del
 * checklist. Irving (11-nov): quien crea la orden solo elige la plantilla; el técnico va llenando
 * cada campo, y lo que consumió después se descuenta de su custodia.
 *
 * Los valores viven en talento_work_order_data.values (junto con los de la creación) y se validan
 * con el esquema que la orden copió al crearse (schema_snapshot). Se guarda el avance sin exigir lo
 * obligatorio; completar la orden sí lo exige (OrdenTrabajoUnifiedService::completar).
 */
class CierrePlantillaService
{
    /** Datos de cierre de la orden, o null si su plantilla no pide nada al cerrar. */
    public function datos(int $taskId): ?array
    {
        $d = TalentoWorkOrderData::where('tarea_id', $taskId)->first();
        $checklist = $this->checklist($taskId);
        $campos = $d ? EsquemaCamposOrden::camposDe($d->schema_snapshot ?? [], EsquemaCamposOrden::MOMENTO_CIERRE) : [];
        if (! $campos && ! $checklist) {
            return null;
        }
        $valores = array_intersect_key((array) ($d?->values ?? []), array_flip(array_column($campos, 'key')));

        return [
            'plantilla' => $d?->template?->name,
            'campos'    => $campos,
            'valores'   => (object) $valores,
            'checklist' => $checklist,
            'faltan'    => EsquemaCamposOrden::obligatoriosVacios($campos, $valores, EsquemaCamposOrden::MOMENTO_CIERRE),
        ];
    }

    /** Etiquetas de los campos obligatorios «al cerrar» que siguen vacíos (para no dejar completar). */
    public function faltantes(int $taskId): array
    {
        $d = TalentoWorkOrderData::where('tarea_id', $taskId)->first();
        if (! $d) {
            return [];
        }

        return EsquemaCamposOrden::obligatoriosVacios($d->schema_snapshot ?? [], (array) ($d->values ?? []), EsquemaCamposOrden::MOMENTO_CIERRE);
    }

    /**
     * Guarda lo que el técnico lleva capturado: reemplaza los valores «al cerrar» (los de la creación
     * no se tocan) y marca los materiales con la cantidad usada.
     *
     * @param  array  $datos      key => valor de los campos «al cerrar»
     * @param  array  $checklist  [['id' => , 'checked' => bool, 'cantidad_usada' => ?número], …]
     */
    public function guardar(int $taskId, array $datos, array $checklist, ?int $userId): array
    {
        $d = TalentoWorkOrderData::where('tarea_id', $taskId)->first();
        $campos = $d ? EsquemaCamposOrden::camposDe($d->schema_snapshot ?? [], EsquemaCamposOrden::MOMENTO_CIERRE) : [];
        if ($datos && ! $campos) {
            return ['success' => false, 'message' => 'Esta orden no tiene datos que capturar al cerrar.', 'status_code' => 422];
        }

        $r = $campos ? EsquemaCamposOrden::validarDatos($d->schema_snapshot ?? [], $datos, EsquemaCamposOrden::MOMENTO_CIERRE, false) : ['values' => [], 'errors' => []];
        $errores = $r['errors'];

        $items = TalentoWorkOrderChecklistItem::where('tarea_id', $taskId)->get()->keyBy('id');
        $cambios = [];
        foreach ($checklist as $i => $c) {
            $item = $items[(int) ($c['id'] ?? 0)] ?? null;
            if (! $item) {
                $errores["checklist.$i"] = 'Ese material no es de esta orden.';
                continue;
            }
            $usada = $c['cantidad_usada'] ?? null;
            if ($usada !== null && $usada !== '') {
                $usada = str_replace(',', '.', trim((string) $usada));
                if (! is_numeric($usada) || (float) $usada < 0 || (float) $usada > 99999999) {
                    $errores["checklist.$i"] = 'La cantidad debe ser un número de 0 en adelante.';
                    continue;
                }
                $usada = round((float) $usada, 2);
            } else {
                $usada = null;
            }
            // Con una cantidad usada mayor que cero el material cuenta como usado aunque no lo haya marcado.
            $marcado = filter_var($c['checked'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($usada !== null && $usada > 0);
            $cambios[$item->id] = ['checked' => $marcado, 'cantidad_usada' => $usada];
        }

        if ($errores) {
            return ['success' => false, 'message' => 'Revisa los datos marcados.', 'errors' => $errores, 'status_code' => 422];
        }

        DB::transaction(function () use ($d, $campos, $r, $items, $cambios, $userId) {
            if ($campos) {
                $cierre = array_column($campos, 'key');
                $d->values = array_diff_key((array) ($d->values ?? []), array_flip($cierre)) + $r['values'];
                $d->updated_by = $userId;
                $d->save();
            }
            foreach ($cambios as $id => $c) {
                $item = $items[$id];
                $item->checked = $c['checked'];
                $item->cantidad_usada = $c['cantidad_usada'];
                if ($item->isDirty('checked')) {
                    $item->checked_at = $c['checked'] ? now() : null;
                    $item->checked_by = $c['checked'] ? $userId : null;
                }
                $item->save();
            }
        });

        return ['success' => true, 'data' => $this->datos($taskId)];
    }

    private function checklist(int $taskId): array
    {
        return TalentoWorkOrderChecklistItem::where('tarea_id', $taskId)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'label', 'cantidad', 'unidad', 'checked', 'cantidad_usada'])
            ->map(fn ($i) => [
                'id'             => $i->id,
                'label'          => $i->label,
                'cantidad'       => $i->cantidad,
                'unidad'         => $i->unidad,
                'checked'        => (bool) $i->checked,
                'cantidad_usada' => $i->cantidad_usada !== null ? (float) $i->cantidad_usada : null,
            ])->values()->all();
    }
}
