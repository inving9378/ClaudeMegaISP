<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoDocumentTemplateSignatureSlot extends Model
{
    protected $table = 'talento_document_template_signature_slots';

    protected $fillable = [
        'template_id', 'key', 'label', 'firmante_tipo', 'orden', 'requerido',
    ];

    protected $casts = [
        'requerido' => 'boolean',
        'orden' => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(TalentoDocumentTemplate::class, 'template_id');
    }
}
