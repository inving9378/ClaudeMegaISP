<?php

namespace App\Modules\Addons\Empresa\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualSection extends BaseModel
{
    use SoftDeletes;

    protected $table = 'empresa_manual_sections';

    protected $fillable = ['chapter_id', 'title', 'slug', 'order', 'content', 'published_version_id'];

    public function chapter()
    {
        return $this->belongsTo(ManualChapter::class, 'chapter_id');
    }

    public function versions()
    {
        return $this->hasMany(ManualSectionVersion::class, 'section_id')->orderByDesc('version_number');
    }

    public function publishedVersion()
    {
        return $this->belongsTo(ManualSectionVersion::class, 'published_version_id');
    }

    public function hasUnpublishedChanges(): bool
    {
        if (! $this->published_version_id) {
            return true;
        }

        return $this->content !== optional($this->publishedVersion)->content;
    }
}
