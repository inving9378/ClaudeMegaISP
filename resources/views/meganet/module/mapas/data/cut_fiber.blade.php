<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">Corte de fibra</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    {{-- update object --}}
    <div class="card" style="max-width: 100%;">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="mb-0">Información</h6>
                </div>
                <div class="col text-end">
                    <button type="button" class="btn btn-danger"  onclick="destroyObject('{{ route('maps.'.$objectTable->type.'.destroy') }}', {{ $object->id }}, true)">
                        <img src="{{ asset('images/maps_icons/delete.svg') }}" alt="">
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id='update_fiber_cut_form'>
                <div class="row mb-2">
                    <label for="fiber_cut_description" class="col-md-3 col-form-label form-label text-md-end">Descripción</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="fiber_cut_description" name="description" value="{{ $object->description }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="fiber_cut_date" class="col-md-3 col-form-label form-label text-md-end">Fecha</label>
                    <div class="col-md-9">
                        <input type="date" class="form-control" id="fiber_cut_date" name="date" value="{{ $object->date }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="fiber_cut_type" class="col-md-3 col-form-label form-label text-md-end">Tipo</label>
                    <div class="col-md-9">
                        <select class="form-select" name="type" id="fiber_cut_type" required>
                            <option value=""></option>
                            <option {{ $object->type == "visible"? "selected" : null }} value="visible">visible</option>
                            <option {{ $object->type == "no visible"? "selected" : null }} value="no visible">no visible</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="fiber_cut_power" class="col-md-3 col-form-label form-label text-md-end">Poder</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="fiber_cut_power" name="power" value="{{ $object->power }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="port_map_proyect_id" class="col-md-3 col-form-label form-label text-md-end">Metros</label>
                    <div class="col-md-9">
                        <input type="number" class="form-control" name="meter" value="{{ $object->meter }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="site_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end"><strong>Proyecto</strong></label>
                    <div class="col-md-9">
                        <select class="form-select" name="map_proyect_id" id="rack_map_proyect_id" required>
                            @foreach ($mapProyects as $mapProyect)
                                <option {{ $object->map_proyect_id == $mapProyect->id? "selected" : null }} value="{{ $mapProyect->id }}">{{ $mapProyect->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <input id="object_id" name="object_id" value="{{ $object->id }}" hidden>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success">actualizar!</button>
                </div>
            </form>
        </div>
    </div>
</div>
