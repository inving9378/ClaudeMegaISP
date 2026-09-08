<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Model;

// NO extiende BaseModel a propósito: log append-only de auditoría, mismo patrón que
// TalentoComisionEspejo/TalentoLedgerEntry -- sin LogsActivity por cada inserción.
class TalentoReconciliacionLog extends Model
{
    protected $table = 'talento_reconciliacion_log';

    const UPDATED_AT = null;

    protected $fillable = [
        'colaborador_id', 'seller_id',
        'source_period_start', 'source_period_end',
        'monto_vendedores', 'monto_espejo', 'diferencia',
        'estado',
    ];

    protected $casts = [
        'monto_vendedores'     => 'decimal:2',
        'monto_espejo'         => 'decimal:2',
        'diferencia'           => 'decimal:2',
        'source_period_start'  => 'date',
        'source_period_end'    => 'date',
        'created_at'           => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
}
