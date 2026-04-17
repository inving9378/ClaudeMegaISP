<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $object->name }}</h1>
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
                <div class="col-auto text-end px-0">
                    <button type="button" class="btn btn-danger" onclick="destroyObject('{{ route('maps.'.$objectTable->type.'.destroy') }}', {{ $object->id }}, true)">
                        <img src="{{ asset('images/maps_icons/delete.svg') }}" alt="">
                    </button>
                </div>
                <div class="col-auto text-end">
                    <button type="button" class="btn btn-primary" onclick="backObject( '{{ route('maps.getDataFormById') }}',{{ $object->site_id }}, 'site')">Site</button>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="rack_form">
                <input type="text" id="rack_id" name="rack_id" value="{{ $object->id }}" hidden>
                <div class="row mb-2">
                    <label class="col-md-3 col-form-label  text-md-end" for="rack_name"><strong>Nombre</strong></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="rack_name" name="rack_name" value="{{ $object->name }}">
                    </div>
                </div>
                <div class="row mb-2">
                    <label class="col-md-3 col-form-label  text-md-end" for="number"><strong>Number</strong></label>
                    <div class="col-md-9">
                        <input type="number" class="form-control" id="number" name="number" value="{{ $object->number }}" min="0">
                    </div>
                </div>
                <div class="row mb-2">
                    <label class="col-md-3 col-form-label  text-md-end" for="description"><strong>Descripción</strong></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" name="description" value="{{ $object->description }}">
                    </div>
                </div>
                <div class="row mb-2">
                    <label class="col-md-3 col-form-label  text-md-end" for="description"><strong>Site</strong></label>
                    <div class="col-md-9">
                        <select class="form-select" name="site_id" id="site_id">
                            <option value="{{ $object->site_id }}">{{ $object->site->name }}</option>
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
            <form id='create_passive_equipment_form'>
                <div class="collapse" id="passive_equipments_table_collapse">
                    <div class="mb-3 px-2">
                        <input type="text" id="passive_equipment_rack_id" name="rack_id" value="{{ $object->id }}" hidden>
                        <div class="row mb-2">
                            <label for="passive_equipment_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                            <div class="col-md-9">
                                <input class="form-control mb-2" type="text" id="passive_equipment_name" name="name">
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="passive_equipment_description" class="col-md-3 col-form-label form-label  text-md-end">Descripción</label>
                            <div class="col-md-9">
                                <input class="form-control mb-2" type="text" id="passive_equipment_description" name="description">
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="passive_equipment_type_id" class="col-md-3 col-form-label form-label  text-md-end">Modelo</label>
                            <div class="col-md-9">
                                <select class="form-select mb-2" name="type_id" id="passive_equipment_type_id">
                                    <option value=""></option>
                                    @foreach ($passiveEquipmentOptions as $passiveEquipmentOption)
                                        <option value="{{ $passiveEquipmentOption->id }}">{{ $passiveEquipmentOption->model }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="passive_equipment_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                            <div class="col-md-9">
                                <select class="form-select mb-2" name="map_proyect_id" id="passive_equipment_map_proyect_id">
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
            <table class="table table-striped table-hover mb-0 datatable border-top" id="passive_equipments_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Descripción</th>
                        <th>Proyecto</th>
                    </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body px-0">
            <form id='create_active_equipment_form'>
                <div class="collapse" id="active_equipments_table_collapse">
                    <div class="mb-3 px-2">
                        <div class="mb-3 px-2">
                            <input type="text" id="active_equipment_rack_id" name="rack_id" value="{{ $object->id }}" hidden>
                            <div class="row mb-2">
                                <label for="active_equipment_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                                <div class="col-md-9">
                                    <input class="form-control mb-2" type="text" id="active_equipment_name" name="name">
                                </div>
                            </div>
                            <div class="row mb-2">
                                <label for="active_equipment_description" class="col-md-3 col-form-label form-label  text-md-end">Descripción</label>
                                <div class="col-md-9">
                                    <input class="form-control mb-2" type="text" id="active_equipment_description" name="description">
                                </div>
                            </div>
                            <div class="row mb-2">
                                <label for="active_equipment_type_id" class="col-md-3 col-form-label form-label  text-md-end">Modelo</label>
                                <div class="col-md-9">
                                    <select class="form-select mb-2" name="type_id" id="active_equipment_type_id">
                                        <option value=""></option>
                                        @foreach ($activeEquipmentOptions as $activeEquipmentOption)
                                            <option value="{{ $activeEquipmentOption->id }}">{{ $activeEquipmentOption->model }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <label for="active_equipment_serial_number" class="col-md-3 col-form-label form-label  text-md-end">Serie</label>
                                <div class="col-md-9">
                                    <input class="form-control mb-2" type="text" id="active_equipment_serial_number" name="serial_number">
                                </div>
                            </div>
                            <div class="row mb-2">
                                <label for="active_equipment_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                                <div class="col-md-9">
                                    <select class="form-select mb-2" name="map_proyect_id" id="active_equipment_map_proyect_id" required>
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
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="active_equipments_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Descripción</th>
                        <th>Serie</th>
                        <th>Proyecto</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
