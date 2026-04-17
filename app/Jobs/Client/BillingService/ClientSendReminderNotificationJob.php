<?php

namespace App\Jobs\Client\BillingService;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\ClientSendReminderNotification;

class ClientSendReminderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $clientReminder;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientReminder)
    {
       $this->clientReminder = $clientReminder;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
       $this->clientReminder->notify(new ClientSendReminderNotification($this->clientReminder));
    }
}
