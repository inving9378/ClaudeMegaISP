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
                <div class="col text-end">
                    <button type="button" class="btn btn-danger" onclick="destroyObject('{{ route('maps.'.$objectTable->type.'.destroy') }}', {{ $object->id }}, true)">
                        <img src="{{ asset('images/maps_icons/delete.svg') }}" alt="">
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="site_form">
                <input type="text" id="site_type" name="site_type" value="site" hidden>
                <input type="text" id="site_id" name="site_id" value="{{ $object->id }}" hidden>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label text-lg-end" for="modal_name"><strong>Nombre:</strong></label>
                    <div class="col-lg-9">
                        <input type="text" class="form-control" id="modal_name" name="modal_name" value="{{ $object->name }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label text-lg-end" for="modal_longitude"><strong>Longitude:</strong></label>
                    <div class="col-lg-9">
                        <input type="text" class="form-control" id="modal_longitude" name="modal_longitude" value="{{ $object->position->point->longitude }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label text-lg-end" for="modal_latitude"><strong>Latitude:</strong></label>
                    <div class="col-lg-9">
                        <input type="text" class="form-control" id="modal_latitude" name="modal_latitude" value="{{ $object->position->point->latitude }}"  required>
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
            <form id='create_rack_form'>
                <div class="collapse" id="racks_table_collapse">
                    <div class="mb-3 px-2">
                        <input type="text" id="site_id" name="site_id" value="{{ $object->id }}" hidden>
                        <div class="row mb-2">
                            <label for="rack_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                            <div class="col-md-9">
                                <input class="form-control" type="text" id="rack_name" name="name" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="rack_number" class="col-md-3 col-form-label form-label  text-md-end">Numero</label>
                            <div class="col-md-9">
                                <input class="form-control" type="number" id="rack_number" name="number" min="0" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="rack_description" class="col-md-3 col-form-label form-label  text-md-end">Descripción</label>
                            <div class="col-md-9">
                                <input class="form-control" type="text" id="rack_description" name="description" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="rack_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                            <div class="col-md-9">
                                <select class="form-select" name="map_proyect_id" id="rack_map_proyect_id" required>
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
            <table class="table table-responsive table-striped table-hover mb-0 datatable border-top" id="racks_table" width="100%">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                        <th>Numero</th>
                        <th>Descripción</th>
                        <th>Equipos</th>
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
