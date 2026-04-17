<td>
    <a href="{{ $document }}" data-toggle="tooltip" data-placement="top" title="Ver Documento" target="_black"><i
            class="fas fa-eye"></i></a>


    @if (isset($edit) && $edit)
        <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip"
            data-placement="top" title="edit"><i class="far fa-edit uil-pen-modal"></i></a>
    @endif

    <a class="" href="javascript:void(0);" id-item="{{ $id }}" data-toggle="tooltip"
        data-placement="top" title="Borrar"><i class="fas fa-trash"></i></a>

</td>
