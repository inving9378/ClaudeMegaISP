<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <style>
        .page-break {
            page-break-after: always;
        }

        .conteiner-font {
            font-family: "Helvetica Neue", sans-serif;
        }

        .banner-top {
            margin-top: 25px;
            text-align: right;
            background-color: #E7F7FE;
            padding: 1%;
        }

        .column-header {
            color: #308CD2;
        }

        .info-text {
            color: grey;
            font-size: 14px;
        }

        table,
        td,
        th {
            text-align: left;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .tr-button {
            font-size: 14px;
            background-color: #E7F7FE;
        }

        .table-no-pading {
            padding: 0;
            margin: 0;
        }

        .td-strong {
            font-weight: bold;
        }

        th,
        td {
            padding: 15px;
        }

        .r-border {
            border-bottom: 1px solid #ddd;
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
    <title>Pago</title>
</head>

<body>
    <div>
        <div style="width: 55%; float:left margin-top: 15px; padding-bottom: 45px;">
            <img src="{{ public_path($data['url_logo']) }}" alt="" height="70">
        </div>
        <div style="margin-top: 3px; margin-bottom: 45px">
            <div style="width: 45%; float:right; text-align: left;font-size: 12px; ">
                <span style="font-weight: bold">{{ $data['company_name'] }}</span> <br>
                <span>{{ $data['company_street'] }} {{ $data['company_external_number'] }}
                    {{ $data['company_internal_number'] }}</span><br>
                <span>{{ $data['colony_id'] }} {{ $data['municipality_id'] }} {{ $data['state_id'] }}</span><br>
                <span>Código Postal: {{ $data['company_postal_code'] }}</span><br>
                <span>RFC: {{ $data['rfc'] }}</span><br>
            </div>
        </div>
    </div>

    <div class="conteiner-font">
        <div class="banner-top column-header">
            ADEUDO <strong>{{ $data['debit'] }}</strong>
        </div>

        <div style="margin-top: 15px">
            <div style="width: 60%; float:left">
                <strong>{{ $data['client_name_with_fathers_names'] }}</strong> <br>
                <div class="info-text">
                    {{ $data['street'] }} {{ $data['external_number'] }} {{ $data['internal_number'] }} <br>
                    {{ $data['colony'] }} <br>
                    {{ $data['municipality'] }} <br>
                    {{ $data['state'] }} <br>
                    CP: {{ $data['zip'] }} <br>
                    Id: {{ $data['client_id'] }}
                </div>
            </div>

            <div style="width: 40%; float:right; text-align: right; font-size: 13px;">
                <span class="info-text">Factura: </span> {{ $data['invoice_id'] }} <br>
                <span class="info-text">Fecha de Factura: </span> {{ $data['created_at'] }} <br>
                <span class="info-text">Fecha de Vencimiento: </span> {{ $data['pay_up'] ? $data['pay_up'] : '--' }}
                <br>
                <span class="info-text">Período: </span> {{ $data['periodo'] ? $data['periodo'] : '--' }}
                <br>
            </div>
        </div>

    </div>
    <div style="margin-top: 25%">
        <p
            style="
        font-size: 14px;
        font-weight: bold;
        text-align: left;
        margin-bottom: 10px;
        padding: 10px;">
            Esta es una factura correspondiente al pago de los servicios contratados.
            Por favor, revise los detalles a continuación:
        </p>
        @if ($data['payment'] && $data['estado'] != 'Cancelada')
            <div class="sello">PAGADO</div>
        @endif
        @if ($data['estado'] == 'Cancelada')
            <div class="sello">No Pagado</div>
        @endif
        <table class="table">
            <thead>
                <tr class="r-border column-header">
                    <td>#</td>
                    <td>DESCRIPCIÓN DEL ARTÍCULO</td>
                    <td>IVA %</td>
                    <td>IVA</td>
                    <td>MONTO</td>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['client_services'] as $service)
                    <tr>
                        <td>{{ $service['number'] }}</td>
                        <td>{{ $service['service_name'] }}</td>
                        <td>{{ $service['iva_porcent'] }}</td>
                        <td>{{ $service['iva'] }}</td>
                        <td>{{ $service['monto'] }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="tr-button">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>Sub Total</td>
                    <td>{{ $data['sub_total'] }}</td>
                </tr>
                <tr class="tr-button">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>Mexico IVA</td>
                    <td>{{ $data['total_iva'] }}</td>
                </tr>
                <tr class="tr-button td-strong">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>Total</td>
                    <td>{{ $data['total'] }}</td>
                </tr>
                <tr class="tr-button column-header">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>ADEUDO</td>
                    <td>{{ $data['debit'] }}</td>
                </tr>
            </tbody>
        </table>
        <div style="float: right;margin-top: 5%">
            Recibo de Pago
        </div>
    </div>
</body>

</html>
