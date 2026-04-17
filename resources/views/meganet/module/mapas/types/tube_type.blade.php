<div class="modal-header">
    <h1 class="modal-title fs-5" id="tube_modal_Label">Tuberías</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_tube_form'>
                <div class="collapse" id="tubes_table_collapse">
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="type" class="col-md-2 col-form-label form-label  text-md-end">Tipo</label>
                            <div class="col-md-10">
                                <select class="form-select brand_select" name="type" id="tube_type" required>
                                    <option value=""></option>
                                    <option value="No definido">No definido</option>
                                    <option value="Inoxidable">Inoxidable</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="diameter" class="col-md-2 col-form-label form-label  text-md-end">Diámetro</label>
                            <div class="col-md-10">
                                <input class="form-control" type="number" step="0.1" min="0" id="tube_diameter" name="diameter" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-responsive table-striped table-hover mb-0 datatable border-top" id="tube_types_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Tipo</th>
                        <th>Diámetro</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
