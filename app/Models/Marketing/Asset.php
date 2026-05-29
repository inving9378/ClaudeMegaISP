<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_assets';

    protected $fillable = [
        'company_id', 'type', 'category', 'name', 'path', 'url', 'mime_type',
        'size_bytes', 'duration_seconds', 'width', 'height', 'tags', 'license',
        'source', 'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'duration_seconds' => 'decimal:2',
    ];
}
