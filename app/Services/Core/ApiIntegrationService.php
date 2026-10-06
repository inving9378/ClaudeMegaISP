<?php

namespace App\Services\Core;

use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationLog;
use App\Models\Core\ApiIntegrationProvider;
use App\Models\Core\ApiIntegrationUsage;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IAAdaptadorFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiIntegrationService
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    // ── Key retrieval ───────────────────────────────────────────────────────────

    public function getKey(string $provider, int $companyId = 1): ?string
    {
        return $this->getIntegration($provider, $companyId)?->value;
    }

    public function getIntegration(string $provider, int $companyId = 1): ?ApiIntegration
    {
        return ApiIntegration::forCompany($companyId)
            ->defaultFor($provider)
            ->first();
    }

    public function getBySlug(string $slug, int $companyId = 1): ?ApiIntegration
    {
        return ApiIntegration::forCompany($companyId)->where('slug', $slug)->first();
    }

    // ── Usage tracking ──────────────────────────────────────────────────────────

    public function trackUsage(ApiIntegration $integration, string $feature, int $calls = 1, float $costUsd = 0.0): void
    {
        try {
            ApiIntegrationUsage::upsert(
                [[
                    'integration_id' => $integration->id,
                    'company_id'     => $integration->company_id,
                    'usage_date'     => now()->toDateString(),
                    'feature'        => $feature,
                    'call_count'     => $calls,
                    'cost_usd'       => $costUsd,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]],
                ['integration_id', 'usage_date', 'feature'],
                ['call_count' => \DB::raw("call_count + {$calls}"), 'cost_usd' => \DB::raw("cost_usd + {$costUsd}"), 'updated_at' => now()]
            );
        } catch (\Throwable $e) {
            Log::warning('ApiIntegrationService: trackUsage failed', ['error' => $e->getMessage()]);
        }
    }

    // ── Audit logging ───────────────────────────────────────────────────────────

    public function audit(ApiIntegration $integration, string $eventType, array $metadata = [], ?string $actor = null): void
    {
        try {
            ApiIntegrationLog::create([
                'integration_id' => $integration->id,
                'company_id'     => $integration->company_id,
                'event_type'     => $eventType,
                'actor'          => $actor ?? (auth()->user()?->email ?? 'system'),
                'metadata'       => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ApiIntegrationService: audit failed', ['error' => $e->getMessage()]);
        }
    }

    // ── Validation ──────────────────────────────────────────────────────────────

    public function validate(ApiIntegration $integration): array
    {
        // Las integraciones de IA se prueban con el MISMO adaptador que usan los
        // módulos (por protocolo, no por nombre del proveedor): si aquí dice
        // "funciona", Módulos IA funciona con esa llave.
        $catalogo = ApiIntegrationProvider::where('slug', $integration->provider)->first();
        if ($catalogo?->type === 'ia') {
            return $this->validarIA($integration, $catalogo);
        }

        // Cuenta de correo: se conecta y autentica con el servidor (sin mandar nada)
        if ($catalogo?->servicio === 'correo') {
            [$ok, $mensaje] = \App\Modules\Addons\Hub\Services\Correo::probarConfig(
                \App\Modules\Addons\Hub\Services\Correo::configDe($integration)
            );
            return $this->validationResult($integration, $ok, $mensaje);
        }

        $key = $integration->value;

        if (!$key) {
            return $this->validationResult($integration, false, 'No key configured');
        }

        $validators = $this->serviceValidators($integration, $key);

        // Sin validador para este proveedor: NO se marca como válida (sería mentir)
        if (!isset($validators[$integration->provider])) {
            $this->audit($integration, 'validation_skipped', ['message' => 'Proveedor sin validación automática']);
            return [
                'success' => false,
                'message' => 'Este proveedor no tiene validación automática; la key no se verificó',
                'status'  => $integration->last_validation_status,
            ];
        }

        try {
            $ok = $validators[$integration->provider]();
        } catch (\Throwable $e) {
            return $this->validationResult($integration, false, $e->getMessage());
        }

        return $this->validationResult($integration, $ok, $ok ? 'OK' : 'Validation failed');
    }

    /** ¿Se puede verificar esta integración automáticamente? (IA siempre; servicios si hay validador) */
    public function puedeValidar(ApiIntegration $integration): bool
    {
        $catalogo = ApiIntegrationProvider::where('slug', $integration->provider)->first();
        return $catalogo?->type === 'ia' || $catalogo?->servicio === 'correo'
            || isset($this->serviceValidators($integration, '')[$integration->provider]);
    }

    /** Texto de la última validación (está en el historial de la integración). */
    public function ultimoMensajeValidacion(ApiIntegration $integration): ?string
    {
        $log = ApiIntegrationLog::where('integration_id', $integration->id)
            ->whereIn('event_type', ['validated', 'validation_failed'])
            ->latest('id')
            ->first();

        return $log ? data_get($log->metadata, 'message') : null;
    }

    /**
     * Prueba real de una llave de IA: un mensaje mínimo con un modelo barato,
     * por el adaptador del protocolo. Distingue llave inválida, sin saldo, etc.
     */
    private function validarIA(ApiIntegration $integration, ApiIntegrationProvider $catalogo): array
    {
        $driver = $catalogo->driver;
        if (!$driver) {
            return $this->validationResult($integration, false, 'El proveedor no tiene definido su protocolo de IA (pestaña Proveedores).');
        }

        $compatible = $driver === 'openai_compatible';
        $llave = $integration->value;
        $endpoint = data_get($integration->config, 'endpoint') ?: null;

        if (!$llave && !$compatible) {
            return $this->validationResult($integration, false, 'No tiene llave capturada.');
        }
        if ($compatible && !$endpoint) {
            return $this->validationResult($integration, false, 'Falta la URL del servicio (edita la integración).');
        }

        $modelo = data_get($integration->config, 'modelo_prueba') ?: (IA::MODELO_PRUEBA[$driver] ?? null);
        if (!$modelo) {
            [$modelo, $error] = $this->primerModeloCompatible($endpoint, $llave);
            if (!$modelo) {
                return $this->validationResult($integration, false, $error);
            }
        }

        // Proveedor en memoria con ESTA llave (no la del Hub por defecto): así se
        // prueba la integración aunque esté inactiva o no sea la principal.
        $proveedor = new IAProveedor([
            'nombre'         => $integration->name,
            'driver'         => $driver,
            'endpoint_url'   => $endpoint,
            'modelo_default' => $modelo,
            'activo'         => true,
        ]);
        $proveedor->api_key = $llave;

        try {
            IAAdaptadorFactory::crear($proveedor)->enviarMensaje(
                [], 'Responde únicamente con la palabra OK.', [], null,
                ['max_tokens' => 20, 'reintentos' => 0, 'timeout' => 25]
            );
            return $this->validationResult($integration, true, "La llave funciona (probada con {$modelo}).");
        } catch (\Throwable $e) {
            $x = IA::explicarError($e->getMessage());
            // Si el único problema es el modelo de prueba, la llave sí autenticó.
            if ($x['motivo'] === 'modelo') {
                return $this->validationResult($integration, true, "La llave es válida, pero el modelo de prueba {$modelo} no está disponible en esta cuenta.");
            }
            return $this->validationResult($integration, false, "{$x['etiqueta']}. {$x['que_hacer']}");
        }
    }

    /**
     * Servidor compatible con OpenAI sin "modelo_prueba": se toma el primer
     * modelo que anuncie en /models (eso además prueba la llave y la URL).
     *
     * @return array{0:?string, 1:string} [modelo, error]
     */
    private function primerModeloCompatible(string $endpoint, ?string $llave): array
    {
        try {
            $res = Http::timeout(10)
                ->when($llave, fn($h) => $h->withToken($llave))
                ->get(IA::baseOpenAI($endpoint) . '/models');
        } catch (\Throwable $e) {
            return [null, 'No se pudo conectar con la URL del servicio: ' . $e->getMessage()];
        }

        if (!$res->successful()) {
            $x = IA::explicarError($res->status() . ' ' . $res->body());
            return [null, "{$x['etiqueta']}. {$x['que_hacer']}"];
        }

        $modelo = data_get($res->json(), 'data.0.id') ?? data_get($res->json(), 'models.0.name');
        return $modelo
            ? [$modelo, '']
            : [null, 'El servicio respondió pero no anuncia modelos; captura un "modelo de prueba" al editar la integración.'];
    }

    /** Validadores de las integraciones de servicios (no IA), por proveedor. */
    private function serviceValidators(ApiIntegration $integration, string $key): array
    {
        return [
            'evolution'        => fn() => $this->validateEvolution($key, $integration->config ?? []),
            'whatsapp-oficial' => fn() => $this->validateWhatsappOficial($key, $integration->config ?? []),
            'pexels'           => fn() => $this->validatePexels($key),
            'google_maps'      => fn() => $this->validateGoogleMaps($key),
        ];
    }

    private function validationResult(ApiIntegration $integration, bool $ok, string $message): array
    {
        $status = $ok ? 'valid' : 'invalid';
        $integration->update([
            'last_validation_status' => $status,
            'last_validated_at'      => now(),
        ]);
        $this->audit($integration, $ok ? 'validated' : 'validation_failed', ['message' => $message]);
        return ['success' => $ok, 'message' => $message, 'status' => $status];
    }

    private function validateEvolution(string $key, array $config): bool
    {
        $url = rtrim($config['endpoint'] ?? config('whatsapp.api_url', ''), '/');
        if (!$url) {
            return false;
        }
        $res = Http::timeout(10)
            ->withHeaders(['apikey' => $key])
            ->get("{$url}/instance/fetchInstances");
        return $res->successful();
    }

    /**
     * Valida credenciales de WhatsApp Business Platform (Meta Cloud API):
     * pide el nombre verificado del numero -- si el token y el
     * phone_number_id son correctos, Meta responde 200 con `verified_name`.
     */
    private function validateWhatsappOficial(string $key, array $config): bool
    {
        $phoneNumberId = $config['phone_number_id'] ?? null;
        $apiVersion    = $config['api_version'] ?? 'v21.0';
        if (!$phoneNumberId) {
            return false;
        }
        $res = Http::timeout(10)
            ->withToken($key)
            ->get("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}", [
                'fields' => 'verified_name',
            ]);
        return $res->successful();
    }

    private function validatePexels(string $key): bool
    {
        $res = Http::timeout(10)
            ->withHeaders(['Authorization' => $key])
            ->get('https://api.pexels.com/v1/search', ['query' => 'test', 'per_page' => 1]);
        return $res->status() === 200;
    }

    private function validateGoogleMaps(string $key): bool
    {
        $res = Http::timeout(10)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => 'Mexico',
                'key'     => $key,
            ]);
        return isset($res->json()['status']) && $res->json()['status'] !== 'REQUEST_DENIED';
    }

    // ── Provider catalog ────────────────────────────────────────────────────────

    public function getProviders(): array
    {
        // Activos + los inactivos que aún tienen integraciones (para no esconderlas de la UI)
        $withIntegrations = ApiIntegration::query()->distinct()->pluck('provider');

        return \App\Models\Core\ApiIntegrationProvider::query()
            ->where(fn($q) => $q->where('active', true)->orWhereIn('slug', $withIntegrations))
            ->orderBy('type')->orderBy('name')->get()
            ->map(fn($p) => [
                'id'          => $p->slug,
                'name'        => $p->name,
                'description' => $p->description,
                'type'        => $p->type,
                'icon'        => $p->icon,
                'docs_url'    => $p->docs_url,
                'key_format'  => $p->key_format,
                'has_config'  => $p->has_config,
                'active'      => $p->active,
                'driver'      => $p->driver,
                'servicio'    => $p->servicio,
            ])->all();
    }
}
