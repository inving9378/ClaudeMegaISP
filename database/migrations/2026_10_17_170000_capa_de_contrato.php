<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-07 (#541, decisión de Irving 2026-10-17): capa de contrato. Aditiva e idempotente:
 *  - billing_configurations.facturas_max_sin_pago: «Facturas máximas sin pago» (default 3) para el
 *    contrato cumplido y sin autorrenovación. grace_period NO se toca;
 *  - el campo en la Configuración de facturación (pago recurrente);
 *  - client_contrato_cargos: cada cargo sin saldo que autoriza la capa (para contar las facturas
 *    impagas seguidas desde el último pago). Solo registra desde que la capa se enciende.
 * La capa está apagada (BILLING_CAPA_CONTRATO=false): esta migración no cambia ningún cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_configurations', 'facturas_max_sin_pago')) {
            Schema::table('billing_configurations', function (Blueprint $table) {
                $table->unsignedTinyInteger('facturas_max_sin_pago')->default(3);
            });
        }

        if (! Schema::hasTable('client_contrato_cargos')) {
            Schema::create('client_contrato_cargos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->date('ciclo');                       // fecha de facturación cobrada (fecha_pago)
                $table->string('estado_contrato', 20);       // vigente | renovado | cumplido
                $table->unsignedSmallInteger('plazo_numero')->default(1);
                $table->timestamp('created_at')->nullable();

                $table->unique(['client_id', 'ciclo']);
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            });
        }

        $moduleId = DB::table('modules')->where('name', 'ClientBillingConfigurationRecurrent')->value('id');
        if ($moduleId && ! DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'facturas_max_sin_pago')->exists()) {
            $posicion = (int) (DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'grace_period')->value('position') ?? 99);
            DB::table('field_modules')->insert([
                'module_id' => $moduleId, 'name' => 'facturas_max_sin_pago', 'type' => '15', 'include' => 1,
                'label' => 'Facturas máximas sin pago (contrato cumplido)', 'placeholder' => '3',
                'hint' => 'Con el contrato cumplido y sin autorrenovación: cuántas facturas seguidas sin pago se emiten antes de dejar de facturar y pasarlo a inactivo.',
                'options' => '[]', 'position' => $posicion, 'partition' => '', 'additional_field' => 0, 'class_col' => 'full',
                'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                'class_field' => 'col-sm-12 col-md-9', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('name', 'ClientBillingConfigurationRecurrent')->value('id');
        if ($moduleId) {
            DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'facturas_max_sin_pago')->delete();
        }
        Schema::dropIfExists('client_contrato_cargos');
        if (Schema::hasColumn('billing_configurations', 'facturas_max_sin_pago')) {
            Schema::table('billing_configurations', function (Blueprint $table) {
                $table->dropColumn('facturas_max_sin_pago');
            });
        }
    }
};
