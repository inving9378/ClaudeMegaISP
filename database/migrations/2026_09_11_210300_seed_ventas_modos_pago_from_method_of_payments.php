<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siembra `ventas_modos_pago` con el catálogo real de `method_of_payments`
 * (item #9990808, Fase 1 de #9990781). Aditiva e idempotente (firstOrCreate
 * por `codigo`, derivado con slug del nombre). Solo lectura sobre
 * `method_of_payments` — no se modifica esa tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $metodos = DB::table('method_of_payments')->orderBy('id')->get(['id', 'type', 'active']);

        foreach ($metodos as $metodo) {
            $codigo = Str::slug($metodo->type, '_');

            $existe = DB::table('ventas_modos_pago')->where('codigo', $codigo)->exists();
            if ($existe) {
                continue;
            }

            DB::table('ventas_modos_pago')->insert([
                'codigo'     => $codigo,
                'nombre'     => $metodo->type,
                'activo'     => (bool) $metodo->active,
                'orden'      => $metodo->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Reversión intencionalmente vacía: no se borran datos sembrados.
    }
};
