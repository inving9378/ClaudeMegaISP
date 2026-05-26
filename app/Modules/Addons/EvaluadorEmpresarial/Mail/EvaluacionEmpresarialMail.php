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
                config('mail.mailers.ventas.from.address', env('MAIL_VENTAS_FROM_ADDRESS')),
                config('mail.mailers.ventas.from.name', env('MAIL_VENTAS_FROM_NAME', 'MegaNet Ventas'))
            )
            ->subject('Resultado de tu evaluación empresarial — MegaNet')
            ->view('addon-evaluador-empresarial::emails.evaluacion-empresarial')
            ->with(['evaluacion' => $this->evaluacion]);
    }
}
