<div class="row mb-2">
    <label for="pole_description" class="col-md-3 col-form-label form-label text-md-end">Descripción</label>
    <div class="col-md-9">
        <input type="text" class="form-control" id="pole_description" name="description">
    </div>
</div>
<div class="row mb-2">
    <label for="pole_height" class="col-md-3 col-form-label form-label text-md-end">Altura</label>
    <div class="col-md-9">
        <input type="number" class="form-control" value="{{ isset($lastRecord)?$lastRecord->height: 7 }}" id="pole_height" name="height">
    </div>
</div>
<div class="row mb-2">
    <label for="pole_type" class="col-md-3 col-form-label form-label text-md-end">Tipo</label>
    <div class="col-md-9">
        <select class="form-select" name="type" id="pole_type">
            @foreach ($types as $type)
                <option {{ isset($lastRecord)?$type == $lastRecord->type?'selected':null:null }} value="{{ $type }}">{{ $type }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row mb-2">
    <label for="pole_tension" class="col-md-3 col-form-label form-label text-md-end">Tensión</label>
    <div class="col-md-9">
        <select class="form-select" name="tension" id="pole_tension">
            @foreach ($tensions as $tension)
                <option {{ isset($lastRecord)?$tension == $lastRecord->tension?'selected':null:null }} value="{{ $tension }}">{{ $tension }}</option>
            @endforeach
        </select>
    </div>
</div>
