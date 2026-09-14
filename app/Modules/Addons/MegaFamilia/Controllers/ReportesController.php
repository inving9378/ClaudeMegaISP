<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use App\Modules\Addons\MegaFamilia\Models\ParentalRequest as ParentalReq;
use App\Modules\Addons\MegaFamilia\Models\ParentalTask;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::reportes.index');
    }

    public function profiles(): JsonResponse
    {
        $account = $this->accountForCurrentUser();
        if (! $account) {
            // Sin cuenta parental propia = usuario staff, no cliente final.
            // Mismo criterio que en Perfiles/Tareas: exigir megafamilia_admin
            // en vez de listar los perfiles de TODAS las familias.
            abort_unless(Auth::user()->can('megafamilia_admin'), 403);
        }

        $profiles = ParentalProfile::query()
            ->where('active', true)
            ->when($account, fn ($q) => $q->where('account_id', $account->id))
            ->orderBy('name')
            ->get(['id', 'name', 'profile_type', 'photo']);
        return response()->json(['profiles' => $profiles]);
    }

    public function data(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $profileId = $this->resolveProfileFilter($request);

        return response()->json([
            'range'              => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis'               => $this->kpis($profileId, $from, $to),
            'screen_time_by_day' => $this->screenTimeByDay($profileId, $from, $to),
            'top_apps'           => $this->topApps($profileId, $from, $to),
            'activity_by_hour'   => $this->activityByHour($profileId, $from, $to),
            'blocked_sites'      => $this->blockedSites($profileId, $from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $profileId = $this->resolveProfileFilter($request);

        $byDay   = $this->screenTimeByDay($profileId, $from, $to);
        $topApps = $this->topApps($profileId, $from, $to);
        $blocked = $this->blockedSites($profileId, $from, $to);

        $filename = 'megafamilia-reportes-' . now()->format('Ymd-His') . '.csv';
        $profileLabel = is_array($profileId) ? 'Mis perfiles' : ($profileId ?: 'Todos');

        return new StreamedResponse(function () use ($byDay, $topApps, $blocked, $from, $to, $profileLabel) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM Excel

            fputcsv($out, ['Reporte MegaFamilia']);
            fputcsv($out, ['Rango', $from->toDateString() . ' - ' . $to->toDateString()]);
            fputcsv($out, ['Perfil', $profileLabel]);
            fputcsv($out, []);

            fputcsv($out, ['Tiempo de pantalla por día']);
            fputcsv($out, ['Fecha', 'Minutos']);
            foreach ($byDay as $r) fputcsv($out, [$r['day'], $r['minutes']]);
            fputcsv($out, []);

            fputcsv($out, ['Top apps']);
            fputcsv($out, ['App', 'Tiempo total (min)', 'Porcentaje']);
            foreach ($topApps as $r) fputcsv($out, [$r['app'], $r['minutes'], $r['percent'] . '%']);
            fputcsv($out, []);

            fputcsv($out, ['Sitios bloqueados intentados']);
            fputcsv($out, ['Dominio', 'Intentos', 'Última vez']);
            foreach ($blocked as $r) fputcsv($out, [$r['domain'], $r['attempts'], $r['last_at']]);

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function range(Request $request): array
    {
        $from = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::now()->subDays(14)->startOfDay();
        $to = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();
        return [$from, $to];
    }

    private function kpis($profileId, Carbon $from, Carbon $to): array
    {
        $screen = $this->screenTimeByDay($profileId, $from, $to);
        $totalScreen = (int) collect($screen)->sum('minutes');

        $topAppRow = $this->topApps($profileId, $from, $to)->first();

        $requests = ParentalReq::when($profileId !== null, fn ($qq) => $this->applyProfileScope($qq, $profileId))
            ->whereBetween('created_at', [$from, $to])->count();

        $tasksDone = ParentalTask::when($profileId !== null, fn ($qq) => $this->applyProfileScope($qq, $profileId))
            ->whereIn('status', ['completed', 'approved'])
            ->whereBetween('updated_at', [$from, $to])->count();

        return [
            'total_screen_minutes' => $totalScreen,
            'top_app'              => $topAppRow['app'] ?? '—',
            'requests_count'       => $requests,
            'tasks_completed'      => $tasksDone,
        ];
    }

    /**
     * Tiempo de pantalla por día. Convención: action='screen_time' y detail
     * puede ser JSON {minutes: N}, número, o vacío (en cuyo caso 1 evento ≈ 1 min).
     */
    private function screenTimeByDay($profileId, Carbon $from, Carbon $to)
    {
        $events = ParentalEvent::query()
            ->where('action', 'screen_time')
            ->when($profileId !== null, fn ($qq) => $this->applyProfileScope($qq, $profileId))
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['created_at', 'detail']);

        $bucket = [];
        foreach ($events as $e) {
            $day = $e->created_at->format('Y-m-d');
            $bucket[$day] = ($bucket[$day] ?? 0) + $this->extractMinutes($e->detail);
        }

        $rows = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $k = $d->format('Y-m-d');
            $rows[] = ['day' => $k, 'minutes' => $bucket[$k] ?? 0];
        }
        return collect($rows);
    }

    /**
     * Top 10 apps. Convención: action='app_usage', detail JSON {app,minutes} o
     * string con el nombre de la app.
     */
    private function topApps($profileId, Carbon $from, Carbon $to)
    {
        $events = ParentalEvent::query()
            ->where('action', 'app_usage')
            ->when($profileId !== null, fn ($qq) => $this->applyProfileScope($qq, $profileId))
            ->whereBetween('created_at', [$from, $to])
            ->get(['detail']);

        $totals = [];
        foreach ($events as $e) {
            $parsed = $this->parseDetail($e->detail);
            $app    = $parsed['app'] ?? $parsed['app_name'] ?? (is_string($e->detail) ? trim($e->detail) : null);
            if (!$app) continue;
            $totals[$app] = ($totals[$app] ?? 0) + $this->extractMinutes($e->detail);
        }
        arsort($totals);
        $top   = array_slice($totals, 0, 10, true);
        $grand = array_sum($totals) ?: 1;

        return collect($top)->map(fn ($mins, $app) => [
            'app'     => $app,
            'minutes' => (int) $mins,
            'percent' => round(($mins / $grand) * 100, 1),
        ])->values();
    }

    private function activityByHour($profileId, Carbon $from, Carbon $to)
    {
        $rows = ParentalEvent::query()
            ->when($profileId !== null, fn ($qq) => $this->applyProfileScope($qq, $profileId))
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as total'))
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $out[] = ['hour' => $h, 'total' => (int) ($rows[$h]->total ?? 0)];
        }
        return collect($out);
    }

    /**
     * Sitios bloqueados intentados. Convención: action='web_blocked', detail
     * JSON {domain:'x'} o string con el dominio.
     */
    private function blockedSites($profileId, Carbon $from, Carbon $to)
    {
        $events = ParentalEvent::query()
            ->where('action', 'web_blocked')
            ->when($profileId !== null, fn ($qq) => $this->applyProfileScope($qq, $profileId))
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get(['detail', 'created_at']);

        $totals = [];
        foreach ($events as $e) {
            $parsed = $this->parseDetail($e->detail);
            $domain = $parsed['domain'] ?? $parsed['url'] ?? (is_string($e->detail) ? trim($e->detail) : null);
            if (!$domain) continue;
            if (!isset($totals[$domain])) {
                $totals[$domain] = ['attempts' => 0, 'last_at' => $e->created_at];
            }
            $totals[$domain]['attempts']++;
            if ($e->created_at->gt($totals[$domain]['last_at'])) {
                $totals[$domain]['last_at'] = $e->created_at;
            }
        }
        uasort($totals, fn ($a, $b) => $b['attempts'] <=> $a['attempts']);

        return collect($totals)->take(30)->map(fn ($r, $domain) => [
            'domain'   => $domain,
            'attempts' => $r['attempts'],
            'last_at'  => $r['last_at']->toDateTimeString(),
        ])->values();
    }

    private function parseDetail(?string $detail): array
    {
        if (!$detail) return [];
        $j = json_decode($detail, true);
        return is_array($j) ? $j : [];
    }

    private function extractMinutes($detail): int
    {
        if (is_numeric($detail)) return (int) $detail;
        $p = $this->parseDetail($detail);
        if (isset($p['minutes']))  return (int) $p['minutes'];
        if (isset($p['duration'])) return (int) $p['duration'];
        return 1;
    }

    private function accountForCurrentUser(): ?ParentalAccount
    {
        $userId = Auth::id();
        if (! $userId) return null;
        return ParentalAccount::where('user_id', $userId)->first();
    }

    /**
     * Mismo criterio que PerfilesController::guardOwnership / TareasController::
     * guardProfileAccess: el cliente final solo ve reportes de sus propios
     * perfiles; sin cuenta parental propia (staff) se exige megafamilia_admin.
     */
    private function guardOwnership(ParentalProfile $profile): void
    {
        $account = $this->accountForCurrentUser();
        if (! $account) {
            abort_unless(Auth::user()->can('megafamilia_admin'), 403);
            return;
        }
        abort_unless($profile->account_id === $account->id, 403);
    }

    /**
     * Resuelve el filtro de perfil para los reportes:
     * - profile_id explícito -> valida ownership y devuelve el id (int).
     * - sin profile_id y con cuenta propia -> devuelve el arreglo de ids de
     *   ESA cuenta (puede ser [] si aún no tiene perfiles), nunca "todos".
     * - sin profile_id y sin cuenta (staff) -> exige megafamilia_admin y
     *   devuelve null (agregado global autorizado).
     *
     * @return int|array<int>|null
     */
    private function resolveProfileFilter(Request $request)
    {
        $profileId = $request->input('profile_id');
        if ($profileId) {
            $this->guardOwnership(ParentalProfile::findOrFail($profileId));
            return (int) $profileId;
        }

        $account = $this->accountForCurrentUser();
        if (! $account) {
            abort_unless(Auth::user()->can('megafamilia_admin'), 403);
            return null;
        }

        return $account->profiles()->pluck('id')->all();
    }

    /**
     * Aplica el filtro resuelto por resolveProfileFilter() a una query por
     * 'profile_id'. Un arreglo vacío (cuenta sin perfiles) fuerza un
     * whereIn que no matchea nada, en vez de dejar la query sin filtrar.
     *
     * @param  int|array<int>|null  $profileId
     */
    private function applyProfileScope(Builder|\Illuminate\Database\Query\Builder $query, $profileId)
    {
        if (is_array($profileId)) {
            return $query->whereIn('profile_id', $profileId ?: [-1]);
        }
        return $query->where('profile_id', $profileId);
    }
}
