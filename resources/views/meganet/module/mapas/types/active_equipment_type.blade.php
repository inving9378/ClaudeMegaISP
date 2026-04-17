<div class="modal-header">
    <h1 class="modal-title fs-5" id="active_equipment_modal_Label">Equipos Activos</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_active_equipment_type_form'>
                <div class="collapse" id="active_equipment_type_table_collapse">
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="active_equipment_type_model" class="col-md-2 col-form-label form-label  text-md-end">Model</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" id="active_equipment_type_model" name="model" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="active_equipment_type_brand_id" class="col-md-2 col-form-label form-label  text-md-end">Marca</label>
                            <div class="col-md-10">
                                <select class="form-select brand_select" name="brand_id" id="active_equipment_type_brand_id" required></select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="active_equipment_type_type" class="col-md-2 col-form-label form-label  text-md-end">Tipo</label>
                            <div class="col-md-10">
                                <select class="form-select" id="active_equipment_type_type" name="type" required>
                                    <option value=""></option>
                                    @foreach ($activeEquipmentTypes as $activeEquipmentType)
                                        <option value="{{ $activeEquipmentType }}">{{ $activeEquipmentType }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="active_equipment_type_ethernet_ports" class="col-md-2 col-form-label form-label  text-md-end">Puertos Eternet</label>
                            <div class="col-md-10">
                                <input class="form-control" type="text" id="active_equipment_type_ethernet_ports" name="ethernet_ports">
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="active_equipment_type_sfp_ports" class="col-md-2 col-form-label form-label  text-md-end">Puertos SFP</label>
                            <div class="col-md-10">
                                <input class="form-control" type="text" id="active_equipment_type_sfp_ports" name="sfp_ports">
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="active_equipment_type_sfp_plus_ports" class="col-md-2 col-form-label form-label  text-md-end">Puertos SFP +</label>
                            <div class="col-md-10">
                                <input class="form-control" type="text" id="active_equipment_type_sfp_plus_ports" name="sfp_plus_ports">
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="active_equipment_type_cards" class="col-md-2 col-form-label form-label  text-md-end">Tarjetas</label>
                            <div class="col-md-10">
                                <input class="form-control" type="text" id="active_equipment_type_cards" name="cards">
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="active_equipment_types_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Modelo</th>
                        <th>Marca</th>
                        <th>Tipo</th>
                        <th>Tarjetas</th>
                        <th>Eternet</th>
                        <th>SFP</th>
                        <th>SFP+</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
