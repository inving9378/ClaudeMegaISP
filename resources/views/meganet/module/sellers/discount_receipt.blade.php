<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Pago {{ $discount->invoice_number }} </title>
    <style>
        body {
            font-size: 14px;
            font-family: "IBM Plex Sans", sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #eee;
        }

        th,
        td {
            padding: 5px;
        }

        table#header tr td {
            padding: 5px;
            width: 50%;
        }

        .header {
            padding: 5px;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }
    </style>
</head>

<body>
    <table id="header" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr style="background: #3668d3">
            <td width="60%" style="padding: 15px;">
                <img src="{{ public_path('images/logo_meganet_oficial.png') }}" alt="" height="70"
                    style="display: block;">
            </td>
            <td width="40%" style="padding: 15px; text-align: left; font-size: 12px; color:#fff;">
                <strong>MEGANET TELECOMUNICACIONES S.A. DE C.V.</strong><br>
                Código Postal: {{ $company->company_postal_code }}<br>
                RFC: {{ $company->rfc }}<br>
                Atención a clientes: {{ $company->atention_client_phone }}<br>
            </td>
        </tr>
    </table>
    <div class="header">
        <b>Fecha</b>: {{ $discount->date }}
    </div>
    <div class="header">
        <b>Número de recibo</b>: {{ $discount->invoice_number }}
    </div>
    <div class="header">
        <b>Total cobrado</b>: ${{ $discount->discount }}
    </div>
    <div class="header">
        <b>Vendedor</b>: {{ $seller }}
    </div>
    <div class="header">
        <b>Realiza el cobro</b>: {{ $user }}
    </div>
    <div class="header" style="background: #eee; text-align: center; padding: 10px; border-bottom: 1px solid #fff;">
        <b>Ventas cobradas</b>
    </div>
    <table>
        <thead>
            <th class="text-left">Id del cliente</th>
            <th class="text-left">Nombre del cliente</th>
            <th class="text-right">Servicio</th>
            <th class="text-right">Costo de instalación</th>
            <th class="text-right">Pagado por el cliente</th>
            <th class="text-right">Deuda del cliente</th>
            <th class="text-right">Deuda por venta adicional</th>
            <th class="text-right">Deuda total</th>
            <th class="text-right">Total pagado</th>
            <th class="text-right">Deuda pagada</th>
            <th class="text-right">Deuda restante</th>
        <tbody>
            @foreach ($discount->sales as $s)
                <tr>
                    <td class="text-left">{{ $s->sale->client_id }}</td>
                    <td class="text-left">{{ $s->sale->client_name_with_fathers_names }}</td>
                    <td class="text-right">${{ number_format($s->data['service'], 2, '.') }}</td>
                    <td class="text-right">${{ number_format($s->data['installation_cost'], 2, '.') }}</td>
                    <td class="text-right">${{ number_format($s->data['amount_by_client'], 2, '.') }}</td>
                    <td class="text-right">${{ number_format($s->data['discount_by_client'], 2, '.') }}</td>
                    <td class="text-right">${{ number_format($s->data['discount_by_additional_sale'], 2, '.') }}</td>
                    <td class="text-right">${{ number_format($s->data['total_discount'], 2, '.') }}</td>
                    <td class="text-right">
                        ${{ number_format($s->data['amount_by_seller'] + $s->data['to_pay'], 2, '.') }}</td>
                    <td class="text-right">${{ number_format($s->data['to_pay'], 2, '.') }}</td>
                    <td class="text-right">${{ number_format($s->data['current_debt'] - $s->data['to_pay'], 2, '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
