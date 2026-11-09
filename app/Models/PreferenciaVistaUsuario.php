<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Preferencia de vista de un usuario (ver PreferenciaVistaController y config/preferencias_vista.php).
 */
class PreferenciaVistaUsuario extends Model
{
    protected $table = 'preferencias_vista_usuario';

    protected $fillable = [
        'user_id',
        'vista',
        'preferencia',
        'valor',
    ];

    protected $casts = [
        'valor' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
