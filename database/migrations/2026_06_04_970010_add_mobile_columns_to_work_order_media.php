<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas necesarias para la app móvil Talento Equipo a la tabla
 * talento_work_order_media existente. Totalmente aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talento_work_order_media')) {
            return;
        }

        Schema::table('talento_work_order_media', function (Blueprint $table) {
            // Lectura de potencia de señal en dBm (para bono de salud de red, Fase 4b)
            if (! Schema::hasColumn('talento_work_order_media', 'potencia_dbm')) {
                $table->decimal('potencia_dbm', 6, 2)->nullable()->after('location_distance_m')
                    ->comment('Potencia de señal medida en campo (dBm). Negativo = pérdida.');
            }

            // Precisión del GPS enviado por la app (metros)
            if (! Schema::hasColumn('talento_work_order_media', 'gps_accuracy_m')) {
                $table->decimal('gps_accuracy_m', 8, 1)->nullable()->after('potencia_dbm')
                    ->comment('Precisión del fix GPS reportada por la app (metros).');
            }

            // Canal de origen (mobile | web | field_app)
            if (! Schema::hasColumn('talento_work_order_media', 'source')) {
                $table->string('source', 30)->default('web')->after('gps_accuracy_m')
                    ->comment('Origen del upload: web | mobile | field_app.');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('talento_work_order_media')) {
            return;
        }

        Schema::table('talento_work_order_media', function (Blueprint $table) {
            $table->dropColumn(array_filter(
                ['potencia_dbm', 'gps_accuracy_m', 'source'],
                fn($col) => Schema::hasColumn('talento_work_order_media', $col)
            ));
        });
    }
};
