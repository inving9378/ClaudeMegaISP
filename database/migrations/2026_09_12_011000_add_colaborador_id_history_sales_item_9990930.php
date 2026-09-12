<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase "siguiente" (q2) de #9990877 (Identidad unificada) — puente aditivo `colaborador_id` en
 * las 2 tablas que quedaron fuera de la migración `2026_09_12_010000` a propósito (aprobado por
 * Irving en #9990930): `history_sellers_rules` (histórico/auditoría, 38 filas en dev) y `sales`
 * (0 filas, 0 consumidores reales confirmados en
 * docs/vendedores-sales-commissions-prospects-item-9990471-verificacion.md — se agrega por
 * completitud, sin riesgo: tabla vacía).
 *
 * Mismo patrón exacto que la migración anterior: doble join `sellers.id` → `sellers.user_id` →
 * `talento_colaboradores.user_id`. `seller_id` NO se toca. Reversible: down() limpio.
 */
return new class extends Migration
{
    /** @var array<int,string> */
    private array $tablas = [
        'history_sellers_rules',
        'sales',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (!Schema::hasColumn($tabla, 'colaborador_id')) {
                    $table->unsignedBigInteger('colaborador_id')->nullable()->after('seller_id');
                    $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
                    $table->index('colaborador_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (Schema::hasColumn($tabla, 'colaborador_id')) {
                    $table->dropForeign(['colaborador_id']);
                    $table->dropColumn('colaborador_id');
                }
            });
        }
    }
};
