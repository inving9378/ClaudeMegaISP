<td>
    @if ($archived && auth()->user()->can('task_archive_task'))
        <a class="mr-2"  href="javascript:void(0);" class="unarchive" id-item="{{ $id }}" data-toggle="tooltip"
            data-placement="top" title="Dejar de archivar"><i class="fas fa-undo unarchive"></i></a>
    @endif
    @can($group . '_edit_' . $submodule)
        <a class="mr-2" href="/{{ $module }}/editar/{{ $id }}" data-toggle="tooltip" data-placement="top"
            title="Editar"><i class="far fa-edit"></i></a>
    @endcan
    @can($group . '_delete_' . $submodule)
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip"
            data-placement="top" title="Borrar"><i class="fas fa-trash"></i></a>
    @endcan

</td>
