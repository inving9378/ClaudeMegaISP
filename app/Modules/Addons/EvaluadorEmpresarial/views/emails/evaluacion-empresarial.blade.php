<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Tu Evaluación de Servicios Empresariales</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#222;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 0;">
    <tr>
        <td align="center">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">

                {{-- HEADER --}}
                <tr>
                    <td style="background:#0d3b66;padding:20px 32px;color:#ffffff;">
                        <table role="presentation" width="100%"><tr>
                            <td style="font-size:20px;font-weight:700;letter-spacing:.5px;">MegaNet</td>
                            <td style="text-align:right;font-size:12px;">Telecomunicaciones</td>
                        </tr></table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 32px 8px 32px;">
                        <h1 style="margin:0 0 4px 0;font-size:22px;color:#0d3b66;">Tu Evaluación de Servicios Empresariales</h1>
                        <p style="margin:0;color:#666;font-size:14px;">
                            Hola <strong>{{ $evaluacion->nombre_contacto }}</strong> de <strong>{{ $evaluacion->empresa }}</strong>
                        </p>
                    </td>
                </tr>

                {{-- RESUMEN --}}
                <tr><td style="padding:16px 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:6px;">
                        <tr>
                            <td style="padding:16px;text-align:center;border-right:1px solid #e0e6ed;width:33%;">
                                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Categoría</div>
                                <div style="font-size:18px;font-weight:700;color:#0d3b66;margin-top:4px;">{{ $evaluacion->categoria }}</div>
                            </td>
                            <td style="padding:16px;text-align:center;border-right:1px solid #e0e6ed;width:33%;">
                                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Puntaje</div>
                                <div style="font-size:18px;font-weight:700;color:#0d3b66;margin-top:4px;">{{ $evaluacion->puntaje_total }}</div>
                            </td>
                            <td style="padding:16px;text-align:center;width:34%;">
                                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Fecha</div>
                                <div style="font-size:13px;font-weight:600;color:#0d3b66;margin-top:4px;">
                                    {{ optional($evaluacion->completado_at)->format('d/m/Y') }}
                                </div>
                            </td>
                        </tr>
                    </table>
                </td></tr>

                {{-- PLAN RECOMENDADO --}}
                @if(!empty($evaluacion->plan_recomendado))
                <tr><td style="padding:8px 32px 16px 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef6ff;border-left:4px solid #0d3b66;border-radius:4px;">
                        <tr><td style="padding:14px 18px;">
                            <div style="font-size:11px;color:#0d3b66;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Recomendación</div>
                            <div style="font-size:16px;font-weight:700;color:#0d3b66;margin-top:4px;">{{ $evaluacion->plan_recomendado }}</div>
                        </td></tr>
                    </table>
                </td></tr>
                @endif

                {{-- SCORES POR ÁREA --}}
                <tr><td style="padding:8px 32px 16px 32px;">
                    <h2 style="font-size:14px;color:#0d3b66;margin:0 0 12px 0;text-transform:uppercase;letter-spacing:.05em;">Scores por área</h2>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
                        @foreach([
                            'Criticidad' => $evaluacion->score_criticidad,
                            'Redundancia' => $evaluacion->score_redundancia,
                            'Ancho de Banda' => $evaluacion->score_ancho_banda,
                            'SLA' => $evaluacion->score_sla,
                        ] as $label => $valor)
                            @php $pct = min(100, max(0, $valor * 4)); @endphp
                            <tr>
                                <td style="padding:6px 0;width:130px;color:#444;">{{ $label }}</td>
                                <td style="padding:6px 0;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9ecef;border-radius:3px;">
                                        <tr><td width="{{ $pct }}%" style="background:#0d3b66;height:8px;border-radius:3px;">&nbsp;</td><td>&nbsp;</td></tr>
                                    </table>
                                </td>
                                <td style="padding:6px 0 6px 10px;width:36px;text-align:right;color:#444;font-weight:600;">{{ $valor }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td></tr>

                {{-- CTA --}}
                <tr><td style="padding:16px 32px 8px 32px;text-align:center;">
                    <a href="https://wa.me/525568175643?text=Hola%20MegaNet,%20completé%20mi%20evaluación"
                       style="display:inline-block;background:#25d366;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;font-size:14px;">
                        Contactar a MegaNet por WhatsApp
                    </a>
                </td></tr>

                {{-- FOOTER --}}
                <tr><td style="padding:24px 32px;background:#f8fafc;border-top:1px solid #e0e6ed;text-align:center;color:#6b7280;font-size:12px;">
                    <div>MegaNet Telecomunicaciones</div>
                    <div style="margin-top:4px;">
                        <a href="mailto:ventas@meganett.com.mx" style="color:#0d3b66;text-decoration:none;">ventas@meganett.com.mx</a>
                        &nbsp;·&nbsp;
                        <a href="tel:+525568175643" style="color:#0d3b66;text-decoration:none;">+52 55 6817 5643</a>
                    </div>
                    <div style="margin-top:8px;font-size:11px;color:#9ca3af;">
                        Este correo fue generado a partir de la evaluación que completaste en nuestro sitio.
                        Si no fuiste tú, ignóralo o respóndenos para eliminar tus datos.
                    </div>
                </td></tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
