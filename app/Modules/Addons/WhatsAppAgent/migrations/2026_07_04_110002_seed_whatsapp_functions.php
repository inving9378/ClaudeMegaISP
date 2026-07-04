<?php

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use Illuminate\Database\Migrations\Migration;

/**
 * FASE 3 — Semilla del catálogo de funciones.
 *
 * 4 funciones base, todas exclusive=true, SIN asignación (huérfanas de creación
 * permitidas). Irving las asigna a líneas desde el panel. Idempotente: firstOrCreate
 * por slug (no duplica ni pisa si ya existen).
 */
return new class extends Migration
{
    public function up(): void
    {
        $functions = [
            ['slug' => 'ventas',   'name' => 'Ventas'],
            ['slug' => 'cobranza', 'name' => 'Cobranza'],
            ['slug' => 'soporte',  'name' => 'Soporte'],
            ['slug' => 'atencion', 'name' => 'Atención'],
        ];

        foreach ($functions as $i => $f) {
            WhatsAppFunction::firstOrCreate(
                ['slug' => $f['slug']],
                [
                    'name'      => $f['name'],
                    'exclusive' => true,
                    'active'    => true,
                    'position'  => $i,
                ]
            );
        }
    }

    public function down(): void
    {
        WhatsAppFunction::whereIn('slug', ['ventas', 'cobranza', 'soporte', 'atencion'])->forceDelete();
    }
};
