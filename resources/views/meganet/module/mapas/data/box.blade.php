<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">Caja</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
{{-- data-object --}}
<input type="text" value="{{ $object->id }}" id="object_id" hidden>
<input type="text" value="{{ $objectTable->type }}" id="object_type" hidden>


<div class="modal-body">
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
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="box_form">
                <input type="text" id="box_id" name="box_id" value="{{ $object->id }}" hidden>
                <div class="row mb-2">
                    <label for="box_type_id" class="col-md-3 col-form-label form-label text-md-end">Modelo</label>
                    <div class="col-md-9">
                        <select class="form-select" name="box_type_id" id="box_type_id" required>
                            <option value="{{ $object->box_type_id }}">{{ $object->type->model }}</option>
                            @foreach ($boxTypes as $boxType)
                                @if ($object->box_type_id !== $boxType->id)
                                    <option value="{{ $boxType->id }}">{{ $boxType->model }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="box_nomenclature" class="col-md-3 col-form-label form-label text-md-end">Nomenclatura</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="box_nomenclature" name="nomenclature" value="{{ $object->nomenclature }}" required>
                    </div>
                </div>
                @if (empty($object->point_id))
                    <div class="row mb-2">
                        <label for="site_box_input_latitude" class="col-md-3 col-form-label form-label  text-md-end"><strong>Latitud</strong></label>
                        <div class="col-md-9">
                            <input class="form-control" type="text" id="site_box_input_latitude" name="latitude" value="{{ $object->position->point->latitude }}" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="site_box_input_longitude" class="col-md-3 col-form-label form-label  text-md-end"><strong>Longitud</strong></label>
                        <div class="col-md-9">
                            <input class="form-control" type="text" id="site_box_input_longitude" name="longitude" value="{{ $object->position->point->longitude }}" required>
                        </div>
                    </div>
                @endif
                <div class="row mb-2">
                    <label for="site_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end"><strong>Proyecto</strong></label>
                    <div class="col-md-9">
                        <select class="form-select" name="map_proyect_id" id="rack_map_proyect_id" required>
                            <option value="{{ $object->mapProyect->id }}">{{ $object->mapProyect->name }}</option>
                            @foreach ($mapProyects as $mapProyect)
                                @if ($object->mapProyect->id !== $mapProyect->id)
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

    @if (!empty($object->point_id))
        <div class="card mb-3" style="max-width: 100%;">
            <div class="card-body rounded-4 px-0">
                <form id='create_map_link_form'>
                    <div class="collapse" id="map_links_table_collapse">
                        <div class="mb-3 px-2">
                            <input type="text" name="input_id" value="{{ $object->id }}" hidden>
                            <input type="text" name="input_type" value="{{ $objectTable->type }}" hidden>
                            <div class="row mb-2">
                                <label for="select_box_out_type" class="col-md-3 col-form-label form-label  text-md-end">Tipo</label>
                                <div class="col-md-9">
                                    <select class="form-select mb-2" id="select_box_out_type" name="output_type" required>
                                        @foreach ($positionableObjects as $positionableObject)
                                            <option value="{{ $positionableObject->type }}">{{ $positionableObject->label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <label for="select_box_output_id" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                                <div class="col-md-9">
                                    <select class="form-select mb-2" id="select_box_output_id" name="output_id" required></select>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">Guardar</button>
                            </div>
                        </div>
                    </div>
                </form>
                <table class="table table-striped table-hover mb-0 datatable border-top" id="map_links_table">
                    <thead>
                        <tr>
                            <th class="d-none">id</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Typo</th>
                            <th>Proyecto</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- box inputs --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="box_inputs_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Numero</th>
                        <th>Ruta</th>
                        <th>Conectado a</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    {{-- trays --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="trays_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Numero</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    {{-- splitters --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_splitter_form'>
                <div class="collapse" id="splitters_table_collapse">
                    <div class="mb-3 px-2">
                        <input type="text" name="box_id" value="{{ $object->id }}" hidden>
                        <div class="row mb-2">
                            <label for="splitter_number" class="col-md-3 col-form-label form-label text-md-end">Numero</label>
                            <div class="col-md-9">
                                <input class="form-control mb-2" type="number" id="splitter_number" name="number" required>
                            </div>
                            <label for="splitter_outputss" class="col-md-3 col-form-label form-label text-md-end">Salidas</label>
                            <div class="col-md-9">
                                <input class="form-control mb-2" type="number" id="splitter_outputs" name="outputs" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-responsive table-striped table-hover mb-0 datatable border-top" id="splitters_table" width="100%">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Numero</th>
                        <th>Salidas</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Ports --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="ports_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Equipo</th>
                        <th>Puerto</th>
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
</div>
