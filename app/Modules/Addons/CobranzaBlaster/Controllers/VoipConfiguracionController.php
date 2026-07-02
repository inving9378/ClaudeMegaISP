<?php

namespace App\Modules\Addons\CobranzaBlaster\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CobranzaBlaster\Models\VoipConfiguracion;
use App\Modules\Core\Voice\VoiceGateway;
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
            // sometimes: si el front omite el secret (no lo reescribieron), se conserva
            // el existente; si viene, se valida. Evita 422 al re-guardar sin tocar el secret.
            'sip_secret'      => 'sometimes|required|string|min:4',
            'sip_fromuser'    => 'nullable|string|max:100',
            'sip_fromdomain'  => 'nullable|string|max:255',
            'callerid_nombre' => 'nullable|string|max:100',
            'callerid_numero' => 'nullable|string|max:30',
            'activa'          => 'nullable|boolean',
        ]);

        try {
            $config = VoipConfiguracion::updateOrCreate(['id' => 1], $data);

            // C4: provisión en PJSIP Realtime vía el VoiceGateway (ya NO se escribe
            // /etc/asterisk/sip.conf ni se hace `sip reload` chan_sip).
            $gateway = app(VoiceGateway::class);
            $result  = $gateway->configureTrunk([
                'host'            => $config->sip_host,
                'port'            => $config->sip_port ?: 5060,
                'username'        => $config->sip_username,
                'secret'          => $config->sip_secret,      // accessor → texto plano (AJUSTE-B)
                'fromuser'        => $config->sip_fromuser,
                'fromdomain'      => $config->sip_fromdomain,
                'callerid_nombre' => $config->callerid_nombre,
                'callerid_numero' => $config->callerid_numero,
            ]);
            $reloaded = $gateway->reloadPjsip();

            $config->update(['estado' => 'activa']);

            return response()->json([
                'ok'       => true,
                'reloaded' => $reloaded,
                'warnings' => $result['warnings'],
                'ips'      => $result['ips'],
                'msg'      => 'Troncal Servnet provisionada en PJSIP Realtime'
                              . ($reloaded ? ' y PJSIP recargado.' : ' (recarga de PJSIP no confirmada; revisa AMI).'),
            ]);
        } catch (\Throwable $e) {
            // Fallo de infraestructura (Asterisk/AMI/BD realtime): nunca 500 hacia la UI.
            Log::error('CobranzaBlaster: fallo al provisionar troncal VoIP', ['error' => $e->getMessage()]);
            optional(VoipConfiguracion::find(1))->update(['estado' => 'error']);

            return response()->json([
                'ok'  => false,
                'msg' => 'No se pudo provisionar la troncal en Asterisk: ' . $e->getMessage(),
            ], 200);
        }
    }

    public function testConexion(): JsonResponse
    {
        try {
            // C4: estado real por PJSIP Realtime/ARI/AMI (ya NO `sip show peers` chan_sip).
            $status = app(VoiceGateway::class)->testConnection();

            // Compatibilidad con el frontend actual (espera `registrado` + `output`).
            return response()->json(array_merge($status, [
                'registrado' => $status['registrado'] ?? false,
                'output'     => $this->formatEstadoOutput($status),
            ]));
        } catch (\Throwable $e) {
            Log::error('CobranzaBlaster: fallo al consultar estado de la troncal', ['error' => $e->getMessage()]);

            return response()->json([
                'ok'         => false,
                'registrado' => false,
                'output'     => 'No se pudo consultar el estado de la troncal: ' . $e->getMessage(),
                'msg'        => $e->getMessage(),
            ], 200);
        }
    }

    /** Resumen legible del estado de la troncal para el panel "Output de Asterisk". */
    private function formatEstadoOutput(array $status): string
    {
        $lineas = [
            'Provisionado:   ' . (($status['provisionado'] ?? false) ? 'sí' : 'no'),
            'Estado endpoint: ' . ($status['estado'] ?? 'desconocido')
                . (isset($status['source']) ? " ({$status['source']})" : ''),
            'IPs inbound:    ' . ($status['ips'] ?: '—'),
        ];
        foreach (($status['warnings'] ?? []) as $w) {
            $lineas[] = '⚠ ' . $w;
        }
        return implode("\n", $lineas);
    }

    /**
     * C4: NO-OP. La troncal Servnet ya se provisiona en PJSIP Realtime vía el
     * VoiceGateway (ver store()), no en chan_sip. Se deja el método (sin llamarlo)
     * para no alterar el contrato de la clase; NO se borra /etc/asterisk/sip.conf
     * de producción (allí Asterisk se instala en la próxima actualización — items
     * 185/186). El cuerpo chan_sip original queda comentado como referencia histórica.
     */
    private function escribirSipConf(VoipConfiguracion $config): void
    {
        // no-op — reemplazado por VoiceGateway::configureTrunk() (PJSIP Realtime).
        //
        // $fromuser   = $config->sip_fromuser   ? "fromuser = {$config->sip_fromuser}" : '';
        // $fromdomain = $config->sip_fromdomain ? "fromdomain = {$config->sip_fromdomain}" : '';
        // $callerid   = $config->callerid_numero ? "<{$config->callerid_numero}>" : '';
        //
        // $conf = <<<CONF
        // [general]
        // context = default
        // allowoverlap = no
        // udpbindaddr = 0.0.0.0
        // tcpenable = no
        // transport = udp
        // srvlookup = yes
        // qualify = yes
        //
        // [servnet-trunk]
        // type = peer
        // host = {$config->sip_host}
        // port = {$config->sip_port}
        // username = {$config->sip_username}
        // secret = {$config->sip_secret}
        // {$fromuser}
        // {$fromdomain}
        // insecure = port,invite
        // nat = force_rport,comedia
        // dtmfmode = rfc2833
        // disallow = all
        // allow = ulaw
        // allow = alaw
        // context = from-servnet
        // qualify = yes
        // CONF;
        //
        // file_put_contents('/etc/asterisk/sip.conf', $conf);
    }
}
