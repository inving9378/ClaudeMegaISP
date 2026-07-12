<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;background:#f4f6fa;font-family:Arial,Helvetica,sans-serif;color:#1a1a2e;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.06);">
            <div style="background:#0057a8;padding:18px 24px;color:#fff;">
                <div style="font-size:13px;opacity:.85;letter-spacing:.5px;">PORTAL CLIENTE · MEGANET</div>
                <div style="font-size:19px;font-weight:700;margin-top:2px;">¡Pago recibido!</div>
            </div>
            <div style="padding:24px;">
                <p style="font-size:16px;margin:0 0 18px;">
                    Hola{{ !empty($d['client_name']) ? ' ' . $d['client_name'] : '' }}, confirmamos tu pago de la factura
                    <strong>#{{ $d['invoice_number'] }}</strong>.
                </p>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:6px 0;color:#6c757d;">Factura</td><td style="padding:6px 0;text-align:right;font-weight:600;">#{{ $d['invoice_number'] }}</td></tr>
                    <tr><td style="padding:6px 0;color:#6c757d;">Monto</td><td style="padding:6px 0;text-align:right;font-weight:600;">${{ number_format((float) $d['amount'], 2) }} MXN</td></tr>
                    <tr><td style="padding:6px 0;color:#6c757d;">Fecha</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $d['date'] }}</td></tr>
                    <tr><td style="padding:6px 0;color:#6c757d;">Método</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $d['method'] }}</td></tr>
                    <tr><td style="padding:6px 0;color:#6c757d;">Referencia</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $d['charge_id'] }}</td></tr>
                </table>
            </div>
            <div style="padding:14px 24px;background:#f4f6fa;color:#9ba3af;font-size:12px;text-align:center;">
                Este es un correo automático de confirmación de pago. Puedes consultar tu historial completo en el Portal Cliente.
            </div>
        </div>
    </div>
</body>
</html>
