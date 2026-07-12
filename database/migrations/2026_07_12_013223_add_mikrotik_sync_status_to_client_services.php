<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fundamento aditivo para el item #86 (roadmap): columnas de estado de
 * sincronización con Mikrotik en los 3 tipos de servicio de cliente.
 * SUBSUME la idea temporal "service_mikrotik_sync_pending" mencionada en la
 * descripción del item. Puramente aditivo/reversible: nullable, sin default
 * que fuerce un valor en filas existentes, sin cambiar ninguna lógica de
 * negocio (el candado de MikrotikRequiredForService sigue igual).
 *
 * Alcance de ESTA migración: solo el esquema (paso 1 de 7 de la solución
 * definitiva descrita en el item). El resto (job de cola, reintentos con
 * backoff, dashboard, botón "Reintentar sync", webhook, wiring en los
 * controllers de creación de servicio) queda documentado como pendiente en
 * comentarios_claude del item — requiere decisiones de diseño de Irving
 * sobre cómo debe comportarse la creación de servicios ante fallo de
 * Mikrotik respecto a cortes/facturación (ya escalado varias veces).
 */
return new class extends Migration
{
    private array $tables = [
        'client_internet_services',
        'client_custom_services',
        'client_bundle_services',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::hasColumn($table, 'mikrotik_sync_status')) {
                    $blueprint->enum('mikrotik_sync_status', ['pending', 'synced', 'failed'])
                        ->nullable()
                        ->default(null)
                        ->after('id')
                        ->comment('Estado de sincronización con Mikrotik. NULL = no rastreado (legacy). Item roadmap #86.');
                }
                if (!Schema::hasColumn($table, 'mikrotik_sync_error')) {
                    $blueprint->text('mikrotik_sync_error')->nullable()->after('mikrotik_sync_status');
                }
                if (!Schema::hasColumn($table, 'mikrotik_synced_at')) {
                    $blueprint->timestamp('mikrotik_synced_at')->nullable()->after('mikrotik_sync_error');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (['mikrotik_sync_status', 'mikrotik_sync_error', 'mikrotik_synced_at'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
