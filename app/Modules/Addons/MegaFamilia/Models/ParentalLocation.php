<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalLocation extends BaseModel
{
    protected $table = 'parental_locations';
    public $timestamps = false; // only `recorded_at`

    protected $fillable = ['device_id', 'lat', 'lng', 'accuracy', 'battery', 'recorded_at'];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'accuracy' => 'integer',
        'battery' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(ParentalDevice::class, 'device_id');
    }
}
