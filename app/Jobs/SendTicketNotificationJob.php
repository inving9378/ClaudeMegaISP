<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Notifications\TicketOpen;

class SendTicketNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $model;
    protected $ticket_thread;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($model, $ticket_thread)
    {
        $this->model = $model;
        $this->ticket_thread = $ticket_thread;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = User::find($this->model->assigned_to);
        if ($user) $user->notify(new TicketOpen($this->model, $this->ticket_thread));
       
    }
}
