<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;
use App\Models\User;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IAPromptUsuario extends BaseModel
{
    use SoftDeletes;

    protected $table = 'ia_prompts_usuario';

    protected $fillable = [
        'user_id',
        'titulo',
        'contenido',
        'categoria',
        'es_publico',
        'usos',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'es_publico' => 'boolean',
        'usos' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDelUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePublicos($query)
    {
        return $query->where('es_publico', true);
    }

    public function incrementarUso(): void
    {
        $this->increment('usos');
    }
}
