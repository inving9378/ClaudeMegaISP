<td class="text-center">
    @if(isset($parent_id) && isset($parent_route) && isset($submodule))
        @php
            $viewPermission = 'inventory_' . $group . '_view_' . $submodule;
        @endphp
        @can($viewPermission)
            <a class="mr-2" href="/{{ $parent_route }}/{{ $parent_id }}/{{ $submodule }}" data-toggle="tooltip" data-placement="top" title="Ver">
                <i class="fas fa-eye"></i>
            </a>
        @endcan
    @elseif(isset($href))
        <a class="mr-2" href="{{ $href }}" data-toggle="tooltip" data-placement="top" title="Ver">
            <i class="fas fa-eye"></i>
        </a>
    @elseif(isset($document))
        <a href="{{ $document }}" data-toggle="tooltip" data-placement="top" title="Ver Documento" target="_black">
            <i class="fas fa-eye"></i>
        </a>
    @endif

    @if(isset($documentslip))
        <a href="{{ $documentslip }}" data-toggle="tooltip" data-placement="top" title="Descargar Documento" target="_black">
            <i class="fas fa-file-pdf"></i>
        </a>
    @endif

    @php
        $editPermission = 'inventory_' . $group . '_edit_' . $submodule;
    @endphp
    @if(
        \Illuminate\Support\Facades\Auth::user()->can($editPermission) ||
        \Illuminate\Support\Facades\Auth::user()->isAdmin()
    )
        <a class="mr-2 uil-pen-modal" href="javascript:void(0);"
           toggle-modal="{{ $modal }}"
           id-item="{{ $id }}"
           @if(isset($parent_id)) parent-id="{{ $parent_id }}" @endif
           data-toggle="tooltip" data-placement="top" title="Editar">
            <i class="far fa-edit"></i>
        </a>
    @endif

    @php
        $deletePermission = 'inventory_' . $group . '_delete_' . $submodule;
    @endphp
    @if(
        \Illuminate\Support\Facades\Auth::user()->can($deletePermission) ||
        \Illuminate\Support\Facades\Auth::user()->isAdmin()
    )
        <a href="javascript:void(0);"
           class="btn-delete-item"
           id-item="{{ $id }}"
           @if(isset($parent_id)) parent-id="{{ $parent_id }}" @endif
           data-toggle="tooltip" data-placement="top" title="Borrar">
            <i class="fas fa-trash text-danger"></i>
        </a>
    @endif
</td>