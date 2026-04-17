<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $object->name }}</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

{{-- data-object --}}
<input type="text" value="{{ $object->id }}" id="object_id" hidden>
<input type="text" value="{{ $objectTable->type }}" id="object_type" hidden>

<div class="modal-body">

    {{-- update form --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="mb-0">Información</h6>
                </div>
                <div class="col text-end">
                    <button type="button" class="btn btn-danger" onclick="destroyObject('{{ route('maps.'.$objectTable->type.'.destroy') }}', {{ $object->id }}, true)">
                        <img src="{{ asset('images/maps_icons/delete.svg') }}" alt="">
                    </button>
                </div>
                <div class="col-auto text-end">
                    <button type="button" class="btn btn-primary" onclick="backObject( '{{ route('maps.getDataFormById') }}',{{ $object->box_id }}, 'box')">Caja</button>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="box_form">
                <input type="text" name="splitter_id" value="{{ $object->id }}" hidden>
                <div class="row mb-2">
                    <label for="splitter_number" class="col-md-3 col-form-label form-label text-md-end">Numero</label>
                    <div class="col-md-9">
                        <input class="form-control mb-2" type="number" id="splitter_number" name="number" value="{{ $object->number }}" required>
                    </div>
                    <label for="splitter_outputss" class="col-md-3 col-form-label form-label text-md-end">Salidas</label>
                    <div class="col-md-9">
                        <input class="form-control mb-2" type="number" id="splitter_outputs" name="outputs" value="{{ $object->outputs }}" required>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success">Actualizar!</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ports in --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="ports_table_in">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Ruta</th>
                        <th>Buffer</th>
                        <th>Fibra</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Ports out --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="ports_table_out">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Equipo</th>
                        <th>Puerto</th>
                        <th>Entrada</th>
                        <th>Buffer</th>
                        <th>Fibra</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
