<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Addons\Marketing\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use SoftDeletes;
    use PerteneceAEmpresa;

    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'company_id', 'name', 'slug', 'type', 'source_id', 'external_id',
        'budget', 'spent', 'currency', 'start_date', 'end_date', 'status',
        'config', 'goals',
        // Fase 2 (Campañas 360, §2.3 de docs/marketing/fase-2-paquetes.md)
        'objetivo', 'publico', 'oferta', 'canales', 'estrategia', 'estrategia_estado',
        'estrategia_error', 'estrategia_generada_at', 'creado_por_user_id',
    ];

    protected $casts = [
        'config' => 'array',
        'goals' => 'array',
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'publico' => 'array',
        'oferta' => 'array',
        'canales' => 'array',
        'estrategia' => 'array',
        'estrategia_generada_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(Attribution::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function generatedContent(): HasMany
    {
        return $this->hasMany(GeneratedContent::class, 'source_campaign_id');
    }

    /** Piezas de texto de la Campaña 360 (guiones, copies, anuncios, WhatsApp). Fase 2. */
    public function piezas(): HasMany
    {
        return $this->hasMany(GeneratedContent::class, 'source_campaign_id')
            ->whereIn('pieza', ['reel_guion', 'video_guion', 'copy_facebook', 'copy_instagram', 'mensaje_whatsapp', 'variante_anuncio']);
    }
}
