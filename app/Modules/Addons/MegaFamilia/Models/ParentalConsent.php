<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;

class ParentalConsent extends BaseModel
{
    protected $table = 'parental_consents';

    protected $fillable = [
        'version_number', 'content', 'notes', 'is_draft', 'require_reacceptance', 'published_at',
    ];

    protected $casts = [
        'version_number'      => 'integer',
        'is_draft'            => 'boolean',
        'require_reacceptance'=> 'boolean',
        'published_at'        => 'datetime',
    ];
}
