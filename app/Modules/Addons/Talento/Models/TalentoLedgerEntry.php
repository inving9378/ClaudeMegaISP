<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Immutable ledger. Never update or soft-delete entries.
 * To reverse: insert a counter-entry with opposite type.
 */
class TalentoLedgerEntry extends Model
{
    protected $table = 'talento_ledger_entries';
    public $timestamps = false;

    protected $fillable = [
        'colaborador_id', 'type', 'concept', 'amount',
        'reference_type', 'reference_id',
        'period_start', 'period_end', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'period_start' => 'date',
        'period_end'   => 'date',
        'created_at'   => 'datetime',
    ];

    /**
     * Llave de identidad de un movimiento (UNI-T J1): colaborador + concepto + origen (tipo/id) + periodo.
     * Incentivos legítimos distintos difieren al menos en una de estas columnas.
     */
    public const LLAVE = ['colaborador_id', 'concept', 'reference_type', 'reference_id', 'period_start', 'period_end'];

    /**
     * Inserta un movimiento. Con el interruptor `unificacion.interruptores.ledger_identidad_unica`
     * apagado (default) equivale a create(). Encendido, es idempotente por LLAVE: un reintento, una
     * doble validación o una carrera devuelven el movimiento ya existente en vez de duplicarlo.
     * Movimientos sin origen (reference_id null) no tienen identidad y siempre se insertan.
     */
    public static function registrar(array $attrs): self
    {
        if (! config('unificacion.interruptores.ledger_identidad_unica') || empty($attrs['reference_id'])) {
            return static::create($attrs);
        }

        $llave = [];
        foreach (self::LLAVE as $k) {
            $v = $attrs[$k] ?? null;
            $llave[$k] = ($v instanceof \DateTimeInterface) ? $v->format('Y-m-d') : $v;
        }

        try {
            return static::where($llave)->first() ?? static::create($attrs);
        } catch (QueryException $e) {
            // 1062 = el índice único ganó la carrera: el movimiento ya existe.
            if (($e->errorInfo[1] ?? null) === 1062 && ($existente = static::where($llave)->first())) {
                return $existente;
            }
            throw $e;
        }
    }

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class);
    }

    public function reference()
    {
        return $this->morphTo('reference');
    }
}
