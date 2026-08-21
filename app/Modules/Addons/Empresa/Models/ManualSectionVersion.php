<?php

namespace App\Modules\Addons\Empresa\Models;

use Illuminate\Database\Eloquent\Model;

/** Snapshot inmutable de una sección. Sin updated_at: una versión nunca se edita, solo se crea. */
class ManualSectionVersion extends Model
{
    public $timestamps = false;

    protected $table = 'empresa_manual_section_versions';

    protected $fillable = ['section_id', 'version_number', 'content', 'is_published', 'published_at', 'created_by', 'created_at'];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function section()
    {
        return $this->belongsTo(ManualSection::class, 'section_id');
    }
}
