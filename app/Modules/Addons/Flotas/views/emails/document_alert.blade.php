<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.06);">
            <div style="background:{{ $daysUntil <= 7 ? '#dc2626' : ($daysUntil <= 30 ? '#d97706' : '#2563eb') }};padding:18px 24px;color:#fff;">
                <div style="font-size:13px;opacity:.85;letter-spacing:.5px;">MEGANET · FLOTAS</div>
                <div style="font-size:19px;font-weight:700;margin-top:2px;">⚠️ Documento por vencer</div>
            </div>
            <div style="padding:24px;">
                <p style="font-size:16px;margin:0 0 18px;">
                    El documento <strong>{{ $typeLabel }}</strong> del vehículo
                    <strong>{{ $vehicleName }}</strong>
                    @if($daysUntil === 0)
                        <span style="color:#dc2626;font-weight:700;">vence HOY</span>.
                    @elseif($daysUntil === 1)
                        vence <span style="color:#dc2626;font-weight:700;">mañana</span>.
                    @else
                        vence en <span style="font-weight:700;">{{ $daysUntil }} días</span>.
                    @endif
                </p>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:6px 0;color:#6b7280;">Documento</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $typeLabel }}</td></tr>
                    @if($vehicle)
                    <tr><td style="padding:6px 0;color:#6b7280;">Vehículo</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $vehicle->display_name }}</td></tr>
                    @if($vehicle->plates)
                    <tr><td style="padding:6px 0;color:#6b7280;">Placas</td><td style="padding:6px 0;text-align:right;font-weight:600;">{{ $vehicle->plates }}</td></tr>
                    @endif
                    @endif
                    <tr>
                        <td style="padding:6px 0;color:#6b7280;">Vencimiento</td>
                        <td style="padding:6px 0;text-align:right;font-weight:600;color:{{ $daysUntil <= 7 ? '#dc2626' : ($daysUntil <= 30 ? '#d97706' : '#1f2937') }};">
                            {{ $document->expiration_date->format('d/m/Y') }}
                        </td>
                    </tr>
                    @if($document->folio_number)
                    <tr><td style="padding:6px 0;color:#6b7280;">Folio</td><td style="padding:6px 0;text-align:right;">{{ $document->folio_number }}</td></tr>
                    @endif
                    @if($document->issued_by)
                    <tr><td style="padding:6px 0;color:#6b7280;">Emisor</td><td style="padding:6px 0;text-align:right;">{{ $document->issued_by }}</td></tr>
                    @endif
                </table>
                <div style="text-align:center;margin-top:24px;">
                    <a href="{{ $vehicleUrl }}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:600;font-size:14px;">Ver documentos del vehículo</a>
                </div>
            </div>
            <div style="padding:14px 24px;background:#f8fafc;color:#94a3b8;font-size:12px;text-align:center;">
                Recibes este correo porque administras la flota de tu empresa en Meganet. Actualiza el documento en el sistema para desactivar las alertas.
            </div>
        </div>
    </div>
</body>
</html>
