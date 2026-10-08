<?php

namespace App\Modules\Addons\MonitoreoOficial\Services;

use App\Modules\Addons\MonitoreoOficial\Models\MonitoringModuleConnection;
use Illuminate\Support\Facades\Schema;

/**
 * Descubre, de forma DEFENSIVA, qué servicios compartidos están disponibles en
 * ESTA instancia de MEGAISP y registra el resultado en monitoring_module_connections.
 *
 * Nunca asume que WhatsApp, IA o el Hub estén presentes: una VM de otro ISP puede
 * no tenerlos instalados. Cada capacidad se evalúa en hasta 3 niveles:
 *   1. ¿Existe el CÓDIGO?            class_exists(...)
 *   2. ¿Está el módulo INSTALADO?    module_registry.active = true
 *   3. ¿Está CONFIGURADO?            credenciales/instancia presentes
 *
 * Estados: disponible | sin_config | sin_conexion | no_instalado | error.
 */
class CapabilityDetector
{
    private const CONSUMER = 'addon-monitoreo-oficial';

    /** Refresca TODAS las conexiones (idempotente). */
    public function refreshConnections(): void
    {
        if (! Schema::hasTable('monitoring_module_connections')) {
            return; // aún no migrado
        }

        foreach ($this->detect() as $c) {
            $disponible = $c['status'] === 'disponible';
            MonitoringModuleConnection::updateOrCreate(
                [
                    'consumer_module' => self::CONSUMER,
                    'provider_module' => $c['provider'],
                    'capability'      => $c['capability'],
                ],
                [
                    'contract_version' => $c['version'] ?? null,
                    'status'           => $c['status'],
                    'last_checked_at'  => now(),
                    'connected_at'     => $disponible ? now() : null,
                    'disconnected_at'  => $disponible ? null : now(),
                    'error_message'    => $c['error'] ?? null,
                    'metadata'         => $c['metadata'] ?? null,
                ]
            );
        }
    }

    /** Marca todo como desconectado (desinstalación con keepData). */
    public function markAllDisconnected(string $reason = 'disconnected'): void
    {
        if (! Schema::hasTable('monitoring_module_connections')) {
            return;
        }
        MonitoringModuleConnection::where('consumer_module', self::CONSUMER)->update([
            'status'          => 'no_instalado',
            'disconnected_at' => now(),
            'error_message'   => $reason,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function detect(): array
    {
        return [
            // Correo: capacidad de NÚCLEO (Laravel Mail). Disponible si hay remitente configurado.
            [
                'provider'   => 'core-mail',
                'capability' => 'notification.email.send',
                'status'     => config('mail.from.address') ? 'disponible' : 'sin_config',
                'metadata'   => ['mailer' => config('mail.default')],
            ],
            // Campana interna: tabla Laravel `notifications` (compartida del núcleo).
            [
                'provider'   => 'core-bell',
                'capability' => 'notification.internal.send',
                'status'     => Schema::hasTable('notifications') ? 'disponible' : 'sin_conexion',
            ],
            // WhatsApp: add-on OPCIONAL. Puede no existir en esta VM.
            $this->detectWhatsApp(),
            // IA / OpenAI: add-on OPCIONAL.
            $this->detectIa(),
        ];
    }

    private function detectWhatsApp(): array
    {
        $base = [
            'provider'   => 'addon-whatsapp-agent',
            'capability' => 'notification.whatsapp.send',
        ];
        $clase = 'App\\Modules\\Addons\\WhatsAppAgent\\Services\\WhatsAppGateway';
        if (! class_exists($clase)) {
            return $base + ['status' => 'no_instalado'];
        }
        if (! $this->addonActivo('addon-whatsapp-agent')) {
            return $base + ['status' => 'no_instalado'];
        }
        return $base + ['status' => 'disponible'];
    }

    private function detectIa(): array
    {
        $base = [
            'provider'   => 'addon-ia',
            'capability' => 'ai.document.analyze',
        ];
        $clase = 'App\\Modules\\Addons\\IA\\Services\\IA';
        if (! class_exists($clase)) {
            return $base + ['status' => 'no_instalado'];
        }
        if (! $this->addonActivo('addon-ia')) {
            return $base + ['status' => 'no_instalado'];
        }
        // Código presente y módulo activo: falta validar que la clave del módulo
        // esté configurada (proveedor asignado en Integraciones → Módulos IA).
        try {
            $clave = config('monitoreo.service_key', 'monitoreo-oficial');
            if (method_exists($clase, 'configurada') && $clase::configurada($clave)) {
                return $base + ['status' => 'disponible'];
            }
            return $base + ['status' => 'sin_config'];
        } catch (\Throwable $e) {
            return $base + ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /** ¿El add-on está instalado y activo en el registry del núcleo? */
    private function addonActivo(string $slug): bool
    {
        if (! Schema::hasTable('module_registry')) {
            return false;
        }
        try {
            return \App\Modules\Core\ModuleManager\Models\ModuleRegistry::query()
                ->where('slug', $slug)
                ->where('active', true)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
