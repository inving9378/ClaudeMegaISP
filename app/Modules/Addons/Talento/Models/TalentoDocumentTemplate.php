<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentoDocumentTemplate extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_document_templates';

    protected $fillable = [
        'name', 'category', 'description', 'current_version_id', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function versions()
    {
        return $this->hasMany(TalentoDocumentTemplateVersion::class, 'template_id')->orderByDesc('version_number');
    }

    public function currentVersion()
    {
        return $this->belongsTo(TalentoDocumentTemplateVersion::class, 'current_version_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
