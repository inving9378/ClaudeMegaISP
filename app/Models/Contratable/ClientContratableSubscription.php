<?php

namespace App\Models\Contratable;

use App\Models\BaseModel;
use App\Models\Interface\ServiceInterface;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Core\Clientes\Models\Client;
use App\Services\Contratable\ContratableTrialService;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Suscripción de un cliente a un servicio contratable (la "instancia").
 *
 * Aislamiento por cliente con el patrón estándar #1: tenantColumn = client_id,
 * fail-closed (sin cliente resuelto → cero resultados; NUNCA $allowNullTenant en
 * módulos de cliente).
 *
 * Implementa {@see ServiceInterface} para fluir como un "servicio del cliente" más en
 * el motor de facturación. El motor (calculateAmounts) solo lee 5 miembros:
 * `service_name`, `price_service`, `getTax()`, `serviceHasIva()`, `id`/`get_class()`.
 * El resto del interface se implementa como STUBS seguros (no se usan en este flujo).
 *
 * IMPORTANTE: `price_service` puede consultar la BD (conteo + prueba) pero NO escribe
 * nada — `calculateAmounts` debe seguir PURA.
 */
class ClientContratableSubscription extends BaseModel implements ServiceInterface
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

    // ───────────────────────── Conteo de unidades ─────────────────────────

    /**
     * Conteo REAL de unidades del cliente según la métrica del servicio, usando el
     * scope de aislamiento de cada módulo (mismo patrón que Flotas/MegaFamilia hoy).
     * Base del auto-ajuste de paquete. Solo lectura.
     */
    public function conteoActual(): int
    {
        $clientId = (int) $this->client_id;

        return match ($this->service->metrica ?? null) {
            ContratableService::METRICA_VEHICULOS    => FleetVehicle::forClient($clientId)->count(),
            ContratableService::METRICA_DISPOSITIVOS => ParentalDevice::forClient($clientId)->count(),
            default                                  => 0,
        };
    }

    // ──────────────── Interface que LEE el motor (5 miembros) ────────────────

    /** service_name → nombre del servicio del catálogo. */
    public function getServiceNameAttribute(): string
    {
        return (string) ($this->service->nombre ?? '');
    }

    /**
     * price_service → precio del paquete que cubre el conteo actual; 0 si está en
     * prueba; NULL si el conteo no resuelve paquete (el inyector lo salta + loguea).
     * IVA INCLUIDO en el precio del paquete (consistente con el resto del sistema).
     */
    public function getPriceServiceAttribute(): ?float
    {
        $package = $this->service?->packageForQuantity($this->conteoActual());
        if (! $package) {
            return null; // sin paquete → no facturable (lo decide el inyector, Paso 3)
        }

        if (app(ContratableTrialService::class)->isInTrial($this)) {
            return 0.0;
        }

        return (float) $package->precio;
    }

    /** Tasa de IVA del servicio. */
    public function getTax()
    {
        return $this->service->iva_porcentaje ?? 0;
    }

    /** ¿El precio lleva IVA? (equivale a custom.tax_include). */
    public function serviceHasIva(): bool
    {
        return (bool) ($this->service->aplica_iva ?? false);
    }

    // ──────── Resto de ServiceInterface: STUBS seguros (no usados aquí) ────────
    // calculateAmounts NO invoca estos métodos. Existen solo para cumplir el contrato.

    public function getClientWithBalanceAndBillingConfiguration()
    {
        return $this->client; // no usado en el flujo de contratables
    }

    public function haveTransaction(): bool
    {
        return false; // los contratables no participan del flujo de transacciones legacy
    }

    public function getPlanRelation()
    {
        return null; // no aplica: el "plan" aquí son los paquetes por rango
    }

    public function getService()
    {
        return $this->service; // el catálogo contratable
    }

    public function getRepository()
    {
        return null; // no aplica
    }

    public function getInstalationCostAttribute()
    {
        return 0; // los contratables no tienen costo de instalación
    }

    public function getHasActiveInstalationCostAttribute()
    {
        return false;
    }
}
