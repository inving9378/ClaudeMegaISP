<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapCredential extends Model
{
    use HasFactory;

    protected $table = 'system_map_credentials';

    protected $fillable = [
        'latitude',
        'longitude',
    ];

    protected $hidden = ['api_key'];

    public function getApiKeyPreviewAttribute(): ?string
    {
        if (!$this->api_key) {
            return null;
        }
        return '••••••••••••' . substr($this->api_key, -3);
    }
}
