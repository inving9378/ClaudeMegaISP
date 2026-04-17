<div class="modal-header">
    <h1 class="modal-title fs-5" id="box_modal_Label">Cajas</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_box_type_form'>
                <div class="collapse" id="box_table_collapse">
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="box_type_model" class="col-md-3 col-form-label form-label  text-md-end">Modelo</label>
                            <div class="col-md-9">
                                <input type="text" class="form-control" id="box_type_model" name="model" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="box_type_type" class="col-md-3 col-form-label form-label  text-md-end">Tipo</label>
                            <div class="col-md-9">
                                <select class="form-select" name="type" id="box_type_type" required>
                                    <option value=""></option>
                                    <option value="Empalme">Empalme</option>
                                    <option value="Primer nivel">Primer nivel</option>
                                    <option value="Segundo nivel">Segundo nivel</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="box_type_brand_id" class="col-md-3 col-form-label form-label  text-md-end">Marca</label>
                            <div class="col-md-9">
                                <select class="form-select brand_select" name="brand_id" id="box_type_brand_id" required>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="box_type_inputs" class="col-md-3 col-form-label form-label  text-md-end">Entradas</label>
                            <div class="col-md-9">
                                <input type="number" class="form-control" id="box_type_inputs" name="inputs" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="box_type_trays" class="col-md-3 col-form-label form-label  text-md-end">Charolas</label>
                            <div class="col-md-9">
                                <input type="number" class="form-control" id="box_type_trays" name="trays" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="box_type_mergers_by_tray" class="col-md-3 col-form-label form-label  text-md-end">Fuciones por charola</label>
                            <div class="col-md-9">
                                <input type="number" class="form-control" id="box_type_mergers_by_tray" name="mergers_by_tray" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="box_type_ports" class="col-md-3 col-form-label form-label  text-md-end">Puertos</label>
                            <div class="col-md-9">
                                <input type="number" class="form-control" id="box_type_ports" name="ports" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="box_types_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Modelo</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Entradas</th>
                        <th>Charolas</th>
                        <th>Fuciones por charola</th>
                        <th>Puertos</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
