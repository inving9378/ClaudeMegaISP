<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
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
        $userId = $this->resolveUserId($cmi);
        $megafamiliaActiva = false;
        if ($userId) {
            $megafamiliaActiva = DB::table('parental_accounts')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->exists();
        }

        return view('addon-portal-cliente::marketplace', compact('cmi', 'megafamiliaActiva', 'userId'));
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
            return back()->withErrors(['error' => 'No se pudo asociar tu cuenta al módulo MegaFamilia. Contacta a soporte.']);
        }

        $existente = DB::table('parental_accounts')
            ->where('user_id', $userId)
            ->first();

        if ($existente) {
            if ($existente->status !== 'active') {
                DB::table('parental_accounts')
                    ->where('id', $existente->id)
                    ->update(['status' => 'active', 'updated_at' => now()]);
            }
            return redirect()->route('portal.marketplace')
                ->with('success', 'MegaFamilia reactivado correctamente.');
        }

        // Crear cuenta básica (plan_id=1 default o el primero disponible)
        $planId = DB::table('parental_plans')->orderBy('id')->value('id') ?? 1;

        DB::table('parental_accounts')->insert([
            'user_id'           => $userId,
            'client_isp_id'     => $clientId,
            'plan_id'           => $planId,
            'status'            => 'active',
            'licensed_at'       => now(),
            'terms_accepted_at' => now(),
            'terms_ip'          => $request->ip(),
            'terms_version_accepted' => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return redirect()->route('portal.marketplace')
            ->with('success', '¡MegaFamilia activado! Descarga la app para configurar tu control parental.');
    }

    /**
     * Desactivar MegaFamilia (suspende la cuenta, no borra datos).
     */
    public function desactivarMegafamilia()
    {
        $cmi    = Auth::guard('cliente')->user();
        $userId = $this->resolveUserId($cmi);

        if ($userId) {
            DB::table('parental_accounts')
                ->where('user_id', $userId)
                ->update(['status' => 'suspended', 'updated_at' => now()]);
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
