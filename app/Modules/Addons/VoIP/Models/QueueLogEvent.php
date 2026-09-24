<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un evento crudo de `queue_log` ya importado. Ver la migración para el
 * porqué de cada campo — el vocabulario de `event` es el de Asterisk
 * (ENTERQUEUE, CONNECT, COMPLETEAGENT, COMPLETECALLER, ABANDON,
 * RINGNOANSWER, RINGCANCELED, ADDMEMBER, REMOVEMEMBER, PAUSE, UNPAUSE,
 * EXITWITHTIMEOUT, EXITEMPTY, TRANSFER, QUEUESTART…), no uno propio.
 */
class QueueLogEvent extends Model
{
    protected $table = 'voip_queue_log';

    public $timestamps = false;

    protected $casts = [
        'ts' => 'datetime',
    ];
}
