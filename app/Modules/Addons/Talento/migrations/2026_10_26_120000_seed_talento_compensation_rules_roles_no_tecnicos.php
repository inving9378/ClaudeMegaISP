<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #121 — decisión de Irving (2026-10-26): contabilidad y atención a clientes cobran SUELDO
 * FIJO SEMANAL sin puntos; los montos se capturan en Talento → Compensación (no en código).
 * Aquí solo se deja el esquema listo: una regla por rol con monto 0 y APAGADA, para que no se
 * pague nada hasta que se capture el monto y se active. Idempotente por (target_type, nombre).
 */
return new class extends Migration
{
    public function up(): void
    {
        $reglas = [
            ['accounting', 'Contabilidad — sueldo fijo semanal (monto por capturar)'],
            ['support',    'Atención a clientes — sueldo fijo semanal (monto por capturar)'],
        ];

        foreach ($reglas as [$tipo, $nombre]) {
            if (DB::table('talento_compensation_rules')->where('target_type', $tipo)->exists()) {
                continue;
            }
            DB::table('talento_compensation_rules')->insert([
                'name'               => $nombre,
                'target_type'        => $tipo,
                'base_salary'        => 0,
                'period'             => 'weekly',
                'weekly_quota_units' => 0,
                'active'             => false,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('talento_compensation_rules')
            ->whereIn('target_type', ['accounting', 'support'])
            ->where('base_salary', 0)->where('active', false)
            ->where('name', 'like', '%monto por capturar)')
            ->delete();
    }
};
