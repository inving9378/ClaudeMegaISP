<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Acta de asamblea (ordinaria/extraordinaria) o de sesión del consejo de
 * administración — apartado I. `tipo` distingue el libro al que pertenece
 * (ver filtros del catálogo, `CatalogoSeeder`).
 */
class DcActa extends Model
{
    use SoftDeletes;

    protected $table = 'dc_actas';

    protected $fillable = [
        'empresa_id', 'tipo', 'fecha', 'folio', 'resumen', 'protocolizada', 'documento_id',
    ];

    protected $casts = [
        'fecha'         => 'date',
        'protocolizada' => 'boolean',
    ];

    public const TIPOS = ['asamblea_ordinaria', 'asamblea_extraordinaria', 'consejo'];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function documento()
    {
        return $this->belongsTo(DcDocumento::class, 'documento_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
