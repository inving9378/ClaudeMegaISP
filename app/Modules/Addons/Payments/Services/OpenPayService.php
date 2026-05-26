<?php

namespace App\Modules\Addons\Payments\Services;

use App\Models\Client;
use App\Modules\Addons\Payments\Models\PaymentProvider;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper Guzzle de la API de OpenPay (openpay.mx).
 *
 * Endpoints base:
 *   producción: https://api.openpay.mx/v1/{merchant_id}
 *   sandbox:    https://sandbox-api.openpay.mx/v1/{merchant_id}
 *
 * Auth: HTTP Basic con api_key como user, password vacío.
 *
 * El config viene del modelo PaymentProvider (campo encriptado JSON):
 *   ['merchant_id' => '...', 'api_key' => '...', 'webhook_secret' => '...', 'sandbox' => true]
 *
 * IMPORTANTE: este servicio se entrega con STUBS para createClabe() y
 * getTransaction() cuando provider.config no tiene api_key — facilita
 * desarrollo sin sandbox real. Cuando lleguen las credenciales se quita
 * el bypass y queda solo el camino real (marcado TODO abajo).
 */
class OpenPayService
{
    public function __construct(
        private ?HttpClient $http = null
    ) {
        $this->http ??= new HttpClient(['timeout' => 10, 'connect_timeout' => 5]);
    }

    /**
     * Genera una CLABE virtual asociada a un cliente OpenPay.
     * Devuelve ['clabe' => '012...', 'openpay_id' => 'ab123...'].
     *
     * @throws \RuntimeException si la API responde error o falta config.
     */
    public function createClabe(Client $client, PaymentProvider $provider): array
    {
        $cfg = $provider->config ?? [];
        if ($this->credsAreStub($cfg)) {
            // TODO: remover stub cuando lleguen credenciales sandbox reales
            return $this->stubCreateClabe($client);
        }

        $base = $this->baseUrl($cfg);

        // OpenPay requiere que el cliente exista primero (POST /customers) y
        // después se le crea un "store" type charge que genera la CLABE.
        // Para mantener el servicio compacto agrupamos ambos pasos.
        try {
            $customerId = $this->ensureOpenPayCustomer($client, $cfg, $base);

            $charge = $this->postJson(
                "{$base}/customers/{$customerId}/charges",
                $cfg['api_key'],
                [
                    'method'      => 'bank_account',
                    'amount'      => 0.01, // OpenPay requiere monto >0; usamos sentinel mínimo
                    'description' => "CLABE virtual cliente {$client->id}",
                ]
            );

            if (empty($charge['payment_method']['clabe'])) {
                throw new \RuntimeException('OpenPay no devolvió campo clabe en la respuesta.');
            }

            return [
                'clabe'      => $charge['payment_method']['clabe'],
                'openpay_id' => $charge['id'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Error al crear CLABE en OpenPay: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Consulta los detalles de una transacción específica.
     */
    public function getTransaction(string $transactionId, PaymentProvider $provider): array
    {
        $cfg = $provider->config ?? [];
        if ($this->credsAreStub($cfg)) {
            // TODO: stub fallback hasta tener creds reales
            return ['id' => $transactionId, '_stub' => true];
        }

        $base = $this->baseUrl($cfg);
        try {
            $res = $this->http->get("{$base}/charges/{$transactionId}", [
                'auth' => [$cfg['api_key'], ''],
            ]);
            return json_decode((string) $res->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Error al consultar transacción OpenPay: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verifica que el webhook entrante esté firmado correctamente.
     *
     * OpenPay envía webhooks con HTTP Basic Auth (configurado al dar de alta
     * la URL en su dashboard: user+password elegidos por el merchant). El
     * "webhook_secret" guardado en provider.config es ese password.
     *
     * @param Request $request — request entrante (acceso a headers)
     * @param string  $secret  — webhook_secret guardado en provider.config
     */
    public function verifyWebhookAuth(Request $request, string $secret): bool
    {
        $authHeader = $request->header('Authorization', '');
        if (!str_starts_with(strtolower($authHeader), 'basic ')) {
            return false;
        }
        $decoded = base64_decode(substr($authHeader, 6), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return false;
        }
        [, $providedPassword] = explode(':', $decoded, 2);
        // hash_equals previene timing attacks
        return hash_equals((string) $secret, (string) $providedPassword);
    }

    /* ============================================================
     |  Internos
     * ============================================================ */

    /**
     * Detecta credenciales claramente-no-reales (placeholder, dev). Si
     * api_key/merchant_id están vacíos o son literal "test" / "stub" /
     * "demo", asumimos modo desarrollo y enrutamos a stubCreateClabe().
     * Evita golpear sandbox-api.openpay.mx con creds basura (devuelve 401
     * y trona el flujo en lugar de degradar al stub).
     */
    private function credsAreStub(array $cfg): bool
    {
        $key = strtolower(trim((string) ($cfg['api_key'] ?? '')));
        $mid = strtolower(trim((string) ($cfg['merchant_id'] ?? '')));
        if ($key === '' || $mid === '') return true;
        $stubMarkers = ['test', 'stub', 'demo', 'fake', 'placeholder'];
        return in_array($key, $stubMarkers, true) || in_array($mid, $stubMarkers, true);
    }

    private function baseUrl(array $cfg): string
    {
        $sandbox = !empty($cfg['sandbox']);
        $host = $sandbox ? 'https://sandbox-api.openpay.mx' : 'https://api.openpay.mx';
        return "{$host}/v1/{$cfg['merchant_id']}";
    }

    /**
     * Asegura que el cliente exista en OpenPay (busca por external_id local,
     * crea si no existe). Devuelve el id de OpenPay.
     */
    private function ensureOpenPayCustomer(Client $client, array $cfg, string $base): string
    {
        // Intentar búsqueda por external_id (nuestro client->id)
        try {
            $found = $this->http->get("{$base}/customers", [
                'auth'  => [$cfg['api_key'], ''],
                'query' => ['external_id' => (string) $client->id, 'limit' => 1],
            ]);
            $list = json_decode((string) $found->getBody(), true) ?? [];
            if (!empty($list[0]['id'])) {
                return $list[0]['id'];
            }
        } catch (GuzzleException $e) {
            // continúa a creación
            Log::warning('OpenPay customer lookup falló, intentando crear', ['error' => $e->getMessage()]);
        }

        // Crear cliente nuevo
        // TODO: la BD megaisp guarda nombre completo en client_main_information,
        // no directo en clients. Se extrae al armar el payload cuando esté
        // disponible; por ahora usamos id como fallback.
        $created = $this->postJson("{$base}/customers", $cfg['api_key'], [
            'name'        => "Cliente {$client->id}",
            'last_name'   => '',
            'email'       => "cliente-{$client->id}@megaisp.local",
            'external_id' => (string) $client->id,
        ]);

        if (empty($created['id'])) {
            throw new \RuntimeException('OpenPay no devolvió id al crear customer.');
        }
        return $created['id'];
    }

    private function postJson(string $url, string $apiKey, array $body): array
    {
        $res = $this->http->post($url, [
            'auth' => [$apiKey, ''],
            'json' => $body,
        ]);
        return json_decode((string) $res->getBody(), true) ?? [];
    }

    /**
     * Stub para desarrollo sin credenciales. Genera una CLABE pseudo-aleatoria
     * de 18 dígitos. NO usar en producción — el banco rechazaría depósitos a
     * esta CLABE porque no está provisionada por OpenPay.
     */
    private function stubCreateClabe(Client $client): array
    {
        Log::warning('OpenPayService stub: createClabe sin credenciales reales', ['client_id' => $client->id]);
        $rand = str_pad((string) random_int(0, 999999999999999), 15, '0', STR_PAD_LEFT);
        return [
            'clabe'      => '646' . $rand, // 646 = prefijo simulado STP
            'openpay_id' => 'stub_' . bin2hex(random_bytes(8)),
            '_stub'      => true,
        ];
    }
}
