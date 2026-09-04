<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Depura el tipo 'POWER' (item #684, seguimiento de #218): era un tipo MEZCLADO con 5 artículos
 * heterogéneos a los que no se les podía asignar una sola `categoria` sin equivocarse en al menos
 * 3 de los 5. Se reclasifica cada `inventory_item` a su tipo real, en vez de clasificar el tipo
 * 'POWER' completo:
 *
 * - id 17 "MODEM HUAWEI BLANCO GRANDE": mal capturado en 'POWER' → mueve a 'MODEM HUAWEI' (id
 *   histórico 34 en dev; aquí se resuelve por NOMBRE, no por id — dev/prod no coinciden), que ya
 *   contiene los demás equipos Huawei de cliente (`categoria=equipo_cliente`).
 * - ids 239 y 1173 "OPTICAL POWER METER" (medidor de potencia óptica): mueve a 'MEDIDOR', que ya
 *   contiene dos "MEDIDOR DE POTENCIA OPTICA..." — el mismo artículo con otro nombre
 *   (`categoria=herramienta`, ya clasificado por #572).
 * - ids 483 "POWER SUOOLY..." y 486 "POWER SYSTEM CACHE ENGINE 207": fuente de poder de equipo de
 *   red, sin tipo existente que encaje → crea el tipo nuevo 'FUENTE DE PODER EQUIPO DE RED'
 *   (`categoria=equipo_red`, decisión de Irving #1007) y mueve ambos ahí.
 *
 * El tipo 'POWER' (`categoria` sigue NULL) queda sin artículos tras esto — se deja la fila (no se
 * borra): no tiene FK ni consumidor que dependa de que exista, y borrar catálogo es un paso aparte
 * del que pide este item.
 *
 * REGLAS DE SEGURIDAD (mismas que #572/#1007/#218):
 * - Empareja por NOMBRE NORMALIZADO, NO por id (ids de dev/prod no coinciden).
 * - Solo mueve artículos que hoy siguen en el tipo 'POWER' → si alguien ya los reclasificó a mano,
 *   no se pisa.
 * - Idempotente: re-correrla no cambia nada (los artículos ya no estarán en 'POWER').
 */
return new class extends Migration
{
    /** [nombre normalizado del artículo en 'POWER' => nombre normalizado del tipo destino] */
    private array $moverATipoExistente = [
        'MODEM HUAWEI BLANCO GRANDE' => 'MODEM HUAWEI',
        'OPTICAL POWER METER' => 'MEDIDOR',
    ];

    /** Artículos que van al tipo nuevo 'FUENTE DE PODER EQUIPO DE RED'. */
    private array $moverATipoNuevo = [
        'POWER SUOOLY INPUD 100-127/200-240V UPC-1,2,3',
        'POWER SYSTEM CACHE ENGINE 207',
    ];

    private const TIPO_NUEVO = 'FUENTE DE PODER EQUIPO DE RED';

    public function up(): void
    {
        $tipoPower = DB::table('inventory_item_types')
            ->whereRaw('UPPER(name) = ?', ['POWER'])
            ->whereNull('deleted_at')
            ->first();

        if (! $tipoPower) {
            return; // catálogo ausente en este entorno: no inventamos el tipo aquí
        }

        $articulosEnPower = DB::table('inventory_items')
            ->where('inventory_item_type_id', $tipoPower->id)
            ->whereNull('deleted_at')
            ->get(['id', 'name']);

        if ($articulosEnPower->isEmpty()) {
            return; // ya depurado (idempotencia)
        }

        $tiposPorNombre = DB::table('inventory_item_types')
            ->whereNull('deleted_at')
            ->get(['id', 'name'])
            ->keyBy(fn ($t) => $this->normaliza($t->name));

        // 1) Artículos con tipo destino ya existente en el catálogo.
        foreach ($this->moverATipoExistente as $nombreArticulo => $nombreTipoDestino) {
            $tipoDestino = $tiposPorNombre->get($this->normaliza($nombreTipoDestino));
            if (! $tipoDestino) {
                continue; // tipo destino ausente en este entorno: no se inventa
            }

            $ids = $articulosEnPower
                ->filter(fn ($a) => $this->normaliza($a->name) === $this->normaliza($nombreArticulo))
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('inventory_items')
                    ->whereIn('id', $ids)
                    ->where('inventory_item_type_id', $tipoPower->id)
                    ->update(['inventory_item_type_id' => $tipoDestino->id]);
            }
        }

        // 2) Artículos que necesitan el tipo nuevo (crear si no existe, idempotente por nombre).
        $idsTipoNuevo = $articulosEnPower
            ->filter(fn ($a) => in_array($this->normaliza($a->name), array_map(fn ($n) => $this->normaliza($n), $this->moverATipoNuevo), true))
            ->pluck('id');

        if ($idsTipoNuevo->isNotEmpty()) {
            $tipoNuevoId = DB::table('inventory_item_types')
                ->whereRaw('UPPER(name) = ?', [self::TIPO_NUEVO])
                ->whereNull('deleted_at')
                ->value('id');

            if (! $tipoNuevoId) {
                $tipoNuevoId = DB::table('inventory_item_types')->insertGetId([
                    'name' => self::TIPO_NUEVO,
                    'type' => 'material',
                    'categoria' => 'equipo_red',
                    'created_by' => 1, // "created_by=0" (usado en catálogo legacy) viola la FK; 1 = Admin
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('inventory_items')
                ->whereIn('id', $idsTipoNuevo)
                ->where('inventory_item_type_id', $tipoPower->id)
                ->update(['inventory_item_type_id' => $tipoNuevoId]);
        }
    }

    /**
     * `down()` vacío A PROPÓSITO, mismo motivo que #572/#1007/#218: revertir perdería la
     * reclasificación de negocio y podría pisar una reclasificación manual posterior.
     */
    public function down(): void
    {
    }

    private function normaliza(string $nombre): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtoupper($nombre, 'UTF-8')));
    }
};
