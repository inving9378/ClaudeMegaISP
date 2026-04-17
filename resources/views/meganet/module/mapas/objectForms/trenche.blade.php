<div class="row mb-2">
    <label for="trenche_name" class="col-md-3 col-form-label form-label text-md-end">nombre</label>
    <div class="col-md-9">
        <input type="text" class="form-control" id="trenche_name" name="name">
    </div>
</div>
<div class="row mb-2">
    <label for="trenche_type" class="col-md-3 col-form-label form-label text-md-end">Tipo</label>
    <div class="col-md-9">
        <select class="form-select" name="trenche_type_id" id="trenche_type">
            @foreach ($types as $type)
                <option {{ isset($lastRecord)?$type->id == $lastRecord->trenche_type_id?'selected':null:null }} value="{{ $type->id }}">{{ $type->model }}</option>
            @endforeach
        </select>
    </div>
</div>
