<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListTemplateVerificationTask extends BaseModel
{
    use HasFactory;
    protected $table = 'list_template_verifications_tasks';
    protected $fillable = ['task_id', 'checks', 'list_template_verification_id'];
}
