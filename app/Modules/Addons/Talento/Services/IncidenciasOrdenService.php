<?php

namespace App\Modules\Addons\Talento\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Incidencias de órdenes en `tasks` (#1326): lo que el técnico reporta con «No puedo completar».
 *
 * Una incidencia sin atender manda la orden al supervisor: el técnico la ve como «Incidencia» y no la
 * puede iniciar, y quien gestiona órdenes la ve en la bandeja de Talento › Órdenes. Editarla,
 * reprogramarla, eliminarla o regresarla al técnico la deja atendida.
 */
class IncidenciasOrdenService
{
    /** Mismas claves que acepta la app; «El cliente canceló» desde #1326. */
    public const MOTIVOS = [
        'cliente_cancelo' => 'El cliente canceló',
        'cliente_ausente' => 'Cliente ausente',
        'sin_acceso'      => 'Sin acceso al lugar',
        'falta_material'  => 'Falta material',
        'olt_sin_senal'   => 'OLT sin señal',
        'riesgo'          => 'Riesgo en el lugar',
        'otro'            => 'Otro',
    ];

    public const ATENCIONES = ['editada', 'reprogramada', 'eliminada', 'regresada'];

    private static ?bool $conColumnas = null;

    /** Sin la migración de #1326 todavía no hay bandeja: nada cuenta como abierta. */
    public static function disponible(): bool
    {
        return self::$conColumnas ??= Schema::hasColumn('talento_work_order_incidents', 'atendida_at');
    }

    /** La incidencia sin atender más reciente de una orden, o null. */
    public function abierta(int $taskId): ?array
    {
        if (! self::disponible()) {
            return null;
        }
        $i = DB::table('talento_work_order_incidents as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.created_by')
            ->where('i.tarea_id', $taskId)
            ->whereNull('i.atendida_at')
            ->orderByDesc('i.id')
            ->first(['i.id', 'i.motivo', 'i.nota', 'i.created_at', 'u.name', 'u.father_last_name']);

        return $i ? [
            'id'           => (int) $i->id,
            'motivo'       => $i->motivo,
            'motivo_texto' => self::MOTIVOS[$i->motivo] ?? $i->motivo,
            'nota'         => $i->nota,
            'cuando'       => $i->created_at,
            'por'          => trim(($i->name ?? '') . ' ' . ($i->father_last_name ?? '')) ?: null,
        ] : null;
    }

    /** Filtra una consulta de `tasks` a las que tienen una incidencia sin atender. */
    public function scopeConAbierta(Builder $query): Builder
    {
        if (! self::disponible()) {
            return $query->whereRaw('1=0');
        }

        return $query->whereExists(fn ($q) => $q->select(DB::raw(1))
            ->from('talento_work_order_incidents as i')
            ->whereColumn('i.tarea_id', 'tasks.id')
            ->whereNull('i.atendida_at'));
    }

    /** Deja atendidas las incidencias abiertas de una orden. Devuelve cuántas. */
    public function atender(int $taskId, string $atencion, ?int $userId): int
    {
        if (! self::disponible()) {
            return 0;
        }

        return DB::table('talento_work_order_incidents')
            ->where('tarea_id', $taskId)
            ->whereNull('atendida_at')
            ->update(['atendida_at' => now(), 'atendida_por' => $userId, 'atencion' => $atencion, 'updated_at' => now()]);
    }
}
