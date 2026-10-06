<?php
namespace App\Notifications;

use App\Modules\Addons\Hub\Services\Correo;
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
        // Con una cuenta asignada en Integraciones → Módulos de servicios se usa
        // esa; si no, la de Configuración → Correo, como siempre.
        $mailer = Correo::nombreMailer('facturacion.correos_sistema');
        if (!$mailer) {
            \App\Modules\Core\Configuracion\Services\EmailConfigService::setMailConfig();
            return (new StandardMail($this->emailProps))->build();
        }

        $mail = new StandardMail($this->emailProps);
        if ($remitente = Correo::remitente('facturacion.correos_sistema')) {
            $mail->from($remitente['address'], $remitente['name']);
        }
        return $mail->build()->mailer($mailer);
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