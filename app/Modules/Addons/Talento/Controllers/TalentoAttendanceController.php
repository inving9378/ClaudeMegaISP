<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoShiftExtension;
use App\Modules\Addons\Talento\Services\AttendanceService;
use Illuminate\Http\Request;

class TalentoAttendanceController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    public function index()
    {
        $this->authorize('talento.attendance.view');
        return view('addon-talento::talento.asistencia');
    }

    public function data(Request $request)
    {
        $this->authorize('talento.attendance.view');

        $q = TalentoAttendance::with(['colaborador.user', 'site'])
            ->when($request->colaborador_id, fn($q, $v) => $q->where('colaborador_id', $v))
            ->when($request->status,         fn($q, $v) => $q->where('status', $v))
            ->when($request->flagged,        fn($q) => $q->where('check_in_flagged', true))
            ->when($request->from,           fn($q, $v) => $q->where('check_in_at', '>=', $v))
            ->when($request->to,             fn($q, $v) => $q->where('check_in_at', '<=', $v . ' 23:59:59'))
            ->orderBy('check_in_at', 'desc')
            ->paginate($request->per_page ?? 30);

        return response()->json($q);
    }

    public function show($id)
    {
        $this->authorize('talento.attendance.view');

        $att = TalentoAttendance::with(['colaborador.user', 'site', 'workOrder', 'extensions'])->findOrFail($id);

        // Last known ping
        $lastPing = $att->pings()->latest('recorded_at')->first();
        $att->last_ping = $lastPing;

        return response()->json($att);
    }

    // ── App endpoints (check-in / check-out / ping) ──────────────────────────

    /**
     * POST /talento/api/asistencia/check-in
     * Called by mobile app.
     */
    public function checkIn(Request $request)
    {
        $this->authorize('talento.attendance.checkin');

        $data = $request->validate([
            'colaborador_id'  => 'required|exists:talento_colaboradores,id',
            'latitude'        => 'required|numeric|between:-90,90',
            'longitude'       => 'required|numeric|between:-180,180',
            'accuracy_m'      => 'nullable|numeric|min:0',
            'work_order_id'   => 'nullable|exists:talento_work_orders,id',
        ]);

        $result = $this->service->checkIn(
            $data['colaborador_id'],
            (float)$data['latitude'],
            (float)$data['longitude'],
            isset($data['accuracy_m']) ? (float)$data['accuracy_m'] : null,
            $data['work_order_id'] ?? null
        );

        $code = $result['status'] === 'already_open' ? 200 : 201;
        return response()->json($result, $code);
    }

    /**
     * POST /talento/api/asistencia/check-out
     */
    public function checkOut(Request $request)
    {
        $this->authorize('talento.attendance.checkin');

        $data = $request->validate([
            'colaborador_id' => 'required|exists:talento_colaboradores,id',
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
        ]);

        $result = $this->service->checkOut(
            $data['colaborador_id'],
            isset($data['latitude']) ? (float)$data['latitude'] : null,
            isset($data['longitude']) ? (float)$data['longitude'] : null
        );

        return response()->json($result);
    }

    /**
     * POST /talento/api/asistencia/ping
     * High-frequency; only persists when attendance is open.
     */
    public function ping(Request $request)
    {
        $this->authorize('talento.attendance.checkin');

        $data = $request->validate([
            'colaborador_id' => 'required|exists:talento_colaboradores,id',
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
            'accuracy_m'     => 'nullable|numeric|min:0',
            'battery'        => 'nullable|integer|between:0,100',
            'recorded_at'    => 'nullable|date',
        ]);

        $ping = $this->service->storePing(
            $data['colaborador_id'],
            (float)$data['latitude'],
            (float)$data['longitude'],
            isset($data['accuracy_m']) ? (float)$data['accuracy_m'] : null,
            $data['battery'] ?? null,
            $data['recorded_at'] ?? null
        );

        return response()->json(['stored' => $ping !== null]);
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

    /**
     * Update day_type or notes (supervisor classification).
     */
    public function updateAdmin(Request $request, $id)
    {
        $this->authorize('talento.attendance.manage');

        $att = TalentoAttendance::findOrFail($id);

        $data = $request->validate([
            'day_type' => 'sometimes|in:worked,no_work_company,absent',
            'notes'    => 'nullable|string',
            'status'   => 'sometimes|in:open,closed,flagged',
        ]);

        $att->update($data);
        return response()->json($att);
    }

    // ── Shift extension ───────────────────────────────────────────────────────

    public function addExtension(Request $request, $id)
    {
        $this->authorize('talento.attendance.checkin');

        $att = TalentoAttendance::findOrFail($id);

        $data = $request->validate([
            'minutes'         => 'required|integer|min:1|max:480',
            'reason_category' => 'required|in:overtime,emergency,client_request,other',
            'reason_text'     => 'nullable|string|max:500',
        ]);

        $ext = TalentoShiftExtension::create([
            'attendance_id'   => $att->id,
            'minutes'         => $data['minutes'],
            'reason_category' => $data['reason_category'],
            'reason_text'     => $data['reason_text'] ?? null,
            'created_by'      => auth()->id(),
        ]);

        // Advance expected_end_at if set
        if ($att->expected_end_at) {
            $att->update(['expected_end_at' => $att->expected_end_at->addMinutes($data['minutes'])]);
        }

        return response()->json(['extension' => $ext, 'expected_end_at' => $att->fresh()->expected_end_at]);
    }

    // ── Live location data ────────────────────────────────────────────────────

    /**
     * Current location + today's route for a collaborator (for admin map).
     */
    public function liveLocation($colaboradorId)
    {
        $this->authorize('talento.location.view');

        TalentoColaborador::findOrFail($colaboradorId);

        $attendance = TalentoAttendance::where('colaborador_id', $colaboradorId)
            ->whereIn('status', ['open', 'flagged'])
            ->latest('check_in_at')
            ->first();

        if (!$attendance) {
            return response()->json(['status' => 'not_checked_in', 'pings' => []]);
        }

        $pings = $attendance->pings()
            ->select('latitude', 'longitude', 'accuracy_m', 'battery', 'recorded_at')
            ->get();

        $lastPing = $pings->last();

        // Optionally attach fleet vehicle position if collaborator has active assignment
        $vehiclePos = null;
        $fleetAssignment = \Illuminate\Support\Facades\DB::table('fleet_assignments')
            ->where('user_id', \Illuminate\Support\Facades\DB::table('talento_colaboradores')
                ->where('id', $colaboradorId)->value('user_id'))
            ->whereNull('until')
            ->whereNull('deleted_at')
            ->first();

        if ($fleetAssignment) {
            $vehiclePos = \Illuminate\Support\Facades\DB::table('fleet_positions as p')
                ->join('fleet_devices as d', 'd.id', '=', 'p.device_id')
                ->where('d.vehicle_id', $fleetAssignment->vehicle_id)
                ->whereNull('p.deleted_at')
                ->orderBy('p.recorded_at', 'desc')
                ->select('p.lat', 'p.lng', 'p.speed', 'p.recorded_at')
                ->first();
        }

        return response()->json([
            'status'        => 'checked_in',
            'attendance_id' => $attendance->id,
            'check_in_at'   => $attendance->check_in_at,
            'expected_end_at'=> $attendance->expected_end_at,
            'flagged'       => $attendance->check_in_flagged,
            'last_ping'     => $lastPing,
            'pings'         => $pings,
            'vehicle_position' => $vehiclePos,
        ]);
    }

    /**
     * Live status of all checked-in collaborators (for fleet-style map).
     */
    public function liveAll()
    {
        $this->authorize('talento.location.view');

        $openAttendances = TalentoAttendance::with('colaborador.user')
            ->whereIn('status', ['open', 'flagged'])
            ->get();

        $result = $openAttendances->map(function ($att) {
            $lastPing = $att->pings()->latest('recorded_at')->first(['latitude', 'longitude', 'recorded_at', 'battery']);
            return [
                'colaborador_id'   => $att->colaborador_id,
                'name'             => $att->colaborador?->user?->name,
                'attendance_id'    => $att->id,
                'check_in_at'      => $att->check_in_at,
                'expected_end_at'  => $att->expected_end_at,
                'flagged'          => $att->check_in_flagged,
                'last_ping'        => $lastPing,
            ];
        });

        return response()->json($result);
    }
}
