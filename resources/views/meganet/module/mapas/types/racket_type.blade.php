<div class="modal-header">
    <h1 class="modal-title fs-5" id="racket_modal_Label">Raquetas</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_racket_form'>
                <div class="collapse" id="rackets_type_table_collapse">
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="racket_model" class="col-md-2 col-form-label form-label  text-md-end">Modelo</label>
                            <div class="col-md-10">
                                <input class="form-control" type="text" id="racket_model" name="model" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="racket_type_brand_id" class="col-md-2 col-form-label form-label  text-md-end">Marca</label>
                            <div class="col-md-10">
                                <select class="form-select brand_select" name="brand_id" id="racket_type_brand_id" required></select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="racket_types_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Modelo</th>
                        <th>Marca</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
