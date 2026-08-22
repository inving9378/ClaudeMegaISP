<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reclasifica a la CUARTA categoría `equipo_red` (item #1007, decisión de Irving 2026-08-21,
 * seguimiento de #572) los tipos de artículo que #572 dejó DELIBERADAMENTE en null bajo el
 * motivo "EQUIPO DE RED / INFRAESTRUCTURA" (ver comentario de
 * 2026_08_08_150100_clasifica_categorias_inventory_item_types.php).
 *
 * Criterio de Irving (item #1007, q2): activo de infraestructura de red SERIALIZADO — router,
 * switch, ONU, OLT, antena, radio. Excluye accesorios de red no serializados (patchcords,
 * conectores), que ya quedaron en `material` desde #572.
 *
 * Los otros ~55 tipos que siguen en null (motivos 2 y 3 de #572: dudosos de negocio como
 * ELIMINADOR/POWER, o nombre genérico/ilegible/dato de prueba) NO se tocan aquí — el alcance de
 * #1007 es específicamente la categoría "equipo de red", no terminar de clasificar los 75.
 *
 * REGLAS DE SEGURIDAD (mismas que #572):
 * - Solo toca filas con `categoria IS NULL` → nunca pisa una clasificación ya hecha a mano.
 * - Empareja por NOMBRE NORMALIZADO (trim + espacios colapsados + mayúsculas), NO por id.
 * - Idempotente: re-correrla no cambia nada.
 */
return new class extends Migration
{
    /** Infraestructura de red serializada: routers/switches/ONUs/OLTs/antenas/radios (y marcas). */
    private array $equipoRed = [
        'ANTENA', 'ANTENAS', 'ANTENA HP', 'ANTENAS DE IMAN', 'PUNTA ANTENA',
        'CISCO SYSTEMS', 'HUAWEI', 'MIKROTIKS', 'NANO', 'NOT ENGINE', 'OLT', 'RADIO',
        'RAISECOM', 'ROCKET', 'ROKET', 'RPCKET', 'SECTORES', 'SWITCH', 'TP-LINK', 'TSMS',
    ];

    public function up(): void
    {
        $lookup = [];
        foreach ($this->equipoRed as $n) {
            $lookup[$this->normaliza($n)] = true;
        }

        $pendientes = DB::table('inventory_item_types')
            ->whereNull('categoria')->whereNull('deleted_at')
            ->get(['id', 'name']);

        $ids = [];
        foreach ($pendientes as $t) {
            if (isset($lookup[$this->normaliza($t->name)])) {
                $ids[] = $t->id;
            }
        }

        foreach (array_chunk($ids, 500) as $lote) {
            DB::table('inventory_item_types')
                ->whereIn('id', $lote)->whereNull('categoria')
                ->update(['categoria' => 'equipo_red']);
        }
    }

    /**
     * `down()` vacío A PROPÓSITO, mismo motivo que #572: revertir perdería la clasificación de
     * negocio (y podría pisar una reclasificación manual posterior hecha desde el formulario).
     */
    public function down(): void
    {
    }

    private function normaliza(string $nombre): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtoupper($nombre, 'UTF-8')));
    }
};
