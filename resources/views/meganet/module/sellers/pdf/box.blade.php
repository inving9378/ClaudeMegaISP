<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Cierre de la caja {{ $box['id'] }}</title>

    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            position: relative;
            min-height: 100vh;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-header th,
        .table-header td {
            border: none
        }

        th,
        td {
            padding: 5px;
            font-size: 11px;
            border: 1px solid #ddd;
        }

        th.separator,
        td.separator {
            border-bottom: 1px solid #fff !important;
            border-top: 1px solid #fff !important;
            background: #fff !important;
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

        .sello {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            color: red;
            font-size: 6em;
            opacity: 0.3;
            pointer-events: none;
            z-index: 1000;
        }

        .data {
            margin: 15px 0;
            font-size: 12px;
        }

        .data-row {
            margin-bottom: 5px;
        }

        .data-label {
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
            text-align: right;
            background: gainsboro;
            padding: 5px;
        }

        .data-value {
            padding: 5px;
            display: inline-block;
        }
    </style>
</head>

<body>
    <table class="table-header">
        <tr>
            <td width="60%">
                <img src="http://megaisp.meganett.com.mx/storage/logo_meganet/logo-meganet-oficial.png" width="250px" />
            </td>
            <td width="40%" class="header-info text-left">
                <div class="data">
                    <div class="data-row">
                        <span class="data-label">Número de caja:</span>
                        <span class="data-value">{{ $box['id'] }}</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Sucursal:</span>
                        <span class="data-value">{{ $box['Sucursal'] }}</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Operador:</span>
                        <span class="data-value">{{ $box['user_str'] }}</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Fecha:</span>
                        <span class="data-value">{{ $box['start_date'] }} {{ $box['start_time'] }} -
                            {{ $box['end_time'] }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <br />



    @if ($received->count() > 0)
        @php
            $middle = ceil($received->count() / 2);
        @endphp
        <div style="width: 100%; clear: both;">
            <div class="bg-light-blue text-center" style="padding: 10px; margin: 2px 0;">
                <span style="color: #308CD2; font-size: 16px; font-weight: bold;">
                    Pagos recibidos (${{ number_format($box['total_received'], 2) }})
                </span>
            </div>
            <table>
                <thead>
                    <tr class="bg-blue" style="background-color: #3498db; color: white;">
                        <th class="text-left">Cliente</th>
                        <th class="text-right">Monto</th>
                        @if ($received->count() > 1)
                            <th class="separator"></th>
                            <th class="text-left">Cliente</th>
                            <th class="text-right">Monto</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $middle; $i++)
                        <tr style="border-bottom: 0.5px solid #eee;">
                            <td>
                                {{ $received[$i]['client_main_information']['client_id'] }} -
                                {{ $received[$i]['client_main_information']['client_name_with_fathers_names'] }}
                            </td>
                            <td class="text-right">
                                ${{ number_format($received[$i]['amount'], 2) }}
                            </td>
                            @if ($received->count() > 1)
                                <td class="separator"></td>
                                <td>
                                    @if (isset($received[$i + $middle]))
                                        {{ $received[$i + $middle]['client_main_information']['client_id'] }} -
                                        {{ $received[$i + $middle]['client_main_information']['client_name_with_fathers_names'] }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if (isset($received[$i + $middle]))
                                        ${{ number_format($received[$i + $middle]['amount'], 2) }}
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    @endif

    @if ($installations->count() > 0)
        @php
            $middle = ceil($installations->count() / 2);
        @endphp
        <div style="width: 100%; clear: both; margin-top: 20px;">
            <div class="bg-light-blue text-center" style="padding: 10px; margin: 2px 0;">
                <span style="color: #308CD2; font-size: 16px; font-weight: bold;">
                    Instalaciones y servicios técnicos (${{ number_format($box['total_technicals'], 2) }})
                </span>
            </div>
            <table>
                <thead>
                    <tr class="bg-blue" style="background-color: #3498db; color: white;">
                        <th class="text-left">Cliente</th>
                        <th class="text-right">Instalación</th>
                        <th class="text-right">Servicio</th>
                        <th class="text-right">Monto</th>
                        @if ($installations->count() > 1)
                            <th class="separator"></th>
                            <th class="text-left">Cliente</th>
                            <th class="text-right">Instalación</th>
                            <th class="text-right">Servicio</th>
                            <th class="text-right">Monto</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $middle; $i++)
                        <tr style="border-bottom: 0.5px solid #eee;">
                            <td class="text-left">
                                {{ $installations[$i]['client_str'] }}
                            </td>
                            <td class="text-right">
                                ${{ number_format($installations[$i]['installation_cost'], 2) }}
                            </td>
                            <td class="text-right">
                                ${{ number_format($installations[$i]['service_amount'], 2) }}
                            </td>
                            <td class="text-right">
                                ${{ number_format($installations[$i]['installation_cost'] + $installations[$i]['service_amount'], 2) }}
                            </td>
                            @if ($installations->count() > 1)
                                <td class="separator"></td>
                                <td>
                                    @if (isset($installations[$i + $middle]))
                                        {{ $installations[$i + $middle]['client_str'] }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if (isset($installations[$i + $middle]))
                                        ${{ number_format($installations[$i + $middle]['installation_cost'], 2) }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if (isset($installations[$i + $middle]))
                                        ${{ number_format($installations[$i + $middle]['service_amount'], 2) }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if (isset($installations[$i + $middle]))
                                        ${{ number_format($installations[$i + $middle]['installation_cost'] + $installations[$i + $middle]['service_amount'], 2) }}
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    @endif


    <div style="width: 100%; clear: both; margin-top: 20px">
        <div style="width: 70%; float: left; padding-right: 5px;">
            <div class="bg-light-blue text-center" style="padding: 10px; ">
                <span style="color: #308CD2; font-size: 16px; font-weight: bold;">
                    Observaciones
                </span>
            </div>
            <div style="width: 100%; clear: both;">
                @php
                    $middle = ceil($observations->count() / 2);
                @endphp
                <table style="border: 1px solid #ddd">
                    <tbody>
                        @if ($observations->count() > 1)
                            @for ($i = 0; $i < $middle; $i++)
                                <tr>
                                    <td style="border-top: 0px solid #fff; border-bottom: 0px solid #fff;"
                                        width="50%">
                                        {!! $observations[$i]['comment'] !!}</td>
                                    <td style="border-top: 0px solid #fff; border-bottom: 0px solid #fff;"
                                        width="50%">
                                        @if (isset($observations[$i + $middle]))
                                            {!! $observations[$i + $middle]['comment'] !!}
                                        @endif
                                    </td>
                                </tr>
                            @endfor
                        @else
                            <tr>
                                <td style="border-top: 0px solid #fff; border-bottom: 0px solid #fff;"
                                    width="50%; height: 114px;">
                                </td>
                                <td style="border-top: 0px solid #fff; border-bottom: 0px solid #fff;"
                                    width="50%; height: 114px;">

                                </td>
                            </tr>
                        @endif

                    </tbody>
                </table>
            </div>
        </div>
        <div style="width: 29%; float: left; vertical-aligment: middle">
            <table style="margin-top: 40px">
                <tr>
                    <td class="bg-gray bold text-right">Total de pagos recibidos:</td>
                    <td class="text-right">{{ number_format($box['total_received'], 2) }}</td>
                </tr>
                <tr>
                    <td class="bg-gray bold text-right">Total extras:</td>
                    <td class="text-right">{{ number_format($box['total_extras'], 2) }}</td>
                </tr>
                <tr>
                    <td class="bg-gray bold text-right">Total técnicos:</td>
                    <td class="text-right">{{ number_format($box['total_technicals'], 2) }}</td>
                </tr>
                <tr>
                    <td class="bg-gray bold text-right">Total proveedores:</td>
                    <td class="text-right">{{ number_format($box['total_proveedores'], 2) }}</td>
                </tr>
                <tr>
                    <td class="bg-gray bold text-right">Total neto:</td>
                    <td class="text-right">{{ number_format($box['total_net'], 2) }}</td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>
