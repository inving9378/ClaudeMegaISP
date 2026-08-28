<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Version inmutable de una TalentoDocumentTemplate. Nunca se actualiza el `content` de una
 * fila ya creada — ver TemplateVersionService::createVersion(). Un documento generado (Hijo D)
 * referencia el id de la version vigente al momento de generarse, para reproducirla igual
 * aunque la plantilla se edite despues.
 */
class TalentoDocumentTemplateVersion extends Model
{
    protected $table = 'talento_document_template_versions';
    public $timestamps = false;

    protected $fillable = [
        'template_id', 'version_number', 'content', 'change_note', 'created_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(TalentoDocumentTemplate::class, 'template_id');
    }
}
