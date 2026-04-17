<?php

namespace App\Models;

use App\Http\Traits\Models\Ticket\TicketTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\FileController;

class TicketThread extends BaseModel
{
    use HasFactory, TicketTrait;

    protected $guarded = [];
    protected $appends = ['time_human', 'edited_name'];

    const FILE_FIELDS = [
        'file' => 'file'
    ];

    public function file()
    {
        return $this->morphOne(File::class, 'fileable');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function ticket_thread()
    {
        return $this->hasMany(TicketThread::class);
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->toFormattedDateString();
    }

    public function getEditedNameAttribute($value)
    {
        $userId = $this->edited_by;
        $userName = User::find($userId);
        return $userName->name;
    }

    public function getTimeHumanAttribute()
    {
        return $this->ticketTimeHuman($this->created_at);
    }

    public function uploadFile($file)
    {
        if ($file) {
            $file_process = new FileController;
            $module_path = 'ticket/' . $this->ticket->id . '/ticket_thread';
            $properties = $file_process->processSingleFileAndReturnProperties($file, $module_path, $this->id);

            $this->file()->create($properties);
            $file->storeAs('/public/' . $module_path . '/' . $this->id, $properties['name']);
        }
        return $this;
    }

    public function updateFileUpload($file)
    {
        if ($file) {
            $this->file()->first()->delete();

            $file_process = new FileController;
            $module_path = 'ticket/' . $this->ticket->id . '/ticket_thread';
            $properties = $file_process->processSingleFileAndReturnProperties($file, $module_path, $this->id);

            $this->file()->create($properties);
            $file->storeAs('/public/' . $module_path . '/' . $this->id, $properties['name']);
        }

        return $this;
    }
}
