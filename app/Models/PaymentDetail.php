<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends BaseModel
{
    use HasFactory;

    protected $table = 'payments_details';

    protected $fillable = [
        'type',
        'payment_id',
        'client_id',
        'prospect_id',
        'bundle_id',
    ];

    public function payment()
    {
        return $this->belongsTo(PaymentSeller::class);
    }

    public function client()
    {
        return $this->belongsTo(ClientMainInformation::class);
    }

    public function prospect()
    {
        return $this->belongsTo(CrmLeadInformation::class);
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }
}
