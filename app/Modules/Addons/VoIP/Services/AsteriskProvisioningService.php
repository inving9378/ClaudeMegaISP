<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\Troncal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Escribe/borra filas ps_* en la BD asterisk_rt.
 * Asterisk lee esas tablas en tiempo real — sin reload.
 */
class AsteriskProvisioningService
{
    private function db()
    {
        return DB::connection('asterisk_rt');
    }

    public function provisionar(Troncal $troncal): void
    {
        $id       = $troncal->endpointId();
        $authId   = "{$id}_auth";
        $codecs   = $troncal->codecs;
        $now      = now()->toDateTimeString();

        // ── ps_aors ──────────────────────────────────────────────────────────
        $aorData = [
            'id'           => $id,
            'max_contacts' => 1,
        ];
        if ($troncal->tipo === 'ip') {
            $aorData['contact'] = "sip:{$troncal->host}:{$troncal->puerto}";
        }
        $this->upsert('ps_aors', $aorData);

        // ── ps_endpoints ─────────────────────────────────────────────────────
        $endpointData = [
            'id'            => $id,
            'transport'     => $troncal->transporte,
            'aors'          => $id,
            'context'       => $troncal->contexto,
            'allow'         => $codecs,
            'direct_media'  => 'no',
            'from_user'     => $troncal->usuario,
        ];
        if ($troncal->tipo === 'registro') {
            $endpointData['outbound_auth'] = $authId;
        }
        $this->upsert('ps_endpoints', $endpointData);

        // ── tipo=registro: ps_auths + ps_registrations ────────────────────
        if ($troncal->tipo === 'registro') {
            $this->upsert('ps_auths', [
                'id'        => $authId,
                'auth_type' => 'userpass',
                'username'  => $troncal->usuario,
                'password'  => $troncal->secret_plain,
            ]);

            $this->upsert('ps_registrations', [
                'id'             => $id,
                'transport'      => $troncal->transporte,
                'outbound_auth'  => $authId,
                'server_uri'     => "sip:{$troncal->host}",
                'client_uri'     => "sip:{$troncal->usuario}@{$troncal->host}",
                'contact_user'   => $troncal->usuario,
                'retry_interval' => 60,
                'forbidden_retry_interval' => 600,
                'expiration'     => 3600,
            ]);
        } else {
            // tipo=ip: limpiar restos de un cambio previo tipo=registro
            $this->db()->table('ps_auths')->where('id', $authId)->delete();
            $this->db()->table('ps_registrations')->where('id', $id)->delete();
        }

        // ── tipo=ip: ps_endpoint_id_ips ───────────────────────────────────
        if ($troncal->tipo === 'ip') {
            $this->upsert('ps_endpoint_id_ips', [
                'id'       => $id,
                'endpoint' => $id,
                'match'    => $troncal->host,
            ]);
        } else {
            $this->db()->table('ps_endpoint_id_ips')->where('id', $id)->delete();
        }

        $troncal->update(['provisionado_at' => $now]);

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

        $troncal->update(['provisionado_at' => null]);

        Log::info("VoIP: troncal {$troncal->nombre} ({$id}) desprovisionada.");
    }

    private function upsert(string $table, array $data): void
    {
        $id = $data['id'];
        $exists = $this->db()->table($table)->where('id', $id)->exists();

        if ($exists) {
            $this->db()->table($table)->where('id', $id)->update($data);
        } else {
            $this->db()->table($table)->insert($data);
        }
    }
}
