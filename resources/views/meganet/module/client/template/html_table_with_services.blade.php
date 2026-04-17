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
