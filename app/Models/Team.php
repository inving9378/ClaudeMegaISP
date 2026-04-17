<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends BaseModel
{
    use HasFactory;

    protected $fillable = ['name','color'];

    const MULTIPLE_RELATIONS = [
        'users' => 'users',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'team_user', 'team_id', 'user_id');
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value !== 'action') {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        }
        return $query;
    }
}
