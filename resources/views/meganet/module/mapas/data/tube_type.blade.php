<div class="modal-header">
    <div class="row align-items-center d-flex justify-content-between col-12">
        <div class="col">
            <h1 class="modal-title fs-5" >Actualizar tubería</h1>
        </div>
        <div class="col text-end px-0">
            <button type="button" class="btn btn-primary" onclick="loadCatalogModal('tube_type', 'types.tube_type')">Regresar</button>
        </div>
    </div>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='update_tube_type_form'>
                <div class="mb-3 px-2">
                    <div class="row mb-2">
                        <label for="type" class="col-md-2 col-form-label form-label  text-md-end">Tipo</label>
                        <div class="col-md-10">
                            <select class="form-select brand_select" name="type" id="tube_type" required>
                                <option value=""></option>
                                <option {{ $object->type == "No definido" ? 'selected' : '' }} value="No definido">No definido</option>
                                <option {{ $object->type == "Inoxidable" ? 'selected' : '' }} value="Inoxidable">Inoxidable</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="diameter" class="col-md-2 col-form-label form-label  text-md-end">Diámetro</label>
                        <div class="col-md-10">
                            <input class="form-control" type="number" step="0.1" min="0" id="tube_diameter" name="diameter" value="{{ $object->diameter }}" required>
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
