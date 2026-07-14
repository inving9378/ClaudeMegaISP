<?php

namespace App\Modules\Core\Voice;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gateway de voz único hacia Asterisk (PJSIP Realtime + AMI/ARI).
 *
 * Provisiona la troncal Servnet en las tablas ps_* (conexión `asterisk_rt`),
 * recarga PJSIP por AMI y reporta el estado real por ARI/AMI. Sustituye la
 * escritura de /etc/asterisk/sip.conf (chan_sip) de CobranzaBlaster.
 *
 * Servnet es un peer IP estático con outbound_auth (digest saliente) SIN
 * REGISTER (el chan_sip original no tenía `register =>`) → se identifica
 * inbound por IP (ps_endpoint_id_ips) y marca saliente por el contacto del
 * AOR. Por eso NO se crea ps_registrations.
 */
class VoiceGateway
{
    public const TRUNK_ENDPOINT_ID = 'servnet';
    public const TRUNK_AUTH_ID     = 'servnet-auth';
    public const TRUNK_AOR_ID      = 'servnet-aor';
    public const TRUNK_CONTEXT     = 'from-servnet';

    /** IDs que el gateway NUNCA debe crear/pisar (AJUSTE-C: no tocar trunk_2 ni extensiones). */
    private const RESERVED_IDS = ['trunk_2', '1001', '1002', '1003', '1004'];

    private AmiClient $ami;

    public function __construct(?AmiClient $ami = null)
    {
        $this->ami = $ami ?? app(AmiClient::class);
    }

    private function db()
    {
        return DB::connection('asterisk_rt');
    }

    /**
     * Provisiona (idempotente) la troncal Servnet en PJSIP Realtime.
     *
     * @param array $cfg  host, port, username, secret (TEXTO PLANO), fromuser,
     *                    fromdomain, callerid_nombre, callerid_numero
     * @return array{ok:bool, warnings:array<int,string>, ips:string}
     */
    public function configureTrunk(array $cfg): array
    {
        $warnings = [];

        // AJUSTE-C: el gateway solo escribe el namespace servnet; jamás un id reservado.
        $this->assertNotReserved(self::TRUNK_ENDPOINT_ID);
        $this->assertNotReserved(self::TRUNK_AUTH_ID);
        $this->assertNotReserved(self::TRUNK_AOR_ID);

        $host = trim((string) ($cfg['host'] ?? ''));
        if ($host === '') {
            throw new \InvalidArgumentException('El host de la troncal Servnet está vacío.');
        }
        $port = ((int) ($cfg['port'] ?? 0)) ?: 5060;

        // AJUSTE-A: TODAS las IPs del proveedor, no solo la primera.
        $ips   = $this->resolveProviderIps($host);
        $match = implode(',', $ips);

        // AJUSTE-C: si ya hay filas servnet es RECONFIGURACIÓN (update idempotente). Log informativo.
        foreach ([['ps_auths', self::TRUNK_AUTH_ID], ['ps_aors', self::TRUNK_AOR_ID], ['ps_endpoints', self::TRUNK_ENDPOINT_ID]] as [$table, $id]) {
            if ($this->db()->table($table)->where('id', $id)->exists()) {
                Log::info("VoiceGateway: reconfigurando fila existente {$table}.{$id} (servnet).");
            }
        }

        // AJUSTE-B: password en TEXTO PLANO (credencial de Asterisk). NO base64/bcrypt.
        $this->upsert('ps_auths', [
            'id'        => self::TRUNK_AUTH_ID,
            'auth_type' => 'userpass',
            'username'  => (string) ($cfg['username'] ?? ''),
            'password'  => (string) ($cfg['secret'] ?? ''),
        ]);

        // qualify_frequency: OPTIONS-ping para que el estado del endpoint sea real
        // (equivale al `qualify=yes` del chan_sip original).
        $this->upsert('ps_aors', [
            'id'                => self::TRUNK_AOR_ID,
            'contact'           => "sip:{$host}:{$port}",
            'max_contacts'      => 1,
            'qualify_frequency' => 60,
        ]);

        $this->upsert('ps_endpoints', [
            'id'            => self::TRUNK_ENDPOINT_ID,
            'transport'     => 'transport-udp',
            'aors'          => self::TRUNK_AOR_ID,
            'outbound_auth' => self::TRUNK_AUTH_ID,
            'context'       => self::TRUNK_CONTEXT,
            'disallow'      => 'all',
            'allow'         => 'ulaw,alaw',
            'direct_media'  => 'no',
            'from_user'     => $cfg['fromuser'] ?? null,
            'from_domain'   => $cfg['fromdomain'] ?? null,
            'callerid'      => $this->buildCallerId($cfg['callerid_nombre'] ?? null, $cfg['callerid_numero'] ?? null),
            'identify_by'   => 'ip,username',
        ]);

        // Servnet NO hace REGISTER → NO ps_registrations. Limpiar restos de un config previo.
        $this->db()->table('ps_registrations')->where('id', self::TRUNK_ENDPOINT_ID)->delete();

        // AJUSTE-A: identificación inbound por IP. Vacío → warning explícito, no falla mudo.
        if ($match === '') {
            $warnings[] = "No se resolvió ninguna IP para el host '{$host}'. La identificación inbound por IP queda sin configurar (revisa DNS/host). Las llamadas salientes sí funcionan por el contacto del AOR.";
            Log::warning("VoiceGateway: resolveProviderIps('{$host}') devolvió vacío; se omite ps_endpoint_id_ips.");
            $this->db()->table('ps_endpoint_id_ips')->where('id', self::TRUNK_ENDPOINT_ID)->delete();
        } else {
            $this->upsert('ps_endpoint_id_ips', [
                'id'       => self::TRUNK_ENDPOINT_ID,
                'endpoint' => self::TRUNK_ENDPOINT_ID,
                'match'    => $match,
            ]);
        }

        return ['ok' => true, 'warnings' => $warnings, 'ips' => $match];
    }

    /**
     * Origina una llamada por AMI (Action: Originate). Capa de abstracción compartida
     * para que cualquier consumidor (CobranzaBlaster, futuros módulos VoIP) construya
     * el Originate en UN solo lugar en vez de armar el texto AMI a mano.
     *
     * Item #185 (unificación incremental, opción elegida por Irving): esta pieza queda
     * DISPONIBLE para consumidores nuevos; NO se migra aquí el hot-path de llamadas en
     * vivo de CobranzaBlaster (AmiConnectionService::originate, conexión persistente
     * reusada por lote en BlastCampanaJob) — esa migración es una fase separada que
     * requiere validar contra la troncal real antes de tocar el flujo de cobranza.
     *
     * @param array<string,scalar> $params  Channel, Context, Exten, Priority, CallerID,
     *                                       Variable(s), ActionID, Timeout, etc. — mismas
     *                                       claves que la acción AMI Originate.
     * @return array{success:bool, raw:string, parsed:array<string,string>}
     */
    public function originate(array $params): array
    {
        $params += ['Async' => 'true'];

        $raw    = $this->ami->send('Originate', $params);
        $parsed = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (str_contains($line, ': ')) {
                [$key, $value] = explode(': ', $line, 2);
                $parsed[trim($key)] = trim($value);
            }
        }

        return [
            'success' => ($parsed['Response'] ?? null) === 'Success',
            'raw'     => $raw,
            'parsed'  => $parsed,
        ];
    }

    /** Recarga res_pjsip.so por AMI para que Asterisk relea el realtime. */
    public function reloadPjsip(): bool
    {
        try {
            $raw = $this->ami->action("Action: Reload\r\nModule: res_pjsip.so\r\n\r\n", false);
            $ok  = str_contains($raw, 'Response: Success') || str_contains($raw, 'Reloaded');
            if (!$ok) {
                Log::warning("VoiceGateway: respuesta inesperada al recargar pjsip: {$raw}");
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::warning("VoiceGateway: falló pjsip reload: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Estado real de la troncal. Servnet NO registra (peer IP estático), así que
     * "registrado" se mapea a la alcanzabilidad del endpoint por ARI (qualify);
     * AMI PJSIPShowRegistrationsOutbound queda como respaldo informativo.
     *
     * @return array{registered:bool, state:string, source:?string}
     */
    public function getRegistrationStatus(): array
    {
        $state      = 'desconocido';
        $registered = false;
        $source     = null;

        // 1) ARI: estado del endpoint (online/offline/unknown).
        try {
            $ari = $this->ariGet('/ari/endpoints/PJSIP/' . self::TRUNK_ENDPOINT_ID);
            if ($ari !== null) {
                $state      = $ari['state'] ?? 'unknown';
                $registered = ($state === 'online');
                $source     = 'ari';
            }
        } catch (\Throwable $e) {
            Log::warning('VoiceGateway: ARI endpoint state falló: ' . $e->getMessage());
        }

        // 2) Respaldo AMI (para troncales tipo registro; servnet normalmente no aparece).
        if ($source === null) {
            try {
                $raw    = $this->ami->action("Action: PJSIPShowRegistrationsOutbound\r\n\r\n", true);
                $events = AmiClient::parseEvents($raw);
                foreach ($events as $ev) {
                    if (($ev['Event'] ?? '') === 'OutboundRegistrationDetail'
                        && str_contains($ev['ObjectName'] ?? '', self::TRUNK_ENDPOINT_ID)) {
                        $state      = $ev['Status'] ?? 'unknown';
                        $registered = strtolower($state) === 'registered';
                    }
                }
                $source = 'ami';
            } catch (\Throwable $e) {
                Log::warning('VoiceGateway: AMI PJSIPShowRegistrationsOutbound falló: ' . $e->getMessage());
            }
        }

        return ['registered' => $registered, 'state' => $state, 'source' => $source];
    }

    /**
     * Prueba de conexión lista para JSON: provisión + estado real + warnings.
     *
     * @return array<string,mixed>
     */
    public function testConnection(): array
    {
        $provisioned = $this->db()->table('ps_endpoints')->where('id', self::TRUNK_ENDPOINT_ID)->exists();

        if (!$provisioned) {
            return [
                'ok'           => false,
                'provisionado' => false,
                'registrado'   => false,
                'estado'       => 'sin_provisionar',
                'ips'          => null,
                'warnings'     => ['La troncal Servnet aún no está provisionada en PJSIP Realtime.'],
            ];
        }

        $warnings = [];
        $idIps    = $this->db()->table('ps_endpoint_id_ips')->where('id', self::TRUNK_ENDPOINT_ID)->value('match');
        if (empty($idIps)) {
            $warnings[] = 'La troncal no tiene IPs de identificación inbound (ps_endpoint_id_ips vacío); revisa el host/DNS.';
        }

        $status = $this->getRegistrationStatus();

        return [
            'ok'           => true,
            'provisionado' => true,
            'registrado'   => $status['registered'],
            'estado'       => $status['state'],
            'source'       => $status['source'],
            'ips'          => $idIps,
            'warnings'     => $warnings,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Caller ID en formato PJSIP: "Nombre" <numero>. */
    private function buildCallerId(?string $nombre, ?string $numero): ?string
    {
        $nombre = trim((string) $nombre);
        $numero = trim((string) $numero);
        if ($numero === '' && $nombre === '') {
            return null;
        }
        if ($numero === '') {
            return $nombre;
        }
        return $nombre !== '' ? "\"{$nombre}\" <{$numero}>" : "<{$numero}>";
    }

    /** AJUSTE-A: resuelve TODAS las IPs A del host (o el host mismo si ya es IP). */
    private function resolveProviderIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips     = [];
        $records = @dns_get_record($host, DNS_A);
        if (!empty($records)) {
            $ips = array_column($records, 'ip');
        }
        if (empty($ips)) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }

        return array_values(array_unique(array_filter($ips)));
    }

    private function ariGet(string $path): ?array
    {
        $host = config('voip.ari_host', '127.0.0.1');
        $port = (int) config('voip.ari_port', 8088);
        $user = config('voip.ari_user', 'medussa');
        $pass = config('voip.ari_pass', '');

        $resp = Http::withBasicAuth($user, $pass)->timeout(5)->get("http://{$host}:{$port}{$path}");

        if ($resp->status() === 404) {
            return null;
        }
        if (!$resp->successful()) {
            throw new \RuntimeException("ARI {$path} respondió HTTP {$resp->status()}");
        }

        return $resp->json();
    }

    private function upsert(string $table, array $data): void
    {
        $this->db()->table($table)->updateOrInsert(['id' => $data['id']], $data);
    }

    private function assertNotReserved(string $id): void
    {
        if (in_array($id, self::RESERVED_IDS, true)) {
            throw new \RuntimeException("VoiceGateway: el id '{$id}' está reservado y no puede ser gestionado por el gateway de Servnet.");
        }
    }
}
