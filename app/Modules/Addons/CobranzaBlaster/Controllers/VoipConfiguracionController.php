<?php

namespace App\Modules\Addons\CobranzaBlaster\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CobranzaBlaster\Models\VoipConfiguracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoipConfiguracionController extends Controller
{
    public function index()
    {
        $config = VoipConfiguracion::first();
        return view('addon-cobranza-blaster::voip.index', compact('config'));
    }

    public function show(): JsonResponse
    {
        $config = VoipConfiguracion::first();

        if (!$config) {
            return response()->json(null);
        }

        // Devolver sin el secret desencriptado por seguridad — el frontend solo sabe si está configurado
        return response()->json([
            'id'              => $config->id,
            'nombre'          => $config->nombre,
            'sip_host'        => $config->sip_host,
            'sip_port'        => $config->sip_port,
            'sip_username'    => $config->sip_username,
            'sip_secret'      => $config->sip_secret ? '••••••••' : '',
            'sip_fromuser'    => $config->sip_fromuser,
            'sip_fromdomain'  => $config->sip_fromdomain,
            'callerid_nombre' => $config->callerid_nombre,
            'callerid_numero' => $config->callerid_numero,
            'activa'          => $config->activa,
            'estado'          => $config->estado,
            'ultimo_registro_at' => $config->ultimo_registro_at,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sip_host'        => 'required|string|max:255',
            'sip_port'        => 'nullable|integer|min:1|max:65535',
            'sip_username'    => 'required|string|max:100',
            'sip_secret'      => 'required|string|min:4',
            'sip_fromuser'    => 'nullable|string|max:100',
            'sip_fromdomain'  => 'nullable|string|max:255',
            'callerid_nombre' => 'nullable|string|max:100',
            'callerid_numero' => 'nullable|string|max:30',
            'activa'          => 'nullable|boolean',
        ]);

        try {
            $config = VoipConfiguracion::updateOrCreate(['id' => 1], $data);

            $this->escribirSipConf($config);

            exec('sudo asterisk -rx "sip reload" 2>&1', $output, $rc);

            return response()->json([
                'ok'       => true,
                'asterisk' => implode("\n", $output),
                'rc'       => $rc,
            ]);
        } catch (\Throwable $e) {
            // Fallo de infraestructura (Asterisk/archivo/BD): nunca 500 hacia la UI.
            Log::error('CobranzaBlaster: fallo al guardar configuración VoIP', ['error' => $e->getMessage()]);

            return response()->json([
                'ok'  => false,
                'msg' => 'No se pudo aplicar la configuración de la troncal: ' . $e->getMessage(),
            ], 200);
        }
    }

    public function testConexion(): JsonResponse
    {
        try {
            exec('sudo asterisk -rx "sip show peers" 2>&1', $output, $rc);

            $lineas     = implode("\n", $output);
            $registrado = collect($output)->contains(
                fn ($l) => stripos($l, 'servnet') !== false && stripos($l, 'OK') !== false
            );

            return response()->json([
                'ok'         => true,
                'registrado' => $registrado,
                'output'     => $lineas,
                'rc'         => $rc,
            ]);
        } catch (\Throwable $e) {
            Log::error('CobranzaBlaster: fallo al consultar estado de la troncal', ['error' => $e->getMessage()]);

            return response()->json([
                'ok'         => false,
                'registrado' => false,
                'msg'        => 'No se pudo consultar el estado de la troncal: ' . $e->getMessage(),
            ], 200);
        }
    }

    private function escribirSipConf(VoipConfiguracion $config): void
    {
        $fromuser   = $config->sip_fromuser   ? "fromuser = {$config->sip_fromuser}" : '';
        $fromdomain = $config->sip_fromdomain ? "fromdomain = {$config->sip_fromdomain}" : '';
        $callerid   = $config->callerid_numero ? "<{$config->callerid_numero}>" : '';

        $conf = <<<CONF
[general]
context = default
allowoverlap = no
udpbindaddr = 0.0.0.0
tcpenable = no
transport = udp
srvlookup = yes
qualify = yes

[servnet-trunk]
type = peer
host = {$config->sip_host}
port = {$config->sip_port}
username = {$config->sip_username}
secret = {$config->sip_secret}
{$fromuser}
{$fromdomain}
insecure = port,invite
nat = force_rport,comedia
dtmfmode = rfc2833
disallow = all
allow = ulaw
allow = alaw
context = from-servnet
qualify = yes
CONF;

        file_put_contents('/etc/asterisk/sip.conf', $conf);
    }
}
