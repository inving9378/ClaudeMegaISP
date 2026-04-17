<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">Registro</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<input type="text" value="{{ $object->id }}" id="trench_id" name="trench_id" hidden>
<input type="text" value="{{ $objectTable->type }}" id="trench_type" name="trench_id" hidden>
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
            <form id="update_trench_form">
                <input type="text" name="id" id="object_id" value="{{ $object->id }}" hidden>
                <input type="text" id="object_type" name="type" value="trench" hidden>
                <div class="row mb-2">
                    <label class="col-md-3 col-form-label  text-md-end" for="trench_name"><strong>Nombre</strong></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="trench_name" name="trench_name" value="{{ $object->name }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="trench_type" class="col-md-3 col-form-label form-label  text-md-end"><strong>Modelo</strong></label>
                    <div class="col-md-9">
                        <select class="form-select" name="type" id="trench_type" required>
                            <option value="{{ $object->type->id }}">{{ $object->type->model }}</option>
                            @foreach ($trenchTypes as $trenchType)
                                @if ($trenchType->id !== $object->type->id)
                                    <option value="{{ $trenchType->id }}">{{ $trenchType->model }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end"><strong>Proyecto</strong></label>
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
</div>
