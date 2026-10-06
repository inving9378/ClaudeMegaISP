<?php

namespace App\Modules\Addons\Hub\Services;

use App\Models\Core\ApiIntegration;
use App\Modules\Addons\Hub\Models\ServicioAsignacion;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\WhatsAppAgent\Contracts\WhatsAppProviderInterface;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppProviderFactory;
use Illuminate\Support\Facades\Log;

/**
 * Punto único para que un módulo use "su" servicio externo (WhatsApp, correo,
 * mapas, stock, redes), según Integraciones → "Módulos de servicios".
 *
 *   Servicios::whatsapp('flotas.geocercas')->sendText($jid, $texto);
 *   app(WhatsAppGateway::class)->sendText(Servicios::slugWhatsApp('pagos.pago_recibido'), $tel, $texto);
 *   Servicios::integracion('marketing.broll')?->value
 *
 * A diferencia de la IA, sin asignación (o con una asignación que ya no se
 * puede usar) el módulo sigue como siempre: decisión de Irving 2026-10-06.
 */
class Servicios
{
    public const SERVICIOS = [
        'whatsapp'       => 'WhatsApp',
        'correo'         => 'Correo',
        'mapas'          => 'Mapas',
        'imagenes_stock' => 'Imágenes y videos de stock',
        'redes_sociales' => 'Redes sociales',
    ];

    /** Entrada del catálogo. Las claves llevan punto: no usar config("servicios_modulos.$clave"). */
    public static function modulo(string $clave): ?array
    {
        return config('servicios_modulos', [])[$clave] ?? null;
    }

    public static function asignacion(string $clave): ?ServicioAsignacion
    {
        try {
            return ServicioAsignacion::with(['linea', 'integracion'])->where('clave', $clave)->first();
        } catch (\Throwable) {
            return null; // tabla aún no migrada: como siempre
        }
    }

    /** Línea de WhatsApp asignada y utilizable (activa). Null = la de siempre. */
    public static function lineaWhatsApp(string $clave): ?WhatsAppInstance
    {
        $linea = self::asignacion($clave)?->linea;
        if (!$linea) {
            return null;
        }
        if (!$linea->active) {
            Log::warning("[Servicios] La línea «{$linea->name}» asignada a {$clave} está inactiva; se usa la de siempre");
            return null;
        }
        return $linea;
    }

    /** Slug para los envíos que pasan por el gateway (sendText/sendAndLog). Null = línea principal, como siempre. */
    public static function slugWhatsApp(string $clave): ?string
    {
        return self::lineaWhatsApp($clave)?->slug;
    }

    /**
     * Proveedor de WhatsApp para los envíos directos. Con línea asignada manda
     * por ESA línea (Evolution con su instancia, u oficial de Meta); sin ella,
     * exactamente lo de siempre (WhatsAppProviderFactory::forDefault).
     */
    public static function whatsapp(string $clave, int $companyId = 1): WhatsAppProviderInterface
    {
        $linea = self::lineaWhatsApp($clave);
        if (!$linea) {
            return WhatsAppProviderFactory::forDefault($companyId);
        }

        // Las líneas son de Meganet (empresa 1) aunque el aviso sea de otra
        // empresa (Flotas por cliente): sus credenciales son las de la empresa 1.
        $proveedor = WhatsAppProviderFactory::forInstance($linea, 1);
        if ($proveedor instanceof EvolutionApiService) {
            $proveedor->usarInstancia($linea->slug);
        }
        return $proveedor;
    }

    /** Integración del Hub asignada y utilizable (activa, no borrada). Null = la de siempre. */
    public static function integracion(string $clave): ?ApiIntegration
    {
        $integracion = self::asignacion($clave)?->integracion;
        if (!$integracion) {
            return null;
        }
        if (!$integracion->active) {
            Log::warning("[Servicios] La integración «{$integracion->name}» asignada a {$clave} está inactiva; se usa la de siempre");
            return null;
        }
        return $integracion;
    }

    /** Módulos de servicio que dependen de esta integración (para avisar antes de borrarla). */
    public static function usosDeIntegracion(ApiIntegration $integracion): array
    {
        return self::nombres(ServicioAsignacion::where('api_integration_id', $integracion->id)->pluck('clave'));
    }

    /** Módulos de servicio que mandan por esta línea de WhatsApp. */
    public static function usosDeLinea(WhatsAppInstance $linea): array
    {
        return self::nombres(ServicioAsignacion::where('whatsapp_instance_id', $linea->id)->pluck('clave'));
    }

    private static function nombres($claves): array
    {
        try {
            return collect($claves)->map(fn($c) => self::modulo($c)['nombre'] ?? $c)->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
