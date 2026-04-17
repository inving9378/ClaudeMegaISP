<div class="modal-header">
    <h1 class="modal-title fs-5" id="passive_equipment_type_modal_Label">Equipos Pasivos</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="card mb-2" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_passive_equipment_type_form'>
                <div class="collapse" id="passive_equipment_type_table_collapse">
                    <div class="mb-2 px-2">
                        <div class="row mb-2">
                            <label for="passive_equipment_type_model" class="col-md-2 col-form-label form-label  text-md-end">Model</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" id="passive_equipment_type_model" name="model" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="passive_equipment_type_brand_id" class="col-md-2 col-form-label form-label  text-md-end">Marca</label>
                            <div class="col-md-10">
                                <select class="form-select brand_select" name="brand_id" id="passive_equipment_type_brand_id" required>
                                    <option value=""></option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="passive_equipment_type_type" class="col-md-2 col-form-label form-label  text-md-end">Tipo</label>
                            <div class="col-md-10">
                                <select class="form-select" id="passive_equipment_type_type" name="type" required>
                                    <option value=""></option>
                                    @foreach ($passiveEquipmentTypes as $passiveEquipmentType)
                                        <option value="{{ $passiveEquipmentType }}">{{ $passiveEquipmentType }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="passive_equipment_type_ports" class="col-md-2 col-form-label form-label  text-md-end">Puertos</label>
                            <div class="col-md-10">
                                <input class="form-control" type="text" id="passive_equipment_type_ports" name="ports" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="passive_equipment_type_trays" class="col-md-2 col-form-label form-label  text-md-end">Charolas</label>
                            <div class="col-md-10">
                                <input class="form-control" type="text" id="passive_equipment_type_trays" name="trays" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="passive_equipment_types_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Modelo</th>
                        <th>Marca</th>
                        <th>Tipo</th>
                        <th>Puertos</th>
                        <th>Charolas</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
