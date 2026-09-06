<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MR-12 (item roadmap #948) — empalme como entidad de primera clase: une "hilo A"
 * (`mapared_hilos` de MR-11) con un segundo extremo polimórfico que puede ser otro hilo o un
 * puerto de splitter (`mapared_puertos` de MR-10).
 *
 * `HILO_CLASS` es un string, no un `::class` importado: MR-11 (`MapaRedHilo`) se construye en
 * paralelo y puede no existir todavía en este checkout. `belongsTo()`/`morphTo()` solo resuelven
 * la clase en tiempo de ejecución (al cargar la relación), así que declarar el nombre no requiere
 * que el archivo exista hoy — únicamente al primer query real sobre `mapared_hilos`.
 */
class MapaRedEmpalme extends Model
{
    use SoftDeletes;

    public const HILO_CLASS = 'App\Modules\Addons\MapaRed\Models\MapaRedHilo';

    public const TIPO_FUSION = 'fusion';
    public const TIPO_MECANICO = 'mecanico';
    public const TIPO_CONECTORIZADO = 'conectorizado';

    /**
     * Pérdida en dB por defecto según tipo de empalme (catálogo simple, editable por empalme
     * vía el campo `perdida_db` — DoD #948: "default del catálogo, editable por empalme").
     */
    public const PERDIDA_DB_DEFAULT = [
        self::TIPO_FUSION => 0.10,
        self::TIPO_MECANICO => 0.30,
        self::TIPO_CONECTORIZADO => 0.50,
    ];

    protected $table = 'mapared_empalmes';

    protected $fillable = [
        'hilo_a_id',
        'extremo_b_type',
        'extremo_b_id',
        'elemento_contenedor_type',
        'elemento_contenedor_id',
        'bandeja',
        'posicion',
        'tipo',
        'perdida_db',
        'fecha',
        'tecnico_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function hiloA(): BelongsTo
    {
        return $this->belongsTo(self::HILO_CLASS, 'hilo_a_id');
    }

    public function extremoB(): MorphTo
    {
        return $this->morphTo(null, 'extremo_b_type', 'extremo_b_id');
    }

    public function elementoContenedor(): MorphTo
    {
        return $this->morphTo(null, 'elemento_contenedor_type', 'elemento_contenedor_id');
    }

    /**
     * Un hilo (sea como `hilo_a_id` o como extremo B tipo hilo) no puede estar en dos
     * empalmes ACTIVOS (no soft-deleted) a la vez — validación dura del DoD #948.
     * `$ignorarEmpalmeId` permite re-validar al editar un empalme existente sin chocar consigo mismo.
     */
    public static function hiloDisponible(int $hiloId, ?int $ignorarEmpalmeId = null): bool
    {
        $ocupadoComoA = self::query()
            ->where('hilo_a_id', $hiloId)
            ->when($ignorarEmpalmeId, fn ($q) => $q->where('id', '!=', $ignorarEmpalmeId))
            ->exists();

        $ocupadoComoB = self::query()
            ->where('extremo_b_type', self::HILO_CLASS)
            ->where('extremo_b_id', $hiloId)
            ->when($ignorarEmpalmeId, fn ($q) => $q->where('id', '!=', $ignorarEmpalmeId))
            ->exists();

        return !$ocupadoComoA && !$ocupadoComoB;
    }

    /**
     * Crea un empalme aplicando las 3 validaciones duras del DoD #948:
     * (1) ningún hilo involucrado puede estar ya en otro empalme activo,
     * (2) no se puede empalmar un hilo consigo mismo,
     * (3) el elemento contenedor debe existir.
     * Aplica la pérdida dB por defecto del catálogo si no se especifica una.
     *
     * @throws \InvalidArgumentException si alguna validación falla
     */
    public static function crear(array $datos): self
    {
        $hiloAId = (int) $datos['hilo_a_id'];
        $extremoBType = $datos['extremo_b_type'];
        $extremoBId = (int) $datos['extremo_b_id'];

        if ($extremoBType === self::HILO_CLASS && $hiloAId === $extremoBId) {
            throw new \InvalidArgumentException('No se puede empalmar un hilo consigo mismo.');
        }

        if (!self::hiloDisponible($hiloAId)) {
            throw new \InvalidArgumentException("El hilo #{$hiloAId} ya está en otro empalme activo.");
        }

        if ($extremoBType === self::HILO_CLASS && !self::hiloDisponible($extremoBId)) {
            throw new \InvalidArgumentException("El hilo #{$extremoBId} ya está en otro empalme activo.");
        }

        $contenedorType = $datos['elemento_contenedor_type'];
        $contenedorId = $datos['elemento_contenedor_id'];
        if (!$contenedorType::find($contenedorId)) {
            throw new \InvalidArgumentException("El elemento contenedor ({$contenedorType} #{$contenedorId}) no existe.");
        }

        $datos['perdida_db'] = $datos['perdida_db'] ?? (self::PERDIDA_DB_DEFAULT[$datos['tipo']] ?? null);

        return self::create($datos);
    }
}
