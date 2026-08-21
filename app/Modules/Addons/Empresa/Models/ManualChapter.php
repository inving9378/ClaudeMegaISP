<?php

namespace App\Modules\Addons\Empresa\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualChapter extends BaseModel
{
    use SoftDeletes;

    protected $table = 'empresa_manual_chapters';

    protected $fillable = ['title', 'slug', 'order'];

    public function sections()
    {
        return $this->hasMany(ManualSection::class, 'chapter_id')->orderBy('order');
    }
}
