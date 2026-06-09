<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gateway para la app móvil "Talento Equipo" (React Native).
 *
 * Auth: Sanctum Bearer token. Login provisionado por admin.
 * Todas las rutas son stateless (sin CSRF, sin sesión web).
 *
 * Capa 4.3: los 5 endpoints WRITE delegan a OrdenTrabajoUnifiedService,
 * que reconoce tanto talento_work_orders como tasks tipo=campo.
 */
class TalentoMobileApiController extends Controller
{
    public function __construct(private OrdenTrabajoUnifiedService $unified) {}

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'usuario'  => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('login_user', $request->usuario)
            ->orWhere('email', $request->usuario)
            ->first();

        // El sistema usa base64_encode como hash de contraseña (ver CLAUDE.md)
        if (! $user || $user->password !== base64_encode($request->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        $colaborador = TalentoColaborador::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $colaborador) {
            return response()->json([
                'message' => 'Tu cuenta no tiene un perfil de colaborador activo.',
            ], 403);
        }

        // Un dispositivo activo a la vez
        $user->tokens()->where('name', 'talento-mobile')->delete();

        $token = $user->createToken('talento-mobile')->plainTextToken;

        return response()->json([
            'token'       => $token,
            'colaborador' => $this->colaboradorPayload($colaborador),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request)
    {
        $colaborador = TalentoColaborador::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil activo.'], 403);
        }

        return response()->json($this->colaboradorPayload($colaborador));
    }

    // ── Asistencia ────────────────────────────────────────────────────────────

    public function asistenciaHoy(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $registro = DB::table('talento_attendances')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        if (! $registro) {
            return response()->json(['check_in_at' => null, 'check_out_at' => null]);
        }

        $siteName = null;
        if ($registro->check_in_site_id) {
            $siteName = DB::table('talento_work_sites')
                ->where('id', $registro->check_in_site_id)
                ->value('name');
        }

        return response()->json([
            'id'           => $registro->id,
            'check_in_at'  => $registro->check_in_at,
            'check_out_at' => $registro->check_out_at,
            'geocerca'     => $siteName,
            'lat'          => $registro->check_in_lat,
            'lng'          => $registro->check_in_lng,
            'flagged'      => (bool)$registro->check_in_flagged,
        ]);
    }

    public function checkin(Request $request)
    {
        $request->validate([
            'lat'         => 'required|numeric',
            'lng'         => 'required|numeric',
            'accuracy'    => 'nullable|numeric',
            'client_uuid' => 'nullable|string|max:36',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        // Idempotencia: si ya existe un registro con este client_uuid, devolver el previo
        if ($request->client_uuid) {
            $prev = DB::table('talento_attendances')
                ->where('client_uuid', $request->client_uuid)
                ->first();
            if ($prev) {
                return response()->json([
                    'id'          => $prev->id,
                    'check_in_at' => $prev->check_in_at,
                    'geocerca'    => $prev->check_in_site_id
                        ? DB::table('talento_work_sites')->where('id', $prev->check_in_site_id)->value('name')
                        : null,
                    'flagged'     => (bool)$prev->check_in_flagged,
                ], 200);
            }
        }

        $existing = DB::table('talento_attendances')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->whereNull('check_out_at')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Ya tienes un check-in activo hoy.'], 422);
        }

        $site       = $this->nearestWorkSite($request->lat, $request->lng);
        $withinSite = $site !== null;

        $id = DB::table('talento_attendances')->insertGetId([
            'client_uuid'              => $request->client_uuid,
            'colaborador_id'           => $colaborador->id,
            'check_in_at'              => now(),
            'check_in_lat'             => $request->lat,
            'check_in_lng'             => $request->lng,
            'check_in_site_id'         => $site?->id,
            'check_in_within_geofence' => $withinSite,
            'check_in_flagged'         => ! $withinSite,
            'check_in_flag_reason'     => $withinSite ? null : 'Fuera de geocerca (app móvil)',
            'day_type'                 => 'worked',
            'status'                   => 'open',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        return response()->json([
            'id'          => $id,
            'check_in_at' => now()->toISOString(),
            'geocerca'    => $site?->name,
            'flagged'     => ! $withinSite,
        ], 201);
    }

    public function checkout(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $updated = DB::table('talento_attendances')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->whereNull('check_out_at')
            ->update(['check_out_at' => now(), 'status' => 'closed', 'updated_at' => now()]);

        if (! $updated) {
            return response()->json(['message' => 'Sin check-in activo para cerrar.'], 422);
        }

        return response()->json(['check_out_at' => now()->toISOString()]);
    }

    // ── Órdenes de Trabajo ────────────────────────────────────────────────────

    public function otsHoy(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        return response()->json(['ots' => $this->unified->summaryForHoy($colaborador->id)]);
    }

    public function otShow(Request $request, int $id)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $detail = $this->unified->detail($id, $colaborador->id);
        if (! $detail) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }

        return response()->json(['ot' => $detail]);
    }

    // ── Health / tenant (sin auth — para validar URL antes de login) ─────────

    public function health()
    {
        $tenantName    = DB::table('settings')->where('key', 'tenant_name')->value('value');
        $tenantLogoUrl = DB::table('settings')->where('key', 'tenant_logo_url')->value('value');

        // Fallback: si no hay settings de tenant, usa datos de la empresa
        if (! $tenantName) {
            $info = DB::table('company_information')->first(['company_name', 'url_logo']);
            $tenantName = $info?->company_name ?? 'Medussa';
            if (! $tenantLogoUrl && $info?->url_logo) {
                $raw = $info->url_logo;
                $base = request()->getSchemeAndHttpHost();
                $tenantLogoUrl = str_starts_with($raw, 'http') ? $raw : $base . '/' . ltrim($raw, '/');
            }
        }

        return response()->json([
            'ok'               => true,
            'app'              => 'Medussa',
            'tenant_name'      => $tenantName,
            'tenant_logo_url'  => $tenantLogoUrl,
        ]);
    }

    // ── Branding dinámico ─────────────────────────────────────────────────────

    public function appBranding()
    {
        $info = DB::table('company_information')->first([
            'company_name', 'url_logo', 'talento_app_logo',
        ]);

        $logoUrl = null;
        if ($info) {
            // talento_app_logo sobreescribe url_logo si está configurado
            $raw = $info->talento_app_logo ?: $info->url_logo;
            if ($raw) {
                // Si ya es URL absoluta la usamos tal cual; si es ruta relativa, la prefijamos
                // Prefijo: host real de la request (accesible desde el teléfono)
                $base = request()->getSchemeAndHttpHost();
                $logoUrl = str_starts_with($raw, 'http')
                    ? $raw
                    : $base . '/' . ltrim($raw, '/');
            }
        }

        return response()->json([
            'company_name' => $info?->company_name ?? 'Talento Equipo',
            'logo_url'     => $logoUrl,
        ]);
    }

    public function tiposEvidencia(Request $request, int $id)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $ot = $this->unified->findLight($id, $colaborador->id);
        if (! $ot) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }

        // Todos los tipos de evidencia activos
        $tipos = DB::table('talento_evidence_types')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'permite_varias', 'requiere_justificacion', 'es_lectura_dbm', 'es_firma'])
            ->map(fn($t) => [
                'id'                    => $t->id,
                'name'                  => $t->name,
                'permite_varias'        => (bool)$t->permite_varias,
                'requiere_justificacion'=> (bool)$t->requiere_justificacion,
                'es_lectura_dbm'        => (bool)$t->es_lectura_dbm,
                'es_firma'              => (bool)$t->es_firma,
            ]);

        // Tipos obligatorios para este tipo de OT (condition=null = siempre requeridos)
        $requeridos = DB::table('talento_ot_type_evidence_requirements')
            ->where('ot_type_id', $ot->type_id)
            ->whereNull('condition')
            ->pluck('evidence_type_id')
            ->values();

        // Tipos ya subidos: usa tarea_id o work_order_id según la fuente
        $fkCol   = $ot->is_task ? 'tarea_id' : 'work_order_id';
        $subidos = DB::table('talento_work_order_media')
            ->where($fkCol, $ot->id)
            ->whereNotNull('evidence_type_id')
            ->pluck('evidence_type_id')
            ->values();

        return response()->json([
            'tipos'     => $tipos,
            'requeridos'=> $requeridos,
            'subidos'   => $subidos,
        ]);
    }

    public function otEvidencia(Request $request, int $id)
    {
        $request->validate([
            'foto'             => 'required|file|mimes:jpg,jpeg|max:10240',
            'evidence_type_id' => 'required|integer|exists:talento_evidence_types,id',
            'lat'              => 'required|numeric',
            'lng'              => 'required|numeric',
            'accuracy'         => 'nullable|numeric',
            'potencia_dbm'     => 'nullable|numeric',
            'justificacion'    => 'nullable|string|max:500',
            'is_mock_location' => 'nullable|boolean',
            'client_uuid'      => 'nullable|string|max:36',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        // Idempotencia: si ya existe un media con este client_uuid, devolver el previo
        if ($request->client_uuid) {
            $prev = DB::table('talento_work_order_media')
                ->where('client_uuid', $request->client_uuid)
                ->first();
            if ($prev) {
                return response()->json([
                    'media_id'  => $prev->id,
                    'salud_red' => null,
                ], 200);
            }
        }

        $result = $this->unified->subirEvidencia(
            $id,
            $colaborador->id,
            array_merge(
                $request->only(['evidence_type_id', 'lat', 'lng', 'accuracy', 'potencia_dbm', 'justificacion', 'is_mock_location']),
                ['client_uuid' => $request->client_uuid]
            ),
            $request->file('foto'),
            $request->user()->id
        );

        if (! $result['success']) {
            return response()->json(
                collect($result)->except(['success', 'status_code'])->all(),
                $result['status_code']
            );
        }

        return response()->json([
            'media_id'  => $result['media_id'],
            'salud_red' => $result['salud_red'],
        ], 201);
    }

    // ── Auto-update ───────────────────────────────────────────────────────────

    public function latestRelease(Request $request)
    {
        $currentCode = (int)$request->query('version_code', 0);

        $latest = DB::table('talento_app_releases')
            ->where('active', true)
            ->orderByDesc('version_code')
            ->first();

        if (! $latest || $latest->version_code <= $currentCode) {
            return response()->json(['update_available' => false]);
        }

        return response()->json([
            'update_available' => true,
            'version_name'     => $latest->version_name,
            'version_code'     => $latest->version_code,
            'apk_url'          => $latest->apk_url,
            'changelog'        => $latest->changelog,
            'is_mandatory'     => (bool)$latest->is_mandatory,
        ]);
    }

    // ── Transiciones de estado OT ─────────────────────────────────────────────

    public function iniciarOT(Request $request, int $id)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $result = $this->unified->iniciar($id, $colaborador->id);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['status_code']);
        }

        return response()->json(['status' => $result['status'], 'started_at' => $result['started_at']]);
    }

    public function completarOT(Request $request, int $id)
    {
        $request->validate([
            'nota_tecnico' => 'nullable|string|max:1000',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        // Completar NO escribe en el ledger — los puntos/pago se acreditan
        // únicamente cuando un admin valida la OT (status validated) desde la web.
        $result = $this->unified->completar(
            $id,
            $colaborador->id,
            $request->only(['nota_tecnico'])
        );

        if (! $result['success']) {
            return response()->json(
                collect($result)->except(['success', 'status_code'])->all(),
                $result['status_code']
            );
        }

        return response()->json([
            'status'       => $result['status'],
            'completed_at' => $result['completed_at'],
            'dbm_tier'     => $result['dbm_tier'],
        ]);
    }

    // ── Incidencia ("No puedo completar") ────────────────────────────────────

    public function reportarIncidencia(Request $request, int $id)
    {
        $request->validate([
            'motivo'      => 'required|in:cliente_ausente,sin_acceso,falta_material,olt_sin_senal,riesgo,otro',
            'nota'        => 'nullable|string|max:1000',
            'lat'         => 'nullable|numeric',
            'lng'         => 'nullable|numeric',
            'client_uuid' => 'nullable|string|max:36',
        ]);

        if ($request->input('motivo') === 'otro' && empty(trim($request->input('nota', '')))) {
            return response()->json(['message' => 'El campo nota es obligatorio cuando el motivo es "otro".', 'code' => 'NOTA_REQUERIDA'], 422);
        }

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        // Idempotencia: si ya existe una incidencia con este client_uuid, devolver la previa
        if ($request->client_uuid) {
            $prev = DB::table('talento_work_order_incidents')
                ->where('client_uuid', $request->client_uuid)
                ->first();
            if ($prev) {
                return response()->json([
                    'incident_id' => $prev->id,
                    'status'      => 'incidencia',
                ], 200);
            }
        }

        $result = $this->unified->reportarIncidencia(
            $id,
            $colaborador->id,
            array_merge(
                $request->only(['motivo', 'nota', 'lat', 'lng']),
                ['client_uuid' => $request->client_uuid]
            ),
            $request->user()->id
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['status_code']);
        }

        return response()->json([
            'incident_id' => $result['incident_id'],
            'status'      => $result['status'],
        ], 201);
    }

    // ── Nota del técnico (edición libre) ─────────────────────────────────────

    public function guardarNota(Request $request, int $id)
    {
        $request->validate(['nota_tecnico' => 'nullable|string|max:1000']);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $result = $this->unified->guardarNota(
            $id,
            $colaborador->id,
            $request->input('nota_tecnico')
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['status_code']);
        }

        return response()->json(['ok' => true]);
    }

    // ── Historial de OTs ──────────────────────────────────────────────────────

    public function otHistorial(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        return response()->json(
            $this->unified->historial(
                $colaborador->id,
                $request->query('estado'),
                $request->query('fecha')
            )
        );
    }

    // ── Compensación ──────────────────────────────────────────────────────────

    public function compensacionSemana(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        // Semana actual lunes-domingo
        $inicio = now()->startOfWeek()->toDateString();
        $fin    = now()->endOfWeek()->toDateString();

        // OTs completadas o validadas esta semana (fuente legacy)
        $woUnidades = TalentoWorkOrder::where('colaborador_id', $colaborador->id)
            ->whereIn('status', ['completed', 'validated'])
            ->whereBetween('completed_at', [$inicio . ' 00:00:00', $fin . ' 23:59:59'])
            ->count();

        // Tasks campo validadas esta semana (Capa 5)
        $taskUnidades = 0;
        $taskUserId = TalentoColaborador::where('id', $colaborador->id)->value('user_id');
        if ($taskUserId) {
            $taskUnidades = \App\Models\Task::where('tipo', 'campo')
                ->whereNotNull('talento_type_id')
                ->where('status', 'Done')
                ->whereNotNull('validated_at')
                ->whereBetween('validated_at', [$inicio . ' 00:00:00', $fin . ' 23:59:59'])
                ->whereHas('users', fn($q) => $q->where('users.id', $taskUserId))
                ->count();
        }
        $unidades = $woUnidades + $taskUnidades;

        // Cuota semanal — talento_compensation_rules (global por target_type)
        $cuota = DB::table('talento_compensation_rules')
            ->where('active', true)
            ->where(function ($q) use ($colaborador) {
                $q->where('target_type', 'all')
                  ->orWhere('target_type', $colaborador->tipo ?? $colaborador->role_type ?? 'technician');
            })
            ->orderByDesc('id')
            ->value('weekly_quota_units') ?? 0;

        // Movimientos del ledger esta semana (tabla: talento_ledger_entries)
        $movimientos = DB::table('talento_ledger_entries')
            ->where('colaborador_id', $colaborador->id)
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('period_start', [$inicio, $fin])
                  ->orWhereBetween('period_end',   [$inicio, $fin]);
            })
            ->orderByDesc('created_at')
            ->get([
                'id',
                'type as tipo',
                'concept as concepto',
                'amount as monto',
                'reference_type as categoria',
                'created_at as fecha',
            ])
            ->map(fn($m) => array_merge((array)$m, [
                'tipo' => strtoupper($m->tipo), // credit→CREDIT, debit→DEBIT para la app
            ]))
            ->toArray();

        // Proyectado = CREDITs - DEBITs
        $proyectado = collect($movimientos)->reduce(function ($carry, $m) {
            $m = (object)$m;
            return $carry + ($m->tipo === 'CREDIT' ? (float)$m->monto : -(float)$m->monto);
        }, 0.0);

        // ── Bono de salud de red ──────────────────────────────────────────────
        // Promedio de potencia dBm de evidencias tipo lectura_dbm de la semana
        $avgDbm = DB::table('talento_work_order_media as m')
            ->join('talento_evidence_types as et', 'et.id', '=', 'm.evidence_type_id')
            ->where(function ($q) use ($colaborador) {
                $q->whereIn('m.work_order_id', function ($sub) use ($colaborador) {
                    $sub->select('id')->from('talento_work_orders')
                        ->where('colaborador_id', $colaborador->id);
                })->orWhereNotNull('m.tarea_id');
            })
            ->where('et.es_lectura_dbm', true)
            ->whereNotNull('m.potencia_dbm')
            ->whereBetween('m.created_at', [$inicio . ' 00:00:00', $fin . ' 23:59:59'])
            ->avg('m.potencia_dbm');

        $bonoSaludTier  = null;
        $bonoSaludMonto = 0;
        if ($avgDbm !== null) {
            $umbral = DB::table('talento_dbm_thresholds')
                ->where(fn($q) => $q->whereNull('dbm_min')->orWhere('dbm_min', '<=', $avgDbm))
                ->where(fn($q) => $q->whereNull('dbm_max')->orWhere('dbm_max', '>=', $avgDbm))
                ->orderByDesc('id')
                ->first();
            if ($umbral) {
                $bonoSaludTier = $umbral->categoria;
                if ($umbral->aplica_bono) {
                    $bonoSaludMonto = (float)(DB::table('settings')
                        ->where('key', 'talento_health_bonus_amount')
                        ->value('value') ?? 30);
                }
            }
        }

        // ── Reversiones / garantía (ventana 6 meses) ─────────────────────────
        $reversiones = DB::table('talento_ledger_entries')
            ->where('colaborador_id', $colaborador->id)
            ->where('type', 'debit')
            ->whereIn('reference_type', ['garantia', 'reversion', 'warranty', 'chargeback'])
            ->where('created_at', '>=', now()->subMonths(6)->toDateString())
            ->orderByDesc('created_at')
            ->get([
                'id',
                'concept as concepto',
                'amount as monto',
                'reference_type as tipo',
                'created_at as fecha',
                'notes',
            ])
            ->toArray();

        return response()->json([
            'periodo_inicio'   => $inicio,
            'periodo_fin'      => $fin,
            'corte'            => $fin,
            'unidades'         => $unidades,
            'cuota'            => (int)$cuota,
            'proyectado'       => round($proyectado, 2),
            'movimientos'      => $movimientos,
            'bono_salud_red'   => [
                'dbm_promedio' => $avgDbm !== null ? round((float)$avgDbm, 2) : null,
                'tier'         => $bonoSaludTier,
                'monto'        => $bonoSaludMonto,
            ],
            'reversiones'      => $reversiones,
        ]);
    }

    // ── Registro de token FCM ─────────────────────────────────────────────────

    public function registerDeviceToken(Request $request)
    {
        $request->validate([
            'token'    => 'required|string|max:512',
            'platform' => 'nullable|in:android,ios',
        ]);

        $userId = $request->user()->id;

        DB::table('talento_device_tokens')->updateOrInsert(
            ['user_id' => $userId, 'token' => $request->token],
            [
                'platform'   => $request->input('platform', 'android'),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    // ── Push helper (disparo interno al asignar OT) ───────────────────────────

    public static function sendPushToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DB::table('talento_device_tokens')
            ->where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) return;

        // FCM v1 API — las credenciales (service account JSON) se configuran
        // en el servidor de cada empresa. Esta implementación es el esqueleto
        // de disparo; el adaptador FCM real va en un servicio dedicado.
        $fcmKey = config('services.fcm.server_key')
            ?? env('FCM_SERVER_KEY');

        if (! $fcmKey) {
            // Sin clave FCM configurada — log y salir (no lanzar excepción)
            \Illuminate\Support\Facades\Log::warning('FCM_SERVER_KEY no configurada — push no enviado', compact('userId'));
            return;
        }

        foreach ($tokens as $token) {
            try {
                \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => "key={$fcmKey}",
                    'Content-Type'  => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to'           => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => $data,
                    'priority'     => 'high',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("FCM push fallido para token={$token}: " . $e->getMessage());
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveColaborador(Request $request): ?TalentoColaborador
    {
        return TalentoColaborador::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();
    }

    private function noColaborador()
    {
        return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
    }

    private function colaboradorPayload(TalentoColaborador $col): array
    {
        return [
            'id'       => $col->id,
            'nombre'   => trim(($col->nombre ?? '') . ' ' . ($col->apellido ?? '')),
            'tipo'     => $col->tipo ?? $col->role_type ?? 'technician',
            'email'    => $col->user?->email,
            'telefono' => $col->telefono,
        ];
    }

    private function otSummary(TalentoWorkOrder $ot): array
    {
        // Cliente: primero via client_main_information (tiene nombre completo y dirección),
        // fallback a clients→users para clientes migrados con user_id.
        $clientName    = null;
        $clientAddress = null;
        $clientPhone   = null;
        if ($ot->client_id) {
            $info = DB::table('client_main_information')
                ->where('client_id', $ot->client_id)
                ->first(['name', 'father_last_name', 'mother_last_name', 'address', 'phone']);
            if ($info) {
                $clientName    = trim(implode(' ', array_filter([
                    $info->name,
                    $info->father_last_name,
                    $info->mother_last_name,
                ]))) ?: null;
                $clientAddress = $info->address ?: null;
                $clientPhone   = $info->phone   ?: null;
            } else {
                // fallback legacy: clients.user_id → users
                $client = DB::table('clients')->where('id', $ot->client_id)->first(['user_id']);
                if ($client?->user_id) {
                    $user = DB::table('users')->where('id', $client->user_id)->first(['name', 'address']);
                    $clientName    = $user?->name;
                    $clientAddress = $user?->address;
                }
            }
        }

        return [
            'id'        => $ot->id,
            'folio'     => $ot->id,
            'tipo'      => $ot->type?->name ?? '—',
            'status'    => $ot->status,
            'cliente'   => $clientName,
            'direccion' => $clientAddress,
            'telefono'  => $clientPhone,
        ];
    }

    private function otDetail(TalentoWorkOrder $ot, array $evidencias, array $requeridas = []): array
    {
        return array_merge($this->otSummary($ot), [
            'notas'                   => $ot->notes,
            'nota_tecnico'            => $ot->nota_tecnico,
            'scheduled_at'            => $ot->scheduled_at,
            'potencia_referencia_dbm' => null,
            'evidencias'              => $evidencias,
            'evidencias_requeridas'   => $requeridas,
        ]);
    }

    private function nearestWorkSite(float $lat, float $lng)
    {
        // Columnas reales: latitude, longitude, radius_m (talento_work_sites)
        $sites = DB::table('talento_work_sites')
            ->where('active', true)
            ->get(['id', 'name', 'latitude', 'longitude', 'radius_m']);

        foreach ($sites as $site) {
            if ($this->haversineMeters($lat, $lng, (float)$site->latitude, (float)$site->longitude)
                    <= ($site->radius_m ?? 300)) {
                return $site;
            }
        }
        return null;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dlat = deg2rad($lat1 - $lat2);
        $dlng = deg2rad($lng1 - $lng2);
        $a    = sin($dlat / 2) ** 2
              + cos(deg2rad($lat2)) * cos(deg2rad($lat1)) * sin($dlng / 2) ** 2;
        return 6371000 * 2 * asin(sqrt($a));
    }

}

