<?php

namespace App\Modules\Addons\Talento\Observers;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\EmployeeDocumentPackageService;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Auto-asignación del permiso base 'portal.colaborador' según el estado del colaborador (Fase 0 · SP1b).
 *
 *  - status = active  -> el user recibe el permiso DIRECTO (idempotente).
 *  - status != active -> se REVOCA solo el permiso DIRECTO (un admin que además es colaborador
 *                        conserva el que hereda por rol; no se toca).
 *
 * Tolerante (si el colaborador no tiene user, no hace nada) y limpia la caché de permisos tras
 * cada cambio. El permiso lo crea la migración SP1a (corre antes de que este observer dispare).
 */
class TalentoColaboradorObserver
{
    public function created(TalentoColaborador $colaborador): void
    {
        $this->sync($colaborador);
        $this->generarDocumentos($colaborador);
    }

    public function updated(TalentoColaborador $colaborador): void
    {
        // Solo reacciona a cambios de estado (alta/baja), no a cualquier update.
        if ($colaborador->wasChanged('status')) {
            $this->sync($colaborador);
        }
    }

    private function sync(TalentoColaborador $colaborador): void
    {
        $user = User::find($colaborador->user_id);
        if (! $user) {
            return;
        }

        if ($colaborador->status === 'active') {
            if (! $user->hasDirectPermission('portal.colaborador')) {
                $user->givePermissionTo('portal.colaborador');
            }
        } elseif ($user->hasDirectPermission('portal.colaborador')) {
            $user->revokePermissionTo('portal.colaborador');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Item #871 (Expediente RH — Hijo D2). Un fallo aqui NUNCA debe bloquear el alta del
     * colaborador (best-effort, try/catch propio, solo se registra en log).
     */
    private function generarDocumentos(TalentoColaborador $colaborador): void
    {
        try {
            app(EmployeeDocumentPackageService::class)->generateForColaborador($colaborador);
        } catch (\Throwable $e) {
            Log::warning('Talento: fallo al generar paquete de documentos (Hijo D2) al alta del colaborador', [
                'colaborador_id' => $colaborador->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
