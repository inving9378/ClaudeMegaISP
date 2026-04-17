<?php

namespace App\Models;

use App\Http\Traits\LogActivityCrm;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

const RELATION = [
    'send_email_notification' => 'textEmailNotification',
    'create_crm' => 'textCreateCrm',
    'updated_crm' => 'textUpdatedCrm'
];

class LogActivity extends BaseModel
{
    use HasFactory, LogActivityCrm;

    protected $table = 'log_activities';
    protected $guarded = [];
    protected $appends = ['date', 'text'];

    public function getDataAttribute($value){
        return json_decode($value);
    }

    public function getDateAttribute(){
        return Carbon::parse($this->created_at)->diffForHumans();
    }

    public function getTextAttribute(){
        if (isset(RELATION[$this->type])) {
            $function = RELATION[$this->type];
            return $this->$function();
        }
        return null;
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
