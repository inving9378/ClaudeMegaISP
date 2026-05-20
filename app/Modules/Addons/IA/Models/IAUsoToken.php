<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;
use App\Models\User;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IAUsoToken extends BaseModel
{
    public $timestamps = false;

    protected $table = 'ia_uso_tokens';

    protected $fillable = [
        'user_id',
        'ia_conversacion_id',
        'ia_mensaje_id',
        'ia_proveedor_id',
        'proveedor',
        'modelo',
        'tokens_input',
        'tokens_output',
        'tokens_total',
        'costo_estimado',
        'fecha',
        'origen',
        'created_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'created_at' => 'datetime',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'tokens_total' => 'integer',
        'costo_estimado' => 'decimal:6',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(IAConversacion::class, 'ia_conversacion_id');
    }

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(IAMensaje::class, 'ia_mensaje_id');
    }

    public function proveedorRel(): BelongsTo
    {
        return $this->belongsTo(IAProveedor::class, 'ia_proveedor_id');
    }
}
