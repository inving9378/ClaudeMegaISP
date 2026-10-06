<?php

namespace App\Modules\Addons\Hub\Jobs;

use App\Modules\Addons\Hub\Services\Correo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envío encolado con la cuenta de correo asignada al módulo: el mailer se arma
 * aquí, en el worker, con la contraseña vigente (ver Correo::encolar).
 */
class EnviarCorreoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(public string $clave, public $para, public Mailable $mailable)
    {
    }

    public function handle(): void
    {
        // Ya estamos en la cola: se envía ahora con la cuenta vigente.
        Correo::enviarAhora($this->clave, $this->para, $this->mailable);
    }
}
