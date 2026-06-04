<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Services\DashboardService;
use App\Modules\Addons\Talento\Services\CompensationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Gateway para la app móvil "Talento Equipo" (React Native).
 *
 * Auth: Sanctum token (Bearer). Login provisionado por admin.
 * Todas las rutas son stateless (sin CSRF, sin sesión web).
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

        // Revocar tokens móviles anteriores de este usuario (un dispositivo activo a la vez)
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

        $hoy = now()->toDateString();
        $registro = DB::table('talento_attendances')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('check_in_at', $hoy)
            ->orderByDesc('id')
            ->first();

        if (! $registro) {
            return response()->json(['check_in_at' => null, 'check_out_at' => null]);
        }

        return response()->json([
            'id'           => $registro->id,
            'check_in_at'  => $registro->check_in_at,
            'check_out_at' => $registro->check_out_at,
            'geocerca'     => $registro->work_site_name ?? null,
            'lat'          => $registro->lat,
            'lng'          => $registro->lng,
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

        $hoy = now()->toDateString();
        $existing = DB::table('talento_attendances')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('check_in_at', $hoy)
            ->whereNull('check_out_at')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Ya tienes un check-in activo hoy.'], 422);
        }

        // Validar geocerca en servidor (radio configurado en talento_work_sites)
        $site = $this->nearestWorkSite($request->lat, $request->lng);

        $id = DB::table('talento_attendances')->insertGetId([
            'colaborador_id'  => $colaborador->id,
            'check_in_at'     => now(),
            'lat'             => $request->lat,
            'lng'             => $request->lng,
            'accuracy'        => $request->accuracy,
            'work_site_id'    => $site?->id,
            'work_site_name'  => $site?->name,
            'source'          => 'mobile',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        if (! $site) {
            return response()->json([
                'message'      => 'Check-in registrado fuera de geocerca.',
                'id'           => $id,
                'check_in_at'  => now()->toISOString(),
                'geocerca'     => null,
                'flagged'      => true,
            ], 201);
        }

        return response()->json([
            'id'          => $id,
            'check_in_at' => now()->toISOString(),
            'geocerca'    => $site->name,
            'flagged'     => false,
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
            ->update(['check_out_at' => now(), 'updated_at' => now()]);

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

        $ots = TalentoWorkOrder::with('type')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('scheduled_date', now()->toDateString())
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn($o) => $this->otSummary($o));

        return response()->json(['ots' => $ots]);
    }

    public function otShow(Request $request, int $id)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        $ot = TalentoWorkOrder::with(['type', 'evidences'])
            ->where('id', $id)
            ->where('colaborador_id', $colaborador->id)
            ->first();

        if (! $ot) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }

        return response()->json(['ot' => $this->otDetail($ot)]);
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

        // Guardar foto en disco privado, cifrada si está disponible la llave
        $path = $request->file('foto')->store(
            "talento/evidencias/ot_{$id}",
            'local'
        );

        $potencia = $request->input('potencia_dbm') !== null
            ? (float) $request->input('potencia_dbm')
            : null;

        $evidenciaId = DB::table('talento_field_evidences')->insertGetId([
            'work_order_id' => $ot->id,
            'colaborador_id'=> $colaborador->id,
            'file_path'     => $path,
            'lat'           => $request->input('lat'),
            'lng'           => $request->input('lng'),
            'accuracy'      => $request->input('accuracy'),
            'potencia_dbm'  => $potencia,
            'source'        => 'mobile',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Actualizar OT a en_curso si sigue pendiente
        if ($ot->status === 'pendiente') {
            $ot->update(['status' => 'en_curso']);
        }

        $saludRed = null;
        if ($potencia !== null) {
            $saludRed = $this->calcularSaludRed($potencia, $ot);
        }

        return response()->json([
            'evidencia_id' => $evidenciaId,
            'salud_red'    => $saludRed,
        ], 201);
    }

    // ── Compensación ──────────────────────────────────────────────────────────

    public function compensacionSemana(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) return $this->noColaborador();

        // Semana actual (lunes-domingo)
        $lunes   = now()->startOfWeek()->toDateString();
        $domingo = now()->endOfWeek()->toDateString();

        // Unidades completadas esta semana
        $unidades = TalentoWorkOrder::where('colaborador_id', $colaborador->id)
            ->where('status', 'completada')
            ->whereBetween('validated_at', [$lunes.' 00:00:00', $domingo.' 23:59:59'])
            ->count();

        // Cuota semanal del colaborador (de talento_compensation_rules o config)
        $cuota = DB::table('talento_compensation_rules')
            ->where('colaborador_id', $colaborador->id)
            ->where('active', true)
            ->value('weekly_quota') ?? 0;

        // Movimientos del ledger esta semana
        $movimientos = DB::table('talento_compensation_ledger')
            ->where('colaborador_id', $colaborador->id)
            ->whereBetween('created_at', [$lunes.' 00:00:00', $domingo.' 23:59:59'])
            ->orderByDesc('created_at')
            ->get(['id', 'tipo', 'concepto', 'monto', 'categoria', 'created_at as fecha'])
            ->toArray();

        // Proyectado = suma de CREDITs - suma de DEBITs
        $proyectado = collect($movimientos)->reduce(function ($carry, $m) {
            $m = (object) $m;
            return $carry + ($m->tipo === 'CREDIT' ? (float)$m->monto : -(float)$m->monto);
        }, 0.0);

        // Puntos del escalafón (acumulados históricos)
        $puntos = DB::table('talento_level_assignments')
            ->where('colaborador_id', $colaborador->id)
            ->value('accumulated_points') ?? 0;

        return response()->json([
            'periodo_inicio' => $lunes,
            'periodo_fin'    => $domingo,
            'corte'          => $domingo,
            'unidades'       => $unidades,
            'cuota'          => $cuota,
            'puntos'         => $puntos,
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
            'id'        => $col->id,
            'nombre'    => $col->nombre_completo ?? ($col->nombre.' '.($col->apellido ?? '')),
            'tipo'      => $col->tipo ?? $col->role_type,
            'email'     => $col->user?->email,
            'telefono'  => $col->telefono,
            'foto_url'  => $col->foto_url ?? null,
        ];
    }

    private function otSummary(TalentoWorkOrder $ot): array
    {
        return [
            'id'        => $ot->id,
            'folio'     => $ot->folio,
            'tipo'      => $ot->type?->nombre ?? $ot->type_name ?? '—',
            'status'    => $ot->status,
            'cliente'   => $ot->client_name ?? $ot->cliente,
            'direccion' => $ot->address ?? $ot->direccion,
        ];
    }

    private function otDetail(TalentoWorkOrder $ot): array
    {
        return array_merge($this->otSummary($ot), [
            'notas'                    => $ot->notas ?? $ot->notes,
            'equipo'                   => $ot->equipment_model ?? $ot->equipo,
            'onu_serial'               => $ot->onu_serial,
            'potencia_referencia_dbm'  => $ot->potencia_referencia_dbm,
            'telefono'                 => $ot->client_phone ?? $ot->telefono,
            'referencia'               => $ot->address_reference ?? $ot->referencia,
            'evidencias'               => $ot->evidences?->map(fn($e) => [
                'id'           => $e->id,
                'created_at'   => $e->created_at,
                'potencia_dbm' => $e->potencia_dbm,
            ])->toArray() ?? [],
        ]);
    }

    /** Haversine simplificado — retorna el sitio más cercano dentro de su radio. */
    private function nearestWorkSite(float $lat, float $lng)
    {
        $sites = DB::table('talento_work_sites')
            ->where('active', true)
            ->get(['id', 'name', 'lat', 'lng', 'radius_meters']);

        foreach ($sites as $site) {
            $dlat = deg2rad($lat - $site->lat);
            $dlng = deg2rad($lng - $site->lng);
            $a = sin($dlat / 2) ** 2
                + cos(deg2rad($site->lat)) * cos(deg2rad($lat)) * sin($dlng / 2) ** 2;
            $dist = 6371000 * 2 * asin(sqrt($a));
            if ($dist <= ($site->radius_meters ?? 300)) {
                return $site;
            }
        }
        return null;
    }

    private function calcularSaludRed(float $potencia, TalentoWorkOrder $ot): ?array
    {
        // Compara contra la potencia de referencia de la OT; si no tiene, usa -15 dBm como umbral
        $referencia = $ot->potencia_referencia_dbm ?? -15.0;
        $perdida    = $referencia - $potencia; // positivo = señal peor que referencia

        // Bono básico: si la pérdida <= 3 dB = bono verde, <= 6 = bono amarillo, > 6 = sin bono
        // Las reglas exactas se definen en talento_compensation_rules (Fase 4b del backend)
        $bono = match(true) {
            $perdida <= 3  => DB::table('talento_health_bonus_config')->value('bono_verde')  ?? 50,
            $perdida <= 6  => DB::table('talento_health_bonus_config')->value('bono_amarillo') ?? 25,
            default        => 0,
        };

        return [
            'potencia_medida_dbm'   => $potencia,
            'potencia_referencia_dbm' => $referencia,
            'perdida_db'            => round($perdida, 2),
            'calidad'               => $perdida <= 3 ? 'buena' : ($perdida <= 6 ? 'aceptable' : 'deficiente'),
            'bono'                  => $bono,
        ];
    }
}
