<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Pago {{ $payment->invoice_number }} </title>
    <style>
        body {
            font-size: 14px;
            font-family: "IBM Plex Sans", sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table tr td {
            padding: 5px;
            width: 50%;
        }

        .header {
            padding: 5px;
        }
    </style>
</head>

<body>
    @php
        $types = [
            'fixed_salary' => 'Salario fijo',
            'additional_sales_commissions' => 'Comisión por venta adicional',
            'distributors_commission' => 'Comisión distribuidores',
            'monthly_bonus' => 'Bonos mensuales',
            'sales_commission' => 'Comisión por venta',
        ];
        $months = [
            'January' => 'Enero',
            'February' => 'Febrero',
            'March' => 'Marzo',
            'April' => 'Abril',
            'May' => 'Mayo',
            'June' => 'Junio',
            'July' => 'Julio',
            'August' => 'Agosto',
            'September' => 'Septiembre',
            'October' => 'Octubre',
            'November' => 'Noviembre',
            'December' => 'Diciembre',
        ];
    @endphp
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 30px;">
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
        <b>Fecha</b>: {{ $payment->payment_date }}
    </div>
    <div class="header">
        <b>Número de recibo</b>: {{ $payment->invoice_number }}
    </div>
    <div class="header">
        <b>Método de pago</b>: {{ $paymentMethod }}
    </div>
    <div class="header">
        <b>Total pagado</b>: ${{ $payment->amount }}
    </div>
    <div class="header">
        <b>Vendedor</b>: {{ $seller }}
    </div>
    <div class="header">
        <b>Realiza el pago</b>: {{ $user }}
    </div>
    @if (count($general_commissions) > 0)
        <div class="header">
            <b>Comisiones pagadas</b>
        </div>
        <div style="padding-left: 10px">
            <table>
                <tbody>
                    @foreach ($general_commissions as $c)
                        @php
                            $payments = App\Models\PaymentByRuleDetails::where('payment_id', $payment->id)
                                ->whereDate('start_date', $c->start_date)
                                ->whereDate('end_date', $c->end_date)
                                ->where('type', '<>', 'monthly_bonus')
                                ->get();
                        @endphp
                        <tr>
                            <td><b>{{ $c->period }}</b></td>
                            <td style="text-align: right;">
                                <b>${{ number_format($payments->sum('amount'), 2, '.', '') }}</b>
                            </td>
                        </tr>
                        @foreach ($payments as $p)
                            <tr>
                                <td style="padding-left: 20px">{{ $types[$p->type] }} </td>
                                <td style="text-align: right;">${{ $p->amount }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if (count($monthly_commissions) > 0)
        <div class="header">
            <b>Bonos mensules</b>
        </div>
        <div style="padding-left: 10px">
            <table>
                <tbody>
                    @foreach ($monthly_commissions as $c)
                        @php
                            $payments = App\Models\PaymentByRuleDetails::where('payment_id', $payment->id)
                                ->whereDate('start_date', $c->start_date)
                                ->whereDate('end_date', $c->end_date)
                                ->where('type', 'monthly_bonus')
                                ->get();
                        @endphp
                        <tr>
                            <td>{{ $months[$c->start_date->format('F')] }}/{{ $c->start_date->format('Y') }}
                            </td>
                            <td style="text-align: right;">
                                ${{ number_format($payments->sum('amount'), 2, '.', '') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>

</html>
