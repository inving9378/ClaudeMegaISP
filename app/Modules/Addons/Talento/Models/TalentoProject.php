<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentoProject extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_projects';

    protected $fillable = [
        'name', 'description', 'status', 'lead_colaborador_id',
        'start_date', 'end_date', 'bonus_amount', 'bonus_scale',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'bonus_amount' => 'decimal:2',
        'bonus_scale'  => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(TalentoColaborador::class, 'lead_colaborador_id');
    }

    public function activities()
    {
        return $this->hasMany(TalentoProjectActivity::class, 'project_id');
    }

    /**
     * Compute bonus amount for a given progress percentage using bonus_scale.
     * bonus_scale: [{threshold_pct: 80, amount: 300}, {threshold_pct: 100, amount: 500}]
     * Returns the highest matching bracket amount, or flat bonus_amount if no scale.
     */
    public function computeBonus(float $pct): float
    {
        $scale = $this->bonus_scale;
        if (!$scale || !is_array($scale)) {
            return (float)($this->bonus_amount ?? 0);
        }

        // Sort descending by threshold; pick first where pct >= threshold
        usort($scale, fn($a, $b) => $b['threshold_pct'] <=> $a['threshold_pct']);

        foreach ($scale as $bracket) {
            if ($pct >= (float)$bracket['threshold_pct']) {
                return (float)$bracket['amount'];
            }
        }

        return 0.0;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
