<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\WhatsAppAgent\Contracts\WhatsAppProviderInterface;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;

/**
 * Resuelve QUÉ driver de WhatsApp usar -- Evolution o la API oficial de Meta
 * -- según el campo `provider` de la línea (whatsapp_instances.provider).
 * El proveedor es CONFIGURABLE por línea, no un interruptor global: permite
 * migrar una línea a la vez sin apagar las demás mientras se tramitan las
 * plantillas de Meta.
 *
 * Los ~15 consumidores existentes que hoy hacen `new EvolutionApiService()`
 * directo deben migrar a `WhatsAppProviderFactory::forInstance($instance)` (o
 * `::forDefault()` si no tienen una instancia a la mano) para que el cambio
 * de proveedor sea real en vez de solo posible sobre el papel.
 */
class WhatsAppProviderFactory
{
    public static function forInstance(WhatsAppInstance $instance, int $companyId = 1): WhatsAppProviderInterface
    {
        return match ($instance->provider) {
            'meta'  => new MetaCloudApiService($companyId),
            default => new EvolutionApiService($companyId),
        };
    }

    /** Para consumidores sin una instancia concreta a la mano -- cae a la línea activa marcada default_instance. */
    public static function forDefault(int $companyId = 1): WhatsAppProviderInterface
    {
        $instance = WhatsAppInstance::active()->default()->first();

        return $instance
            ? self::forInstance($instance, $companyId)
            : new EvolutionApiService($companyId);
    }

    /** Para cuando solo se tiene el slug de la línea (patrón usado por sendAndLog/WhatsAppGateway). */
    public static function forSlug(?string $slug, int $companyId = 1): WhatsAppProviderInterface
    {
        if (!$slug) {
            return self::forDefault($companyId);
        }

        $instance = WhatsAppInstance::where('slug', $slug)->where('active', true)->first();

        return $instance
            ? self::forInstance($instance, $companyId)
            : self::forDefault($companyId);
    }
}
