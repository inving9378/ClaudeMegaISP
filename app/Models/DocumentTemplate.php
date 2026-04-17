<?php

namespace App\Models;

use App\Http\Traits\Models\DocumentTemplate\scope\ScopeDocumentTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends BaseModel
{
    use HasFactory;
    use SoftDeletes;
    use ScopeDocumentTemplate;
    protected $table = 'document_templates';
    protected $fillable = [
        'name',
        'html',
        'type',
        'created_by'
    ];


    public function type()
    {
        return $this->hasOne(DocumentTypeTemplate::class, 'id', 'type');
    }

    public function type_template()
    {
        return $this->hasOne(DocumentTypeTemplate::class, 'id', 'type');
    }
}
