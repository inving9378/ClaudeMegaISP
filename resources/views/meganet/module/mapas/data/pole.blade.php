<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">Poste</h1>
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
            <form id="pole_form">
                <input type="text" id="pole_id" name="pole_id" value="{{ $object->id }}" hidden>
                <div class="row mb-2">
                    <label class="col-md-3 col-form-label  text-md-end" for="pole_description"><strong>Descripción</strong></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="pole_description" name="pole_description" value="{{ $object->description }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="site_pole_height" class="col-md-3 col-form-label form-label  text-md-end"><strong>Altura</strong></label>
                    <div class="col-md-9">
                        <input type="number" class="form-control" id="site_pole_height" name="height" value="{{ $object->height }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="site_pole_type" class="col-md-3 col-form-label form-label  text-md-end"><strong>Tipo</strong></label>
                    <div class="col-md-9">
                        <select class="form-select" name="type" id="site_pole_type" required>
                            <option value="{{ $object->type }}">{{ $object->type }}</option>
                            @foreach ($types as $type)
                                @if ($type !== $object->type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="site_pole_tension" class="col-md-3 col-form-label form-label  text-md-end"><strong>Tensión</strong></label>
                    <div class="col-md-9">
                        <select class="form-select" name="tension" id="site_pole_tension" required>
                            <option value="{{ $object->tension }}">{{ $object->tension }}</option>
                            @foreach ($tensions as $tension)
                                @if ($tension !== $object->tension)
                                    <option value="{{ $tension }}">{{ $tension }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="site_pole_input_latitude" class="col-md-3 col-form-label form-label  text-md-end"><strong>Latitud</strong></label>
                    <div class="col-md-9">
                        <input class="form-control" type="text" id="site_pole_input_latitude" name="latitude" value="{{ $object->position->point->latitude }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="site_pole_input_longitude" class="col-md-3 col-form-label form-label  text-md-end"><strong>Longitud</strong></label>
                    <div class="col-md-9">
                        <input class="form-control" type="text" id="site_pole_input_longitude" name="longitude" value="{{ $object->position->point->longitude }}" required>
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

    {{-- links --}}
    @include('meganet.module.mapas.auxViews.point')

    {{-- pole_accessories --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_pole_accessory_form'>
                <div class="collapse" id="pole_accessory_table_collapse">
                    <div class="mb-3 px-2">
                        <input type="text" name="pole_id" value="{{ $object->id }}" hidden>
                        <input type="text" name="input_type" value="{{ $objectTable->type }}" hidden>
                        <div class="row mb-2">
                            <label for="pole_accessory_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                            <div class="col-md-9">
                                <select class="form-select" id="pole_accessory_name" name="name" required>
                                    @foreach ($poleAccessoryTypes as $poleAccessoryType)
                                        <option value="{{ $poleAccessoryType }}">{{ $poleAccessoryType }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="pole_accessory_amount" class="col-md-3 col-form-label form-label  text-md-end">Cantidad</label>
                            <div class="col-md-9">
                                <input class="form-control" type="number" id="pole_accessory_amount" name="amount" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="pole_accessory_observations" class="col-md-3 col-form-label form-label  text-md-end">Observaciones</label>
                            <div class="col-md-9">
                                <input class="form-control" type="text" id="pole_accessory_observations" name="observations" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="box_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                            <div class="col-md-9">
                                <select class="form-select" name="map_proyect_id" id="box_map_proyect_id" required>
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
            <table class="table table-striped table-hover mb-0 datatable border-top" id="pole_accessories_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Cantidad</th>
                        <th>Observaciones</th>
                        <th>Proyecto</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
