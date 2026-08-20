<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de la Torre — fila ÚNICA (singleton).
 *
 * No se lee directo: se lee por `TorreConfigService::get()`, que la cachea e invalida al guardar.
 * Leerla a pelo desde varios sitios sería reabrir el mismo problema que esta tabla viene a cerrar.
 */
class TorreConfig extends Model
{
    protected $table = 'torre_config';

    /** Niveles válidos del TECHO GLOBAL, de menos a más automático. El orden importa. */
    public const NIVELES = ['manual', 'estandar', 'asistido', 'autonomo'];

    /**
     * Techo de `nivel_riesgo` que cada nivel de automatización permite aprobar A LA MÁQUINA.
     * `null` = la máquina no aprueba nada; todo va a Irving.
     *
     * Es la traducción entre el vocabulario del panel (que Irving lee) y el del circuito
     * (`nivel_riesgo` A/B/C). Vive aquí y en ningún otro lado.
     */
    public const TECHO_POR_NIVEL = [
        'manual'   => null,
        'estandar' => 'A',
        'asistido' => 'B',
        'autonomo' => 'C',
    ];

    protected $fillable = [
        'nivel_automatizacion',
        'auditor_activo',
        'auditor_max_por_corrida',
        'auditor_cooldown_min',
    ];

    protected $casts = [
        'auditor_activo'          => 'boolean',
        'auditor_max_por_corrida' => 'integer',
        'auditor_cooldown_min'    => 'integer',
    ];

    /** El techo de nivel_riesgo (A|B|C|null) que implica el nivel de automatización vigente. */
    public function techoGlobal(): ?string
    {
        return self::TECHO_POR_NIVEL[$this->nivel_automatizacion] ?? 'A';
    }
}
