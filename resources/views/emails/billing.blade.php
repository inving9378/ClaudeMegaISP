<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->subject }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f0f4f8; font-family: Arial, Helvetica, sans-serif; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a5faa; color: #fff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: bold; }
        .header p { margin: 6px 0 0; font-size: 13px; opacity: .85; }
        .body { padding: 28px 32px; color: #333; font-size: 14px; line-height: 1.6; }
        .body h2 { font-size: 16px; color: #1a5faa; margin: 0 0 12px; }
        .meta-box { background: #f6f9fd; border: 1px solid #d0d7e2; border-radius: 4px; padding: 12px 16px; margin: 16px 0; font-size: 13px; }
        .meta-box table { width: 100%; border-collapse: collapse; }
        .meta-box td { padding: 3px 0; }
        .meta-box .lbl { color: #666; width: 140px; }
        .attach-note { background: #fff8e1; border-left: 4px solid #e5b53a; padding: 10px 14px; font-size: 13px; margin: 16px 0; border-radius: 0 4px 4px 0; }
        .footer { background: #f6f9fd; padding: 16px 32px; font-size: 11px; color: #888; text-align: center; border-top: 1px solid #e0e5ed; }
        .no-fiscal { display: inline-block; background: #e5b53a; color: #1a1a1a; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 3px; margin-top: 8px; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <h1>
            @if($notification->document_type === 'prefactura')
                Aviso de cobro
            @elseif($notification->document_type === 'recibo')
                Recibo de pago
            @else
                Documento de facturación
            @endif
        </h1>
        <p>{{ config('mail.from.name') }}</p>
    </div>

    <div class="body">
        @php
            $client = $notification->client()->with('client_main_information')->first();
            $nombre = $client?->client_main_information?->name ?? 'Cliente';
        @endphp

        <h2>Hola, {{ $nombre }}.</h2>

        @if($notification->document_type === 'prefactura')
            <p>Te enviamos tu aviso de cobro correspondiente al período de servicio próximo.
            Por favor, revisa el documento adjunto para ver el desglose de tu servicio y la fecha límite de pago.</p>
        @elseif($notification->document_type === 'recibo')
            <p>Hemos registrado tu pago exitosamente. Adjunto encontrarás tu recibo de pago
            con el desglose del servicio contratado. Consérvalo como comprobante.</p>
        @endif

        <div class="meta-box">
            <table>
                <tr>
                    <td class="lbl">ID cliente:</td>
                    <td><strong>{{ $client?->id }}</strong></td>
                </tr>
                <tr>
                    <td class="lbl">Documento:</td>
                    <td>{{ strtoupper($notification->document_type) }}</td>
                </tr>
                @if($notification->invoice_id)
                <tr>
                    <td class="lbl">Prefactura N°:</td>
                    <td>{{ $notification->invoice?->number ?? $notification->invoice_id }}</td>
                </tr>
                @endif
                @if($notification->payment_id)
                <tr>
                    <td class="lbl">Pago ID:</td>
                    <td>{{ $notification->payment_id }}</td>
                </tr>
                @endif
                <tr>
                    <td class="lbl">Fecha de envío:</td>
                    <td>{{ now()->format('d/m/Y H:i') }}</td>
                </tr>
            </table>
        </div>

        <div class="attach-note">
            📎 El documento PDF se adjunta a este correo. Si no puedes verlo, verifica tu cliente de correo.
        </div>

        <p>Si tienes dudas sobre tu estado de cuenta, comunícate con nosotros a través de nuestros canales de atención.</p>

        <span class="no-fiscal">DOCUMENTO SIN VALOR FISCAL</span>
    </div>

    <div class="footer">
        Este mensaje fue generado automáticamente. Por favor no respondas a este correo.<br>
        {{ config('mail.from.name') }}
    </div>

</div>
</body>
</html>
