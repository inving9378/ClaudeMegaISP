<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\PaymentByRule;
use App\Models\PaymentByRuleDetails;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

// NO extiende BaseModel a propósito: ledger append-only de alto volumen potencial,
// igual que TalentoLedgerEntry/FleetPosition -- sin LogsActivity por cada inserción.
class TalentoComisionEspejo extends Model
{
    protected $table = 'talento_comisiones_espejo';

    // Ledger append-only: solo created_at (manual), sin updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'colaborador_id', 'user_id', 'seller_id',
        'payment_by_rule_id', 'payment_by_rule_details_id',
        'amount',
        'period_start', 'period_end',
        'source_period_start', 'source_period_end',
    ];

    protected $casts = [
        'amount'               => 'decimal:2',
        'period_start'         => 'date',
        'period_end'           => 'date',
        'source_period_start'  => 'date',
        'source_period_end'    => 'date',
        'created_at'           => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    // Relaciones lógicas (sin FK dura en BD -- ver comentario de la migración).
    public function paymentByRule()
    {
        return $this->belongsTo(PaymentByRule::class, 'payment_by_rule_id');
    }

    public function paymentByRuleDetails()
    {
        return $this->belongsTo(PaymentByRuleDetails::class, 'payment_by_rule_details_id');
    }
}
