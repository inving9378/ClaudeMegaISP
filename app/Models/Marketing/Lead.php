<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_leads';

    protected $fillable = [
        'company_id', 'source_id', 'lead_form_id', 'full_name', 'email', 'phone', 'whatsapp',
        'address', 'neighborhood', 'city', 'state', 'postal_code', 'lat', 'lng',
        'plan_interested_id', 'notes', 'raw_payload', 'score', 'score_reason',
        'tags', 'status', 'assigned_user_id', 'captured_at', 'last_activity_at',
        'converted_at', 'lost_reason',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'tags' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'captured_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function leadForm(): BelongsTo
    {
        return $this->belongsTo(LeadForm::class, 'lead_form_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(Attribution::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function pipelines(): BelongsToMany
    {
        return $this->belongsToMany(Pipeline::class, 'marketing_lead_pipeline')
            ->withPivot(['stage_id', 'position', 'entered_stage_at', 'exited_stage_at'])
            ->withTimestamps();
    }
}
