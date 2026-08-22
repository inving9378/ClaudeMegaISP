<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cursor de procesamiento incremental del minero de bitácora (item #1016).
 */
class AuditoriaMineroCursor extends Model
{
    protected $table = 'auditoria_minero_cursores';
    public $timestamps = false;
    protected $guarded = [];
}
