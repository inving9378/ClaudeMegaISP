<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_settings';

    protected $fillable = [
        'company_id', 'key', 'value', 'encrypted', 'group',
    ];

    protected $hidden = ['value'];

    protected $casts = [
        'encrypted' => 'boolean',
    ];

    public static function get(string $key, int $companyId = 1): ?string
    {
        $setting = static::where('company_id', $companyId)->where('key', $key)->first();
        if (!$setting) {
            return null;
        }
        if ($setting->encrypted && $setting->value) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($setting->value);
            } catch (\Throwable $e) {
                return $setting->value;
            }
        }
        return $setting->value;
    }
}
