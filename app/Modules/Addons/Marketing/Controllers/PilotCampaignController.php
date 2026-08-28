<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\PilotCampaign;
use App\Modules\Addons\Marketing\Services\Pilot\PilotCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Piloto interno de campaña multivariante A/B por email — item roadmap #47.
 * Solo lista de prueba (empleados/cuentas dummy) — nunca clientes reales.
 */
class PilotCampaignController extends Controller
{
    public function __construct(private PilotCampaignService $service)
    {
    }

    public function index()
    {
        $campaigns = PilotCampaign::withCount('sends')
            ->orderByDesc('id')
            ->get(['id', 'name', 'status', 'dry_run_at', 'sent_at', 'created_at']);

        return response()->json(['campaigns' => $campaigns]);
    }

    public function show(int $id)
    {
        $campaign = PilotCampaign::with('sends')->findOrFail($id);

        $sends = $campaign->sends;

        return response()->json([
            'campaign' => $campaign,
            'stats'    => [
                'total'     => $sends->count(),
                'sent'      => $sends->whereIn('status', ['sent', 'opened', 'clicked', 'converted'])->count(),
                'failed'    => $sends->where('status', 'failed')->count(),
                'opened'    => $sends->whereNotNull('opened_at')->count(),
                'clicked'   => $sends->whereNotNull('clicked_at')->count(),
                'converted' => $sends->whereNotNull('converted_at')->count(),
                'by_variant' => [
                    'a' => $this->variantStats($sends->where('variant', 'a')),
                    'b' => $this->variantStats($sends->where('variant', 'b')),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'variant_a_subject'     => ['required', 'string', 'max:255'],
            'variant_a_body'        => ['required', 'string'],
            'variant_a_cta_label'   => ['nullable', 'string', 'max:80'],
            'variant_a_cta_url'     => ['nullable', 'string', 'max:500', 'url'],
            'variant_b_subject'     => ['required', 'string', 'max:255'],
            'variant_b_body'        => ['required', 'string'],
            'variant_b_cta_label'   => ['nullable', 'string', 'max:80'],
            'variant_b_cta_url'     => ['nullable', 'string', 'max:500', 'url'],
            'batch_size'            => ['nullable', 'integer', 'min:1', 'max:500'],
            'batch_pause_seconds'   => ['nullable', 'integer', 'min:0', 'max:120'],
            'recipients'            => ['required', 'array', 'min:1'],
            'recipients.*.email'    => ['required', 'email'],
            'recipients.*.name'     => ['nullable', 'string', 'max:150'],
        ]);

        $data['created_by_user_id'] = Auth::id();

        $campaign = $this->service->create($data);

        return response()->json(['campaign' => $campaign], 201);
    }

    public function destroy(int $id)
    {
        $campaign = PilotCampaign::findOrFail($id);
        if (in_array($campaign->status, ['sending'], true)) {
            return response()->json(['message' => 'No se puede eliminar una campaña en envío.'], 422);
        }
        $campaign->delete();

        return response()->json(['deleted' => true]);
    }

    public function dryRun(int $id)
    {
        $campaign = PilotCampaign::findOrFail($id);
        $report = $this->service->dryRun($campaign);

        return response()->json(['report' => $report, 'campaign' => $campaign->fresh()]);
    }

    public function send(int $id)
    {
        $campaign = PilotCampaign::findOrFail($id);

        try {
            $result = $this->service->send($campaign);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['result' => $result, 'campaign' => $campaign->fresh()]);
    }

    public function markConverted(int $id, int $sendId)
    {
        $campaign = PilotCampaign::findOrFail($id);
        $send = $campaign->sends()->findOrFail($sendId);
        $this->service->markConverted($send);

        return response()->json(['send' => $send->fresh()]);
    }

    private function variantStats($sends): array
    {
        return [
            'total'     => $sends->count(),
            'sent'      => $sends->whereIn('status', ['sent', 'opened', 'clicked', 'converted'])->count(),
            'opened'    => $sends->whereNotNull('opened_at')->count(),
            'clicked'   => $sends->whereNotNull('clicked_at')->count(),
            'converted' => $sends->whereNotNull('converted_at')->count(),
        ];
    }
}
