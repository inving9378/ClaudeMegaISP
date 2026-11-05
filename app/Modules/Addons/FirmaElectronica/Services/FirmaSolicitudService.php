<?php

namespace App\Modules\Addons\FirmaElectronica\Services;

use App\Modules\Addons\FirmaElectronica\Contracts\FirmaProviderInterface;
use App\Modules\Addons\FirmaElectronica\Models\FirmaEvento;
use App\Modules\Addons\FirmaElectronica\Models\FirmaParticipante;
use App\Modules\Addons\FirmaElectronica\Models\FirmaSolicitud;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Ciclo de vida de la solicitud: creación, envío simulado y eventos idempotentes. */
class FirmaSolicitudService
{
    public function __construct(private FirmaProviderInterface $proveedor, private FirmaTokenService $tokens) {}

    /** @param array<int,array{nombre:string,correo:string,rol?:string,orden?:int}> $participantes */
    public function crear(array $participantes, ?int $emisionId = null, bool $secuencial = false, ?int $creadoPor = null, ?string $sha256 = null): FirmaSolicitud
    {
        return DB::transaction(function () use ($participantes, $emisionId, $secuencial, $creadoPor, $sha256) {
            $s = FirmaSolicitud::create([
                'emision_id' => $emisionId,
                'orden_secuencial' => $secuencial,
                'proveedor' => $this->proveedor->nombre(),
                'sha256_original' => $sha256,
                'creado_por' => $creadoPor,
                'expira_at' => now()->addHours((int) config('firma_electronica.token_ttl_horas', 72)),
            ]);
            foreach (array_values($participantes) as $i => $p) {
                FirmaParticipante::create([
                    'solicitud_id' => $s->id, 'nombre' => $p['nombre'], 'correo' => $p['correo'],
                    'rol' => $p['rol'] ?? 'firmante', 'orden' => $p['orden'] ?? $i + 1,
                ]);
            }
            return $s;
        });
    }

    /** Envía al proveedor y emite un enlace por participante. Devuelve [participante_id => token en claro]. */
    public function enviar(FirmaSolicitud $s): array
    {
        try {
            $s->id_externo = $this->proveedor->crearSolicitud($s);
        } catch (\Throwable $e) {
            $s->update(['estado' => FirmaSolicitud::ERROR]);
            throw $e;
        }
        $s->estado = FirmaSolicitud::ENVIADA;
        $s->save();

        $enlaces = [];
        foreach ($s->participantes as $p) {
            $enlaces[$p->id] = $this->tokens->emitir($p, $s->expira_at);
        }
        return $enlaces;
    }

    public function cancelar(FirmaSolicitud $s): FirmaSolicitud
    {
        if (! $s->esTerminal()) {
            if ($s->id_externo) {
                $this->proveedor->cancelar($s->id_externo);
            }
            $s->update(['estado' => FirmaSolicitud::CANCELADA]);
        }
        return $s;
    }

    /**
     * Aplica un evento (webhook o interno). Idempotente por proveedor_evento_id y tolerante
     * a desorden: nunca retrocede de estado ni modifica uno terminal.
     * Devuelve false si el evento ya estaba registrado.
     */
    public function aplicarEvento(FirmaSolicitud $s, string $eventoId, string $tipo, array $payload = []): bool
    {
        try {
            FirmaEvento::create(['solicitud_id' => $s->id, 'proveedor_evento_id' => $eventoId, 'tipo' => $tipo, 'payload' => $payload]);
        } catch (QueryException $e) {
            return false; // duplicado
        }

        $s->refresh();
        if ($s->esTerminal()) {
            return true;
        }

        switch ($tipo) {
            case 'entregada': $this->avanzar($s, FirmaSolicitud::ENTREGADA); break;
            case 'abierta': $this->avanzar($s, FirmaSolicitud::ABIERTA); break;
            case 'participante_firmado':
                $p = FirmaParticipante::where('solicitud_id', $s->id)->find($payload['participante_id'] ?? 0);
                if ($p && $p->estado !== 'firmado') {
                    $p->update(['estado' => 'firmado', 'firmado_at' => now()]);
                }
                $this->recalcularFirmas($s);
                break;
            case 'rechazada': $s->update(['estado' => FirmaSolicitud::RECHAZADA]); break;
            case 'expirada': $s->update(['estado' => FirmaSolicitud::EXPIRADA]); break;
            case 'cancelada': $s->update(['estado' => FirmaSolicitud::CANCELADA]); break;
        }
        return true;
    }

    /** Marca expiradas las solicitudes vencidas y sin terminar. */
    public function expirarVencidas(): int
    {
        return FirmaSolicitud::whereNotIn('estado', FirmaSolicitud::TERMINALES)
            ->whereNotNull('expira_at')->where('expira_at', '<', now())
            ->update(['estado' => FirmaSolicitud::EXPIRADA]);
    }

    private function avanzar(FirmaSolicitud $s, string $nuevo): void
    {
        $r = FirmaSolicitud::RANGO;
        if (($r[$nuevo] ?? -1) > ($r[$s->estado] ?? -1)) {
            $s->update(['estado' => $nuevo]);
        }
    }

    private function recalcularFirmas(FirmaSolicitud $s): void
    {
        $total = $s->participantes()->count();
        $firmados = $s->participantes()->where('estado', 'firmado')->count();
        if ($total > 0 && $firmados === $total) {
            $s->update(['estado' => FirmaSolicitud::FIRMADA]);
        } elseif ($firmados > 0) {
            $this->avanzar($s, FirmaSolicitud::PARCIAL);
        }
    }
}
