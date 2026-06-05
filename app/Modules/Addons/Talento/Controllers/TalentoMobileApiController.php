<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gateway para la app móvil "Talento Equipo" (React Native).
 *
 * Auth: Sanctum Bearer token. Login provisionado por admin.
 * Todas las rutas son stateless (sin CSRF, sin sesión web).
 *
 * Tablas reales usadas:
 *  - talento_work_order_media  (evidencias, con potencia_dbm/gps_accuracy_m/source vía migración 970010)
 *  - talento_ledger_entries    (compensación)
 *  - talento_work_order_types  (columna: name)
 *  - settings                  (health bonus: talento_health_bonus_amount, talento_health_bonus_max_loss_db)
 */
class TalentoMobileApiController extends Controller
{
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
            'lat'      => 'required|numeric',
            'lng'      => 'required|numeric',
            'accuracy' => 'nullable|numeric',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $existing = DB::table('talento_attendances')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->whereNull('check_out_at')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Ya tienes un check-in activo hoy.'], 422);
        }

        $site         = $this->nearestWorkSite($request->lat, $request->lng);
        $withinSite   = $site !== null;

        $id = DB::table('talento_attendances')->insertGetId([
            'colaborador_id'         => $colaborador->id,
            'check_in_at'            => now(),
            'check_in_lat'           => $request->lat,
            'check_in_lng'           => $request->lng,
            'check_in_site_id'       => $site?->id,
            'check_in_within_geofence' => $withinSite,
            'check_in_flagged'       => ! $withinSite,
            'check_in_flag_reason'   => $withinSite ? null : 'Fuera de geocerca (app móvil)',
            'day_type'               => 'worked',
            'status'                 => 'open',
            'created_at'             => now(),
            'updated_at'             => now(),
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

        $ots = TalentoWorkOrder::with(['type'])
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('scheduled_at', now()->toDateString())
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn($o) => $this->otSummary($o));

        return response()->json(['ots' => $ots]);
    }

    public function otShow(Request $request, int $id)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $ot = TalentoWorkOrder::with(['type'])
            ->where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

        if (! $ot) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }

        // Cargar evidencias (media) de esta OT
        $evidencias = DB::table('talento_work_order_media')
            ->where('work_order_id', $ot->id)
            ->orderByDesc('created_at')
            ->get(['id', 'created_at', 'potencia_dbm', 'watermark_applied', 'location_flagged'])
            ->toArray();

        return response()->json(['ot' => $this->otDetail($ot, $evidencias)]);
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

        $ot = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

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

        // Tipos ya subidos para esta OT
        $subidos = DB::table('talento_work_order_media')
            ->where('work_order_id', $ot->id)
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
            'foto'              => 'required|file|mimes:jpg,jpeg|max:10240',
            'evidence_type_id'  => 'required|integer|exists:talento_evidence_types,id',
            'lat'               => 'required|numeric',
            'lng'               => 'required|numeric',
            'accuracy'          => 'nullable|numeric',
            'potencia_dbm'      => 'nullable|numeric',
            'justificacion'     => 'nullable|string|max:500',
            'is_mock_location'  => 'nullable|boolean',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $ot = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

        if (! $ot) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }

        $evTypeId = (int)$request->input('evidence_type_id');
        $evType   = DB::table('talento_evidence_types')->where('id', $evTypeId)->first();

        // Tipo "Otro" (requiere_justificacion=true) exige justificacion
        if ($evType->requiere_justificacion && empty(trim($request->input('justificacion', '')))) {
            return response()->json([
                'message' => 'Este tipo de evidencia requiere una justificación.',
                'code'    => 'JUSTIFICACION_REQUERIDA',
            ], 422);
        }

        // Anti-duplicado: solo un upload por tipo salvo permite_varias
        if (! $evType->permite_varias) {
            $existe = DB::table('talento_work_order_media')
                ->where('work_order_id', $ot->id)
                ->where('evidence_type_id', $evTypeId)
                ->exists();

            if ($existe) {
                return response()->json([
                    'message' => "Ya existe evidencia de tipo '{$evType->name}' para esta OT.",
                    'code'    => 'TIPO_DUPLICADO',
                ], 422);
            }
        }

        $lat      = (float)$request->input('lat');
        $lng      = (float)$request->input('lng');
        $accuracy = $request->input('accuracy') !== null ? (float)$request->input('accuracy') : null;
        $potencia = $request->input('potencia_dbm') !== null ? (float)$request->input('potencia_dbm') : null;
        $isMock   = (bool)$request->input('is_mock_location', false);

        // Calcular distancia al sitio de la OT
        $locationFlagged = false;
        $distanceM       = null;
        if ($ot->latitude && $ot->longitude) {
            $distanceM = $this->haversineMeters($lat, $lng, (float)$ot->latitude, (float)$ot->longitude);
            $flaggedRadius = (float)(DB::table('settings')->where('key', 'talento_media_flagged_radius_m')->value('value') ?? 500);
            $locationFlagged = $distanceM > $flaggedRadius;
        }

        // Guardar foto en disco privado (marca de agua ya quemada en la app)
        $file = $request->file('foto');
        $dir  = "talento/media/{$ot->id}";
        $name = Str::uuid() . '.jpg';
        $path = $file->storeAs($dir, $name, 'local');

        $mediaId = DB::table('talento_work_order_media')->insertGetId([
            'work_order_id'       => $ot->id,
            'evidence_type_id'    => $evTypeId,
            'type'                => 'completion',
            'file_path'           => $path,
            'captured_lat'        => $lat,
            'captured_lng'        => $lng,
            'captured_at'         => now(),
            'server_captured_at'  => now(),
            'captured_in_app'     => true,
            'watermark_applied'   => true,
            'location_flagged'    => $locationFlagged,
            'is_mock_location'    => $isMock,
            'location_distance_m' => $distanceM,
            'potencia_dbm'        => $potencia,
            'gps_accuracy_m'      => $accuracy,
            'justificacion'       => $request->input('justificacion') ?: null,
            'source'              => 'mobile',
            'created_by'          => $request->user()->id,
            'created_at'          => now(),
        ]);

        if ($ot->status === 'pending') {
            $ot->update(['status' => 'in_progress']);
        }

        $saludRed = $potencia !== null ? $this->calcularSaludRed($potencia, $ot) : null;

        return response()->json([
            'media_id'  => $mediaId,
            'salud_red' => $saludRed,
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

        $ot = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

        if (! $ot) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }
        if ($ot->status !== 'pending') {
            return response()->json(['message' => "La OT ya está en estado '{$ot->status}'."], 422);
        }

        $ot->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json(['status' => $ot->status, 'started_at' => $ot->started_at]);
    }

    public function completarOT(Request $request, int $id)
    {
        $request->validate([
            'nota_tecnico' => 'nullable|string|max:1000',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $ot = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

        if (! $ot) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }
        if ($ot->status !== 'in_progress') {
            return response()->json(['message' => "Solo se puede completar una OT en curso (estado actual: '{$ot->status}')."], 422);
        }

        // ── 1. Checklist de evidencias obligatorias ──────────────────────────
        $requeridos = DB::table('talento_ot_type_evidence_requirements')
            ->where('ot_type_id', $ot->type_id)
            ->whereNull('condition')
            ->pluck('evidence_type_id')
            ->map(fn($v) => (int)$v);

        $subidos = DB::table('talento_work_order_media')
            ->where('work_order_id', $ot->id)
            ->whereNotNull('evidence_type_id')
            ->pluck('evidence_type_id')
            ->map(fn($v) => (int)$v)
            ->unique();

        $faltantes = $requeridos->diff($subidos);

        if ($faltantes->isNotEmpty()) {
            $nombres = DB::table('talento_evidence_types')
                ->whereIn('id', $faltantes)
                ->pluck('name', 'id');

            return response()->json([
                'message'  => 'Faltan evidencias obligatorias antes de completar la OT.',
                'code'     => 'EVIDENCIAS_FALTANTES',
                'faltantes'=> $faltantes->map(fn($evId) => [
                    'evidence_type_id' => $evId,
                    'name'             => $nombres[$evId] ?? "Tipo #{$evId}",
                ])->values(),
            ], 422);
        }

        // ── 2. Gate dBm ────────────────────────────────────────────────────────
        // Si el tipo de OT exige lectura dBm, verificar el valor subido
        $requiereDbm = DB::table('talento_ot_type_evidence_requirements')
            ->join('talento_evidence_types', 'talento_evidence_types.id', '=', 'talento_ot_type_evidence_requirements.evidence_type_id')
            ->where('talento_ot_type_evidence_requirements.ot_type_id', $ot->type_id)
            ->whereNull('talento_ot_type_evidence_requirements.condition')
            ->where('talento_evidence_types.es_lectura_dbm', true)
            ->exists();

        $dbmTier = null;
        if ($requiereDbm) {
            $ultimoDbm = DB::table('talento_work_order_media')
                ->where('work_order_id', $ot->id)
                ->whereNotNull('potencia_dbm')
                ->orderByDesc('created_at')
                ->value('potencia_dbm');

            if ($ultimoDbm !== null) {
                $valor = (float) $ultimoDbm;
                $umbral = DB::table('talento_dbm_thresholds')
                    ->where(function ($q) use ($valor) {
                        $q->whereNull('dbm_min')->orWhere('dbm_min', '<=', $valor);
                    })
                    ->where(function ($q) use ($valor) {
                        $q->whereNull('dbm_max')->orWhere('dbm_max', '>=', $valor);
                    })
                    ->orderBy('sort_order')
                    ->first();

                if ($umbral && $umbral->accion === 'bloquea') {
                    return response()->json([
                        'message'  => $umbral->mensaje,
                        'code'     => 'DBM_BLOQUEADO',
                        'categoria'=> $umbral->categoria,
                        'dbm'      => $valor,
                    ], 422);
                }
                $dbmTier = $umbral ? ['categoria' => $umbral->categoria, 'mensaje' => $umbral->mensaje] : null;
            }
        }

        // ── 3. Guardar nota si viene ───────────────────────────────────────────
        $updates = ['status' => 'completed', 'completed_at' => now()];
        if ($request->filled('nota_tecnico')) {
            $updates['nota_tecnico'] = $request->input('nota_tecnico');
        }

        // Completar NO escribe en el ledger — los puntos/pago se acreditan
        // únicamente cuando un admin valida la OT (status validated) desde la web.
        $ot->update($updates);

        return response()->json([
            'status'       => $ot->status,
            'completed_at' => $ot->completed_at,
            'dbm_tier'     => $dbmTier,
        ]);
    }

    // ── Incidencia ("No puedo completar") ────────────────────────────────────

    public function reportarIncidencia(Request $request, int $id)
    {
        $request->validate([
            'motivo' => 'required|in:cliente_ausente,sin_acceso,falta_material,olt_sin_senal,riesgo,otro',
            'nota'   => 'nullable|string|max:1000',
            'lat'    => 'nullable|numeric',
            'lng'    => 'nullable|numeric',
        ]);

        if ($request->input('motivo') === 'otro' && empty(trim($request->input('nota', '')))) {
            return response()->json(['message' => 'El campo nota es obligatorio cuando el motivo es "otro".', 'code' => 'NOTA_REQUERIDA'], 422);
        }

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $ot = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

        if (! $ot) return response()->json(['message' => 'OT no encontrada.'], 404);
        if (! in_array($ot->status, ['pending', 'in_progress'])) {
            return response()->json(['message' => "No se puede reportar incidencia en estado '{$ot->status}'."], 422);
        }

        $incidentId = DB::table('talento_work_order_incidents')->insertGetId([
            'work_order_id' => $ot->id,
            'motivo'        => $request->input('motivo'),
            'nota'          => $request->input('nota') ?: null,
            'lat'           => $request->input('lat') !== null ? (float)$request->input('lat') : null,
            'lng'           => $request->input('lng') !== null ? (float)$request->input('lng') : null,
            'server_at'     => now(),
            'created_by'    => $request->user()->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $ot->update(['status' => 'incidencia']);

        return response()->json([
            'incident_id' => $incidentId,
            'status'      => 'incidencia',
        ], 201);
    }

    // ── Nota del técnico (edición libre) ─────────────────────────────────────

    public function guardarNota(Request $request, int $id)
    {
        $request->validate(['nota_tecnico' => 'nullable|string|max:1000']);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $ot = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

        if (! $ot) return response()->json(['message' => 'OT no encontrada.'], 404);

        $ot->update(['nota_tecnico' => $request->input('nota_tecnico') ?: null]);

        return response()->json(['ok' => true]);
    }

    // ── Historial de OTs ──────────────────────────────────────────────────────

    public function otHistorial(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $estado = $request->query('estado'); // filtro opcional
        $fecha  = $request->query('fecha');  // filtro opcional YYYY-MM-DD

        $query = TalentoWorkOrder::with(['type'])
            ->where('colaborador_id', $colaborador->id)
            ->orderByDesc('scheduled_at');

        if ($estado) $query->where('status', $estado);
        if ($fecha)  $query->whereDate('scheduled_at', $fecha);

        $ots = $query->paginate(20);

        return response()->json([
            'data'         => $ots->map(fn($o) => $this->otSummary($o)),
            'current_page' => $ots->currentPage(),
            'last_page'    => $ots->lastPage(),
            'total'        => $ots->total(),
        ]);
    }

    // ── Compensación ──────────────────────────────────────────────────────────

    public function compensacionSemana(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        // Semana actual lunes-domingo
        $inicio = now()->startOfWeek()->toDateString();
        $fin    = now()->endOfWeek()->toDateString();

        // OTs completadas o validadas esta semana
        $unidades = TalentoWorkOrder::where('colaborador_id', $colaborador->id)
            ->whereIn('status', ['completed', 'validated'])
            ->whereBetween('completed_at', [$inicio . ' 00:00:00', $fin . ' 23:59:59'])
            ->count();

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

        return response()->json([
            'periodo_inicio' => $inicio,
            'periodo_fin'    => $fin,
            'corte'          => $fin,
            'unidades'       => $unidades,
            'cuota'          => (int)$cuota,
            'proyectado'     => round($proyectado, 2),
            'movimientos'    => $movimientos,
        ]);
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

    private function otDetail(TalentoWorkOrder $ot, array $evidencias): array
    {
        return array_merge($this->otSummary($ot), [
            'notas'                   => $ot->notes,
            'scheduled_at'            => $ot->scheduled_at,
            'potencia_referencia_dbm' => null, // no está en work_orders; dejar para cuando se agregue
            'evidencias'              => $evidencias,
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

    private function calcularSaludRed(float $potencia, TalentoWorkOrder $ot): array
    {
        // Umbral de pérdida aceptable desde settings (Fase 4b: talento_health_bonus_max_loss_db)
        $maxLoss = (float)(DB::table('settings')
            ->where('key', 'talento_health_bonus_max_loss_db')
            ->value('value') ?? 1.0);
        $bonusMonto = (float)(DB::table('settings')
            ->where('key', 'talento_health_bonus_amount')
            ->value('value') ?? 30);

        // Sin referencia en la OT; comparamos contra el umbral directamente
        // (la referencia óptima en fibra óptica es ~0 dB pérdida; negativo ya implica pérdida)
        $perdida = abs($potencia); // dBm negativo → pérdida en dB
        $bono    = $perdida <= $maxLoss ? $bonusMonto : 0;

        return [
            'potencia_medida_dbm' => $potencia,
            'max_loss_db'         => $maxLoss,
            'perdida_estimada_db' => round($perdida, 2),
            'calidad'             => $perdida <= $maxLoss ? 'buena' : ($perdida <= $maxLoss * 3 ? 'aceptable' : 'deficiente'),
            'bono'                => $bono,
        ];
    }
}
