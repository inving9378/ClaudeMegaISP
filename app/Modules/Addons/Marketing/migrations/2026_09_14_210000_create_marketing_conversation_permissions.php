<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Item roadmap #9991147 (sub-item de #9991116, hallazgo de la auditoría de permisos). Las 4
 * rutas CRÍTICAS de MarketingConversationController (send-message/toggle-ai/assign/close) no
 * tenían NINGÚN guard de permiso — solo 'web'+'auth' — así que cualquier usuario autenticado
 * podía enviar WhatsApp real a nombre del negocio, togglear IA, reasignar o cerrar cualquier
 * conversación. Decisión de Irving (respuestas estructuradas de este item): permisos GRANULARES
 * por acción (no uno solo), asignados por defecto SOLO a super-administrator+DESARROLLADOR
 * (no existe rol 'marketing_supervisor' en BD) vía el patrón estándar de
 * PermissionSyncService::syncPermissionToBaseRoles — listados aquí para que se redistribuyan
 * después, igual que el resto de hallazgos de la auditoría #9990745/#9991116.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'marketing.conversation.send',
        'marketing.conversation.toggle-ai',
        'marketing.conversation.assign',
        'marketing.conversation.close',
    ];

    public function up(): void
    {
        $syncService = app(PermissionSyncService::class);

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            $syncService->syncPermissionToBaseRoles($permiso);
        }
    }

    public function down(): void
    {
        // Catálogo de permisos: no se revierte (mismo criterio que el resto de migraciones de permisos del proyecto).
    }
};
