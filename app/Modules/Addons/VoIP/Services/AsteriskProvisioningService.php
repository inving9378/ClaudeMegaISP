<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\Troncal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestiona la provisión de troncales y extensiones PJSIP.
 *
 * Endpoints / auths / aors → ps_* en DB realtime (sin reload).
 * Registraciones salientes → archivo estático incluido en pjsip.conf + pjsip reload vía AMI.
 */
class AsteriskProvisioningService
{
    private string $regFile;

    public function __construct()
    {
        $this->regFile = storage_path('app/asterisk/megaisp_registrations.conf');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TRONCALES
    // ─────────────────────────────────────────────────────────────────────────

    public function provisionar(Troncal $troncal): void
    {
        if ($troncal->tipo === 'registro') {
            $this->warnIfTransportMissing();
        }

        $id     = $troncal->endpointId();
        $authId = "{$id}_auth";

        // ── ps_aors ──────────────────────────────────────────────────────────
        $aorData = ['id' => $id, 'max_contacts' => 1];
        if ($troncal->tipo === 'ip') {
            $aorData['contact'] = "sip:{$troncal->host}:{$troncal->puerto}";
        }
        $this->upsert('ps_aors', $aorData);

        // ── ps_endpoints ──────────────────────────────────────────────────────
        $endpointData = [
            'id'           => $id,
            'transport'    => $troncal->transporte,
            'aors'         => $id,
            'context'      => $troncal->contexto,
            'allow'        => $troncal->codecs,
            'direct_media' => 'no',
            'from_user'    => $troncal->usuario,
        ];
        if ($troncal->tipo === 'registro') {
            $endpointData['outbound_auth'] = $authId;
        }
        $this->upsert('ps_endpoints', $endpointData);

        if ($troncal->tipo === 'registro') {
            // Auth en realtime, registración en archivo estático
            $this->upsert('ps_auths', [
                'id'        => $authId,
                'auth_type' => 'userpass',
                'username'  => $troncal->usuario,
                'password'  => $troncal->secret_plain,
            ]);
            $this->db()->table('ps_registrations')->where('id', $id)->delete();
            $this->provisionarRegistracion($troncal);
        } else {
            // tipo=ip: limpiar restos de registro previo
            $this->db()->table('ps_auths')->where('id', $authId)->delete();
            $this->db()->table('ps_registrations')->where('id', $id)->delete();
            $this->desprovisionarRegistracion($troncal, reload: true);
        }

        if ($troncal->tipo === 'ip') {
            $this->upsert('ps_endpoint_id_ips', [
                'id'       => $id,
                'endpoint' => $id,
                'match'    => $troncal->host,
            ]);
        } else {
            $this->db()->table('ps_endpoint_id_ips')->where('id', $id)->delete();
        }

        $troncal->update(['provisionado_at' => now()->toDateTimeString()]);
        Log::info("VoIP: troncal {$troncal->nombre} ({$id}) provisionada.");
    }

    public function desprovisionar(Troncal $troncal): void
    {
        $id     = $troncal->endpointId();
        $authId = "{$id}_auth";

        foreach (['ps_registrations', 'ps_endpoint_id_ips', 'ps_endpoints', 'ps_aors'] as $table) {
            $this->db()->table($table)->where('id', $id)->delete();
        }
        $this->db()->table('ps_auths')->where('id', $authId)->delete();
        $this->desprovisionarRegistracion($troncal, reload: true);

        $troncal->update(['provisionado_at' => null]);
        Log::info("VoIP: troncal {$troncal->nombre} ({$id}) desprovisionada.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ARCHIVO DE REGISTRACIONES ESTÁTICAS
    // ─────────────────────────────────────────────────────────────────────────

    public function provisionarRegistracion(Troncal $troncal): void
    {
        $regId  = "trunk_reg_{$troncal->id}";
        $authId = $troncal->endpointId() . '_auth';

        $block = implode("\n", [
            "; ── {$troncal->nombre}" . ($troncal->proveedor ? " ({$troncal->proveedor})" : '') . " ──",
            "[{$regId}]",
            "type=registration",
            "transport=transport-udp",
            "outbound_auth={$authId}",
            "server_uri=sip:{$troncal->host}",
            "client_uri=sip:{$troncal->usuario}@{$troncal->host}",
            "retry_interval=60",
            "expiration=3600",
            "auth_rejection_permanent=no",
        ]);

        $this->mergeBlock($regId, $block);
        $this->reloadPjsip();
    }

    public function desprovisionarRegistracion(Troncal $troncal, bool $reload = false): void
    {
        $regId = "trunk_reg_{$troncal->id}";
        $this->removeBlock($regId);
        if ($reload) {
            $this->reloadPjsip();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VERIFICAR ESTADO DE REGISTRO (AMI)
    // ─────────────────────────────────────────────────────────────────────────

    public function verificarRegistro(Troncal $troncal): array
    {
        if ($troncal->tipo !== 'registro') {
            return [
                'ok'         => null,
                'status'     => 'n/a',
                'server'     => $troncal->host,
                'expires'    => null,
                'last_error' => 'Troncal tipo IP — sin registro SIP saliente',
            ];
        }

        $regId = "trunk_reg_{$troncal->id}";

        try {
            $events = $this->amiAction('PJSIPShowRegistrationsOutbound');

            foreach ($events as $event) {
                if (($event['Event'] ?? '') !== 'OutboundRegistrationDetail') continue;
                if (($event['ObjectName'] ?? '') !== $regId) continue;

                $status  = $event['Status'] ?? 'Unknown';
                $nextReg = isset($event['NextReg']) ? (int) $event['NextReg'] : null;

                return [
                    'ok'         => $status === 'Registered',
                    'status'     => $status,
                    'server'     => $event['ServerUri'] ?? $troncal->host,
                    'expires'    => $status === 'Registered' ? $nextReg : null,
                    'last_error' => in_array($status, ['Rejected', 'Stopped'])
                                    ? "Servidor rechazó el registro ({$event['ServerUri']}). Verifica credenciales."
                                    : null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("VoIP: verificarRegistro AMI error: {$e->getMessage()}");
            return [
                'ok'         => false,
                'status'     => 'Error AMI',
                'server'     => $troncal->host,
                'expires'    => null,
                'last_error' => $e->getMessage(),
            ];
        }

        // El AMI respondió pero no encontró el registro
        $inFile = $this->blockExists($regId);
        return [
            'ok'         => false,
            'status'     => $inFile ? 'Registering' : 'Not provisioned',
            'server'     => $troncal->host,
            'expires'    => null,
            'last_error' => $inFile
                ? 'Bloque en config pero sin respuesta de Asterisk aún'
                : 'Sin bloque de registración — ejecuta Provisionar',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AMI
    // ─────────────────────────────────────────────────────────────────────────

    private function warnIfTransportMissing(): void
    {
        try {
            $events = $this->amiAction('PJSIPShowTransports');
            $found  = false;
            foreach ($events as $event) {
                if (($event['Event'] ?? '') === 'TransportDetail' && ($event['ObjectName'] ?? '') === 'transport-udp') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                Log::warning('VoIP: [transport-udp] no encontrado en Asterisk. Agrega el bloque en pjsip.conf y recarga.');
            }
        } catch (\Throwable) {
            // No bloquear la provisión si el check de transporte falla
        }
    }

    public function reloadPjsip(): bool
    {
        try {
            $raw = $this->amiSend("Action: Reload\r\nModule: res_pjsip.so\r\n\r\n", readAll: false);
            $ok  = str_contains($raw, 'Response: Success') || str_contains($raw, 'Module reloaded');
            if (!$ok) {
                Log::warning("VoIP: pjsip reload AMI response: {$raw}");
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::warning("VoIP: pjsip reload AMI failed: {$e->getMessage()}");
            return false;
        }
    }

    /** Envía una acción AMI de tipo EventList y retorna los eventos parseados. */
    private function amiAction(string $action, array $params = []): array
    {
        $str = "Action: {$action}\r\n";
        foreach ($params as $k => $v) {
            $str .= "{$k}: {$v}\r\n";
        }
        $str .= "\r\n";

        $raw    = $this->amiSend($str, readAll: true);
        $events = [];
        $block  = [];

        foreach (explode("\r\n", $raw) as $line) {
            if ($line === '') {
                if (!empty($block)) {
                    $events[] = $block;
                    $block    = [];
                }
            } elseif (str_contains($line, ': ')) {
                [$key, $val] = explode(': ', $line, 2);
                $block[$key] = $val;
            }
        }
        if (!empty($block)) {
            $events[] = $block;
        }
        return $events;
    }

    private function amiSend(string $action, bool $readAll = false): string
    {
        $host   = config('voip.ami_host', '127.0.0.1');
        $port   = (int) config('voip.ami_port', 5038);
        $user   = config('voip.ami_user', 'megaisp');
        $secret = config('voip.ami_pass', '');

        $sock = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$sock) {
            throw new \RuntimeException("AMI connect failed: [{$errno}] {$errstr}");
        }

        stream_set_timeout($sock, 6);
        fgets($sock, 1024); // Leer banner Asterisk

        fwrite($sock, "Action: Login\r\nUsername: {$user}\r\nSecret: {$secret}\r\n\r\n");
        $loginResp = $this->readAmiBlock($sock);
        if (!str_contains($loginResp, 'Response: Success')) {
            fclose($sock);
            throw new \RuntimeException("AMI login failed: {$loginResp}");
        }

        fwrite($sock, $action);
        $response = $readAll
            ? $this->readAmiUntilComplete($sock)
            : $this->readAmiBlock($sock);

        @fwrite($sock, "Action: Logoff\r\n\r\n");
        @fclose($sock);

        return $response;
    }

    private function readAmiBlock($sock, int $timeout = 4): string
    {
        $buf      = '';
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $line = @fgets($sock, 4096);
            if ($line === false) break;
            $buf .= $line;
            if (str_ends_with(rtrim($buf, "\n"), "\r\n\r\n") || str_ends_with($buf, "\r\n\r\n")) break;
        }
        return $buf;
    }

    private function readAmiUntilComplete($sock, int $timeout = 8): string
    {
        $buf      = '';
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $line = @fgets($sock, 4096);
            if ($line === false) {
                if (feof($sock)) break;
                continue;
            }
            $buf .= $line;
            if (str_contains($buf, 'EventList: Complete')) break;
        }
        return $buf;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GESTIÓN DEL ARCHIVO DE REGISTRACIONES
    // ─────────────────────────────────────────────────────────────────────────

    private function mergeBlock(string $sectionId, string $block): void
    {
        $this->ensureRegFile();
        $content = file_get_contents($this->regFile);
        $content = $this->removeSection($content, $sectionId);
        $content = rtrim($content) . "\n\n" . $block . "\n";
        file_put_contents($this->regFile, $content, LOCK_EX);
    }

    private function removeBlock(string $sectionId): void
    {
        if (!file_exists($this->regFile)) return;
        $content = file_get_contents($this->regFile);
        $content = $this->removeSection($content, $sectionId);
        file_put_contents($this->regFile, $content, LOCK_EX);
    }

    private function removeSection(string $content, string $sectionId): string
    {
        $lines     = explode("\n", $content);
        $result    = [];
        $inSection = false;

        foreach ($lines as $line) {
            if ($line === "[{$sectionId}]") {
                $inSection = true;
                // Quitar el comentario "; ── ..." de la línea anterior si lo pusimos nosotros
                if (!empty($result) && str_starts_with(end($result), '; ── ')) {
                    array_pop($result);
                }
                continue;
            }
            if ($inSection) {
                // Salir de la sección al encontrar el inicio de otra
                if (str_starts_with($line, '[') && $line !== '') {
                    $inSection = false;
                    $result[]  = $line;
                }
                // Ignorar las líneas de la sección (incluidas las en blanco)
                continue;
            }
            $result[] = $line;
        }

        return implode("\n", $result);
    }

    private function blockExists(string $sectionId): bool
    {
        if (!file_exists($this->regFile)) return false;
        return str_contains(file_get_contents($this->regFile), "[{$sectionId}]");
    }

    private function ensureRegFile(): void
    {
        $dir = dirname($this->regFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($this->regFile)) {
            file_put_contents(
                $this->regFile,
                "; MegaISP PJSIP Registrations — generado automáticamente. No editar manualmente.\n"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXTENSIONES
    // ─────────────────────────────────────────────────────────────────────────

    public function provisionarExtension(Extension $ext): void
    {
        $id     = $ext->endpointId();
        $authId = "{$id}_auth";

        $this->upsert('ps_aors', [
            'id'                => $id,
            'max_contacts'      => 1,
            'remove_existing'   => 'yes',
            'qualify_frequency' => 60,
        ]);

        $this->upsert('ps_auths', [
            'id'        => $authId,
            'auth_type' => 'userpass',
            'username'  => $ext->numero,
            'password'  => $ext->secret_plain,
        ]);

        $endpoint = [
            'id'           => $id,
            'transport'    => $ext->transporte,
            'aors'         => $id,
            'auth'         => $authId,
            'context'      => $ext->contexto,
            'allow'        => $ext->codecs,
            'direct_media' => 'no',
        ];
        if ($ext->callerid) {
            $endpoint['callerid'] = $ext->callerid;
        }
        $this->upsert('ps_endpoints', $endpoint);

        $ext->update(['provisionado_at' => now()->toDateTimeString()]);
        Log::info("VoIP: extensión {$ext->numero} ({$id}) provisionada.");
    }

    public function desprovisionarExtension(Extension $ext): void
    {
        $id     = $ext->endpointId();
        $authId = "{$id}_auth";

        foreach (['ps_endpoints', 'ps_aors'] as $table) {
            $this->db()->table($table)->where('id', $id)->delete();
        }
        $this->db()->table('ps_auths')->where('id', $authId)->delete();

        $ext->update(['provisionado_at' => null]);
        Log::info("VoIP: extensión {$ext->numero} ({$id}) desprovisionada.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function db()
    {
        return DB::connection('asterisk_rt');
    }

    private function upsert(string $table, array $data): void
    {
        $id     = $data['id'];
        $exists = $this->db()->table($table)->where('id', $id)->exists();
        if ($exists) {
            $this->db()->table($table)->where('id', $id)->update($data);
        } else {
            $this->db()->table($table)->insert($data);
        }
    }
}
