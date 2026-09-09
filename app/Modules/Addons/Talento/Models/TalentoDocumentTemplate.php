<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentoDocumentTemplate extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_document_templates';

    protected $fillable = [
        'name', 'category', 'description', 'current_version_id', 'active', 'requires_signature',
        'fillable_fields',
    ];

    protected $casts = [
        'active' => 'boolean',
        'requires_signature' => 'boolean',
        'fillable_fields' => 'array',
    ];

    public function versions()
    {
        return $this->hasMany(TalentoDocumentTemplateVersion::class, 'template_id')->orderByDesc('version_number');
    }

    public function currentVersion()
    {
        return $this->belongsTo(TalentoDocumentTemplateVersion::class, 'current_version_id');
    }

    public function signatureSlots()
    {
        return $this->hasMany(TalentoDocumentTemplateSignatureSlot::class, 'template_id')->orderBy('orden');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
