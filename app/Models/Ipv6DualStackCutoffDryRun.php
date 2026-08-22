<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría del orquestador dry-run IPv6 Fase 3.1a (item #1047,
 * sub-item de #991). Cada fila es la INTENCIÓN de un corte dual-stack
 * (comandos generados, nunca ejecutados) — no una acción real contra el
 * router. Modelo plano sin LogsActivity a propósito, ver migración de
 * creación de la tabla.
 */
class Ipv6DualStackCutoffDryRun extends Model
{
    protected $table = 'ipv6_dual_stack_cutoff_dry_runs';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'client_id' => 'integer',
        'comandos' => 'array',
        'advertencias' => 'array',
        'created_at' => 'datetime',
    ];
}
