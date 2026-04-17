<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionDetail extends Model
{
    use HasFactory;

    protected $table = 'commissions_details';

    protected $fillable = [
        'type',
        'bonus',
        'commission_id',
        'bundle_id',
        'prospect_id',
        'client_id',
    ];

    public function commission()
    {
        return $this->belongsTo(Commission::class, 'commission_id');
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    public function prospect()
    {
        return $this->belongsTo(CrmLeadInformation::class);
    }

    public function client()
    {
        return $this->belongsTo(ClientMainInformation::class);
    }
}
