<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escrituras de DISPOSITIVOS (G2) — guard cliente.
 *
 * Registro manual desde el portal (la APK no está en uso; sin pairing token):
 * el dispositivo se crea contra un perfil del cliente. El "tipo" del dispositivo
 * (smartphone/tablet/pc/otro) se persiste en la columna `model` — no existe una
 * columna `tipo` dedicada en parental_devices y `os` es enum android/ios.
 *
 * Ownership: requireProfile/requireDevice verifican que el recurso ∈ cuentas del
 * cliente; account_id se toma del perfil (no del input) → abort(403) si no.
 */
class MegaFamiliaDispositivosController extends MegaFamiliaBaseController
{
    /** Tipos de dispositivo soportados en el portal (se guardan en `model`). */
    private const TIPOS = ['smartphone', 'tablet', 'pc', 'otro'];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile_id' => 'required|integer',
            'nombre'     => 'required|string|max:100',
            'tipo'       => 'nullable|in:' . implode(',', self::TIPOS),
        ]);

        // Ownership: el perfil debe pertenecer a una cuenta del cliente.
        $profile = $this->requireProfile((int) $data['profile_id']);

        ParentalDevice::create([
            'profile_id' => $profile->id,
            'account_id' => $profile->account_id, // del perfil, NUNCA del input
            'name'       => $data['nombre'],
            'model'      => $data['tipo'] ?? null, // "tipo" → model (sin columna dedicada)
            'status'     => 'offline',
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Dispositivo «{$data['nombre']}» registrado.");
    }

    public function edit(int $id): View
    {
        $device = $this->requireDevice($id);

        // Perfiles del cliente para el selector de reasignación (misma cuenta).
        $profiles = $this->requireAccount($device->account_id)->profiles()->orderBy('name')->get();

        return view('addon-portal-cliente::megafamilia_dispositivo_edit', [
            'cmi'      => auth('cliente')->user(),
            'device'   => $device,
            'profiles' => $profiles,
            'tipos'    => self::TIPOS,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $device = $this->requireDevice($id);

        $data = $request->validate([
            'profile_id' => 'required|integer',
            'nombre'     => 'required|string|max:100',
            'tipo'       => 'nullable|in:' . implode(',', self::TIPOS),
        ]);

        // El nuevo perfil también debe pertenecer al cliente; account_id se deriva de él.
        $profile = $this->requireProfile((int) $data['profile_id']);

        $device->update([
            'profile_id' => $profile->id,
            'account_id' => $profile->account_id,
            'name'       => $data['nombre'],
            'model'      => $data['tipo'] ?? null,
        ]);

        return redirect()->route('portal.megafamilia')
            ->with('success', "Dispositivo «{$device->name}» actualizado.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $device = $this->requireDevice($id);
        $nombre = $device->name;
        $device->delete();

        return redirect()->route('portal.megafamilia')
            ->with('success', "Dispositivo «{$nombre}» eliminado.");
    }
}
