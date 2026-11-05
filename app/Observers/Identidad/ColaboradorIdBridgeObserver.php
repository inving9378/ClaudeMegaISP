<?php

namespace App\Observers\Identidad;

use App\Modules\Core\Clientes\Models\ClientMainInformation;
use App\Services\Identidad\ColaboradorIdResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3b de #9990778 (item #9990963) — doble escritura de colaborador_id en
 * client_main_information. Se registra EXPLÍCITAMENTE en las dos clases Eloquent
 * (App\Modules\Core\Clientes\Models\ClientMainInformation y su proxy legacy
 * App\Models\ClientMainInformation): Fase 3a (#9990962) probó empíricamente que,
 * pese a la herencia, los eventos de modelo de Eloquent NO se comparten entre
 * ambas — cada una necesita su propio observe().
 *
 * Detrás del feature flag `identidad.doble_escritura_colaborador_id` (default
 * OFF). Sin match: deja colaborador_id NULL y registra el hueco en
 * identidad_colaborador_id_pendientes — nunca bloquea el alta/edición.
 */
class ColaboradorIdBridgeObserver
{
    public function creating(ClientMainInformation $model): void
    {
        $this->resolver($model, esNuevo: true);
    }

    public function updating(ClientMainInformation $model): void
    {
        $this->resolver($model, esNuevo: false);
    }

    /** UNI-T C2 (#878): historial de cambios de seller_id/colaborador_id (solo con la doble escritura encendida). */
    public function created(ClientMainInformation $model): void
    {
        $this->historial($model, true);
    }

    public function updated(ClientMainInformation $model): void
    {
        $this->historial($model, false);
    }

    private function historial(ClientMainInformation $model, bool $esNuevo): void
    {
        if (!ColaboradorIdResolver::habilitado()) {
            return;
        }
        try {
            if (!Schema::hasTable('identidad_cambios_historial')) {
                return;
            }
            foreach (['seller_id', 'colaborador_id'] as $campo) {
                $cambio = $esNuevo ? $model->{$campo} !== null : $model->wasChanged($campo);
                if (!$cambio) {
                    continue;
                }
                DB::table('identidad_cambios_historial')->insert([
                    'tabla' => 'client_main_information',
                    'registro_id' => $model->getKey(),
                    'campo' => $campo,
                    'valor_anterior' => $esNuevo ? null : $model->getOriginal($campo),
                    'valor_nuevo' => $model->{$campo},
                    'origen' => 'observer',
                    'user_id' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // El historial nunca bloquea el alta/edición.
        }
    }

    private function resolver(ClientMainInformation $model, bool $esNuevo): void
    {
        if (!ColaboradorIdResolver::habilitado()) {
            return;
        }

        $sellerId = $model->seller_id;
        if ($sellerId === null) {
            return;
        }

        if (!$esNuevo && !$model->isDirty('seller_id')) {
            return;
        }

        $registroId = $esNuevo ? null : $model->getKey();
        $model->colaborador_id = ColaboradorIdResolver::resolveOrRegistrarPendiente(
            (int) $sellerId,
            'client_main_information',
            $registroId
        );
    }
}
