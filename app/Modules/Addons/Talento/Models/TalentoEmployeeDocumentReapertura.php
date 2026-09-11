<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot inmutable de un talento_employee_documents justo antes de reabrirse por una version
 * nueva "mayor" del acuse (ver AcuseReopeningService). Append-only: nunca se actualiza ni se
 * borra una fila de aqui.
 */
class TalentoEmployeeDocumentReapertura extends Model
{
    protected $table = 'talento_employee_document_reaperturas';
    public $timestamps = false;

    protected $fillable = [
        'employee_document_id', 'template_version_id_anterior', 'rendered_html_anterior',
        'status_anterior', 'signed_at_anterior', 'signed_by_anterior', 'signature_method_anterior',
        'signature_path_anterior', 'firmas_slots_anterior', 'motivo', 'created_at',
    ];

    protected $casts = [
        'signed_at_anterior' => 'datetime',
        'firmas_slots_anterior' => 'array',
        'created_at' => 'datetime',
    ];

    public function employeeDocument()
    {
        return $this->belongsTo(TalentoEmployeeDocument::class, 'employee_document_id');
    }
}
