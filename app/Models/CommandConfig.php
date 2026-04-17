<?php

namespace App\Models;

use App\Http\Traits\Models\Settings\Commandconfig\ScopeCommandConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommandConfig extends BaseModel
{
    use HasFactory, ScopeCommandConfig;
    protected $fillable = [
        'command', 'process_name', 'frequency_id', 'execution_time', 'command_description', 'status'
    ];





}
