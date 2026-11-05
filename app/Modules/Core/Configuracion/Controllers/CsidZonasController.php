<?php

namespace App\Modules\Core\Configuracion\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PppoeCsidReaprenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** #1144 — Configuración → Zonas PPPoE (Calling-Station-Id). Delega en los comandos de la Fase 2. */
class CsidZonasController extends Controller
{
    public function index()
    {
        return view('core-configuracion::csid.index');
    }

    public function listar()
    {
        return response()->json([
            'zonas' => DB::table('pppoe_csid_zonas')->orderBy('nas_ip')->get(),
            'pendientes' => DB::table('pppoe_csid_reaprender_pendientes')->whereNull('resuelto_at')->orderByDesc('id')->get(),
        ]);
    }

    public function cambiarModo(Request $r)
    {
        $d = $r->validate(['nas_ip' => 'required|string|max:45', 'modo' => 'required|in:apagado,aprender,aplicar']);
        $code = Artisan::call('pppoe:csid-zona', ['nas_ip' => $d['nas_ip'], 'modo' => $d['modo'], '--por' => 'ui:' . auth()->user()->login_user]);
        return response()->json(['ok' => $code === 0, 'mensaje' => trim(Artisan::output())], $code === 0 ? 200 : 422);
    }

    public function reaprender(Request $r, PppoeCsidReaprenderService $svc)
    {
        $d = $r->validate(['username' => 'required|string|max:100']);
        $n = $svc->reaprender($d['username'], 'ui:' . auth()->user()->login_user);
        DB::table('pppoe_csid_reaprender_pendientes')->where('username', $d['username'])->whereNull('resuelto_at')->update(['resuelto_at' => now()]);
        return response()->json(['ok' => true, 'mensaje' => "MAC olvidadas: {$n}. Restricción RADIUS retirada."]);
    }
}
