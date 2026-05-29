<?php

namespace App\Models\Core;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class ApiIntegration extends BaseModel
{
    use SoftDeletes;

    protected $table = 'api_integrations';

    protected $fillable = [
        'company_id', 'provider', 'slug', 'name',
        'encrypted_value', 'key_preview', 'key_fingerprint',
        'config', 'active', 'is_default_for_provider',
        'last_validation_status', 'last_validated_at',
    ];

    protected $hidden = ['encrypted_value'];

    protected $casts = [
        'config'                  => 'array',
        'active'                  => 'boolean',
        'is_default_for_provider' => 'boolean',
        'last_validated_at'       => 'datetime',
    ];

    // ── Accessors / Mutators ────────────────────────────────────────────────────

    public function getValueAttribute(): ?string
    {
        if (!$this->encrypted_value) {
            return null;
        }
        try {
            return Crypt::decryptString($this->encrypted_value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setValueAttribute(string $plainKey): void
    {
        $this->attributes['encrypted_value'] = Crypt::encryptString($plainKey);
        $this->attributes['key_preview']     = $this->buildPreview($plainKey);
        $this->attributes['key_fingerprint'] = hash('sha256', $plainKey);
    }

    // ── Relationships ───────────────────────────────────────────────────────────

    public function logs()
    {
        return $this->hasMany(ApiIntegrationLog::class, 'integration_id');
    }

    public function usage()
    {
        return $this->hasMany(ApiIntegrationUsage::class, 'integration_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────────

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeDefaultFor($query, string $provider)
    {
        return $query->where('provider', $provider)
                     ->where('is_default_for_provider', true)
                     ->where('active', true);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function buildPreview(string $key): string
    {
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($key, 0, 8) . '...' . substr($key, -4);
    }
}
