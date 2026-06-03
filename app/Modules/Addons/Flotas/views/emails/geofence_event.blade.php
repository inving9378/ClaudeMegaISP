<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.06);">
            <div style="background:#2563eb;padding:18px 24px;color:#fff;">
                <div style="font-size:13px;opacity:.85;letter-spacing:.5px;">MEGANET · FLOTAS</div>
                <div style="font-size:19px;font-weight:700;margin-top:2px;">Alerta de geocerca</div>
            </div>
            <div style="padding:24px;">
                <p style="font-size:16px;margin:0 0 18px;">
                    {{ $d['action_emoji'] }} <strong>{{ $d['vehicle_name'] }}</strong>
                    ({{ $d['plates'] }}) <strong>{{ mb_strtolower($d['action']) }}</strong>
                    la geocerca <strong>{{ $d['geofence_name'] }}</strong>.
                </p>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:6px 0;color:#6b7280;">Vehículo</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $d['vehicle_name'] }}</td></tr>
                    <tr><td style="padding:6px 0;color:#6b7280;">Placas</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $d['plates'] }}</td></tr>
                    <tr><td style="padding:6px 0;color:#6b7280;">Evento</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $d['action'] }} {{ $d['geofence_name'] }}</td></tr>
                    <tr><td style="padding:6px 0;color:#6b7280;">Fecha/hora</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $d['time_human'] }}</td></tr>
                </table>
                <div style="text-align:center;margin-top:24px;">
                    <a href="{{ $d['url'] }}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:600;font-size:14px;">Ver detalle en el mapa</a>
                </div>
            </div>
            <div style="padding:14px 24px;background:#f8fafc;color:#94a3b8;font-size:12px;text-align:center;">
                Recibes este correo porque configuraste alertas de flota. Ajusta tus preferencias en el detalle del vehículo.
            </div>
        </div>
    </div>
</body>
</html>
