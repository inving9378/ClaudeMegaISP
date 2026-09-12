<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 de #9990877 (Identidad unificada), ejecutada vía #9990932 — tabla de auditoría
 * `colaboradores_merges` (metodología aprobada por Irving en #9990877 q4: from_id,to_id,fecha,
 * usuario,snapshot; los registros espejo/duplicados se MARCAN, NUNCA se borran).
 *
 * Primer uso: dar de baja las 2 únicas cuentas espejo inertes confirmadas en el diagnóstico de
 * solo-lectura #9990802 (Bloque A: seller_id 23 y 25 — 0 clientes, 0 pagos, 0 balance,
 * status_id null, 0 comisiones/transacciones/reglas). Sin fusión real (to_id=null): no hay
 * cartera ni comisión que reasignar.
 *
 * NO se toca Guadalupe (seller 12 vs 48): ya investigado en #9990472 y confirmado que son 2
 * personas reales distintas, no un duplicado — fusionarlas dañaría datos reales de dos cuentas
 * legítimas. Decisión confirmada vía `circuito:consultar` sobre el item #9990932 (contradicción
 * entre la respuesta aprobada q1, generada antes de conocer #9990472, y el propio prompt del
 * item, que ya decía explícitamente "NO tocar Guadalupe... ya investigada aparte").
 */
return new class extends Migration
{
    private array $sellerIdsEspejo = [23, 25];

    public function up(): void
    {
        if (!Schema::hasTable('colaboradores_merges')) {
            Schema::create('colaboradores_merges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('from_id');
                $table->unsignedBigInteger('to_id')->nullable();
                $table->timestamp('fecha');
                $table->string('usuario', 100);
                $table->json('snapshot')->nullable();
                $table->timestamps();
                $table->index('from_id');
                $table->index('to_id');
            });
        }

        $sellers = DB::table('sellers')->whereIn('id', $this->sellerIdsEspejo)->get();

        foreach ($sellers as $seller) {
            $yaRegistrado = DB::table('colaboradores_merges')
                ->where('from_id', $seller->user_id)
                ->whereNull('to_id')
                ->exists();
            if ($yaRegistrado) {
                continue;
            }

            $user = DB::table('users')->where('id', $seller->user_id)->first();

            DB::table('colaboradores_merges')->insert([
                'from_id'    => $seller->user_id,
                'to_id'      => null,
                'fecha'      => now(),
                'usuario'    => 'circuito-cc#9990932',
                'snapshot'   => json_encode([
                    'tipo'   => 'baja_cuenta_espejo_inerte',
                    'motivo' => 'Cuenta espejo (patrón login_user Meganet+hex) con fila sellers sin '
                        . 'actividad real: 0 clientes, 0 pagos, 0 balance, 0 comisiones/transacciones/reglas. '
                        . 'Ver docs/identidad-vendedores-diagnostico-8-seller-id-item-9990802.md Bloque A.',
                    'seller' => (array) $seller,
                    'user'   => $user ? (array) $user : null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($seller->status_id === null) {
                DB::table('sellers')->where('id', $seller->id)->update(['status_id' => 2]);
            }
        }
    }

    public function down(): void
    {
        $userIds = DB::table('sellers')->whereIn('id', $this->sellerIdsEspejo)->pluck('user_id');

        DB::table('colaboradores_merges')
            ->whereIn('from_id', $userIds)
            ->whereNull('to_id')
            ->delete();

        DB::table('sellers')->whereIn('id', $this->sellerIdsEspejo)->update(['status_id' => null]);

        if (Schema::hasTable('colaboradores_merges') && DB::table('colaboradores_merges')->count() === 0) {
            Schema::dropIfExists('colaboradores_merges');
        }
    }
};
