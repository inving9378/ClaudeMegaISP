<?php


namespace App\Http\Traits\Models\Ticket;

use App\Http\Controllers\FileController;
use App\Http\Controllers\Utils\ReceiptController;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait TicketTrait
{

    public function ticketCreateTicketThread($request)
    {
        $this->ticket_thread()->create($request);
        return $this;
    }

    public function uploadManyFiles($files)
    {
        if (count($files)) {
            foreach ($files['attachments'] as $file) {
                $file_process = new FileController;
                $module_path = 'ticket/' . $this->id;
                $properties = $file_process->processSingleFileAndReturnProperties($file, $module_path, $this->id);

                $this->files()->create($properties);
                $file->storeAs('/public/' . $module_path . '/' . $this->id, $properties['name']);
            }
        }
        return $this;
    }

    public function ticketTimeHuman($value)
    {
        return Carbon::parse($value)->diffForHumans();
    }

    public function ticketModifyStatus($id,$value){
     $model = Ticket::find($id);
       $model->update([
           'estado' => $value
       ]);
    }
}
