<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseDescription extends Model
{
    use HasFactory;
    protected $fillable = ['release_id', 'title', 'description', 'created_by', 'updated_by'];

    public function release()
    {
        return $this->belongsTo(Release::class);
    }
}
