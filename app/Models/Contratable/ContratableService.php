<?php

namespace App\Models\Contratable;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de un servicio contratable (Flotas, MegaFamilia…).
 *
 * El precio NO vive aquí: vive en los paquetes ({@see ContratablePackage}). El IVA
 * (aplica_iva / iva_porcentaje) nace aquí para que en Fase 2 la suscripción exponga
 * getTax()/serviceHasIva() sin nuevas migraciones.
 */
class ContratableService extends BaseModel
{
    use SoftDeletes;

    /** Métricas de conteo de unidades soportadas. */
    public const METRICA_VEHICULOS    = 'vehiculos';    // Flotas
    public const METRICA_DISPOSITIVOS = 'dispositivos'; // MegaFamilia

    protected $table = 'contratable_services';

    protected $fillable = [
        'module_key', 'nombre', 'descripcion', 'metrica',
        'activo', 'meses_prueba', 'aplica_iva', 'iva_porcentaje',
    ];

    protected $casts = [
        'activo'         => 'boolean',
        'aplica_iva'     => 'boolean',
        'meses_prueba'   => 'integer',
        'iva_porcentaje' => 'decimal:2',
    ];

    public function packages(): HasMany
    {
        return $this->hasMany(ContratablePackage::class, 'contratable_service_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ClientContratableSubscription::class, 'contratable_service_id');
    }

    /**
     * Dado un conteo de unidades (vehículos/dispositivos), devuelve el paquete cuyo
     * rango lo cubre. Base del auto-ajuste "sin tope":
     *  - match directo si rango_min <= n <= rango_max (rango_max NULL = abierto).
     *  - si n excede TODOS los rangos finitos, cae al paquete superior (mayor rango_min).
     *  - si n queda por debajo del catálogo (hueco inferior), devuelve null.
     *
     * NO conecta a facturación: solo resuelve el paquete (Fase 2 lo usará para el precio).
     */
    public function packageForQuantity(int $cantidad): ?ContratablePackage
    {
        $packages = $this->packages()->orderBy('rango_min')->get();

        foreach ($packages as $package) {
            $dentroDelMin = $cantidad >= $package->rango_min;
            $dentroDelMax = $package->rango_max === null || $cantidad <= $package->rango_max;
            if ($dentroDelMin && $dentroDelMax) {
                return $package;
            }
        }

        // "Sin tope": si excede todos los rangos finitos, absorbe el paquete superior.
        $superior = $packages->last(); // mayor rango_min (orden asc)
        if ($superior && $cantidad > $superior->rango_min) {
            return $superior;
        }

        return null;
    }
}
