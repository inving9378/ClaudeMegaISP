<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

/**
 * Splitter como objeto de primera clase (MR-13, item roadmap #949).
 *
 * Reemplaza el splitter-como-texto: cada fila conoce su tipo (catálogo, ratio + pérdida
 * balanceada), su nivel (1 o 2), su contenedor físico (NAP/mufa/rack — hoy todos
 * `MapaRedDevice`) y, si es nivel 2, el puerto de salida del splitter padre (nivel 1) que
 * alimenta su entrada — así queda modelada la cascada 1:8 → 1:8 que pide el DoD sin depender de
 * la entidad `empalme` de MR-12 (todavía en construcción).
 *
 * Al crearse, genera solos sus propios puertos (1 entrada + N salidas según `tipo.numero_puertos`)
 * vía `MapaRedPuerto::generarParaSplitter()`.
 */
class MapaRedSplitter extends Model
{
    public const NIVEL_1 = 1;
    public const NIVEL_2 = 2;

    protected $table = 'mapared_splitters';

    protected $fillable = [
        'tipo_splitter_id',
        'nivel',
        'device_id',
        'puerto_entrada_padre_id',
        'perdida_db',
        'etiqueta',
    ];

    protected $casts = [
        'nivel' => 'integer',
        'perdida_db' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $splitter) {
            $splitter->validarCascada();
        });

        static::created(function (self $splitter) {
            $splitter->generarPuertos();
        });
    }

    public function tipo()
    {
        return $this->belongsTo(MapaRedTipoSplitter::class, 'tipo_splitter_id');
    }

    public function device()
    {
        return $this->belongsTo(MapaRedDevice::class, 'device_id');
    }

    public function puertoEntradaPadre()
    {
        return $this->belongsTo(MapaRedPuerto::class, 'puerto_entrada_padre_id');
    }

    public function puertos(): MorphMany
    {
        return $this->morphMany(MapaRedPuerto::class, 'puertable', 'puertable_type', 'puertable_id');
    }

    /**
     * Splitter nivel 1 del que este nivel 2 desciende, derivado de `puerto_entrada_padre_id`
     * (sin columna redundante `splitter_padre_id`).
     */
    public function splitterPadre(): ?self
    {
        $puerto = $this->puertoEntradaPadre;

        if (! $puerto || ! ($puerto->puertable instanceof self)) {
            return null;
        }

        return $puerto->puertable;
    }

    /**
     * Pérdida a usar en el presupuesto óptico (MR-18): la propia si fue sobreescrita, si no la
     * del catálogo.
     */
    public function getPerdidaEfectivaDbAttribute(): float
    {
        return $this->perdida_db ?? (float) $this->tipo->perdida_db;
    }

    /**
     * Valida la cascada nivel 1 → nivel 2 (DoD #949): nivel 1 no cuelga de ningún padre; nivel 2
     * exige un puerto padre que sea salida de un splitter nivel 1.
     */
    protected function validarCascada(): void
    {
        if (! in_array($this->nivel, [self::NIVEL_1, self::NIVEL_2], true)) {
            throw new InvalidArgumentException('mapared_splitters.nivel solo admite 1 o 2 (cascada soportada por MR-13).');
        }

        if ($this->nivel === self::NIVEL_1) {
            if ($this->puerto_entrada_padre_id) {
                throw new InvalidArgumentException('Un splitter nivel 1 no puede tener puerto_entrada_padre_id (no cuelga de otro splitter).');
            }

            return;
        }

        // Nivel 2: debe colgar de un puerto de salida de un splitter nivel 1.
        if (! $this->puerto_entrada_padre_id) {
            throw new InvalidArgumentException('Un splitter nivel 2 requiere puerto_entrada_padre_id (de qué splitter nivel 1 desciende).');
        }

        $puertoPadre = MapaRedPuerto::find($this->puerto_entrada_padre_id);

        if (! $puertoPadre || $puertoPadre->rol !== MapaRedPuerto::ROL_SPLITTER_OUT) {
            throw new InvalidArgumentException('puerto_entrada_padre_id debe ser un puerto de salida (splitter_out) de un splitter.');
        }

        if (! ($puertoPadre->puertable instanceof self) || $puertoPadre->puertable->nivel !== self::NIVEL_1) {
            throw new InvalidArgumentException('La cascada solo se soporta nivel 1 → nivel 2: el puerto padre debe pertenecer a un splitter nivel 1.');
        }
    }

    /**
     * Genera 1 puerto de entrada + N de salida según el ratio del catálogo (idempotente: no
     * duplica si el splitter ya tiene puertos).
     */
    public function generarPuertos(): array
    {
        if (MapaRedPuerto::query()->delDueno(self::class, $this->id)->exists()) {
            return MapaRedPuerto::query()->delDueno(self::class, $this->id)->get()->all();
        }

        return MapaRedPuerto::generarParaSplitter($this, (int) $this->tipo->numero_puertos);
    }
}
