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

    public function otEvidencia(Request $request, int $id)
    {
        $request->validate([
            'foto'         => 'required|file|mimes:jpg,jpeg|max:10240',
            'lat'          => 'nullable|numeric',
            'lng'          => 'nullable|numeric',
            'accuracy'     => 'nullable|numeric',
            'potencia_dbm' => 'nullable|numeric',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $ot = TalentoWorkOrder::where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

        if (! $ot) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }

        // Guardar foto en disco privado (la marca de agua ya viene quemada desde la app)
        $file  = $request->file('foto');
        $dir   = "talento/media/{$ot->id}";
        $name  = Str::uuid() . '.' . ($file->getClientOriginalExtension() ?: 'jpg');
        $path  = $file->storeAs($dir, $name, 'local');

        $lat      = $request->input('lat') !== null ? (float)$request->input('lat') : null;
        $lng      = $request->input('lng') !== null ? (float)$request->input('lng') : null;
        $accuracy = $request->input('accuracy') !== null ? (float)$request->input('accuracy') : null;
        $potencia = $request->input('potencia_dbm') !== null ? (float)$request->input('potencia_dbm') : null;

        // Calcular distancia al sitio de la OT para flag de ubicación
        $locationFlagged = false;
        $distanceM       = null;
        if ($ot->latitude && $ot->longitude && $lat && $lng) {
            $distanceM = $this->haversineMeters($lat, $lng, (float)$ot->latitude, (float)$ot->longitude);
            $flaggedRadius = (float)(DB::table('settings')->where('key', 'talento_media_flagged_radius_m')->value('value') ?? 500);
            $locationFlagged = $distanceM > $flaggedRadius;
        }

        $mediaId = DB::table('talento_work_order_media')->insertGetId([
            'work_order_id'      => $ot->id,
            'type'               => 'completion',
            'file_path'          => $path,
            'captured_lat'       => $lat,
            'captured_lng'       => $lng,
            'captured_at'        => now(),
            'captured_in_app'    => true,
            'watermark_applied'  => true,   // quemada por la app antes de subir
            'location_flagged'   => $locationFlagged,
            'location_distance_m'=> $distanceM,
            'potencia_dbm'       => $potencia,  // columna añadida en migración 970010
            'gps_accuracy_m'     => $accuracy,
            'source'             => 'mobile',
            'created_by'         => $request->user()->id,
            'created_at'         => now(),
        ]);

        // Avanzar estado a in_progress si sigue en pending
        if ($ot->status === 'pending') {
            $ot->update(['status' => 'in_progress']);
        }

        $saludRed = null;
        if ($potencia !== null) {
            $saludRed = $this->calcularSaludRed($potencia, $ot);
        }

        return response()->json([
            'media_id'  => $mediaId,
            'salud_red' => $saludRed,
        ], 201);
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
        // Cliente: work_order → client_id → clients → user_id → users.name / users.address
        $clientName = null;
        $clientAddress = null;
        if ($ot->client_id) {
            $client = DB::table('clients')->where('id', $ot->client_id)->first(['user_id']);
            if ($client?->user_id) {
                $user = DB::table('users')->where('id', $client->user_id)->first(['name', 'address']);
                $clientName    = $user?->name;
                $clientAddress = $user?->address;
            }
        }

        return [
            'id'        => $ot->id,
            'folio'     => $ot->id,   // sin columna folio dedicada — usamos id
            'tipo'      => $ot->type?->name ?? '—',
            'status'    => $ot->status,
            'cliente'   => $clientName,
            'direccion' => $clientAddress,
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
