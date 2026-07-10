<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reforma de permisos B3.2 — clasificación curada de permisos del Portal colaborador.
 *
 * Decisión de Irving (checkpoint): PORTAL = solo la "Lista A" (9), acciones/vistas
 * EXCLUSIVAS del colaborador en su app/portal. Todo lo demás queda 'panel' (default),
 * incluida la administración de Talento (*.manage/*.configure) y las vistas dual-use.
 * Idempotente: UPDATE por lista explícita de nombres. down() los regresa a 'panel'.
 */
return new class extends Migration
{
    private const PORTAL = [
        'portal.colaborador',
        'talento.portal_tecnico',
        'talento.custody.view',
        'talento.attendance.checkin',
        'talento.field_flow.accept',
        'talento.media.upload',
        'talento.penalties.appeal',
        'talento.project_reports.create',
        'talento.survey.view',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('permissions', 'context')) {
            return; // depende de B3.1
        }
        DB::table('permissions')->whereIn('name', self::PORTAL)->update(['context' => 'portal']);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('permissions', 'context')) {
            return;
        }
        DB::table('permissions')->whereIn('name', self::PORTAL)->update(['context' => 'panel']);
    }
};
