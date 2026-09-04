<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fila del libro de variaciones de capital — apartado I.
 */
class DcCapitalVariacion extends Model
{
    use SoftDeletes;

    protected $table = 'dc_capital_variaciones';

    protected $fillable = [
        'empresa_id', 'fecha', 'tipo', 'monto', 'capital_resultante', 'nota',
    ];

    protected $casts = [
        'fecha'              => 'date',
        'monto'              => 'decimal:2',
        'capital_resultante' => 'decimal:2',
    ];

    public const TIPOS = ['aumento', 'disminucion'];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
