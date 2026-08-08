<?php

namespace App\Modules\Addons\Flotas\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una lectura por IA de un documento de vehículo (item #580).
 *
 * Model plano a propósito (NO BaseModel): es una bitácora append-only de alto volumen potencial,
 * sin `updated_at` ni activity log — mismo criterio que FleetPosition / FleetDeviceEvent.
 */
class FleetDocumentOcrRun extends Model
{
    protected $table = 'fleet_document_ocr_runs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id', 'vehicle_id', 'user_id', 'ok', 'needs_review', 'mime', 'bytes',
        'file_hash', 'fields', 'unreadable', 'error', 'provider', 'model', 'raw',
    ];

    protected $casts = [
        'ok'           => 'boolean',
        'needs_review' => 'boolean',
        'fields'       => 'array',
        'unreadable'   => 'array',
        'created_at'   => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(FleetDocument::class, 'document_id');
    }

    /** Estado que se copia a `fleet_documents.ocr_status` al guardar el documento. */
    public function estadoParaDocumento(): string
    {
        if (! $this->ok) {
            return 'fallido';
        }

        return $this->needs_review ? 'baja_confianza' : 'ok';
    }
}
