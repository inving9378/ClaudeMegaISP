<?php
namespace App\Notifications;

use App\Mail\StandardMail;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class StandardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $model;
    protected $via;
    protected $emailProps;

    public function __construct($model, $via = ['database'], $emailProps = null)
    {
        $this->model = $model;
        $this->via = $via;
        $this->emailProps = $emailProps;
    }

    public function via($notifiable)
    {
        return $this->afterCommit()->via;
    }

    public function toMail($notifiable)
    {
        // Cargar la configuración de correo desde la base de datos
        \App\Services\EmailConfigService::setMailConfig();

        return (new StandardMail($this->emailProps))->build();
    }

    public function toDatabase($notifiable)
    {
        return [
            'id' => $this->model->id ?? null,
            'type' => get_class($this->model),
            'attributes' => method_exists($this->model, 'toArray') ? $this->model->toArray() : [],
        ];
    }
}