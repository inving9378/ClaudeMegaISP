<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;

class IaBotKnowledgeBase extends Model
{
    protected $table = 'ia_bot_knowledge_base';

    protected $fillable = [
        'category', 'problem', 'keywords', 'solution_steps', 'escalate_if_fails',
    ];

    protected $casts = [
        'solution_steps'    => 'array',
        'escalate_if_fails' => 'boolean',
    ];
}
