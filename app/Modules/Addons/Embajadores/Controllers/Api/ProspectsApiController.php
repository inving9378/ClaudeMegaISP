<?php

namespace App\Modules\Addons\Embajadores\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Embajadores\StoreProspectRequest;
use App\Http\Requests\Embajadores\UpdateProspectRequest;
use App\Http\Resources\Embajadores\ProspectResource;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\ReferralProspect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProspectsApiController extends Controller
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

    private function noClient(): JsonResponse
    {
        return response()->json(['error' => 'Cliente no encontrado.'], 404);
    }

    private function requireEmbajador(Client $client): ?ClientReferralProfile
    {
        return ClientReferralProfile::where('client_id', $client->id)->first();
    }

    // ---- index -------------------------------------------------------------

    /**
     * GET /api/megafamilia/embajadores/prospects
     * Query params: search, status, source, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        if (! $this->requireEmbajador($client)) {
            return response()->json(['error' => 'Aún no eres embajador.'], 403);
        }

        $q = ReferralProspect::where('embajador_id', $client->id)
            ->withCount('followups');

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $q->where(fn ($w) => $w->where('name', 'like', $term)
                                   ->orWhere('phone', 'like', $term));
        }

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $q->where('source', $request->source);
        }

        $perPage   = min((int) ($request->per_page ?? 20), 100);
        $paginated = $q->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data'         => ProspectResource::collection($paginated->items()),
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // ---- store -------------------------------------------------------------

    /**
     * POST /api/megafamilia/embajadores/prospects
     */
    public function store(StoreProspectRequest $request): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        if (! $this->requireEmbajador($client)) {
            return response()->json(['error' => 'Aún no eres embajador.'], 403);
        }

        $data = $request->validated();

        $prospect = ReferralProspect::create([
            'embajador_id' => $client->id,
            'name'         => $data['name'],
            'phone'        => $data['phone'],
            'email'        => $data['email'] ?? null,
            'address'      => $data['address'] ?? null,
            'source'       => $data['source'] ?? 'manual',
            'status'       => 'new',
            'notes'        => $data['notes'] ?? null,
        ]);

        return response()->json(new ProspectResource($prospect), 201);
    }

    // ---- show --------------------------------------------------------------

    /**
     * GET /api/megafamilia/embajadores/prospects/{id}
     * Devuelve el prospecto con su historial de seguimientos.
     */
    public function show(int $id): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $prospect = ReferralProspect::with(['followups' => fn ($q) => $q->orderByDesc('created_at')])
            ->find($id);

        if (! $prospect) {
            return response()->json(['error' => 'Prospecto no encontrado.'], 404);
        }

        $this->authorize('manage', $prospect);

        return response()->json(new ProspectResource($prospect));
    }

    // ---- update ------------------------------------------------------------

    /**
     * PUT /api/megafamilia/embajadores/prospects/{id}
     */
    public function update(UpdateProspectRequest $request, int $id): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $prospect = ReferralProspect::find($id);
        if (! $prospect) {
            return response()->json(['error' => 'Prospecto no encontrado.'], 404);
        }

        $this->authorize('manage', $prospect);

        $data = $request->validated();
        $prospect->update($data);

        // Si el status avanzó, actualizar last_contact_at
        if (isset($data['status']) && $data['status'] !== 'new') {
            $prospect->last_contact_at = now();
            $prospect->save();
        }

        return response()->json(new ProspectResource($prospect->fresh()));
    }

    // ---- destroy -----------------------------------------------------------

    /**
     * DELETE /api/megafamilia/embajadores/prospects/{id}
     * No borra el registro — lo marca como "lost" para preservar el historial.
     */
    public function destroy(int $id): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $prospect = ReferralProspect::find($id);
        if (! $prospect) {
            return response()->json(['error' => 'Prospecto no encontrado.'], 404);
        }

        $this->authorize('manage', $prospect);

        $prospect->update(['status' => 'lost']);

        return response()->json(['success' => true, 'message' => 'Prospecto marcado como perdido.']);
    }
}
