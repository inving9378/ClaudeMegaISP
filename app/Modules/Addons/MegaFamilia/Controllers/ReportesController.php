<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAppBlock;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReportesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::reportes.index');
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->buildData($request->input('profile_id')));
    }

    public function export(Request $request): Response
    {
        $profileId = $request->input('profile_id');
        $data = $this->buildData($profileId);
        $profileName = $profileId
            ? optional(ParentalProfile::find($profileId))->name
            : null;

        $pdf = Pdf::loadView('addon-megafamilia::pdf.reportes', [
            'topApps' => $data['top_apps'],
            'screenByDay' => $data['screen_by_day'],
            'generatedAt' => now()->format('Y-m-d H:i'),
            'profileName' => $profileName,
        ])->setPaper('letter');
        $filename = 'megafamilia-reportes-' . now()->format('Ymd-His') . '.pdf';
        return $pdf->download($filename);
    }

    private function buildData($profileId): array
    {
        $topApps = ParentalAppBlock::query()
            ->when($profileId, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->select('app_name', DB::raw('count(*) as total'))
            ->whereNotNull('app_name')
            ->groupBy('app_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $screenByDay = ParentalEvent::query()
            ->where('action', 'screen_time_log')
            ->when($profileId, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->where('created_at', '>=', now()->subDays(14))
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as samples'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'top_apps' => $topApps,
            'screen_by_day' => $screenByDay,
        ];
    }
}
