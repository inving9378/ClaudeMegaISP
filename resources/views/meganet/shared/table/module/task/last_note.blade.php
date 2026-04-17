<td>
    <span data-id="{{ $id }}" class="note-class cursor-pointer" data-bs-toggle="modal"
        data-bs-target="#note_{{ $id }}">
        {!! $note !!}
    </span>

    <div class="modal fade" id="note_{{ $id }}" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">Nota # {{ $id }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {!! $note !!}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</td>
