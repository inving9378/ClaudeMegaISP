<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;
use App\Models\User;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IAProyecto extends BaseModel
{
    protected $table = 'ia_proyectos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'color',
        'es_default',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'es_default' => 'boolean',
    ];

    public function conversaciones(): HasMany
    {
        return $this->hasMany(IAConversacion::class, 'ia_proyecto_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
