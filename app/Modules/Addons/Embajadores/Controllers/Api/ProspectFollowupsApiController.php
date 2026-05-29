<?php

namespace App\Modules\Addons\Embajadores\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Embajadores\StoreFollowupRequest;
use App\Http\Resources\Embajadores\ProspectFollowupResource;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\Referrals\ProspectFollowup;
use App\Models\Referrals\ReferralProspect;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProspectFollowupsApiController extends Controller
{
    // ---- helpers -----------------------------------------------------------

    private function resolveClient(): ?Client
    {
        $client = Client::where('user_id', Auth::id())->first();
        if ($client) return $client;

        $loginUser = optional(Auth::user())->login_user;
        if (! $loginUser) return null;

        $cmi = ClientMainInformation::where('user', $loginUser)->first();
        if (! $cmi) return null;

        return Client::find($cmi->client_id);
    }

    // ---- index -------------------------------------------------------------

    /**
     * GET /api/megafamilia/embajadores/prospects/{id}/followups
     */
    public function index(int $id): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) {
            return response()->json(['error' => 'Cliente no encontrado.'], 404);
        }

        $prospect = ReferralProspect::find($id);
        if (! $prospect) {
            return response()->json(['error' => 'Prospecto no encontrado.'], 404);
        }

        $this->authorize('manage', $prospect);

        $followups = ProspectFollowup::where('prospect_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(ProspectFollowupResource::collection($followups));
    }

    // ---- store -------------------------------------------------------------

    /**
     * POST /api/megafamilia/embajadores/prospects/{id}/followups
     */
    public function store(StoreFollowupRequest $request, int $id): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) {
            return response()->json(['error' => 'Cliente no encontrado.'], 404);
        }

        $prospect = ReferralProspect::find($id);
        if (! $prospect) {
            return response()->json(['error' => 'Prospecto no encontrado.'], 404);
        }

        $this->authorize('manage', $prospect);

        $data = $request->validated();

        $followup = ProspectFollowup::create([
            'prospect_id'      => $id,
            'embajador_id'     => $client->id,
            'action'           => $data['action'],
            'notes'            => $data['notes'],
            'next_action_date' => $data['next_action_date'] ?? null,
            'created_at'       => now(),
        ]);

        // Mantener last_contact_at actualizado en el prospecto
        $prospect->update(['last_contact_at' => now()]);

        return response()->json(new ProspectFollowupResource($followup), 201);
    }
}
