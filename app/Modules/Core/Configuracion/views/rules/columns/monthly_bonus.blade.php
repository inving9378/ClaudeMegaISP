<b>Descuento IVA: </b>{{ $commission['iva'] == 1 ? 'Si' : 'No' }}<br>
<b>Criterios:</b>
<ul>
    @foreach ($commission['bonus'] as $b)
        <li>
            ${{ $b['bonus'] }} si {{ $b['sales'] }} ventas
        </li>
    @endforeach
</ul>
