<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escrituras de PERFILES de hijos (G1) — guard cliente.
 *
 * Ownership: el perfil se crea contra una cuenta del cliente (requireAccount) y
 * se edita/borra solo si pertenece a una de sus cuentas (requireProfile → 403).
 * client_isp_id lo setea el trait DerivesClientIspId al crear (deriva de account).
 */
class MegaFamiliaPerfilesController extends MegaFamiliaBaseController
{
    public function store(Request $request): RedirectResponse
    {
        $account = $this->requireAccount($request->integer('account_id') ?: null);

        $data = $request->validate([
            'account_id'   => 'nullable|integer',
            'nombre'       => 'required|string|max:100',
            'edad'         => 'nullable|integer|min:1|max:17',
            'school_level' => 'nullable|in:primaria,secundaria,preparatoria',
            'profile_type' => 'nullable|in:nino,preadolescente,adolescente',
        ], [], [
            'nombre' => 'nombre',
            'edad'   => 'edad',
        ]);

        ParentalProfile::create([
            'account_id'   => $account->id,
            'name'         => $data['nombre'],
            'age'          => $data['edad'] ?? null,
            'school_level' => $data['school_level'] ?? null,
            'profile_type' => $data['profile_type'] ?? null,
            'active'       => true,
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Perfil «{$data['nombre']}» creado correctamente.");
    }

    public function edit(int $id): View
    {
        $profile = $this->requireProfile($id);

        return view('addon-portal-cliente::megafamilia_perfil_edit', [
            'cmi'     => auth('cliente')->user(),
            'profile' => $profile,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $profile = $this->requireProfile($id);

        $data = $request->validate([
            'nombre'       => 'required|string|max:100',
            'edad'         => 'nullable|integer|min:1|max:17',
            'school_level' => 'nullable|in:primaria,secundaria,preparatoria',
            'profile_type' => 'nullable|in:nino,preadolescente,adolescente',
        ]);

        $profile->update([
            'name'         => $data['nombre'],
            'age'          => $data['edad'] ?? null,
            'school_level' => $data['school_level'] ?? null,
            'profile_type' => $data['profile_type'] ?? null,
            'active'       => $request->boolean('active'),
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Perfil «{$profile->name}» actualizado.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $profile = $this->requireProfile($id);

        // Informativo, NO bloquea: el borrado en cascada (FK cascadeOnDelete)
        // arrastra dispositivos, reglas, bloqueos, horarios, tareas, etc.
        $devices = $profile->devices()->count();
        $nombre  = $profile->name;

        $profile->delete();

        $msg = "Perfil «{$nombre}» eliminado.";
        if ($devices > 0) {
            $msg .= " También se eliminaron {$devices} dispositivo(s) vinculado(s).";
        }

        return redirect()->route('portal.megafamilia')->with('success', $msg);
    }
}
