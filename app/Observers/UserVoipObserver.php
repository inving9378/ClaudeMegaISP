<?php

namespace App\Observers;

use App\Models\User;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use Illuminate\Support\Facades\Log;

class UserVoipObserver
{
    public function __construct(private AsteriskProvisioningService $provisioner) {}

    public function updated(User $user): void
    {
        if (! $user->isDirty('estado')) {
            return;
        }

        $estadoAnterior = $user->getOriginal('estado');
        $estadoNuevo    = $user->estado;

        if ($estadoAnterior === $estadoNuevo) {
            return;
        }

        $extensiones = Extension::where('user_id', $user->id)->get();
        if ($extensiones->isEmpty()) {
            return;
        }

        foreach ($extensiones as $ext) {
            try {
                match ($estadoNuevo) {
                    'inactivo'  => $this->handleInactivo($ext),
                    'bloqueado' => $this->handleBloqueado($ext),
                    'activo'    => $this->handleActivo($ext, $estadoAnterior),
                };
            } catch (\Throwable $e) {
                Log::error("VoIP UserObserver: error al procesar ext {$ext->numero} para user {$user->id}: {$e->getMessage()}");
            }
        }
    }

    private function handleInactivo(Extension $ext): void
    {
        if ($ext->estaProvisionada()) {
            $this->provisioner->desprovisionarExtension($ext);
            Log::info("VoIP: usuario inactivo → ext {$ext->numero} desprovisionada.");
        }
    }

    private function handleBloqueado(Extension $ext): void
    {
        if ($ext->estaProvisionada()) {
            $this->provisioner->restringirExtension($ext);
            Log::info("VoIP: usuario bloqueado → ext {$ext->numero} restringida (sin salientes).");
        }
    }

    private function handleActivo(Extension $ext, string $estadoAnterior): void
    {
        if ($estadoAnterior === 'inactivo' && ! $ext->estaProvisionada()) {
            // Re-provisionar con credenciales actuales
            $this->provisioner->provisionarExtension($ext);
            Log::info("VoIP: usuario reactivado (desde inactivo) → ext {$ext->numero} re-provisionada.");
        } elseif ($estadoAnterior === 'bloqueado' && $ext->estaProvisionada()) {
            // Solo restaurar el contexto, no tocar auth/aors
            $this->provisioner->restaurarExtension($ext);
            Log::info("VoIP: usuario desbloqueado → ext {$ext->numero} restaurada a from-internal.");
        }
    }
}
