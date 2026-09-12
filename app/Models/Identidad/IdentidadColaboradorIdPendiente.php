<?php

namespace App\Models\Identidad;

use Illuminate\Database\Eloquent\Model;

/**
 * Auditoría de faltantes de la doble escritura de colaborador_id (Fase 3b, #9990963).
 * Modelo plano (sin BaseModel/LogsActivity): es un log de solo-inserción, no un
 * registro de negocio con created_by/updated_by.
 */
class IdentidadColaboradorIdPendiente extends Model
{
    protected $table = 'identidad_colaborador_id_pendientes';

    public $timestamps = false;

    protected $guarded = [];
}
