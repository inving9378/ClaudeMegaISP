<?php

namespace App\Modules\Addons\GestionRed\Controllers\OLTs;

use App\Http\Controllers\Controller;
use App\Models\OltSmartoltConfig;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class OLTsConfigController extends Controller
{
    public function getSmartoltConfig(): JsonResponse
    {
        $cfg   = OltSmartoltConfig::current();
        $token = $cfg->exists ? $cfg->api_token : '';

        return response()->json([
            'api_domain'    => $cfg->api_domain ?? '',
            'token_set'     => $token !== '',
            'token_masked'  => $token !== ''
                ? substr($token, 0, 4) . str_repeat('*', max(0, strlen($token) - 8)) . substr($token, -4)
                : null,
            'ttl'           => $cfg->ttl ?? 120,
            'hourly_budget' => $cfg->hourly_budget ?? 1000,
            'activa'        => $cfg->activa ?? false,
        ]);
    }

    public function saveSmartoltConfig(Request $request): JsonResponse
    {
        $request->validate([
            'api_domain'    => 'required|string|max:100',
            'api_token'     => 'nullable|string|max:500',
            'ttl'           => 'integer|min:30|max:3600',
            'hourly_budget' => 'integer|min:100|max:10000',
            'activa'        => 'boolean',
        ]);

        $cfg = OltSmartoltConfig::firstOrNew(['id' => 1]);
        $cfg->api_domain    = trim($request->api_domain);
        if ($request->filled('api_token')) {
            $cfg->api_token = trim($request->api_token);
        }
        $cfg->ttl           = (int) $request->input('ttl', 120);
        $cfg->hourly_budget = (int) $request->input('hourly_budget', 1000);
        $cfg->activa        = $request->boolean('activa');
        $cfg->save();

        // Invalidar el singleton para que el próximo uso tome las nuevas credenciales
        app()->forgetInstance('SmartOlt');

        return response()->json(['success' => true, 'message' => 'Configuración guardada correctamente.']);
    }

    public function testSmartoltConnection(Request $request): JsonResponse
    {
        $request->validate([
            'api_domain' => 'required|string|max:100',
            'api_token'  => 'required|string|max:500',
        ]);

        // '__use_saved__' es el centinela del frontend cuando no se ha cambiado el token
        $token = trim($request->api_token) === '__use_saved__'
            ? OltSmartoltConfig::current()->api_token
            : trim($request->api_token);

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay token guardado. Ingresa el token para probar la conexión.',
            ]);
        }

        try {
            $client   = new Client([
                'base_uri' => 'https://' . trim($request->api_domain) . '.smartolt.com/api/',
                'headers'  => ['X-Token' => $token],
                'timeout'  => 10,
                'verify'   => env('VERIFY_SSL', true),
            ]);
            $response = $client->get('system/get_olts');
            $body     = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() === 200 && isset($body['response'])) {
                $count = count($body['response']);
                return response()->json([
                    'success' => true,
                    'message' => "Conexión exitosa. Se encontraron {$count} OLT(s).",
                    'olts'    => $count,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Respuesta inesperada de SmartOLT. Verifica el dominio y el token.',
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            return response()->json([
                'success' => false,
                'message' => "SmartOLT respondió HTTP {$status}. Verifica que el token sea válido.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
            ]);
        }
    }

    public function importInventory(): JsonResponse
    {
        if (!OltSmartoltConfig::isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Configura y activa las credenciales antes de importar.',
            ]);
        }

        // Despacha el comando como job en segundo plano (queue: default)
        Artisan::queue('smartolt:sync-inventory');

        return response()->json([
            'success' => true,
            'message' => 'Importación iniciada en segundo plano. Las OLTs y ONUs se actualizarán en los próximos minutos.',
        ]);
    }
}
