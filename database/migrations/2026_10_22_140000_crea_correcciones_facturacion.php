<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #571, Parte B (2026-10-22): correcciones de facturación que se aplican solas al terminar la
 * actualización y las que esperan a una persona en Finanzas › «Correcciones pendientes».
 *
 * - facturacion_corridas: una fila por corrida programada (clave única). Esta migración deja la
 *   primera en «pendiente»; facturacion:correcciones-automaticas (cada 10 min en el schedule) la
 *   toma una sola vez. Aplicar la actualización dos veces no crea otra: la clave ya existe.
 * - facturacion_correcciones: la bitácora en la base. Cada propuesta o corrección, con el antes y
 *   el después, quién y cuándo, el respaldo y el SQL para deshacer.
 * Aditiva: no toca facturas, pagos ni saldos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facturacion_corridas')) {
            Schema::create('facturacion_corridas', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 100)->unique();
                $table->string('descripcion')->nullable();
                $table->string('estado', 20)->default('pendiente'); // pendiente | en_proceso | terminada | fallida
                $table->string('servidor', 100)->nullable();
                $table->timestamp('iniciada_at')->nullable();
                $table->timestamp('terminada_at')->nullable();
                $table->json('resumen')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('facturacion_correcciones')) {
            Schema::create('facturacion_correcciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('corrida_id')->nullable()->constrained('facturacion_corridas')->nullOnDelete();
                $table->unsignedBigInteger('client_id')->index();
                $table->string('clave', 100)->index();          // reacomodo:<cliente> | monto_pago:<pago> | revision:<cliente>
                $table->string('tipo', 20);                      // reacomodo | monto_pago | revision
                $table->string('origen', 20);                    // automatica | decidida | propuesta
                $table->string('categoria', 30)->nullable();
                $table->string('estado', 20)->default('pendiente'); // pendiente | aplicada | descartada | obsoleta
                $table->json('parametros')->nullable();
                $table->json('antes')->nullable();
                $table->json('despues')->nullable();
                $table->decimal('deuda_antes', 12, 2)->nullable();
                $table->decimal('deuda_despues', 12, 2)->nullable();
                $table->text('detalle')->nullable();
                $table->string('huella', 64);
                $table->unsignedBigInteger('resuelta_por')->nullable();
                $table->timestamp('resuelta_at')->nullable();
                $table->text('motivo')->nullable();
                $table->string('respaldo_path')->nullable();
                $table->longText('deshacer_sql')->nullable();
                $table->timestamps();
                $table->index(['estado', 'tipo']);
            });
        }

        DB::table('facturacion_corridas')->insertOrIgnore([
            'clave' => '571-correcciones-v1',
            'descripcion' => 'Item #571: deuda fantasma del cobro en caja (facturas duplicadas o abiertas sin saldo)',
            'estado' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_correcciones');
        Schema::dropIfExists('facturacion_corridas');
    }
};
