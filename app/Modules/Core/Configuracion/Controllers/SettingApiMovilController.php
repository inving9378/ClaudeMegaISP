<?php

namespace App\Modules\Core\Configuracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Configuracion\Models\ApiMobileConfig;
use App\Modules\Core\Configuracion\Models\ApiMobileLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingApiMovilController extends Controller
{
    private const DEFAULTS = [
        'api_base_url' => 'http://192.168.105.11/api/megafamilia',
        'api_enabled' => true,
        'allowed_roles' => ['cliente', 'tecnico', 'hijo'],
        'token_expiry_hours' => 24,
        'max_requests_per_minute' => 60,
        'min_app_version' => '0.1.0',
        'apk_url' => 'http://192.168.105.11/apk/megafamilia.apk',
    ];

    // ---------------------------------------------------------------- views
    public function index() { return view('core-configuracion::api-movil.index'); }
    public function tokens() { return view('core-configuracion::api-movil.tokens'); }
    public function docs() { return view('core-configuracion::api-movil.docs'); }
    public function logs() { return view('core-configuracion::api-movil.logs'); }

    // -------------------------------------------------------------- config
    public function getConfig(): JsonResponse
    {
        return response()->json(['config' => array_merge(self::DEFAULTS, ApiMobileConfig::getAll())]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'api_base_url' => 'sometimes|string|url',
            'api_enabled' => 'sometimes|boolean',
            'allowed_roles' => 'sometimes|array',
            'allowed_roles.*' => 'string',
            'token_expiry_hours' => 'sometimes|integer|min:1|max:8760',
            'max_requests_per_minute' => 'sometimes|integer|min:1|max:6000',
            'min_app_version' => 'sometimes|string|max:32',
            'apk_url' => 'sometimes|string|url',
        ]);
        ApiMobileConfig::setBulk($data);
        return response()->json(['success' => true, 'config' => array_merge(self::DEFAULTS, ApiMobileConfig::getAll())]);
    }

    // -------------------------------------------------------------- tokens
    /**
     * Lista los Sanctum personal_access_tokens activos. Devuelve el usuario
     * dueño, el nombre del token (que el cliente puede usar como "device"),
     * last_used_at y expires_at.
     */
    public function listTokens(Request $request): JsonResponse
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
            return response()->json(['data' => [], 'note' => 'Sanctum no migrado (tabla personal_access_tokens ausente)']);
        }

        $q = DB::table('personal_access_tokens as t')
            ->leftJoin('users as u', function ($j) {
                $j->on('t.tokenable_id', '=', 'u.id')->where('t.tokenable_type', '=', 'App\\Models\\User');
            })
            ->select('t.id', 't.name as device', 't.last_used_at', 't.expires_at', 't.created_at', 'u.id as user_id', 'u.name as user_name', 'u.email')
            ->when($request->search, function ($qq, $v) {
                $qq->where(function ($w) use ($v) {
                    $w->where('u.name', 'like', "%$v%")
                      ->orWhere('u.email', 'like', "%$v%")
                      ->orWhere('t.name', 'like', "%$v%");
                });
            })
            ->orderByDesc('t.last_used_at')
            ->orderByDesc('t.id');

        return response()->json($q->paginate(25));
    }

    public function revokeToken(int $id): JsonResponse
    {
        DB::table('personal_access_tokens')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function revokeAllTokens(): JsonResponse
    {
        $deleted = DB::table('personal_access_tokens')->delete();
        return response()->json(['success' => true, 'revoked' => $deleted]);
    }

    // ---------------------------------------------------------------- docs
    /**
     * Reflexiona el router buscando rutas con prefijo /api/megafamilia y
     * devuelve method/uri/action/middleware/auth — base para la docs page.
     */
    public function endpoints(): JsonResponse
    {
        $rows = [];
        foreach (Route::getRoutes() as $route) {
            $uri = '/' . ltrim($route->uri(), '/');
            if (! str_starts_with($uri, '/api/megafamilia')) continue;

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') continue;
                $rows[] = [
                    'method' => $method,
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'middleware' => array_values(array_filter($route->gatherMiddleware(), fn ($m) => $m !== 'web')),
                    'auth_required' => in_array('auth:sanctum', $route->gatherMiddleware(), true),
                ];
            }
        }
        usort($rows, fn ($a, $b) => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]);

        return response()->json([
            'endpoints' => $rows,
            'apk_url' => ApiMobileConfig::getAll()['apk_url'] ?? self::DEFAULTS['apk_url'],
        ]);
    }

    // ---------------------------------------------------------------- logs
    public function logsData(Request $request): JsonResponse
    {
        $q = ApiMobileLog::query()
            ->when($request->user_id, fn ($qq, $v) => $qq->where('user_id', $v))
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
            ->when($request->date_from, fn ($qq, $v) => $qq->where('created_at', '>=', $v))
            ->when($request->date_to, fn ($qq, $v) => $qq->where('created_at', '<=', $v . ' 23:59:59'))
            ->when($request->endpoint, fn ($qq, $v) => $qq->where('endpoint', 'like', "%$v%"))
            ->orderByDesc('id');

        return response()->json($q->paginate((int) $request->input('per_page', 50)));
    }

    public function logsCsv(Request $request): StreamedResponse
    {
        $filename = 'api-mobile-logs-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($request) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM para Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'created_at', 'user_id', 'method', 'endpoint', 'status', 'ip', 'duration_ms']);

            $q = ApiMobileLog::query()
                ->when($request->user_id, fn ($qq, $v) => $qq->where('user_id', $v))
                ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
                ->when($request->date_from, fn ($qq, $v) => $qq->where('created_at', '>=', $v))
                ->when($request->date_to, fn ($qq, $v) => $qq->where('created_at', '<=', $v . ' 23:59:59'))
                ->when($request->endpoint, fn ($qq, $v) => $qq->where('endpoint', 'like', "%$v%"))
                ->orderByDesc('id')
                ->limit(50000); // safety cap

            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->id, $r->created_at, $r->user_id, $r->method, $r->endpoint, $r->status, $r->ip, $r->duration_ms]);
                }
            });

            fclose($out);
        }, 200, $headers);
    }
}
