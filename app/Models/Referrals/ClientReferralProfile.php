<?php

namespace App\Models\Referrals;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientReferralProfile extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ClientReferralProfileFactory::new();
    }

    protected $table = 'client_referral_profiles';

    protected $fillable = [
        'client_id',
        'referral_code',
        'referral_link',
        'plan_type',
        'activated_at',
        'threshold_amount_paid',
        'threshold_covered_at',
        'is_eligible',
        'total_commissions_earned',
        'total_rewards_earned',
        'total_referrals',
    ];

    protected $casts = [
        'activated_at'             => 'datetime',
        'threshold_covered_at'     => 'datetime',
        'is_eligible'              => 'boolean',
        'threshold_amount_paid'    => 'decimal:2',
        'total_commissions_earned' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function referredClients(): HasMany
    {
        return $this->hasMany(Referral::class, 'embajador_id', 'client_id');
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(ReferralProspect::class, 'embajador_id', 'client_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'embajador_id', 'client_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'beneficiary_id', 'client_id');
    }

    /**
     * Genera código único tipo MGN-IRV-A4F2.
     * Toma las 3 primeras letras del nombre del cliente como parte identificable.
     */
    public static function generateUniqueCode(Client $client): string
    {
        $prefix     = 'MGN';
        $name       = $client->clientGetName() ?? ($client->client_name ?? '');
        $clientPart = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        $clientPart = str_pad($clientPart, 3, 'X');

        do {
            $random = strtoupper(substr(md5(uniqid()), 0, 4));
            $code   = "{$prefix}-{$clientPart}-{$random}";
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Retorna true si el cliente puede activar el programa
     * (cubrió umbral propio, pero aún no eligió plan).
     */
    public function canActivate(): bool
    {
        return $this->is_eligible && is_null($this->plan_type);
    }

    /**
     * Resuelve el tier del servicio de internet activo del cliente embajador.
     * Velocidad en Kbps (campo download_speed) → convertida a Mbps para el tier.
     */
    public function resolveEmbajadorTier(): ?ReferralCommissionTier
    {
        $speedMbps = $this->getEmbajadorSpeedMbps();
        return ReferralCommissionTier::resolveForSpeedMbps($speedMbps);
    }

    public function getEmbajadorSpeedMbps(): float
    {
        $service = $this->client
            ?->internet_service()
            ->whereNotIn('estado', ['Cancelado', 'Baja', 'Desactivado'])
            ->with('internet')
            ->orderBy('id', 'desc')
            ->first();

        return ($service?->internet?->download_speed ?? 0) / 1024;
    }

    public function getEmbajadorServicePrice(): float
    {
        $service = $this->client
            ?->internet_service()
            ->whereNotIn('estado', ['Cancelado', 'Baja', 'Desactivado'])
            ->orderBy('id', 'desc')
            ->first();

        return (float) ($service?->price ?? 0);
    }
}
