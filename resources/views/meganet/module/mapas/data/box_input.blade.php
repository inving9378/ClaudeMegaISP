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
                    <button type="button" class="btn btn-primary" onclick="backObject( '{{ route('maps.getDataFormById') }}',{{ $object->box->id }}, '{{ $object->box->infoTable()->type }}')">{{ $object->box->infoTable()->label }}</button>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <input type="text" name="box_input_id" value="{{ $object->id }}" hidden>
            <div class="row mb-2">
                <label for="box_input_name" class="col-md-3 col-form-label form-label  text-md-end">Numero</label>
                <div class="col-md-9">
                    <input type="text" class="form-control" id="box_input_name" name="number" value="{{ $object->number }}">
                </div>
            </div>
        </div>
    </div>

    {{-- links --}}
    @include('meganet.module.mapas.auxViews.point')
</div>
