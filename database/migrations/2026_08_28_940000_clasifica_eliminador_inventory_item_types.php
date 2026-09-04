<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cierra el item #218 (seguimiento de #572/#1007): de los ~18 tipos "dudosos" que #572 dejó en
 * `categoria=NULL`, 15 ya quedaron clasificados por #572/#1007 (ONT/MODEM/TELEFONOS DE CASA →
 * equipo_cliente; ACOPLADOR/SPLITTER/CONECTOR/CONECTORES/CABLE/PILAS/PAPELERIA/FLYERS/CARRETE/
 * TENSOR/TENSORES/HOJAS → material). Verificado contra la BD antes de escribir esta migración.
 *
 * Solo quedaban ELIMINADOR y POWER. Revisando los `inventory_items` reales bajo cada tipo:
 * - ELIMINADOR (19 artículos, todos "ELIMINADOR <voltaje/amperaje>"): son los adaptadores AC/DC
 *   que acompañan al ONT/módem instalado en casa del cliente — no vuelven a almacén, igual que
 *   MODEM/ONT/ROUTER (ya clasificados `equipo_cliente`). Se clasifica aquí.
 * - POWER (solo 5 artículos) es un tipo MEZCLADO, no una duda de negocio simple: 1 "MODEM HUAWEI"
 *   mal capturado ahí, 2 "OPTICAL POWER METER" (medidor óptico — herramienta que el técnico usa y
 *   devuelve) y 2 "POWER SUPPLY/POWER SYSTEM..." (fuente de poder de equipo de red). Asignarle una
 *   sola categoría al TIPO sería incorrecto para al menos 3 de sus 5 artículos — necesita depurar
 *   el catálogo (separar en tipos correctos), no solo elegir herramienta/material/equipo_cliente.
 *   Se deja `categoria=NULL` a propósito y se registra sub-item para la limpieza de catálogo.
 *
 * REGLAS DE SEGURIDAD (mismas que #572/#1007):
 * - Solo toca filas con `categoria IS NULL` → nunca pisa una clasificación ya hecha a mano.
 * - Empareja por NOMBRE NORMALIZADO, NO por id (ids de dev/prod no coinciden).
 * - Idempotente: re-correrla no cambia nada.
 */
return new class extends Migration
{
    private array $equipoCliente = ['ELIMINADOR'];

    public function up(): void
    {
        $lookup = [];
        foreach ($this->equipoCliente as $n) {
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
                ->update(['categoria' => 'equipo_cliente']);
        }
    }

    /**
     * `down()` vacío A PROPÓSITO, mismo motivo que #572/#1007: revertir perdería la clasificación
     * de negocio y podría pisar una reclasificación manual posterior hecha desde el formulario.
     */
    public function down(): void
    {
    }

    private function normaliza(string $nombre): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtoupper($nombre, 'UTF-8')));
    }
};
