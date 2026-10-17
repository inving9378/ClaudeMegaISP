<td> @if(auth()->user() && auth()->user()->can('invoice_mark_as_paid_invoice'))
        <span id-item="{{ $id }}" client-id="{{ $clientId }}" class="badge-invoice-{{ $statusClass }} {{ ! in_array($statusClass, ['Pagada', 'Cancelada'], true) ? 'cursor-pointer invoice-paid' : '' }}">{{ $status }}</span>
    @endif
</td>
