<td style="background: red">

    @if(!$is_custom)
    <a class="mr-4" href="javascript:void(0);" toggle-modal="modalchange_item_stock" id-item="{{ $id }}"
    data-toggle="tooltip" data-placement="top" title="Aumentar o disminuir stock"><i
        class="fas fa-exchange-alt change_item_stock" style="transform: rotate(90deg);"></i></a>
    @endif

    @if (
        \Illuminate\Support\Facades\Auth::user()->can($group . '_edit_' . $submodule) ||
            \Illuminate\Support\Facades\Auth::user()->isAdmin())
        <a class="mr-2" href="javascript:void(0);" toggle-modal="{{ $modal }}" id-item="{{ $id_item }}"
            data-toggle="tooltip" data-placement="top" title="Editar"><i class="far fa-edit uil-pen-modal"></i></a>
    @endif
</td>
