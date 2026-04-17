<td>
    @can($group . '_edit_' . $submodule)
        <a class="mr-2" href="/{{ $module }}/editar/{{ $id }}" data-toggle="tooltip" data-placement="top"
            title="Editar"><i class="far fa-edit"></i></a>
    @endcan
    @can($group . '_delete_' . $submodule)
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
            title="Borrar"><i class="fas fa-trash"></i></a>
    @endcan

    @if (auth()->user()->can('client_edit_id') && isset($module) && $module == 'cliente')
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip"
            data-placement="top" title="Editar_id"><i class="fas fa-user-secret edit_id"></i></a>
    @endif
</td>
