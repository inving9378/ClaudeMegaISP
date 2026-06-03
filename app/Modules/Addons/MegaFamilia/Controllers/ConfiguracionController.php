<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Services\FcmService;
use App\Modules\Addons\MegaFamilia\Services\MegaFamiliaSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Settings globales del módulo. Persistencia en tabla `megafamilia_settings`
 * vía MegaFamiliaSettingsService (item #73); el cache es solo acelerador.
 * Las secciones (firebase, general, limits) comparten el mismo namespace de keys.
 */
class ConfiguracionController extends Controller
{
    // Settings sensibles que se guardan cifrados (Crypt) en BD.
    private const SENSITIVE = ['firebase_server_key'];

    public function __construct(private MegaFamiliaSettingsService $settings)
    {
    }

    public function index()
    {
        return view('addon-megafamilia::configuracion.index');
    }

    public function get(): JsonResponse
    {
        $settings = array_merge($this->defaults(), $this->settings->all());
        $settings['mikrotik_status'] = [
            'enabled'   => $this->settings->get('mikrotik_enabled', false),
            'last_test' => $this->settings->get('mikrotik_last_test'),
            'reachable' => $this->settings->get('mikrotik_last_reachable'),
        ];
        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $section = $request->input('section');
        $rules = $this->rulesForSection($section);

        $data = $request->validate($rules);
        unset($data['section']);

        foreach ($data as $key => $value) {
            $this->settings->set($key, $value, in_array($key, self::SENSITIVE, true));
        }
        return response()->json(['success' => true, 'section' => $section]);
    }

    /**
     * Push de prueba al primer fcm_token disponible. Si el usuario tiene un
     * device, usa ese; si no, cualquier device del sistema (la prueba real
     * es que la API key acepte la llamada).
     */
    public function testFcm(Request $request, FcmService $fcm): JsonResponse
    {
        $userId = Auth::id();
        $token  = $request->input('token');

        if (!$token && $userId) {
            $token = ParentalDevice::query()
                ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
                ->whereNotNull('fcm_token')
                ->value('fcm_token');
        }
        if (!$token) {
            $token = ParentalDevice::whereNotNull('fcm_token')->value('fcm_token');
        }
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No hay ningún dispositivo con fcm_token registrado para probar.',
            ], 422);
        }

        $result = $fcm->send([$token], 'Prueba MegaFamilia', 'Tu configuración FCM funciona correctamente.', [
            'type' => 'test_push',
        ]);

        return response()->json($result);
    }

    private function rulesForSection(?string $section): array
    {
        $base = ['section' => 'required|in:firebase,general,limits'];
        return match ($section) {
            'firebase' => $base + [
                'firebase_server_key' => 'sometimes|nullable|string',
                'firebase_sender_id'  => 'sometimes|nullable|string|max:64',
                'firebase_project_id' => 'sometimes|nullable|string|max:120',
                'fcm_enabled'         => 'sometimes|boolean',
            ],
            'general' => $base + [
                'service_name'                => 'sometimes|nullable|string|max:120',
                'terms_url'                   => 'sometimes|nullable|url',
                'min_android_version'         => 'sometimes|nullable|string|max:20',
                'min_ios_version'             => 'sometimes|nullable|string|max:20',
                'grace_days_after_expiration' => 'sometimes|integer|min:0|max:365',
                'allow_signup_without_isp'    => 'sometimes|boolean',
                'require_otp_on_login'        => 'sometimes|boolean',
            ],
            'limits' => $base + [
                'max_devices_per_license'   => 'sometimes|integer|min:1|max:100',
                'max_profiles_per_account'  => 'sometimes|integer|min:1|max:50',
                'event_retention_days'      => 'sometimes|integer|min:7|max:3650',
                'location_interval_seconds' => 'sometimes|integer|min:30|max:86400',
            ],
            default => $base,
        };
    }

    private function defaults(): array
    {
        return [
            // firebase
            'firebase_server_key' => null,
            'firebase_sender_id'  => null,
            'firebase_project_id' => null,
            'fcm_enabled'         => false,
            // general
            'service_name'                => 'MegaFamilia',
            'terms_url'                   => null,
            'min_android_version'         => null,
            'min_ios_version'             => null,
            'grace_days_after_expiration' => 7,
            'allow_signup_without_isp'    => true,
            'require_otp_on_login'        => false,
            // limits
            'max_devices_per_license'   => 10,
            'max_profiles_per_account'  => 5,
            'event_retention_days'      => 90,
            'location_interval_seconds' => 300,
        ];
    }
}
