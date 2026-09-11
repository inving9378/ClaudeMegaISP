<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoEmployeeDocumentSignature extends Model
{
    protected $table = 'talento_employee_document_signatures';

    protected $fillable = [
        'employee_document_id', 'slot_key', 'signature_path', 'signed_by', 'signed_at', 'signature_method',
        'hash_documento', 'ip', 'user_agent', 'dispositivo', 'trazos', 'geolocalizacion',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'trazos' => 'array',
        'geolocalizacion' => 'array',
    ];

    public function employeeDocument()
    {
        return $this->belongsTo(TalentoEmployeeDocument::class, 'employee_document_id');
    }
}
