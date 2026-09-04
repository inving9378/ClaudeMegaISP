<?php

namespace App\Modules\Addons\EvaluadorEmpresarial\Mail;

use App\Modules\Addons\EvaluadorEmpresarial\Models\EvaluacionEmpresarial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EvaluacionEmpresarialMail extends Mailable
{
    use Queueable, SerializesModels;

    public EvaluacionEmpresarial $evaluacion;

    public function __construct(EvaluacionEmpresarial $evaluacion)
    {
        $this->evaluacion = $evaluacion;
    }

    public function build()
    {
        return $this
            ->mailer('ventas')
            ->from(
                config('mail.ventas_from.address'),
                config('mail.ventas_from.name')
            )
            ->subject('Resultado de tu evaluación empresarial — MegaNet')
            ->view('addon-evaluador-empresarial::emails.evaluacion-empresarial')
            ->with(['evaluacion' => $this->evaluacion]);
    }
}
