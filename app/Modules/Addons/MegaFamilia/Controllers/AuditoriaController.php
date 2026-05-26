<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaController extends Controller
{
    /** Categorías de acciones para coloreado en frontend (replicado en Vue). */
    private const CRITICAL_ACTIONS = ['uninstall_attempt', 'sos', 'geofence_exit'];

    public function index()
    {
        return view('addon-megafamilia::auditoria.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = $this->filtered($request);

        $kpis = [
            'today'      => ParentalEvent::whereDate('created_at', Carbon::today())->count(),
            'this_week'  => ParentalEvent::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'critical'   => ParentalEvent::whereIn('action', self::CRITICAL_ACTIONS)
                              ->where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
        ];

        $events = $q->with([
            'profile:id,name,profile_type,photo',
            'device:id,name,os',
            'account:id,user_id',
            'account.user:id,name,email',
        ])->orderByDesc('id')
          ->paginate((int) $request->input('per_page', 50));

        $actions = ParentalEvent::query()
            ->select('action')->distinct()
            ->orderBy('action')->pluck('action');

        return response()->json([
            'kpis'         => $kpis,
            'events'       => $events,
            'actions_list' => $actions,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $q = $this->filtered($request);
        $filename = 'megafamilia-auditoria-' . now()->format('Ymd-His') . '.csv';

        return new StreamedResponse(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Fecha/Hora', 'Acción', 'Cuenta', 'Perfil', 'Dispositivo', 'IP', 'Detalle']);

            $q->with(['profile:id,name', 'device:id,name', 'account.user:id,name'])
              ->orderByDesc('id')
              ->chunk(500, function ($rows) use ($out) {
                  foreach ($rows as $e) {
                      fputcsv($out, [
                          $e->id,
                          $e->created_at,
                          $e->action,
                          $e->account?->user?->name ?? '',
                          $e->profile?->name ?? '',
                          $e->device?->name ?? '',
                          $e->ip ?? '',
                          (string) $e->detail,
                      ]);
                  }
              });

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filtered(Request $request)
    {
        return ParentalEvent::query()
            ->when($request->action,     fn ($q, $v) => $q->where('action', $v))
            ->when($request->account_id, fn ($q, $v) => $q->where('account_id', $v))
            ->when($request->profile_id, fn ($q, $v) => $q->where('profile_id', $v))
            ->when($request->device_id,  fn ($q, $v) => $q->where('device_id', $v))
            ->when($request->date_from,  fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to,    fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
