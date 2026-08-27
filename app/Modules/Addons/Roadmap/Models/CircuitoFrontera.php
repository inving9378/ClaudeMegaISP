<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una categoría de frontera dura (dinero · credenciales · produccion · borrar_datos · …).
 *
 * No se lee directo desde los consumidores: se lee por `FronterasService`, que la cachea y la
 * invalida al guardar. Leerla a pelo desde varios sitios reabriría el problema que la tabla viene
 * a cerrar — la lista repartida en copias que envejecen por separado.
 */
class CircuitoFrontera extends Model
{
    protected $table = 'circuito_fronteras';

    /**
     * Qué hace la categoría cuando dispara. De más duro a más suave — el orden importa para
     * decidir si un cambio AFLOJA (se audita como warning) o ENDURECE.
     */
    public const EFECTOS = ['bloquear', 'bandeja', 'avisar'];

    /** Texto que la pantalla muestra junto a cada efecto. Vive aquí para no duplicarlo en Vue. */
    public const EFECTO_DESCRIPCION = [
        'bloquear' => 'Retiene el item para ti Y lo saca del pool automático al nacer: no vuelve a la cola hasta que tú lo sueltes.',
        'bandeja'  => 'Retiene el item para ti (requiere_irving). Es lo que hace hoy.',
        'avisar'   => 'NO retiene nada: el item sigue su curso y la detección sólo queda registrada y contada.',
    ];

    protected $fillable = ['categoria', 'activa', 'efecto', 'orden'];

    protected $casts = [
        'activa' => 'boolean',
        'orden'  => 'integer',
    ];

    public function terminos(): HasMany
    {
        return $this->hasMany(CircuitoFronteraTermino::class, 'categoria', 'categoria');
    }
}
