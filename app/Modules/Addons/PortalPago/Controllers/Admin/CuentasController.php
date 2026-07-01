<?php

namespace App\Modules\Addons\PortalPago\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalPago\Models\PortalPagoAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD de cuentas de cobro (portal_pago_accounts).
 */
class CuentasController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->can('pagos.cuentas.manage'), 403);

        return view('addon-portal-pago::admin.cuentas');
    }

    public function list()
    {
        abort_unless(auth()->user()?->can('pagos.cuentas.manage'), 403);

        return response()->json(
            PortalPagoAccount::orderByDesc('id')->get()
        );
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->can('pagos.cuentas.manage'), 403);

        $data = $this->validar($request);
        $account = PortalPagoAccount::create($data);

        return response()->json(['ok' => true, 'account' => $account, 'message' => 'Cuenta creada.']);
    }

    public function update(Request $request, PortalPagoAccount $account)
    {
        abort_unless(auth()->user()?->can('pagos.cuentas.manage'), 403);

        $account->update($this->validar($request, $account->id));

        return response()->json(['ok' => true, 'account' => $account, 'message' => 'Cuenta actualizada.']);
    }

    public function toggle(PortalPagoAccount $account)
    {
        abort_unless(auth()->user()?->can('pagos.cuentas.manage'), 403);

        $account->update(['activa' => ! $account->activa]);

        return response()->json([
            'ok'      => true,
            'activa'  => $account->activa,
            'message' => $account->activa ? 'Cuenta activada.' : 'Cuenta desactivada.',
        ]);
    }

    private function validar(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nombre'       => ['required', 'string', 'max:255'],
            'clabe'        => [
                'required',
                'digits:18', // exactamente 18 dígitos
                Rule::unique('portal_pago_accounts', 'clabe')->ignore($ignoreId),
            ],
            'cuenta'       => ['nullable', 'string', 'max:20'],
            'tarjeta'      => ['nullable', 'digits_between:13,19'],
            'banco'        => ['nullable', 'string', 'max:255'],
            'titular'      => ['nullable', 'string', 'max:255'],
            'beneficiario' => ['nullable', 'string', 'max:255'],
            'activa'       => ['sometimes', 'boolean'],
        ], [
            'clabe.digits'           => 'La CLABE debe tener exactamente 18 dígitos.',
            'clabe.unique'           => 'Ya existe una cuenta con esa CLABE.',
            'tarjeta.digits_between' => 'La tarjeta debe tener entre 13 y 19 dígitos.',
        ]);
    }
}
