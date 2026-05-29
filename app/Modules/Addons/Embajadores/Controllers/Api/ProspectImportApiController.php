<?php

namespace App\Modules\Addons\Embajadores\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Embajadores\ImportProspectsRequest;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\ReferralProspect;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProspectImportApiController extends Controller
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

    // ---- import ------------------------------------------------------------

    /**
     * POST /api/megafamilia/embajadores/prospects/import
     * Body: { contacts: [{ name, phone, email? }] }
     *
     * Importación masiva desde la agenda del dispositivo.
     * Omite duplicados por número de teléfono normalizado (solo dígitos)
     * dentro del mismo embajador — un contacto puede estar en otro embajador
     * sin conflicto.
     */
    public function import(ImportProspectsRequest $request): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) {
            return response()->json(['error' => 'Cliente no encontrado.'], 404);
        }

        if (! ClientReferralProfile::where('client_id', $client->id)->exists()) {
            return response()->json(['error' => 'Aún no eres embajador.'], 403);
        }

        $contacts = $request->validated()['contacts'];

        // Teléfonos ya registrados para este embajador (normalizados a solo dígitos)
        $existing = ReferralProspect::where('embajador_id', $client->id)
            ->pluck('phone')
            ->map(fn ($p) => preg_replace('/\D/', '', $p))
            ->flip()
            ->toArray();

        $created = 0;
        $skipped = 0;
        $now     = now();
        $rows    = [];

        foreach ($contacts as $contact) {
            $normalized = preg_replace('/\D/', '', $contact['phone']);

            if (isset($existing[$normalized])) {
                $skipped++;
                continue;
            }

            $rows[] = [
                'embajador_id' => $client->id,
                'name'         => $contact['name'],
                'phone'        => $contact['phone'],
                'email'        => $contact['email'] ?? null,
                'source'       => 'contact_import',
                'status'       => 'new',
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            $existing[$normalized] = true;
            $created++;
        }

        if (! empty($rows)) {
            // Insert en chunks para no saturar la query con 200 placeholders
            foreach (array_chunk($rows, 50) as $chunk) {
                ReferralProspect::insert($chunk);
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'message' => "$created prospecto(s) importado(s), $skipped omitido(s) por duplicado.",
        ]);
    }
}
