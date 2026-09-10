<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Perfil de extensión: lo que una extensión hereda de su rango.
 *
 * Existe para que dar de alta la 1201 no obligue a configurar códecs, permisos de
 * marcación y grabación a mano — hereda el perfil `tecnico_campo` y ya.
 */
class PerfilExtension extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'voip_perfiles_extension';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion',
        'permite_nacional_fijo', 'permite_nacional_movil',
        'permite_internacional', 'permite_premium',
        'permite_entrantes_exterior', 'graba_llamadas',
        'codecs', 'limite_diario_centavos', 'limite_mensual_centavos',
        'canales_simultaneos_max', 'politicas', 'es_plantilla_sistema',
    ];

    protected $casts = [
        'permite_nacional_fijo'      => 'boolean',
        'permite_nacional_movil'     => 'boolean',
        'permite_internacional'      => 'boolean',
        'permite_premium'            => 'boolean',
        'permite_entrantes_exterior' => 'boolean',
        'graba_llamadas'             => 'boolean',
        'es_plantilla_sistema'       => 'boolean',
        'codecs'                     => 'array',
        'politicas'                  => 'array',
    ];

    /** Los campos que la herencia resuelve. Uno solo, para que no se dupliquen listas. */
    public const CAMPOS_HEREDABLES = [
        'permite_nacional_fijo', 'permite_nacional_movil',
        'permite_internacional', 'permite_premium',
        'permite_entrantes_exterior', 'graba_llamadas',
        'codecs', 'limite_diario_centavos', 'limite_mensual_centavos',
        'canales_simultaneos_max', 'politicas',
    ];

    public function rangos()
    {
        return $this->hasMany(RangoNumeracion::class, 'voip_perfil_extension_id');
    }

    public function scopePorCodigo($q, string $codigo)
    {
        return $q->where('codigo', $codigo);
    }
}
