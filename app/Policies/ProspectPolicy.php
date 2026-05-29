<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\Referrals\ReferralProspect;
use App\Models\User;

class ProspectPolicy
{
    /**
     * Gate único para todas las operaciones sobre un prospecto específico.
     * Un embajador solo puede ver/editar/agregar seguimientos a SUS prospectos.
     */
    public function manage(User $user, ReferralProspect $prospect): bool
    {
        return $this->resolveClientId($user) === $prospect->embajador_id;
    }

    private function resolveClientId(User $user): ?int
    {
        $client = Client::where('user_id', $user->id)->first();
        if ($client) return $client->id;

        $loginUser = $user->login_user ?? null;
        if (! $loginUser) return null;

        $cmi = ClientMainInformation::where('user', $loginUser)->first();
        return $cmi?->client_id;
    }
}
