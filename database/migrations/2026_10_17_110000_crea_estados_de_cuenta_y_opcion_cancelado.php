<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-02 (#536, decisión de Irving 2026-10-17): «Cancelado» como baja definitiva. Aditiva e idempotente:
 *  - client_estados_cuenta: estados de cuenta generados (el «final» al pasar a Cancelado; los
 *    mensuales de la Ronda 2 usarán la misma tabla). Es un documento sin valor fiscal; generarlo
 *    NO lo encola para envío (billing_notifications no se toca);
 *  - el campo «Estado» de la ficha ofrece «Cancelado» (antes solo se llegaba con el botón borrar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_estados_cuenta')) {
            Schema::create('client_estados_cuenta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->string('tipo', 20);                 // final | mensual
                $table->string('clave', 20);                // final: AAAA-MM-DD del cambio; mensual: AAAA-MM
                $table->string('estado_cliente', 30)->nullable();
                $table->decimal('saldo', 12, 2)->default(0); // saldo de la cuenta (negativo = adeudo)
                $table->decimal('adeudo', 12, 2)->default(0);
                $table->unsignedInteger('facturas_pendientes')->default(0);
                $table->string('pdf_path', 500)->nullable();
                $table->string('pdf_sha256', 64)->nullable();
                $table->string('origen', 20)->default('sistema'); // usuario | sistema | pago
                $table->unsignedBigInteger('generado_por')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->unique(['client_id', 'tipo', 'clave']);
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            });
        }

        $this->opcionCancelado(true);
    }

    /** Agrega (o quita, en down) «Cancelado» a las opciones del campo Estado de la ficha. */
    private function opcionCancelado(bool $agregar): void
    {
        $moduleId = DB::table('modules')->where('name', 'ClientMainInformation')->value('id');
        if (! $moduleId) {
            return;
        }
        $campo = DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'estado')->first();
        if (! $campo) {
            return;
        }
        $opciones = json_decode((string) $campo->options, true);
        if (! is_array($opciones)) {
            return;
        }
        if ($agregar && ! array_key_exists('Cancelado', $opciones)) {
            $opciones['Cancelado'] = 'Cancelado (baja definitiva, no se le factura)';
        } elseif (! $agregar && array_key_exists('Cancelado', $opciones)) {
            unset($opciones['Cancelado']);
        } else {
            return;
        }
        DB::table('field_modules')->where('id', $campo->id)->update(['options' => json_encode($opciones), 'updated_at' => now()]);
    }

    public function down(): void
    {
        $this->opcionCancelado(false);
        Schema::dropIfExists('client_estados_cuenta');
    }
};
