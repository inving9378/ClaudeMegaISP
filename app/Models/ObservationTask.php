<?php

namespace App\Models;

use App\Services\FormatDateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObservationTask extends Model
{
    use HasFactory;
    protected $appends = ['name_created_by', 'date_created'];
    protected $fillable = ['observation', 'task_id', 'created_by'];


    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getNameCreatedByAttribute()
    {
        return $this->user->name ?? "";
    }

    public function getDateCreatedAttribute()
    {
        $formatDateService = new FormatDateService($this->created_at);
        return $formatDateService->formatDate();
    }
}
