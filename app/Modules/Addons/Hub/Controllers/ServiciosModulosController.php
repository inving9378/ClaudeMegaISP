<?php

namespace App\Modules\Addons\Hub\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationProvider;
use App\Models\MapCredential;
use App\Modules\Addons\Hub\Models\ServicioAsignacion;
use App\Modules\Addons\Hub\Services\Correo;
use App\Modules\Addons\Hub\Services\Servicios;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppProviderFactory;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pestaña "Módulos de servicios" del Integration Hub: qué línea de WhatsApp o
 * qué integración (correo, mapas, stock, redes) usa cada módulo. Sin
 * asignación el módulo sigue como siempre (decisión de Irving 2026-10-06).
 */
class ServiciosModulosController extends Controller
{
    /** Proveedor "de siempre" para los servicios que hoy toman la integración principal del Hub. */
    private const PROVEEDOR_DE_SIEMPRE = ['imagenes_stock' => 'pexels', 'redes_sociales' => 'meta'];

    public function __construct(private ApiIntegrationService $hub)
    {
        $this->middleware('permission:view-integrations')->only(['index']);
        $this->middleware('permission:manage-integrations')->only(['update']);
        $this->middleware('permission:validate-integrations')->only(['probar']);
    }

    public function index(): JsonResponse
    {
        $asignaciones = ServicioAsignacion::all()->keyBy('clave');
        $principal = WhatsAppInstance::active()->default()->first();

        $modulos = collect(config('servicios_modulos', []))->map(function ($m, $clave) use ($asignaciones, $principal) {
            $a = $asignaciones->get($clave);
            $servicio = $m['servicio'];
            return [
                'clave'           => $clave,
                'nombre'          => $m['nombre'] ?? $clave,
                'grupo'           => $m['grupo'] ?? 'Otros',
                'servicio'        => $servicio,
                'servicio_nombre' => Servicios::SERVICIOS[$servicio] ?? $servicio,
                'via'             => $m['via'] ?? null,
                'listo'           => (bool) ($m['listo'] ?? false),
                'destino_id'      => $servicio === 'whatsapp' ? $a?->whatsapp_instance_id : $a?->api_integration_id,
                'como_siempre'    => $this->comoSiempre($m, $principal),
            ];
        })->values();

        $lineas = WhatsAppInstance::orderBy('name')->get()->map(fn(WhatsAppInstance $l) => [
            'id'        => $l->id,
            'nombre'    => $l->name,
            'slug'      => $l->slug,
            'proveedor' => $l->provider === 'meta' ? 'meta' : 'evolution',
            'estado'    => $l->status,
            'activa'    => (bool) $l->active,
            'principal' => (bool) $l->default_instance,
        ])->values();

        $servicioPorProveedor = ApiIntegrationProvider::where('type', 'servicios')->whereNotNull('servicio')->pluck('servicio', 'slug');
        $integraciones = ApiIntegration::forCompany(1)->where('type', 'servicios')->orderBy('name')->get()
            ->filter(fn($i) => isset($servicioPorProveedor[$i->provider]) && $servicioPorProveedor[$i->provider] !== 'whatsapp')
            ->map(fn(ApiIntegration $i) => [
                'id'                 => $i->id,
                'nombre'             => $i->name,
                'proveedor'          => $i->provider,
                'servicio'           => $servicioPorProveedor[$i->provider],
                'activa'             => (bool) $i->active,
                'tiene_llave'        => (bool) $i->encrypted_value,
                'estado_validacion'  => $i->last_validation_status,
                'mensaje_validacion' => $i->last_validation_status ? $this->hub->ultimoMensajeValidacion($i) : null,
            ])->values();

        return response()->json(['data' => [
            'modulos'       => $modulos,
            'lineas'        => $lineas,
            'integraciones' => $integraciones,
            'servicios'     => Servicios::SERVICIOS,
        ]]);
    }

    public function update(Request $request, string $clave): JsonResponse
    {
        $modulo = Servicios::modulo($clave);
        if (!$modulo) {
            return response()->json(['error' => 'Módulo desconocido'], 404);
        }

        $request->validate(['destino_id' => 'nullable|integer']);
        $destino = $request->input('destino_id');

        // Quitar la asignación = el módulo vuelve a lo de siempre
        if (!$destino) {
            ServicioAsignacion::where('clave', $clave)->delete();
            return response()->json(['message' => 'Asignación quitada']);
        }

        if ($modulo['servicio'] === 'whatsapp') {
            $linea = WhatsAppInstance::find($destino);
            if (!$linea) {
                return response()->json(['error' => 'Esa línea de WhatsApp no existe'], 422);
            }
            if (!$linea->active) {
                return response()->json(['error' => "La línea «{$linea->name}» está inactiva"], 422);
            }
            $datos = ['whatsapp_instance_id' => $linea->id, 'api_integration_id' => null];
        } else {
            $integracion = ApiIntegration::forCompany(1)->find($destino);
            $servicio = $integracion
                ? ApiIntegrationProvider::where('slug', $integracion->provider)->value('servicio')
                : null;
            if (!$integracion || $servicio !== $modulo['servicio']) {
                return response()->json(['error' => 'Esa integración no da el servicio que este módulo necesita'], 422);
            }
            if (!$integracion->active) {
                return response()->json(['error' => "La integración «{$integracion->name}» está inactiva"], 422);
            }
            $datos = ['api_integration_id' => $integracion->id, 'whatsapp_instance_id' => null];
        }

        ServicioAsignacion::updateOrCreate(['clave' => $clave], $datos);

        return response()->json(['message' => 'Guardado']);
    }

    /**
     * Prueba lo que el módulo usaría AHORA (lo asignado o lo de siempre), sin
     * mandar mensajes ni correos: estado de la línea, conexión SMTP, validez de
     * la llave.
     */
    public function probar(string $clave): JsonResponse
    {
        $modulo = Servicios::modulo($clave);
        if (!$modulo) {
            return response()->json(['error' => 'Módulo desconocido'], 404);
        }

        try {
            [$ok, $mensaje] = match ($modulo['servicio']) {
                'whatsapp' => $this->probarWhatsApp($clave, $modulo),
                'correo'   => $this->probarCorreo($clave, $modulo),
                'mapas'    => $this->probarMapas($clave),
                default    => $this->probarIntegracion($clave, $modulo['servicio']),
            };
        } catch (\Throwable $e) {
            [$ok, $mensaje] = [false, 'Error al probar: ' . mb_substr($e->getMessage(), 0, 200)];
        }

        return response()->json(['data' => ['ok' => $ok, 'mensaje' => $mensaje]]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function comoSiempre(array $m, ?WhatsAppInstance $principal): string
    {
        return match ($m['servicio']) {
            'whatsapp' => $principal
                ? "Línea principal ({$principal->name})"
                : (($m['via'] ?? null) === 'linea'
                    ? 'Línea principal (hoy no hay ninguna: este aviso no sale)'
                    : 'Instancia de Marketing (no hay línea principal)'),
            'correo' => ($m['defecto'] ?? 'env') === 'email_settings'
                ? 'Cuenta de Configuración → Correo'
                : 'Servidor de correo del sistema',
            'mapas'          => 'Llave de Configuración → Mapas',
            'imagenes_stock' => 'Integración principal de Pexels',
            'redes_sociales' => 'Integración principal de Meta',
            default          => 'Lo de siempre',
        };
    }

    /** @return array{0:bool, 1:string} */
    private function probarWhatsApp(string $clave, array $modulo): array
    {
        $linea = Servicios::lineaWhatsApp($clave) ?? WhatsAppInstance::active()->default()->first();

        if (!$linea) {
            if (($modulo['via'] ?? null) === 'linea') {
                return [false, 'No hay línea principal: este aviso hoy no sale. Asígnale una línea.'];
            }
            $estado = $this->estadoEvolution(Servicios::whatsapp($clave));
            return [$estado === 'open', $estado === 'open'
                ? 'La instancia de Marketing está conectada.'
                : "La instancia de Marketing no está conectada (estado: {$estado})."];
        }

        $proveedor = WhatsAppProviderFactory::forInstance($linea, 1);
        if ($proveedor instanceof EvolutionApiService) {
            $proveedor->usarInstancia($linea->slug);
            $estado = $this->estadoEvolution($proveedor);
            return [$estado === 'open', $estado === 'open'
                ? "La línea «{$linea->name}» está conectada."
                : "La línea «{$linea->name}» no está conectada (estado: {$estado}). Escanea su código QR en WhatsApp → Líneas."];
        }

        // Línea oficial de Meta
        $st = $proveedor->fetchInstanceStatus();
        $ok = ($st['state'] ?? null) === 'open';
        $aviso = ($modulo['via'] ?? null) !== null
            ? ' Ojo: Meta no deja escribir primero; este aviso solo sale si el cliente escribió en las últimas 24 horas.'
            : '';
        return [$ok, $ok
            ? "La línea oficial «{$linea->name}» tiene sus credenciales de Meta.{$aviso}"
            : "La línea oficial «{$linea->name}»: " . ($st['reason'] ?? 'faltan credenciales de Meta.')];
    }

    private function estadoEvolution($proveedor): string
    {
        try {
            $st = $proveedor->fetchInstanceStatus();
            return (string) (data_get($st, 'instance.state') ?? data_get($st, 'state') ?? 'desconocido');
        } catch (\Throwable $e) {
            return 'sin respuesta de Evolution';
        }
    }

    /** @return array{0:bool, 1:string} */
    private function probarCorreo(string $clave, array $modulo): array
    {
        $integracion = Servicios::integracion($clave);
        if ($integracion) {
            [$ok, $mensaje] = Correo::probarConfig(Correo::configDe($integracion));
            return [$ok, "«{$integracion->name}»: {$mensaje}"];
        }
        return Correo::probarConfig(Correo::configPorDefecto($modulo['defecto'] ?? 'env'));
    }

    /** @return array{0:bool, 1:string} */
    private function probarMapas(string $clave): array
    {
        if ($integracion = Servicios::integracion($clave)) {
            return $this->validarIntegracion($integracion);
        }

        $llave = MapCredential::first()?->api_key;
        if (!$llave) {
            return [false, 'No hay llave en Configuración → Mapas.'];
        }
        [$ok, $mensaje] = $this->hub->validarGoogleMaps($llave);
        return [$ok, "Llave de Configuración → Mapas: {$mensaje}"];
    }

    /** @return array{0:bool, 1:string} */
    private function probarIntegracion(string $clave, string $servicio): array
    {
        $integracion = Servicios::integracion($clave)
            ?? (isset(self::PROVEEDOR_DE_SIEMPRE[$servicio]) ? $this->hub->getIntegration(self::PROVEEDOR_DE_SIEMPRE[$servicio]) : null);

        return $integracion
            ? $this->validarIntegracion($integracion)
            : [false, 'No hay integración principal para este servicio. Créala en la pestaña Integraciones.'];
    }

    /** @return array{0:bool, 1:string} */
    private function validarIntegracion(ApiIntegration $integracion): array
    {
        if (!$this->hub->puedeValidar($integracion)) {
            return [false, "«{$integracion->name}» no tiene prueba automática."];
        }
        $r = $this->hub->validate($integracion);
        $mensaje = $r['message'] === 'OK' ? 'la llave funciona.' : $r['message'];
        return [(bool) $r['success'], "«{$integracion->name}»: {$mensaje}"];
    }
}
