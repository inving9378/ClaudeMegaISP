<?php

namespace App\Observers;

use App\Events\Referrals\ProspectConverted;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralProspect;
use App\Modules\Addons\Payments\Services\PaymentReferenceService;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientObserver
{
    /**
     * Al crear un cliente nuevo con referred_by_code, construye el Referral,
     * la cadena materializada (chain_path), e intenta matchear un prospecto
     * existente del mismo embajador por teléfono normalizado.
     */
    public function created(Client $client): void
    {
        // Motor de cobro nativo: clave de conciliación inmutable por cliente.
        // Va antes del early-return de referidos para que TODO cliente nuevo la tenga.
        // En try/catch: un fallo aquí jamás debe abortar el alta del cliente.
        try {
            PaymentReferenceService::ensureFor($client->id);
        } catch (\Throwable $e) {
            Log::error('ClientObserver: no se pudo generar la referencia de pago', [
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);
        }

        if (empty($client->referred_by_code)) {
            return;
        }

        $embajadorProfile = ClientReferralProfile::where('referral_code', $client->referred_by_code)
            ->where('is_eligible', true)
            ->first();

        if (!$embajadorProfile) {
            Log::warning('ClientObserver: código de referido no encontrado o embajador no elegible', [
                'client_id'        => $client->id,
                'referred_by_code' => $client->referred_by_code,
            ]);
            return;
        }

        // Evitar auto-referido
        if ($embajadorProfile->client_id === $client->id) {
            return;
        }

        // Construir chain_path heredando la cadena del embajador
        $parentReferral = Referral::where('referred_client_id', $embajadorProfile->client_id)->first();
        $parentPath     = $parentReferral?->chain_path ?? '/';
        $chainPath      = $parentPath . $embajadorProfile->client_id . '/';
        $chainDepth     = substr_count($chainPath, '/') - 1;

        $referral = Referral::create([
            'embajador_id'       => $embajadorProfile->client_id,
            'referred_client_id' => $client->id,
            'chain_path'         => $chainPath,
            'chain_depth'        => $chainDepth,
            'status'             => 'pending_threshold',
        ]);

        // total_referrals lo recomputa ReferralObserver (recompute-on-write).

        // Mantener closure table: self + todos los ancestros del chain_path
        $this->insertClosures($client->id, $chainPath);

        Log::info('ClientObserver: Referral creado', [
            'embajador_id'       => $embajadorProfile->client_id,
            'referred_client_id' => $client->id,
            'chain_path'         => $chainPath,
            'chain_depth'        => $chainDepth,
        ]);

        // ── Matching automático prospecto → cliente ──────────────────────────
        $matchedProspect = null;

        try {
            $client->load('client_main_information');
            $phone          = $client->client_main_information?->phone ?? '';
            $phoneNormalized = preg_replace('/\D/', '', $phone);

            if (strlen($phoneNormalized) >= 10) {
                $last10 = substr($phoneNormalized, -10);

                $matchedProspect = ReferralProspect::where('embajador_id', $embajadorProfile->client_id)
                    ->whereRaw(
                        "REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', '') LIKE ?",
                        ['%' . $last10]
                    )
                    ->whereIn('status', ['new', 'contacted', 'interested', 'quoted'])
                    ->first();

                if ($matchedProspect) {
                    $matchedProspect->update([
                        'status'              => 'converted',
                        'converted_client_id' => $client->id,
                        'converted_at'        => now(),
                    ]);

                    $referral->update(['prospect_id' => $matchedProspect->id]);

                    Log::info('ClientObserver: prospecto matcheado', [
                        'prospect_id' => $matchedProspect->id,
                        'client_id'   => $client->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('ClientObserver: error en matching de prospecto', [
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);
        }

        // ── Evento (safety net: no relanzar) ─────────────────────────────────
        try {
            event(new ProspectConverted($referral, $matchedProspect));
        } catch (\Throwable $e) {
            Log::error('ClientObserver: error al disparar ProspectConverted', [
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function insertClosures(int $clientId, string $chainPath): void
    {
        $ancestors = array_values(array_filter(explode('/', $chainPath)));

        $rows = [[
            'ancestor_id'   => $clientId,
            'descendant_id' => $clientId,
            'depth'         => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]];

        // chain_path="/root/mid/padre/" → reversed=[padre,mid,root] → depths [1,2,3]
        $reversed = array_reverse($ancestors);
        foreach ($reversed as $depth => $ancestorId) {
            $rows[] = [
                'ancestor_id'   => (int) $ancestorId,
                'descendant_id' => $clientId,
                'depth'         => $depth + 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        DB::table('referral_closures')->insertOrIgnore($rows);
    }
}
