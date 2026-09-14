<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TalentoDocumentReminder extends Model
{
    protected $table = 'talento_document_reminders';

    protected $fillable = ['employee_document_id', 'user_id', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function employeeDocument()
    {
        return $this->belongsTo(TalentoEmployeeDocument::class, 'employee_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
