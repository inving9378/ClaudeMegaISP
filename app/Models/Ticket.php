<?php

namespace App\Models;

use App\Http\Traits\Models\Ticket\TicketTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;


class Ticket extends BaseModel
{
    use HasFactory, TicketTrait;

    protected $guarded = [];
    protected $appends = ['time_human'];

    const Urgente = 1;
    const Alta = 2;
    const Normal = 3;
    const Baja = 4;

    const STATUS_NEW = 'Nuevo';

    const SINGLE_RELATIONS = [
        'TicketThreads' => [
            'relation_name' => 'ticket_thread',
            'relation_field' => 'ticket_id',
            'local_relation' => 'id'
        ],
    ];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->toFormattedDateString();
    }

    public function getTimeHumanAttribute()
    {
        return $this->ticketTimeHuman($this->created_at);
    }

    public function files()
    {
        return $this->morphOne(File::class, 'fileable');
    }

    public function ticket_thread()
    {
        return $this->hasMany(TicketThread::class);
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client', 'customer_lead');
    }

    public function assign()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (collect($columns)->filter(function ($value) {
                    return $value != 'action';
                })->toArray() as $value) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }

    public function scopeStatusNew($query)
    {
        $query->where('estado', self::STATUS_NEW);
    }

    public function scopeFilteredByStatus($query, $status)
    {
        $query->where('estado', $status);
    }
}
