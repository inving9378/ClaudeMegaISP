<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/** Emisión INMUTABLE de un documento: sólo se inserta, jamás se actualiza ni se borra. */
class DocumentoEmision extends Model
{
    public $timestamps = false;

    protected $table = 'documento_emisiones';
    protected $guarded = ['id'];
    protected $casts = ['datos_snapshot' => 'array', 'emitido_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->uuid = $m->uuid ?: (string) Str::uuid();
        });
        static::updating(fn () => throw new LogicException('documento_emisiones es inmutable: no se actualiza.'));
        static::deleting(fn () => throw new LogicException('documento_emisiones es inmutable: no se borra.'));
    }

    /** Update/delete masivos saltan los eventos de modelo: el builder también se cierra. */
    public function newEloquentBuilder($query)
    {
        return new class($query) extends Builder {
            public function update(array $values) { throw new LogicException('documento_emisiones es inmutable.'); }
            public function delete() { throw new LogicException('documento_emisiones es inmutable.'); }
            public function forceDelete() { throw new LogicException('documento_emisiones es inmutable.'); }
        };
    }
}
