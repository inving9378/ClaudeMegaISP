<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        * {
            margin: 0;
            box-sizing: border-box;
            font-family: "VT323", monospace;
            color: #1f1f1f;
        }

        .text-center {
            text-align: center;
        }

        .text-end {
            text-align: end;
        }
        
        .bold {
            font-weight: bold;
        }
        
        .center {
            text-align: center;
        }
        
        .receipt {
            width: 300px;
            background: #fff;
            margin: 0 auto;
            padding: 0;
        }
        
        .header {
            text-align: center;
            margin-top: 50px;
            margin-bottom: 20px;
        }

        .paragraph {
            margin: 10px auto;
            width: 280px;
            font-size: 14px;
            text-transform: uppercase;
        }

        .table-container {
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-bottom: 10px;
        }

        hr {
            margin-top: 10px;
            margin-bottom: 10px; 
        }

        th, td {
            padding: 8px;
            text-align: left;
        }

        th, hr {
            border-bottom: 1px solid #1f1f1f;
            border-top: 1px solid #1f1f1f;
        }

        .foo-total {
            display: flex;
            justify-content: space-between;
        }

        .feedback {
            margin: 20px auto;
        }

        .feedback h4.web {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
        }

        .feedback .break {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0;
        }

        .firm {
            margin-top: 45px;
            margin-bottom: 5px
        }

    </style>
    <title>Meganet</title>
</head>

<body>
    <div class="receipt">
        <div class="header">MEGANET S.A. DE C.V.<br>Tel. 6563336307<br>
            Av. Hda La Purisima Mz3 Lt 54 Casa A Fracc. Ex Hacienda, Santa Ines Nextlalpan 55796, Estado de México</div>

        <div class="bold">
            <div>Datos del vendedor: </div>
        </div>
        <div class="paragraph">
            <p>Nombre: {{ $result['name_complete'] }}</p>
            <p>Dirección: {{ $result['address'] }}</p>
            <p>Municipio: {{ $result['city_municipality'] }}</p>
            <p>CP: {{ $result['code_postal'] }}</p>
            <p>Estado: {{ $result['state_country'] }}</p>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Número de pago</th>
                        <th>Fecha de pago</th>
                        <th>Monto</th>
                        <th>Método de pago</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($result['payments'] as $payment)
                    <tr>
                        <td>{{ $payment['payment_number'] }}</td>
                        <td>{{ $payment['payment_date'] }}</td>
                        <td>{{ $payment['amount'] }}</td>
                        <td>{{ $payment['method_of_payment'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <hr>
                <p class="text-end">TOTAL: ${{ $result['total_amount'] }}</p>
            <hr>

            <p class="text-center firm">________________________</p>
            <p class="text-center bold">Firma del vendedor</p>
        </div>
        
        <div class="feedback">
            <div class="break">
                ***********************************
            </div>
            <p class="center">
                Este recibo es utilizado como un comprobante favor de conservarlo.
            </p>
            <div class="break">
                ***********************************
            </div>
        </div>
    </div>
</body>
</html>

