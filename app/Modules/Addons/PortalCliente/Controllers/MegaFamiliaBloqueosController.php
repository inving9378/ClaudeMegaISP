<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalAppBlock;
use App\Modules\Addons\MegaFamilia\Models\ParentalWebBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Escrituras de BLOQUEOS (G3) — guard cliente.
 *
 * AppBlock y WebBlock cuelgan de un perfil. Ownership: requireProfile verifica
 * que el {profile} ∈ cuentas del cliente; el destroy resuelve el bloqueo SOLO a
 * través de la relación del perfil (profile->appBlocks/webBlocks), nunca por id
 * suelto → un id ajeno devuelve 403. client_isp_id lo deriva el observer del perfil.
 *
 * NOTA: la columna real del bloqueo web es `domain` (no `url`). El form recibe una
 * URL/domino libre y se normaliza al host antes de guardar.
 */
class MegaFamiliaBloqueosController extends MegaFamiliaBaseController
{
    // ── App blocks ────────────────────────────────────────────────────────

    public function storeApp(Request $request, int $profile): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);

        $data = $request->validate([
            'app_name'     => 'required|string|max:100',
            'package_name' => 'nullable|string|max:200',
            'category'     => 'nullable|string|max:100',
        ]);

        ParentalAppBlock::create([
            'profile_id'   => $perfil->id,
            'app_name'     => $data['app_name'],
            'package_name' => $data['package_name'] ?? null,
            'category'     => $data['category'] ?? null,
            'blocked'      => true,
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "App «{$data['app_name']}» bloqueada.");
    }

    public function destroyApp(int $profile, int $id): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);

        $block = $perfil->appBlocks()->whereKey($id)->first();
        abort_if(! $block, 403, 'Ese bloqueo no te pertenece.');

        $name = $block->app_name;
        $block->delete();

        return redirect()->route('portal.megafamilia')
            ->with('success', "Bloqueo de «{$name}» eliminado.");
    }

    // ── Web blocks ────────────────────────────────────────────────────────

    public function storeWeb(Request $request, int $profile): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);

        $data = $request->validate([
            'url'      => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
        ]);

        $domain = $this->normalizeDomain($data['url']);
        abort_if($domain === null, 422, 'La dirección web no es válida.');

        ParentalWebBlock::create([
            'profile_id' => $perfil->id,
            'domain'     => $domain,
            'category'   => $data['category'] ?? null,
            'blocked'    => true,
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Sitio «{$domain}» bloqueado.");
    }

    public function destroyWeb(int $profile, int $id): RedirectResponse
    {
        $perfil = $this->requireProfile($profile);

        $block = $perfil->webBlocks()->whereKey($id)->first();
        abort_if(! $block, 403, 'Ese bloqueo no te pertenece.');

        $domain = $block->domain;
        $block->delete();

        return redirect()->route('portal.megafamilia')
            ->with('success', "Bloqueo de «{$domain}» eliminado.");
    }

    /**
     * Normaliza una entrada libre ("instagram.com", "https://www.x.com/a") al host
     * en minúsculas ("instagram.com", "www.x.com"). Devuelve null si no hay host.
     */
    private function normalizeDomain(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        // Sin esquema → anteponer https para que parse_url extraiga el host.
        $withScheme = preg_match('#^https?://#i', $input) ? $input : 'https://' . $input;
        $host = parse_url($withScheme, PHP_URL_HOST);

        return $host ? mb_strtolower($host) : null;
    }
}
