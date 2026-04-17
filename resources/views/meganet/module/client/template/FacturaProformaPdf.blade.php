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
<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Factura Meganet</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            position: relative;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding-bottom: 60px;
            /* Espacio para el footer */
        }

        .footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            max-width: 800px;
            text-align: center;
            padding: 10px 0;
            border-top: 1px solid #eee;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .bold {
            font-weight: bold;
        }

        .bg-blue {
            background-color: #308CD2;
            color: white;
        }

        .bg-gray {
            background-color: #D3D3D3;
        }

        .bg-light-blue {
            background-color: #E7F7FE;
        }

        .header-info {
            font-size: 11px;
        }

        .total-table {
            width: 40%;
            float: right;
        }

        .sello {
            position: absolute;
            top: 50%;
            /* Centrar verticalmente */
            left: 50%;
            /* Centrar horizontalmente */
            transform: translate(-50%, -50%) rotate(-45deg);
            /* Rotar y centrar */
            color: red;
            /* Color del texto */
            font-size: 6em;
            /* Tamaño del texto */
            opacity: 0.3;
            /* Opacidad para hacerlo transparente */
            pointer-events: none;
            /* Para que no interfiera con los clics */
            z-index: 1000;
            /* Asegura que esté en la parte superior */
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <table>
            <tr>
                <td width="60%">
                    <img src="http://megaisp.meganett.com.mx/storage/logo_meganet/logo-meganet-oficial.png"
                        alt="Logo Meganet" width="180" style="display: block;">
                </td>
                <td width="40%" class="header-info text-left">
                    <strong>{{ $companyInformation['company_name'] }}</strong><br>
                    {{ $companyInformation['colony_id'] }}
                    {{ $companyInformation['municipality_id'] }}
                    {{ $companyInformation['state_id'] }}<br>
                    Código Postal: {{ $companyInformation['company_postal_code'] }}<br>
                    RFC: {{ $companyInformation['rfc'] }}
                </td>
            </tr>
        </table>

        <!-- Monto a pagar -->
        <div class="bg-light-blue text-right" style="padding: 10px; margin: 10px 0;">
            <span style="color: #308CD2; font-size: 16px; font-weight: bold;">
                Monto a pagar: {{ $invoice->pending_balance }}
            </span>
        </div>

        <!-- Datos del cliente -->
        <table style="margin-bottom: 15px;">
            <tr>
                <td width="60%" style="border-right: 1px solid #eee; padding: 10px;">
                    <strong>{{ $dataClient['name'] }} {{ $dataClient['father_last_name'] }}
                        {{ $dataClient['mother_last_name'] }}</strong><br>
                    <span style="color: #666;">
                        {{ $dataClient['street'] }} {{ $dataClient['external_number'] }}
                        {{ $dataClient['internal_number'] }}<br>
                        {{ $dataClient['colony'] }}<br>
                        {{ $dataClient['municipality'] }}<br>
                        {{ $dataClient['state'] }}<br>
                        CP: {{ $dataClient['zip'] }}<br>
                        Id: {{ $dataClient['id'] }}
                    </span>
                </td>
                <td width="40%" style="padding: 10px; color: #666; text-align: right;">
                    Prefactura: {{ $invoice->number }}<br>
                    Fecha: {{ \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y') }}<br>
                    Vencimiento: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}<br>
                    Período: {{ $invoice->period }}
                </td>
            </tr>
        </table>

        <!-- Nota -->
        <div style="background-color: #f9f9f9; padding: 10px; margin: 10px 0; font-weight: bold;">
            Esta es una prefactura correspondiente al pago de los servicios contratados.
        </div>

        @if ($invoice->status == 'paid')
            <div class="sello">
                PAGADO</div>
        @endif

        <!-- Tabla de servicios -->
        <table style="margin-bottom: 15px;">
            <tr class="bg-blue">
                <th width="5%">#</th>
                <th width="55%">DESCRIPCIÓN DEL ARTÍCULO</th>
                <th width="10%" class="text-center">IVA %</th>
                <th width="15%" class="text-right">IVA</th>
                <th width="15%" class="text-right">PRECIO</th>
            </tr>

            @if (!empty($items))
                @foreach ($items as $key => $service)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $service['name'] }}</td>
                        <td class="text-center">{{ $service['tax_rate'] }}%</td>
                        <td class="text-right">{{ number_format($service['tax_amount'], 2) }}</td>
                        <td class="text-right">{{ number_format($service['subtotal'], 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" class="text-center">No hay servicios disponibles</td>
                </tr>
            @endif
        </table>

        <!-- Totales -->
        <table class="total-table">
            <tr>
                <td class="bg-gray bold">Total:</td>
                <td class="text-right">{{ number_format($total, 2) }}</td>
            </tr>
            <tr>
                <td class="bg-gray bold">IVA total:</td>
                <td class="text-right">{{ number_format($iva_total, 2) }}</td>
            </tr>
            <tr>
                <td class="bg-gray bold">Total Neto:</td>
                <td class="text-right">{{ number_format($total_neto, 2) }}</td>
            </tr>
        </table>
        <div style="clear: both; height: 40px;"></div>
        <!-- Footer -->

    </div>
    <div class="footer">
        &copy; {{ date('Y') }} {{ $companyInformation['company_name'] }}. Todos los derechos reservados.
    </div>

</body>

</html>
