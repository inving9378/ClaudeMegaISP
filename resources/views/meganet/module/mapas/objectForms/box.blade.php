<div class="row mb-2">
    <label for="box_type_id" class="col-md-3 col-form-label form-label text-md-end">Modelo</label>
    <div class="col-md-9">
        <select class="form-select" name="box_type_id" id="box_type_id" required>
            <option value=""></option>
            @foreach ($boxTypes as $boxType)
                <option {{ isset($lastRecord)?$boxType->id == $lastRecord->box_type_id?'selected':null:null }} value="{{ $boxType->id }}">{{ $boxType->model }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row mb-2">
    <label for="box_nomenclature" class="col-md-3 col-form-label form-label text-md-end">Nomenclatura</label>
    <div class="col-md-9">
        <input type="text" value="{{ isset($lastRecord)?$lastRecord->nomenclature:null }}" class="form-control" id="box_nomenclature" name="nomenclature" required>
    </div>
</div>
