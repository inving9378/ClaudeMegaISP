<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_channels';

    protected $fillable = [
        'company_id', 'code', 'name', 'active', 'config', 'order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'config' => 'array',
    ];

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function aiAgentConfigs(): HasMany
    {
        return $this->hasMany(AiAgentConfig::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(Attribution::class);
    }
}
