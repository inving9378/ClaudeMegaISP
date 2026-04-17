<div class="modal-header">
    <div class="row align-items-center d-flex justify-content-between col-12">
        <div class="col">
            <h1 class="modal-title fs-5" >Actualizar registro</h1>
        </div>
        <div class="col text-end px-0">
            <button type="button" class="btn btn-primary" onclick="loadCatalogModal('trench_type', 'types.trench_type')">Regresar</button>
        </div>
    </div>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='update_trench_type_form'>
                <div class="mb-1 px-2">
                    <div class="row mb-2">
                        <label for="trench_type_model" class="col-md-2 col-form-label form-label  text-md-end">Modelo</label>
                        <div class="col-md-10">
                            <input class="form-control" type="text" id="trench_type_model" name="model" value="{{ $object->model }}" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="width" class="col-md-2 col-form-label form-label  text-md-end">Ancho</label>
                        <div class="col-md-10">
                            <input class="form-control" type="number" step="0.1" min="0" id="trench_type_width" name="width" value="{{ $object->width }}" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="lenght" class="col-md-2 col-form-label form-label  text-md-end">Largo</label>
                        <div class="col-md-10">
                            <input class="form-control" type="number" step="0.1" min="0" id="trench_type_lenght" name="lenght" value="{{ $object->lenght }}" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="depth" class="col-md-2 col-form-label form-label  text-md-end">Profundidad</label>
                        <div class="col-md-10">
                            <input class="form-control" type="number" step="0.1" min="0" id="trench_type_depth" name="depth" value="{{ $object->depth }}" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="brand_id" class="col-md-2 col-form-label form-label  text-md-end">Marca</label>
                        <div class="col-md-10">
                            <select class="form-select brand_select" name="brand_id" id="trench_type_brand_id" >
                                <option value="{{ $object->brand_id }}">{{ $object->brand->name }}</option>
                            </select>
                        </div>
                    </div>
                    <input id="object_id" name="object_id" value="{{ $object->id }}" hidden>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
