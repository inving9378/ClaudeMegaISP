<div class="modal-header">
    <div class="row align-items-center d-flex justify-content-between col-12">
        <div class="col">
            <h1 class="modal-title fs-5" >Actualizar marca</h1>
        </div>
        <div class="col text-end px-0">
            <button type="button" class="btn btn-primary" onclick="loadCatalogModal('brand', 'types.brand')">Regresar</button>
        </div>
    </div>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='update_brand_form'>
                <div class="mb-1 px-2">
                    <div class="row mb-2">
                        <label for="name" class="col-md-2 col-form-label form-label  text-md-end">Nombre</label>
                        <div class="col-md-10">
                            <input class="form-control" type="text" id="name" name="name" value="{{ $object->name }}" required>
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
