<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetClientWhatsappUsage;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetGpsDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Flotas de clientes" (#635): vista del personal sobre el servicio de flotas de los clientes.
 * Solo LEE suscripción/promo (cobro vive en #622). Las acciones de GPS reutilizan FleetGpsDeviceService.
 * Toda consulta/modificación queda en fleet_client_audit_log.
 */
class FleetClientsAdminController extends Controller
{
    private const ALERT_DAYS = 7;

    public function __construct(private FleetGpsDeviceService $gps)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('fleet.clients.view'), 403);

        $ids = DB::table('fleet_vehicles')->whereNotNull('client_id')->whereNull('deleted_at')->distinct()->pluck('client_id')
            ->merge(DB::table('fleet_subscriptions')->whereNotNull('client_id')->whereNull('deleted_at')->pluck('client_id'))
            ->unique()->values();

        $names = DB::table('client_main_information')->whereIn('client_id', $ids)
            ->selectRaw("client_id, TRIM(CONCAT_WS(' ', name, father_last_name, mother_last_name)) AS nombre")
            ->pluck('nombre', 'client_id');
        $subs = DB::table('fleet_subscriptions')->whereIn('client_id', $ids)->whereNull('deleted_at')->get()->keyBy('client_id');
        $units = DB::table('fleet_vehicles')->whereIn('client_id', $ids)->whereNull('deleted_at')->pluck('id', 'id')->keys();

        $veh = DB::table('fleet_vehicles as v')->whereIn('v.client_id', $ids)->whereNull('v.deleted_at')
            ->leftJoin('fleet_devices as d', fn ($j) => $j->on('d.vehicle_id', '=', 'v.id')->whereNull('d.deleted_at'))
            ->get(['v.id', 'v.client_id', 'd.id as device_id', 'd.status', 'd.last_seen_at'])
            ->groupBy('client_id');

        $alertas = DB::table('fleet_geofence_events as e')->join('fleet_vehicles as v', 'v.id', '=', 'e.vehicle_id')
            ->whereIn('v.client_id', $ids)->where('e.occurred_at', '>=', now()->subDays(self::ALERT_DAYS))
            ->groupBy('v.client_id')->selectRaw('v.client_id, COUNT(*) c, MAX(e.occurred_at) ult')->get()->keyBy('client_id');

        // Mensajes enviados en el mes (bitácora de envíos al cliente, P5) y cercanía al tope de WhatsApp.
        $desde = now()->startOfMonth();
        $envios = DB::table('fleet_client_alert_deliveries')->whereIn('client_id', $ids)
            ->where('status', 'sent')->where('created_at', '>=', $desde)
            ->groupBy('client_id', 'channel')->selectRaw('client_id, channel, COUNT(*) c')->get()->groupBy('client_id');
        $usoWa = DB::table('fleet_client_whatsapp_usage')->whereIn('client_id', $ids)
            ->where('periodo', now()->format('Y-m'))->pluck('enviados', 'client_id');
        $tope = FleetClientWhatsappUsage::tope();

        $rows = $ids->map(function ($cid) use ($names, $subs, $veh, $alertas, $envios, $usoWa, $tope) {
            $porCanal = ($envios->get($cid) ?? collect())->pluck('c', 'channel');
            $wa = (int) ($usoWa[$cid] ?? $porCanal['whatsapp'] ?? 0);
            $vs = $veh->get($cid, collect());
            $con = $vs->whereNotNull('device_id');
            $sub = $subs->get($cid);
            $sinConexion = $con->filter(fn ($x) => $x->status === 'active' && (! $x->last_seen_at || now()->diffInHours($x->last_seen_at) > 24))->count();
            $ultimas = $con->pluck('last_seen_at')->filter()->max();

            return [
                'client_id'       => $cid,
                'nombre'          => $names[$cid] ?? "Cliente #$cid",
                'plan'            => $sub->plan ?? null,
                'estado_servicio' => $sub->status ?? null,
                'en_promocion'    => (bool) ($sub->is_promo ?? false),
                'promo_hasta'     => $sub->trial_ends_at ?? null,
                'unidades'        => $vs->count(),
                'gps_total'       => $con->count(),
                'gps_activos'     => $con->where('status', 'active')->count() - $sinConexion,
                'gps_esperando'   => $con->where('status', 'pending_first_connection')->count(),
                'gps_sin_conexion' => $sinConexion,
                'alertas'         => (int) ($alertas[$cid]->c ?? 0),
                'msj_whatsapp'    => $wa,
                'msj_correo'      => (int) ($porCanal['email'] ?? 0),
                'tope_whatsapp'   => $tope,
                'tope_pct'        => $tope > 0 ? min(100, (int) round($wa * 100 / $tope)) : 0,
                'ultima_actividad' => $ultimas ?: ($alertas[$cid]->ult ?? null),
            ];
        })->values();

        // Filtros
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $rows = $rows->when($q !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['nombre']), $q) || str_contains((string) $r['client_id'], $q)))
            ->when($request->query('gps') === 'con', fn ($c) => $c->filter(fn ($r) => $r['gps_total'] > 0))
            ->when($request->query('gps') === 'sin', fn ($c) => $c->filter(fn ($r) => $r['gps_total'] === 0))
            ->when($request->boolean('promo'), fn ($c) => $c->filter(fn ($r) => $r['en_promocion']))
            ->when($request->boolean('alertas'), fn ($c) => $c->filter(fn ($r) => $r['alertas'] > 0))
            ->sortBy('nombre')->values();

        $this->audit(null, 'listado', ['filtros' => $request->query()]);

        return response()->json(['clientes' => $rows, 'alert_days' => self::ALERT_DAYS]);
    }

    public function show(int $clientId): JsonResponse
    {
        abort_unless(auth()->user()?->can('fleet.clients.view'), 403);
        $this->assertClient($clientId);

        $vehicles = FleetVehicle::where('client_id', $clientId)->with(['lastPosition'])->orderBy('brand')->orderBy('model')->get();
        $vehicles->each(function ($v) {
            $v->setAttribute('gps', $this->gps->resumen($v));
        });
        $vids = $vehicles->pluck('id');

        $eventos = DB::table('fleet_geofence_events as e')->join('fleet_geofences as g', 'g.id', '=', 'e.geofence_id')
            ->join('fleet_vehicles as v', 'v.id', '=', 'e.vehicle_id')
            ->whereIn('e.vehicle_id', $vids)->where('v.client_id', $clientId)
            ->orderByDesc('e.occurred_at')->limit(50)
            ->get(['e.id', 'e.event_type', 'e.occurred_at', 'g.name as geocerca', 'v.plates', 'v.brand', 'v.model']);

        $geocercas = DB::table('fleet_geofences')->where('client_id', $clientId)->whereNull('deleted_at')->get(['id', 'name', 'type', 'active']);
        $reglas = DB::table('fleet_geofence_rules')->where('client_id', $clientId)->whereNull('deleted_at')->get(['id', 'name', 'active']);
        $sub = DB::table('fleet_subscriptions')->where('client_id', $clientId)->whereNull('deleted_at')->first();
        $nombre = DB::table('client_main_information')->where('client_id', $clientId)
            ->selectRaw("TRIM(CONCAT_WS(' ', name, father_last_name, mother_last_name)) AS n")->value('n');

        $this->audit($clientId, 'detalle');

        return response()->json([
            'cliente'   => ['id' => $clientId, 'nombre' => $nombre ?: "Cliente #$clientId"],
            'suscripcion' => $sub ? [
                'plan' => $sub->plan, 'status' => $sub->status, 'is_promo' => (bool) $sub->is_promo,
                'trial_ends_at' => $sub->trial_ends_at, 'vehicles_count' => $sub->vehicles_count,
            ] : null,
            'vehicles'  => $vehicles,
            'eventos'   => $eventos,
            'geocercas' => $geocercas,
            'reglas'    => $reglas,
            'bitacora'  => DB::table('fleet_client_audit_log as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->where('a.client_id', $clientId)->orderByDesc('a.id')->limit(20)
                ->get(['a.action', 'a.created_at', DB::raw("TRIM(CONCAT_WS(' ', u.name, u.father_last_name)) as usuario")]),
        ]);
    }

    public function storeGps(Request $request, int $clientId, int $id): JsonResponse
    {
        abort_unless(auth()->user()?->can('fleet.clients.manage'), 403);
        $this->assertClient($clientId);

        $vehicle = FleetVehicle::where('client_id', $clientId)->findOrFail($id);
        $r = $this->gps->vincular($vehicle, $request->all());
        $this->audit($clientId, 'gps_vincular', ['vehicle_id' => $id]);

        return response()->json(['gps' => $this->gps->resumen($vehicle), 'listener' => $r['listener']], 201);
    }

    public function destroyGps(int $clientId, int $id): JsonResponse
    {
        abort_unless(auth()->user()?->can('fleet.clients.manage'), 403);
        $this->assertClient($clientId);

        $this->gps->quitar(FleetVehicle::where('client_id', $clientId)->findOrFail($id));
        $this->audit($clientId, 'gps_quitar', ['vehicle_id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function audit(?int $clientId, string $action, array $detail = []): void
    {
        DB::table('fleet_client_audit_log')->insert([
            'user_id' => auth()->id(), 'client_id' => $clientId, 'action' => $action,
            'detail' => $detail ? json_encode($detail) : null, 'created_at' => now(),
        ]);
    }

    private function assertClient(int $clientId): void
    {
        abort_unless(DB::table('clients')->where('id', $clientId)->exists(), 404, 'Cliente no encontrado.');
    }
}
