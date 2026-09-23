<?php

namespace App\Modules\Addons\Talento\Observers;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\EmployeeDocumentPackageService;
use App\Modules\Addons\VoIP\Services\ReclamadorExtensionAutomatico;
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
        $this->reclamarExtension($colaborador);
    }

    public function updated(TalentoColaborador $colaborador): void
    {
        // Solo reacciona a cambios de estado (alta/baja), no a cualquier update.
        if ($colaborador->wasChanged('status')) {
            $this->sync($colaborador);
            if ($colaborador->status === 'active') {
                $this->reclamarExtension($colaborador);
            }
        }
    }

    /**
     * Item #9990813: además de 'portal.colaborador' (base), sincroniza los permisos propios de
     * "Mis documentos" del Portal con el mismo criterio aditivo — activo = los tiene, inactivo =
     * se le revocan (solo el directo; si los hereda por rol de staff, se conservan).
     */
    private const PERMISOS_ACTIVO = [
        'portal.colaborador',
        'talento.documentos.ver-propios',
        'talento.documentos.firmar-propios',
    ];

    private function sync(TalentoColaborador $colaborador): void
    {
        $user = User::find($colaborador->user_id);
        if (! $user) {
            return;
        }

        foreach (self::PERMISOS_ACTIVO as $permiso) {
            if ($colaborador->status === 'active') {
                if (! $user->hasDirectPermission($permiso)) {
                    $user->givePermissionTo($permiso);
                }
            } elseif ($user->hasDirectPermission($permiso)) {
                $user->revokePermissionTo($permiso);
            }
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

    /**
     * MegaVoz Fase 1 (23-sep-2026). Reclama una extensión SIP ya sembrada del
     * rango que le corresponde por rol (solo roles de atención directa — ver
     * ReclamadorExtensionAutomatico::ROL_A_RANGO). Best-effort: un fallo aquí
     * NUNCA debe bloquear el alta/activación del colaborador.
     */
    private function reclamarExtension(TalentoColaborador $colaborador): void
    {
        try {
            app(ReclamadorExtensionAutomatico::class)->reclamarParaColaborador($colaborador);
        } catch (\Throwable $e) {
            Log::warning('Talento: fallo al reclamar extensión SIP automática (MegaVoz Fase 1)', [
                'colaborador_id' => $colaborador->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
