<b>Comisión inicial: </b>{{ $commission['initial'] }}%<br>
<b>Ventas mínima: </b>{{ $commission['sales'] }}<br>
<b>Descuento IVA: </b>{{ $commission['iva'] == 1 ? 'Si' : 'No' }}<br>
<b>Criterios:</b>
<ul>
    @foreach ($commission['bonus'] as $b)
        <li>
            {{ $b['commission'] }}% si contrato de
            {{ $contracts->firstWhere('id', $b['contract'])['name'] }}
        </li>
    @endforeach
</ul>
