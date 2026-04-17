<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

@php
    $total = 0;
    $iva_total = 0;
    $total_neto = 0;

    if (!empty($items)) {
        foreach ($items as $service) {
            $total += $service['subtotal'];
            $iva_total += $service['tax_amount'];
            $total_neto += $service['subtotal'] + $service['tax_amount'];
        }
    }
@endphp

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Factura Meganet</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #000000; background-color: #ffffff;">
    <!-- Contenedor principal -->
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <!-- Contenedor de contenido (600px es un ancho seguro para emails) -->
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                    style="border-collapse: collapse;">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 15px; border-bottom: 1px solid #eeeeee;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="border-collapse: collapse;">
                                <tr>
                                    <td width="60%" style="vertical-align: middle;">
                                        <img src="http://megaisp.meganett.com.mx/storage/logo_meganet/logo-meganet-oficial.png"
                                            alt="Logo Meganet" width="180" style="display: block; border: 0;" />
                                    </td>
                                    <td width="40%"
                                        style="vertical-align: middle; font-size: 12px; text-align: left;">
                                        <strong>{{ $companyInformation['company_name'] }}</strong><br />
                                        {{ $companyInformation['colony_id'] }}
                                        {{ $companyInformation['municipality_id'] }}
                                        {{ $companyInformation['state_id'] }}<br />
                                        Código Postal: {{ $companyInformation['company_postal_code'] }}<br />
                                        RFC: {{ $companyInformation['rfc'] }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Banner de monto a pagar -->
                    <tr>
                        <td style="background-color: #E7F7FE; padding: 15px; text-align: right;">
                            <span style="color: #308CD2; font-size: 18px; font-weight: bold;">
                                Monto a pagar: {{ $invoice->pending_balance }}
                            </span>
                        </td>
                    </tr>

                    <!-- Datos del cliente y prefactura -->
                    <tr>
                        <td style="padding: 20px 15px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="border-collapse: collapse;">
                                <tr>
                                    <td width="60%"
                                        style="vertical-align: top; padding: 10px; border-right: 1px solid #eeeeee;">
                                        <strong>{{ $dataClient['name'] }} {{ $dataClient['father_last_name'] }}
                                            {{ $dataClient['mother_last_name'] }}</strong><br />
                                        <span style="color: #666666; font-size: 13px;">
                                            {{ $dataClient['street'] }} {{ $dataClient['external_number'] }}
                                            {{ $dataClient['internal_number'] }}<br />
                                            {{ $dataClient['colony'] }}<br />
                                            {{ $dataClient['municipality'] }}<br />
                                            {{ $dataClient['state'] }}<br />
                                            CP: {{ $dataClient['zip'] }}<br />
                                            Id: {{ $dataClient['id'] }}
                                        </span>
                                    </td>
                                    <td width="40%"
                                        style="vertical-align: top; padding: 10px; text-align: right; font-size: 13px; color: #666666;">
                                        Prefactura: {{ $invoice->number }}<br />
                                        Fecha de Prefactura:
                                        {{ \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y') }}<br />
                                        Fecha de Vencimiento:
                                        {{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}<br />
                                        Período: {{ $invoice->period }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Nota -->
                    <tr>
                        <td style="padding: 15px; font-weight: bold; font-size: 14px; background-color: #f9f9f9;">
                            Esta es una prefactura correspondiente al pago de los servicios contratados. Por favor,
                            revise los detalles a continuación:
                        </td>
                    </tr>
                    @if ($invoice->status == 'paid')
                        <div class="sello"
                            style=" position: absolute;
            top: 50%;

            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            color: red;
            font-size: 6em;
            opacity: 0.3;
            pointer-events: none;
            z-index: 1000;">
                            PAGADO</div>
                    @endif

                    <!-- Tabla de servicios -->
                    <tr>
                        <td style="padding: 15px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="border-collapse: collapse;">
                                <tr>
                                    <th
                                        style="padding: 10px; background-color: #308CD2; color: #ffffff; text-align: left;">
                                        #</th>
                                    <th
                                        style="padding: 10px; background-color: #308CD2; color: #ffffff; text-align: left;">
                                        DESCRIPCIÓN DEL ARTÍCULO</th>
                                    <th
                                        style="padding: 10px; background-color: #308CD2; color: #ffffff; text-align: center;">
                                        IVA %</th>
                                    <th
                                        style="padding: 10px; background-color: #308CD2; color: #ffffff; text-align: right;">
                                        IVA</th>
                                    <th
                                        style="padding: 10px; background-color: #308CD2; color: #ffffff; text-align: right;">
                                        PRECIO</th>
                                </tr>

                                @if (!empty($items))
                                    @foreach ($items as $key => $service)
                                        <tr>
                                            <td
                                                style="padding: 10px; border-bottom: 1px solid #eeeeee; text-align: left;">
                                                {{ $key + 1 }}</td>
                                            <td
                                                style="padding: 10px; border-bottom: 1px solid #eeeeee; text-align: left;">
                                                {{ $service['name'] }}</td>
                                            <td
                                                style="padding: 10px; border-bottom: 1px solid #eeeeee; text-align: center;">
                                                {{ $service['tax_rate'] }}%</td>
                                            <td
                                                style="padding: 10px; border-bottom: 1px solid #eeeeee; text-align: right;">
                                                {{ $service['tax_amount'] }}</td>
                                            <td
                                                style="padding: 10px; border-bottom: 1px solid #eeeeee; text-align: right;">
                                                {{ $service['subtotal'] }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5"
                                            style="padding: 15px; text-align: center; border-bottom: 1px solid #eeeeee;">
                                            No hay servicios disponibles</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- Totales -->
                    <tr>
                        <td>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="border-collapse: collapse;">
                                <tr>
                                    <td width="70%">&nbsp;</td>
                                    <td width="30%">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                                            width="100%"
                                            style="border-collapse: collapse; border: 1px solid #cccccc;">
                                            <tr>
                                                <td
                                                    style="padding: 8px; background-color: #D3D3D3; font-weight: bold; border-bottom: 1px solid #cccccc;">
                                                    Total:</td>
                                                <td
                                                    style="padding: 8px; text-align: right; border-bottom: 1px solid #cccccc;">
                                                    {{ $total }}</td>
                                            </tr>
                                            <tr>
                                                <td
                                                    style="padding: 8px; background-color: #D3D3D3; font-weight: bold; border-bottom: 1px solid #cccccc;">
                                                    IVA total:</td>
                                                <td
                                                    style="padding: 8px; text-align: right; border-bottom: 1px solid #cccccc;">
                                                    {{ $iva_total }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px; background-color: #D3D3D3; font-weight: bold;">
                                                    Total Neto:</td>
                                                <td style="padding: 8px; text-align: right;">{{ $total_neto }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="padding: 20px 15px 10px; text-align: center; font-size: 12px; color: #666666; border-top: 1px solid #eeeeee;">
                            &copy; {{ date('Y') }} {{ $companyInformation['company_name'] }}. Todos los derechos
                            reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
