<div class="modal-header">
    <div class="row align-items-center d-flex justify-content-between col-12">
        <div class="col">
            <h1 class="modal-title fs-5" >Actualizar caja</h1>
        </div>
        <div class="col text-end px-0">
            <button type="button" class="btn btn-primary" onclick="loadCatalogModal('box_type', 'types.box_type')">Regresar</button>
        </div>
    </div>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='update_box_type_form'>
                <div class="mb-1 px-2">
                    <div class="row mb-2">
                        <label for="box_type_model" class="col-md-3 col-form-label form-label  text-md-end">Modelo</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="box_type_model" value="{{ $object->model }}" name="model" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="box_type_type" class="col-md-3 col-form-label form-label  text-md-end">Tipo</label>
                        <div class="col-md-9">
                            <select class="form-select" name="type" id="box_type_type" required>
                                <option value=""></option>
                                <option {{ $object->type == "Empalme" ? 'selected' : '' }} value="Empalme">Empalme</option>
                                <option {{ $object->type == "Primer nivel" ? 'selected' : '' }} value="Primer nivel">Primer nivel</option>
                                <option {{ $object->type == "Segundo nivel" ? 'selected' : '' }} value="Segundo nivel">Segundo nivel</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="box_type_brand_id" class="col-md-3 col-form-label form-label  text-md-end">Marca</label>
                        <div class="col-md-9">
                            <select class="form-select brand_select" name="brand_id" id="box_type_brand_id">
                                <option value="{{ $object->brand_id }}">{{ $object->brand->name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="box_type_inputs" class="col-md-3 col-form-label form-label  text-md-end">Entradas</label>
                        <div class="col-md-9">
                            <input type="number" class="form-control" value="{{ $object->inputs }}" id="box_type_inputs" name="inputs" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="box_type_trays" class="col-md-3 col-form-label form-label  text-md-end">Charolas</label>
                        <div class="col-md-9">
                            <input type="number" class="form-control" value="{{ $object->trays }}" id="box_type_trays" name="trays" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="box_type_mergers_by_tray" class="col-md-3 col-form-label form-label  text-md-end">Fuciones por charola</label>
                        <div class="col-md-9">
                            <input type="number" class="form-control" value="{{ $object->mergers_by_tray }}" id="box_type_mergers_by_tray" name="mergers_by_tray" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="box_type_ports" class="col-md-3 col-form-label form-label  text-md-end">Puertos</label>
                        <div class="col-md-9">
                            <input type="number" class="form-control" value="{{ $object->ports }}" id="box_type_ports" name="ports" required>
                        </div>
                    </div>
                    <input id="object_id" name="object_id" value="{{ $object->id }}" hidden>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success" >Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
