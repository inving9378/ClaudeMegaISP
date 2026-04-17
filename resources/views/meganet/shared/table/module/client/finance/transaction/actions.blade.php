<td>
    @can('client_billing_transaction_edit')
        <a class="mr-2" href="/{{ $module }}/editar/{{ $id }}" data-toggle="tooltip" data-placement="top"
            title="Editar"><i class="far fa-edit"></i></a>
    @endcan
    @can('client_billing_transaction_delete')
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
            title="Borrar"><i class="fas fa-trash"></i></a>
    @endcan
</td>
