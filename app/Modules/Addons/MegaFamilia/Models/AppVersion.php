<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;

class AppVersion extends BaseModel
{
    protected $table = 'app_versions';

    protected $fillable = [
        'platform', 'version_name', 'version_code', 'file_path', 'file_size',
        'changelog', 'is_mandatory', 'min_version_code', 'download_count',
        'active', 'released_at',
    ];

    protected $casts = [
        'version_code'     => 'integer',
        'file_size'        => 'integer',
        'min_version_code' => 'integer',
        'download_count'   => 'integer',
        'is_mandatory'     => 'boolean',
        'active'           => 'boolean',
        'released_at'      => 'datetime',
    ];
}
