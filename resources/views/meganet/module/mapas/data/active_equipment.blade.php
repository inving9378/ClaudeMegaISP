<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $objectTable->label }}</h1>
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
                    <button type="button" class="btn btn-primary" onclick="backObject( '{{ route('maps.getDataFormById') }}',{{ $object->rack->id }}, 'rack')">Rack</button>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="active_equipment_form">
                <input type="text" name="active_equipment_id" value="{{ $object->id }}" hidden>
                <input type="text" name="table_type" value="{{ $objectTable->type }}" hidden>
                <div class="row mb-2">
                    <label for="active_equipment_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="active_equipment_name" name="active_equipment_name" value="{{ $object->name }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="description" class="col-md-3 col-form-label form-label  text-md-end">Descripción</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="description" name="description" value="{{ $object->description }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="serial_number" class="col-md-3 col-form-label form-label  text-md-end">Serie</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="serial_number" name="serial_number" value="{{ $object->serial_number }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="active_equipment_type_id" class="col-md-3 col-form-label form-label  text-md-end">Modelo</label>
                    <div class="col-md-9">
                        <select class="form-select" name="type_id" id="active_equipment_type_id" required>
                            <option value="{{ $object->type_id }}">{{ $object->type->model }}</option>
                            @foreach ($activeEquipmentOptions as $activeEquipmentOption)
                                @if ($object->type_id !== $activeEquipmentOption->id)
                                    <option value="{{ $activeEquipmentOption->id }}">{{ $activeEquipmentOption->model }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="active_equipment_rack_id" class="col-md-3 col-form-label form-label  text-md-end">Rack</label>
                    <div class="col-md-9">
                        <select class="form-select" name="active_equipment_rack_id" id="active_equipment_rack_id">
                            <option value="{{ $object->rack_id }}">{{ $object->rack->name }}</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="active_equipment_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                    <div class="col-md-9">
                        <select class="form-select" name="map_proyect_id" id="active_equipment_map_proyect_id" required>
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
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_card_form'>
                <div class="collapse" id="cards_table_collapse">
                    <input type="text" value="{{ $object->id }}" name="active_equipment_id"  hidden>
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="card_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                            <div class="col-md-9">
                                <input class="form-control" type="text" id="card_name" name="name" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="card_type" class="col-md-3 col-form-label form-label  text-md-end">Tipo</label>
                            <div class="col-md-9">
                                <select class="form-select" name="type" id="card_type" onchange="changeCardType(this)" required>
                                    <option value=""></option>
                                    @foreach ($cartTypes as $cartType)
                                        <option value="{{ $cartType }}">{{ $cartType }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="number_gibic_ports" class="col-md-3 col-form-label form-label text-md-end">gibic C+</label>
                            <div class="col-md-9">
                                <input class="form-control" type="number" id="number_gibic_ports" name="number_gibicC+_ports" disabled>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="number_gibicC++_ports" class="col-md-3 col-form-label form-label text-md-end">gibic C++</label>
                            <div class="col-md-9">
                                <input class="form-control" type="number" id="number_gibicC++_ports" name="number_gibicC++_ports" disabled>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="number_SFP_ports" class="col-md-3 col-form-label form-label  text-md-end">SFP</label>
                            <div class="col-md-9">
                                <input class="form-control" type="number" id="number_SFP_ports" name="number_SFP_ports" disabled>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="number_SFP+_ports" class="col-md-3 col-form-label form-label  text-md-end">SFP+</label>
                            <div class="col-md-9">
                                <input class="form-control" type="number" id="number_SFP+_ports" name="number_SFP+_ports" disabled>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="number_ethernet_ports" class="col-md-3 col-form-label form-label  text-md-end">ethernet</label>
                            <div class="col-md-9">
                                <input class="form-control" type="number" id="number_ethernet_ports" name="number_ethernet_ports" disabled>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="card_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                            <div class="col-md-9">
                                <select class="form-select" name="map_proyect_id" id="card_map_proyect_id" required>
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
            <table class="table table-striped table-hover mb-0 datatable border-top" id="cards_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Puertos</th>
                        <th>Proyecto</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
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
                            <div class="col-md-9">
                                <input class="form-control" type="text" id="port_number" name="number" required>
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
                        <th>Tipo</th>
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
