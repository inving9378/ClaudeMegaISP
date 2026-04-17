{{-- card header --}}
<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $objectTable->label }}</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

{{-- data-object --}}
<input type="text" value="{{ $object->id }}" id="object_id" hidden>
<input type="text" value="{{ $objectTable->type }}" id="object_type" hidden>


<div class="modal-body">
    {{-- update object --}}
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
            <form id="point_assignment_form">
                <input type="text" value="{{ $object->id }}" name="point_id" hidden>
                <div class="row mb-2">
                    <label for="point_object_type" class="col-md-3 col-form-label form-label  text-md-end">Numero</label>
                    <div class="col-md-9">
                        <input class="form-control" type="text" name="point_id" value="{{ $object->id }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="point_object_type" class="col-md-3 col-form-label form-label  text-md-end">Tipo de objeto</label>
                    <div class="col-md-9">
                        <select class="form-select" aria-label="Default select example" id="point_object_type" name="object_type" required>
                            <option selected></option>
                            @foreach ($positionableObjects as $positionableObject)
                                @if ($positionableObject->label != 'caja')
                                    <option value="{{ $positionableObject->model_class }}">{{ $positionableObject->label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="point_assignment_object_form">
                </div>
                <div class="row mb-2">
                    <label for="site_point_input_latitude" class="col-md-3 col-form-label form-label  text-md-end"><strong>Latitud</strong></label>
                    <div class="col-md-9">
                        <input class="form-control" type="text" id="site_point_input_latitude" name="latitude" value="{{ $object->position->point->latitude }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="site_point_input_longitude" class="col-md-3 col-form-label form-label  text-md-end"><strong>Longitud</strong></label>
                    <div class="col-md-9">
                        <input class="form-control" type="text" id="site_point_input_longitude" name="longitude" value="{{ $object->position->point->longitude }}" required>
                    </div>
                </div>
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

    {{-- box --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body px-0">
            <form id='create_box_form'>
                <div class="collapse" id="boxes_table_collapse">
                    <div class="mb-3 px-2">
                        <input type="text" value="{{ $object->id }}" name="point_id" hidden>
                        @include('meganet.module.mapas.objectForms.box')
                        <div class="row mb-1">
                            <label for="box_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                            <div class="col-md-9">
                                <select class="form-select mb-2" name="map_proyect_id" id="box_map_proyect_id" required>
                                    <option value=""></option>
                                    @foreach ($mapProyects as $mapProyect)
                                        <option value="{{ $mapProyect->id }}">{{ $mapProyect->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="boxes_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nomenclatura</th>
                        <th>Modelo</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Proyecto</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    {{-- accesories --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body px-0">
            <form id='create_point_accessory_form'>
                <div class="collapse" id="point_accessory_table_collapse">
                    <div class="mb-3 px-2">
                        <input type="text" value="{{ $object->id }}" name="point_id" hidden>
                        <div class="row mb-2">
                            <label for="point_accessories_name" class="col-md-3 col-form-label form-label text-md-end">Nombre</label>
                            <div class="col-md-9">
                                <select class="form-select" name="name" id="point_accessories_name" required>
                                    <option value=""></option>
                                    @foreach ($pointAccessoryTypes as $pointAccessoryType)
                                        <option value="{{ $pointAccessoryType }}">{{ $pointAccessoryType }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="lenght" class="col-md-3 col-form-label form-label text-md-end">Aumento (M)</label>
                            <div class="col-md-9">
                                <input type="number" class="form-control" id="lenght" name="lenght" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="point_accessory_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                            <div class="col-md-9">
                                <select class="form-select" name="map_proyect_id" id="point_accessories_map_proyect_id" required>
                                    <option value=""></option>
                                    @foreach ($mapProyects as $mapProyect)
                                        <option value="{{ $mapProyect->id }}">{{ $mapProyect->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="point_accessories_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Largo (M)</th>
                        <th>Proyecto</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    {{-- links --}}
    @include('meganet.module.mapas.auxViews.point')
</div>
