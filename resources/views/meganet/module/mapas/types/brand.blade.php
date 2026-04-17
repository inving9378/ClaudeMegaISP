<div class="modal-header">
    <h1 class="modal-title fs-5" id="brand_modal_Label">Marcas</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_brand_form'>
                <div class="collapse" id="brands_table_collapse">
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="brand_name" class="col-md-2 col-form-label form-label text-md-end">Nombre</label>
                            <div class="col-md-10">
                                <input class="form-control" type="text" id="brand_name" name="name" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="brands_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Nombre</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
