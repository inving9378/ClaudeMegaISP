<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $objectTable->label }}</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<input type="text" value="{{ $object->id }}" id="point_accessory_id" hidden>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="mb-0">Información</h6>
                </div>
                <div class="col-auto text-end px-0">
                    <button type="button" class="btn btn-danger" onclick="destroyObject('{{ route('maps.'.$objectTable->type.'.destroy') }}', {{ $object->id }}, true)">
                        <img src="{{ asset('images/maps_icons/delete.svg') }}" alt="">
                    </button>
                </div>
                <div class="col-auto text-end">
                    <button type="button" class="btn btn-primary" onclick="backObject( '{{ route('maps.getDataFormById') }}',{{ $object->point->id }}, 'point')">Punto</button>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="point_accessory_form">
                <input type="text" name="id" value="{{ $object->id }}" hidden>
                <input type="text" name="input_type" value="{{ $objectTable->type }}" hidden>
                <div class="row mb-2">
                    <label for="point_accessory_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                    <div class="col-md-9">
                        <select class="form-select" name="name" id="point_accessory_name" required>
                            <option value="{{ $object->name }}">{{ $object->name }}</option>
                            @foreach ($pointAccessoryTypes as $pointAccessoryType)
                                @if ($object->name !== $pointAccessoryType)
                                    <option value="{{ $pointAccessoryType }}">{{ $pointAccessoryType }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="point_accessory_initial_meter" class="col-md-3 col-form-label form-label  text-md-end">Metro inicial</label>
                    <div class="col-md-9">
                        <input class="form-control" type="number" id="point_accessory_initial_meter" name="initial_meter" value="{{ $object->initial_meter }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="point_accessory_final_meter" class="col-md-3 col-form-label form-label  text-md-end">Metro final</label>
                    <div class="col-md-9">
                        <input class="form-control" type="number" id="point_accessory_final_meter" name="final_meter" value="{{ $object->final_meter }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="point_accessory_lenght" class="col-md-3 col-form-label form-label  text-md-end">Largo</label>
                    <div class="col-md-9">
                        <input class="form-control" type="number" id="point_accessory_lenght" name="lenght" value="{{ $object->lenght }}" readonly>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="point_accessory_rack_id" class="col-md-3 col-form-label form-label  text-md-end">Punto</label>
                    <div class="col-md-9">
                        <select class="form-select" name="rack_id" id="rack_id">
                            <option value="{{ $object->point->id }}">{{ $object->point->id }}</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="point_accessory_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                    <div class="col-md-9">
                        <select class="form-select" name="map_proyect_id" id="point_accessory_map_proyect_id" required>
                            <option value="{{ $object->map_proyect_id }}">{{ $object->mapProyect->name }}</option>
                            @foreach ($mapProyects as $mapProyect)
                                @if ($object->map_proyect_id !== $mapProyect->id)
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
</div>
