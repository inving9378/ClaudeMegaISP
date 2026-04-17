<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        /* Estilos específicos para impresoras térmicas */
        body {
            font-family: "Courier New", monospace;
            width: 80mm; /* Ancho estándar para tickets */
            margin: 0;
            padding: 0;
            font-size: 12px;
            line-height: 1.2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }

        th, td {
            padding: 2px 0;
            text-align: left;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .underline {
            border-bottom: 1px dotted #000;
            padding-bottom: 1px;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }

        /* Evitar saltos de página dentro de elementos importantes */
        tr, td, th {
            page-break-inside: avoid;
        }
    </style>
    <title>Ticket de Pago</title>
</head>
<body>
    <table>
        <tbody>
            <tr>
                <th colspan="3" class="center bold" style="font-size: 14px; padding-bottom: 5px;">
                    Meganet Telecomunicaciones S.A. de C.V.
                </th>
            </tr>
            <tr>
                <td colspan="3" class="center">Atencion a clientes: 55-42-10-62-77</td>
            </tr>
            <tr>
                <td colspan="3" class="center">Whatsapp SOLO PAGOS: 55-25-71-67-18</td>
            </tr>
            <tr>
                <td colspan="3" class="center">OXXO Dep a Tarjeta:5579-0890-0023-7860</td>
            </tr>
            <tr>
                <td colspan="3" class="center">Banco: Santander</td>
            </tr>
            <tr>
                <td colspan="3" class="center" style="padding-top: 5px;">Puede encontrarnos en:</td>
            </tr>
            <tr>
                <td colspan="3" class="center">Av. Hda La Purisima Mz 3 Lt 54 Casa A</td>
            </tr>
            <tr>
                <td colspan="3" class="center">Ex Hda Santa Ines Nextlalpan Edo Mex</td>
            </tr>

            <tr><td colspan="3" class="divider"></td></tr>

            <tr>
                <td colspan="3">Id del Cliente: <span class="underline">{{ $data['client_id'] }}</span></td>
            </tr>
            <tr>
                <td colspan="3">Id de Pago: <span class="underline">{{ $data['payment_id'] }}</span></td>
            </tr>
            <tr>
                <td colspan="3">Tiket: <span class="underline">{{ $data['ticket_number'] }}</span></td>
            </tr>
            <tr>
                <td colspan="3">Fecha: <span class="underline">{{ $data['date'] }}</span></td>
            </tr>
            <tr class="uppercase">
                <td colspan="3" style="padding-top: 5px; font-weight: bold; border-bottom: 1px solid #000;">
                    Nombre: {{ $data['full_name'] }}
                </td>
            </tr>

            <tr><td colspan="3" class="divider"></td></tr>

            <tr>
                <td></td>
                <td class="right">ABONO:</td>
                <td class="underline">{{ $data['amount'] }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="right">SERVICIO:</td>
                <td class="underline">{{ $data['services'] }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="right">VALIDO:</td>
                <td class="underline">{{ $data['payment_period'] }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="right">FECHA DE CORTE:</td>
                <td class="underline">{{ $data['pay_up'] }}</td>
            </tr>

            <tr><td colspan="3" class="divider"></td></tr>

            <tr>
                <td colspan="3" class="center bold" style="padding-top: 10px;">Gracias por SU VISITA</td>
            </tr>
            <tr>
                <td colspan="3" class="center bold">¡¡Que tenga un EXELENTE DIA!!</td>
            </tr>
            <tr>
                <td colspan="3" class="center bold" style="padding-bottom: 10px;">Esto es un Tiket de Pago</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
