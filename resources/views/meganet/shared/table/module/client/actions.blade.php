<td>
    @if(in_array($group . '_edit_' . $submodule, $userPermissions))
        <a class="mr-2" href="/{{ $module }}/editar/{{ $id }}" data-toggle="tooltip" data-placement="top"
            title="Editar"><i class="far fa-edit"></i></a>
    @endif
    @if(in_array($group . '_delete_' . $submodule, $userPermissions))
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip" data-placement="top"
            title="Borrar"><i class="fas fa-trash"></i></a>
    @endif

    @if (in_array('client_edit_id', $userPermissions) && isset($module) && $module == 'cliente')
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip"
            data-placement="top" title="Editar_id"><i class="fas fa-user-secret edit_id"></i></a>
    @endif
</td>
