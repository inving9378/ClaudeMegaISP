<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MegaFamilia\ParentalAccountActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    /**
     * Catálogo de servicios activables.
     * MegaFamilia: se puede activar (crea/enlaza parental_account).
     * Flotas: gateado "en preparación" (módulo interno Meganet, no tiene escopo por cliente final).
     */
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        // Estado MegaFamilia del cliente
        // $userId es null para los ~878 CMI sin fila en users (caso documentado).
        // La vista muestra aviso en lugar del botón Activar cuando es null.
        $userId = $this->resolveUserId($cmi);
        $megafamiliaActiva = false;
        $megafamiliaSinVinculacion = ($userId === null);
        if ($userId) {
            $megafamiliaActiva = DB::table('parental_accounts')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->exists();
        }

        return view('addon-portal-cliente::marketplace', compact(
            'cmi', 'megafamiliaActiva', 'userId', 'megafamiliaSinVinculacion'
        ));
    }

    /**
     * Activar MegaFamilia para el cliente.
     * Crea parental_account solo si no existe.
     * No cobra nada — tier base gratuito (OpenPay queda para Irving).
     */
    public function activarMegafamilia(Request $request)
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $userId = $this->resolveUserId($cmi);
        if (! $userId) {
            // CMI sin fila en users (878 casos documentados en audit).
            // Registrar para seguimiento de soporte antes de responder.
            DB::table('client_logs')->insert([
                'client_id'   => $cmi->client_id,
                'description' => '[Portal] MegaFamilia: no se encontró users.id para login_user=' . ($cmi->user ?? 'null'),
                'add_by'      => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            return back()->withErrors([
                'error' => 'Tu cuenta aún no está vinculada al módulo MegaFamilia. '
                    . 'Por favor contacta a soporte indicando tu número de cliente #' . $cmi->client_id . '.'
            ]);
        }

        // Toda la creación/reactivación vive ahora en el Service (única fuente).
        // El controller resuelve SU cliente (guard portal) y delega la escritura;
        // el Service garantiza client_isp_id poblado (fail-closed) e idempotencia.
        $account = app(ParentalAccountActivationService::class)
            ->activate($userId, (int) $clientId, ['terms_ip' => $request->ip()]);

        $mensaje = $account->wasRecentlyCreated
            ? '¡MegaFamilia activado! Descarga la app para configurar tu control parental.'
            : 'MegaFamilia reactivado correctamente.';

        return redirect()->route('portal.marketplace')->with('success', $mensaje);
    }

    /**
     * Desactivar MegaFamilia (suspende la cuenta, no borra datos).
     */
    public function desactivarMegafamilia()
    {
        $cmi    = Auth::guard('cliente')->user();
        $userId = $this->resolveUserId($cmi);

        if ($userId) {
            app(ParentalAccountActivationService::class)->suspend($userId);
        }

        return redirect()->route('portal.marketplace')
            ->with('success', 'MegaFamilia suspendido. Tus datos permanecen guardados.');
    }

    /**
     * Registrar interés en un servicio (sin activar ni cobrar).
     */
    public function registrarInteres(Request $request)
    {
        $data = $request->validate([
            'servicio' => 'required|string|max:100',
        ]);

        $cmi = Auth::guard('cliente')->user();

        // Solo registra el interés en logs — no toca facturación ni provisioning
        DB::table('client_logs')->insert([
            'client_id'   => $cmi->client_id,
            'description' => '[Portal] Interés registrado en: ' . $data['servicio'],
            'add_by'      => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Tu interés ha sido registrado. Te contactaremos pronto.');
    }

    /**
     * Resuelve el users.id asociado al cliente (via CMI.user = users.login_user).
     */
    private function resolveUserId($cmi): ?int
    {
        if (! $cmi->user) return null;
        return DB::table('users')->where('login_user', $cmi->user)->value('id');
    }
}
