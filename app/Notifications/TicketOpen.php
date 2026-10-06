<?php

namespace App\Notifications;

use App\Modules\Addons\Hub\Services\Correo;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TicketOpen extends Notification
{
    use Queueable;

    protected $ticket;
    protected $data;
    protected $ticket_thread;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Ticket $ticket, $ticket_thread)
    {
        $this->ticket = $ticket;
        $this->ticket_thread = $ticket_thread;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // Cuenta de correo elegida en Integraciones → Módulos de servicios (o la de siempre)
        return (new MailMessage)
            ->mailer(Correo::nombreMailer('tickets.correo'))
            ->from(Correo::remitenteDireccion('tickets.correo'))
            ->markdown('emails.notificate.ticket_open',
                ['ticket' => $this->ticket, 'ticket_thread' => $this->ticket_thread
                ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [$this->ticket];
    }
}
