<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width">
    <title>Pago</title>
</head>

<body style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #000; margin: 0; padding: 0;">

    <!-- Header -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 30px;">
        <tr>
            <td width="60%" style="padding: 15px;">
                <img src="http://megaisp.meganett.com.mx/storage/logo_meganet/logo-meganet-oficial.png" alt=""
                    height="70" style="display: block;">
            </td>
            <td width="40%" style="padding: 15px; text-align: left; font-size: 12px;">
                <strong>Meganet Telecomunicaciones</strong><br>
                <br>
                Ex-hacienda Santa Inés Nextlalpan México<br>
                Código Postal: 55796<br>
                RFC: MTE1709083F3
            </td>
        </tr>
    </table>

    <!-- Banner -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background-color: #E7F7FE; padding: 10px; margin-bottom: 20px;">
        <tr>
            <td style="color: #308CD2; font-size: 18px; font-weight: bold; text-align: right;">
                Monto a pagar: ${data_dinamic.debit}
            </td>
        </tr>
    </table>

    <!-- Datos cliente + Prefactura -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 20px;">
        <tr>
            <td width="60%" style="vertical-align: top; padding: 10px;">
                <strong>${data.full_name}</strong><br>
                <span style="color: grey; font-size: 13px;">
                    ${data.street} ${data.external_number} ${data.internal_number}<br>
                    ${data.colony}<br>
                    ${data.municipality}<br>
                    ${data.state}<br>
                    CP: ${data.zip}<br>
                    Id: ${data.id}
                </span>
            </td>
            <td width="40%"
                style="vertical-align: top; padding: 10px; text-align: right; font-size: 13px; color: grey;">
                Prefactura: ${data_dinamic.invoice_id}<br>
                Fecha de Prefactura: ${data_dinamic.invoice_date}<br>
                Fecha de Vencimiento: ${data_dinamic.invoice_pay_up}<br>
                Período: ${data_dinamic.invoice_period}
            </td>
        </tr>
    </table>

    <!-- Nota -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 20px;">
        <tr>
            <td style="padding: 10px; font-weight: bold; font-size: 14px;">
                Esta es una prefactura correspondiente al pago de los servicios contratados.
                Por favor, revise los detalles a continuación:
            </td>
        </tr>
    </table>

    <!-- Tabla de servicios -->
    @php
        $total = 0;
        $iva_total = 0;
        $total_neto = 0;

        if (!empty($client_services)) {
            foreach ($client_services as $service) {
                $total += $service['monto'];
                $iva_total += $service['iva'];
                $total_neto += $service['monto'] + $service['iva'];
            }
        }
    @endphp

    <table class="table" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr style="color: #308CD2; border-bottom: 1px solid #ddd;">
                <th style="padding: 15px;">#</th>
                <th style="padding: 15px;">DESCRIPCIÓN DEL ARTÍCULO</th>
                <th style="padding: 15px;">IVA %</th>
                <th style="padding: 15px;">IVA</th>
                <th style="padding: 15px;">PRECIO</th>
            </tr>
        </thead>
        <tbody>
            @if (!empty($client_services))
                @foreach ($client_services as $service)
                    <tr>
                        <td style="padding: 15px;">{{ $service['number'] }}</td>
                        <td style="padding: 15px;">{{ $service['service_name'] }}</td>
                        <td style="padding: 15px;">{{ $service['iva_porcent'] }} %</td>
                        <td style="padding: 15px;">{{ $service['iva'] }}</td>
                        <td style="padding: 15px;">{{ $service['monto'] }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" style="padding: 15px; text-align: center;">No hay servicios disponibles</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table style="border-collapse: collapse; margin-top: 10px; float: right;">
        <tr>
            <td style="border: 1px solid black; padding: 5px; background-color: #D3D3D3;">Total:</td>
            <td style="border: 1px solid black; padding: 5px;">{{ $total }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid black; padding: 5px; background-color: #D3D3D3;">IVA total:</td>
            <td style="border: 1px solid black; padding: 5px;">{{ $iva_total }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid black; padding: 5px; background-color: #D3D3D3;">Total Neto:</td>
            <td style="border: 1px solid black; padding: 5px;">{{ $total_neto }}</td>
        </tr>
    </table>

    <!-- Footer -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 40px;">
        <tr>
            <td align="right" style="padding: 10px; font-size: 14px;">

            </td>
        </tr>
    </table>

</body>

</html>
