<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $objectTable->label }}</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<input type="text" value="{{ $object->id }}" id="card_id"  hidden>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="mb-0">Información</h6>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="card_form">
                <input type="text" name="card_id" value="{{ $object->id }}" hidden>
                <div class="row mb-2">
                    <label for="card_name" class="col-md-2 col-form-label form-label  text-md-end">Nombre</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control" id="card_name" name="card_name" value="{{ $object->name }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="passive_equipment_map_proyect_id" class="col-md-2 col-form-label form-label  text-md-end">Proyecto</label>
                    <div class="col-md-10">
                        <select class="form-select mb-2" name="map_proyect_id" id="passive_equipment_map_proyect_id" required>
                            <option value="{{ $object->map_proyect_id }}">{{ $object->mapProyect->name }}</option>
                            @foreach ($mapProyects as $mapProyect)
                                @if ($mapProyect->id !== $object->map_proyect_id)
                                    <option value="{{ $mapProyect->id }}">{{ $mapProyect->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success">Actualizar!</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_port_form'>
                <div class="collapse" id="ports_table_collapse">
                    <input type="text" value="{{ $object->id }}" name="portable_id" hidden>
                    <input type="text" value="{{ $objectTable->type }}" name="portable_type" hidden>
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="port_number" class="col-md-2 col-form-label form-label  text-md-end">Nombre</label>
                            <div class="col-md-10">
                                <input class="form-control mb-2" type="text" id="port_number" name="number" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="ports_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Equipo</th>
                        <th>Puerto</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
