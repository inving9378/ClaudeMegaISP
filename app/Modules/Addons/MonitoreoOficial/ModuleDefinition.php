<?php

namespace App\Modules\Addons\MonitoreoOficial;

use App\Modules\Addons\MonitoreoOficial\Services\CapabilityDetector;
use App\Modules\Addons\MonitoreoOficial\Services\ImpiDemoSeeder;
use App\Modules\Contracts\ModuleDefinition as BaseDefinition;
use App\Support\RolInstancia;
use Illuminate\Support\Facades\Log;

class ModuleDefinition extends BaseDefinition
{
    public function moduleDir(): string
    {
        return __DIR__;
    }

    /**
     * Hook de instalación. Corre DENTRO de la transacción del instalador, DESPUÉS
     * de que las migraciones del módulo ya crearon las tablas monitoring_*.
     */
    public function install(): void
    {
        // 1) Detectar qué servicios opcionales existen en ESTA instancia y
        //    registrarlos en monitoring_module_connections (idempotente).
        //    Nunca asume que WhatsApp/IA/Hub están presentes.
        try {
            app(CapabilityDetector::class)->refreshConnections();
        } catch (\Throwable $e) {
            Log::warning('[MonitoreoOficial] refreshConnections en install falló: ' . $e->getMessage());
        }

        // 2) Expediente de ejemplo de MEGANET (IMPI 2806655):
        //    NO es dato inicial universal. Solo se siembra solo en la instancia
        //    MEGANET (operador). En cualquier otra VM de ISP jamás entra en el
        //    install genérico; ahí solo por importación expresa (comando).
        if ($this->debeSembrarDemo()) {
            try {
                app(ImpiDemoSeeder::class)->seed('install-operador');
            } catch (\Throwable $e) {
                Log::warning('[MonitoreoOficial] seed demo IMPI en install falló: ' . $e->getMessage());
            }
        }
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        // Re-evaluar capacidades tras actualizar (p.ej. nuevos canales).
        try {
            app(CapabilityDetector::class)->refreshConnections();
        } catch (\Throwable $e) {
            Log::warning('[MonitoreoOficial] refreshConnections en upgrade falló: ' . $e->getMessage());
        }
    }

    /**
     * El borrado/preservación de las tablas monitoring_* lo maneja el rollback de
     * migraciones del instalador según $keepData. Aquí NO tocamos tablas
     * compartidas del núcleo. Solo marcamos las conexiones como desconectadas si
     * las tablas aún existen (desinstalación con keepData).
     */
    public function uninstall(bool $keepData = false): void
    {
        try {
            app(CapabilityDetector::class)->markAllDisconnected('module_uninstalled');
        } catch (\Throwable $e) {
            Log::warning('[MonitoreoOficial] markAllDisconnected en uninstall falló: ' . $e->getMessage());
        }
    }

    /**
     * ¿Auto-sembrar el expediente demo de MEGANET en este install?
     * - config null (default) → solo si la instancia es operador (MEGANET).
     * - config true/false → fuerza/veta explícitamente.
     */
    private function debeSembrarDemo(): bool
    {
        $flag = config('monitoreo.auto_seed_demo', null);
        if ($flag === null) {
            return RolInstancia::esOperador();
        }
        return (bool) $flag;
    }
}
