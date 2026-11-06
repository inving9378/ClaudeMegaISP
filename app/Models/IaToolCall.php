<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Auditoría de tool-calls propuestos/ejecutados por el Chat IA (item #171,
 * #9 Fase 2). Model plano (sin BaseModel): solo created_at, tabla append-only.
 *
 * Sin consumidores todavía — se llena cuando exista el compilador de tools +
 * el flujo de confirmación humana (Fase 2b).
 */
class IaToolCall extends Model
{
    public $timestamps = false;

    protected $table = 'ia_tool_calls';

    protected $fillable = [
        'user_id', 'prompt', 'tool', 'arguments', 'status', 'result', 'error', 'created_at',
    ];

    protected $casts = [
        'arguments'  => 'array',
        'result'     => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
