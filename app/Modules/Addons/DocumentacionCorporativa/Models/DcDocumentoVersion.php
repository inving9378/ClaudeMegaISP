<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una versión histórica de un documento. INMUTABLE.
 *
 * Se crea y se lee, nunca se actualiza ni se borra. Subir sobre un concepto no
 * sobrescribe: agrega una versión y la anterior queda intacta.
 */
class DcDocumentoVersion extends Model
{
    protected $table = 'dc_documento_versiones';

    /** Append-only: sólo se sella la creación. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id', 'documento_id', 'version', 'archivo_uuid',
        'archivo_nombre_original', 'mime', 'bytes', 'hash', 'subido_por', 'nota_cambio',
    ];

    protected $casts = [
        'version'    => 'integer',
        'bytes'      => 'integer',
        'created_at' => 'datetime',
    ];

    public function documento()
    {
        return $this->belongsTo(DcDocumento::class, 'documento_id');
    }
}
