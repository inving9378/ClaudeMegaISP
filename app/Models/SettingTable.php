<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingTable extends BaseModel
{
    use HasFactory;

    protected $table = 'setting_tables';

    protected $fillable = [
        'user_id',
        'table_id',
        'columns'
    ];

    protected $casts = [
        'columns' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
