<?php

namespace App\Models\Contratable;

use App\Models\BaseModel;
use App\Modules\Core\Clientes\Models\Client;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Suscripción de un cliente a un servicio contratable (la "instancia").
 *
 * Aislamiento por cliente con el patrón estándar #1: tenantColumn = client_id,
 * fail-closed (sin cliente resuelto → cero resultados; NUNCA $allowNullTenant en
 * módulos de cliente). En Fase 2 esta clase expondrá el interface del motor de
 * facturación (service_name/price_service/getTax/serviceHasIva) — aún NO conectada.
 */
class ClientContratableSubscription extends BaseModel
{
    use BelongsToClientTenant;
    use SoftDeletes;

    /** Tenancy por cliente final, fail-closed. */
    protected string $tenantColumn = 'client_id';
    protected bool $allowNullTenant = false;

    protected $table = 'client_contratable_subscriptions';

    protected $fillable = [
        'client_id', 'contratable_service_id', 'status',
        'activated_at', 'suspended_at', 'trial_invoices_remaining',
        'activated_by', 'created_by',
    ];

    protected $casts = [
        'activated_at'             => 'datetime',
        'suspended_at'             => 'datetime',
        'trial_invoices_remaining' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ContratableService::class, 'contratable_service_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
