<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Color por tipo de orden de Talento (item #1158, pedido de Irving 2026-11-03): en el calendario de
 * carga y en la agenda cada orden se reconoce por su color (instalación azul, garantía naranja…).
 * Aditiva: columna nullable; sin color, la pantalla usa uno de respaldo. Los tipos que ya existen
 * reciben su color por nombre solo si aún no tienen uno (idempotente, no pisa lo que se elija después).
 */
return new class extends Migration
{
    private const COLORES_POR_NOMBRE = [
        'Instalaci%'       => '#1E88E5', // azul
        'Soporte%'         => '#F4511E', // naranja (garantía; el rojo queda para lo atrasado)
        '%garant%'         => '#F4511E',
        'Cambio de equipo%' => '#8E24AA', // morado
        'Reubica%'         => '#00897B', // verde azulado
        'Baja%'            => '#616161', // gris
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('talento_work_order_types', 'color')) {
            Schema::table('talento_work_order_types', function (Blueprint $table) {
                $table->string('color', 7)->nullable()->after('category');
            });
        }

        foreach (self::COLORES_POR_NOMBRE as $patron => $color) {
            DB::table('talento_work_order_types')
                ->whereNull('color')
                ->where('name', 'like', $patron)
                ->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('talento_work_order_types', 'color')) {
            Schema::table('talento_work_order_types', function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
