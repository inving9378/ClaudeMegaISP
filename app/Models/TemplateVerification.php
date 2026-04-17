<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateVerification extends BaseModel
{
    use HasFactory;

    protected $table = 'template_verifications';

    protected $fillable = ['id', 'name', 'list'];

    
}
