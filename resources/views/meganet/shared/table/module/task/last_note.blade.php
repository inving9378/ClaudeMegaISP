<td>
    {{-- Vista corta (2 líneas); la nota completa se abre en el modal. --}}
    <span data-id="{{ $id }}" class="note-class task-nota-corta cursor-pointer" data-bs-toggle="modal"
        data-bs-target="#note_{{ $id }}" title="Ver nota completa">
        {{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', (string) $note))))), 140) }}
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
