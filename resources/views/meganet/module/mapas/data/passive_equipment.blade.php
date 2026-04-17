<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $objectTable->label }}</h1>
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
            <form id="passive_equipment_form">
                <input type="text" name="id" value="{{ $object->id }}" hidden>
                <input type="text" name="input_type" value="{{ $objectTable->type }}" hidden>
                <div class="row mb-2">
                    <label for="passive_equipment_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                    <div class="col-md-9">
                        <input class="form-control" type="text" id="passive_equipment_name" name="name" value="{{ $object->name }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="passive_equipment_description" class="col-md-3 col-form-label form-label  text-md-end">Descripción</label>
                    <div class="col-md-9">
                        <input class="form-control" type="text" id="passive_equipment_description" name="description" value="{{ $object->description }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="passive_equipment_type_id" class="col-md-3 col-form-label form-label  text-md-end">Modelo</label>
                    <div class="col-md-9">
                        <select class="form-select" name="type_id" id="passive_equipment_type_id" required>
                            <option value="{{ $object->type_id }}">{{ $object->type->model }}</option>
                            @foreach ($passiveEquipmentOptions as $passiveEquipmentOption)
                                @if ($object->type_id !== $passiveEquipmentOption->id)
                                    <option value="{{ $passiveEquipmentOption->id }}">{{ $passiveEquipmentOption->model }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="passive_equipment_rack_id" class="col-md-3 col-form-label form-label  text-md-end">Rack</label>
                    <div class="col-md-9">
                        <select class="form-select" name="rack_id" id="rack_id">
                            <option value="{{ $object->rack_id }}">{{ $object->rack->name }}</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="passive_equipment_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                    <div class="col-md-9">
                        <select class="form-select" name="map_proyect_id" id="passive_equipment_map_proyect_id" required>
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
    {{-- fiber connections --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="ports_table_fibers">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Numero</th>
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

    {{-- inputs connections --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="ports_table_inputs">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Numero</th>
                        <th>Equipo</th>
                        <th>Puerto</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    {{-- special port table --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="ports_special_table">
                <thead>
                    <tr>
                        <th>fila</th>
                        <th>
                            <svg style="color: #0000ff; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #ffa500; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #00ff00; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #a52a2a; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #808080; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #ffffff; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #ff0000; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #000000; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #ffff00; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #ee82ee; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #ffc0cb; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                        <th>
                            <svg style="color: #00ffff; border: 1px solid #000;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-square-fill" viewBox="0 0 16 16">
                                <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2z"/>
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
