<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-05 (#539, decisión de Irving 2026-10-17): «Envío prefactura» = días antes del día de
 * facturación en que sale la prefactura. Sustituye a delay_days de #512/#513 (que se deja como
 * estaba, sin uso). No reutiliza reminder_1/2/3_days. Aditiva:
 *  - billing_config.envio_prefactura_dias: valor general, 5 por defecto (editable en Configuración
 *    › Notificaciones de finanzas › Facturas proforma). No toca envio_pausado ni nada más;
 *  - billing_configurations.envio_prefactura_dias: por cliente; vacío = usa el general;
 *  - el campo en la Configuración de facturación de la ficha (recurrente y personalizado).
 * El interruptor BILLING_PROFORMA_ANTICIPADA sigue apagado: nada de esto cambia qué se genera hoy.
 */
return new class extends Migration
{
    private const MODULOS = ['ClientBillingConfigurationRecurrent' => 'billing_date', 'ClientBillingConfigurationCustom' => 'minimum_balance'];

    public function up(): void
    {
        if (! Schema::hasColumn('billing_config', 'envio_prefactura_dias')) {
            Schema::table('billing_config', function (Blueprint $table) {
                $table->unsignedTinyInteger('envio_prefactura_dias')->default(5);
            });
        }
        if (! Schema::hasColumn('billing_configurations', 'envio_prefactura_dias')) {
            Schema::table('billing_configurations', function (Blueprint $table) {
                $table->unsignedTinyInteger('envio_prefactura_dias')->nullable();
            });
        }

        foreach (self::MODULOS as $modulo => $despuesDe) {
            $moduleId = DB::table('modules')->where('name', $modulo)->value('id');
            if (! $moduleId || DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'envio_prefactura_dias')->exists()) {
                continue;
            }
            $posicion = (int) (DB::table('field_modules')->where('module_id', $moduleId)->where('name', $despuesDe)->value('position') ?? 99);
            DB::table('field_modules')->insert([
                'module_id' => $moduleId, 'name' => 'envio_prefactura_dias', 'type' => '15', 'include' => 1,
                'label' => 'Envío prefactura (días antes del día de facturación)',
                'placeholder' => 'Vacío = valor general',
                'hint' => 'Vacío usa el valor general de Configuración › Notificaciones de finanzas › Facturas proforma.',
                'options' => '[]', 'position' => $posicion, 'partition' => '', 'additional_field' => 0, 'class_col' => 'full',
                'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                'class_field' => 'col-sm-12 col-md-9', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::MODULOS) as $modulo) {
            $moduleId = DB::table('modules')->where('name', $modulo)->value('id');
            if ($moduleId) {
                DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'envio_prefactura_dias')->delete();
            }
        }
        foreach (['billing_configurations', 'billing_config'] as $tabla) {
            if (Schema::hasColumn($tabla, 'envio_prefactura_dias')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropColumn('envio_prefactura_dias');
                });
            }
        }
    }
};
