<td>
    @if (
        \Illuminate\Support\Facades\Auth::user()->isAdmin() ||
            \Illuminate\Support\Facades\Auth::user()->can('supervision_store'))
        <a class="mr-2" href="javascript:void(0);" toggle-modal="{{ $modal }}" id-item="{{ $id }}"
            data-toggle="tooltip" data-placement="top" title="Editar"><i class="far fa-edit uil-pen-modal"></i></a>
    @endif
    @if (\Illuminate\Support\Facades\Auth::user()->isAdmin())
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip"
            data-placement="top" title="Borrar"><i class="fas fa-trash"></i></a>
    @endif
</td>
