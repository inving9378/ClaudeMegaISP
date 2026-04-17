<div class="modal-header">
    <div class="row align-items-center d-flex justify-content-between col-12">
        <div class="col">
            <h1 class="modal-title fs-5" >Actualizar equipos activos</h1>
        </div>
        <div class="col text-end px-0">
            <button type="button" class="btn btn-primary" onclick="loadCatalogModal('active_equipment_type', 'types.active_equipment_type')">Regresar</button>
        </div>
    </div>
</div>
<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='update_active_equipment_type_form'>
                <div class="mb-1 px-2">
                    <div class="row mb-2">
                        <label for="active_equipment_type_model" class="col-md-2 col-form-label form-label  text-md-end">Model</label>
                        <div class="col-md-10">
                            <input type="text" class="form-control" id="active_equipment_type_model" value="{{ $object->model }}" name="model" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="active_equipment_type_brand_id" class="col-md-2 col-form-label form-label  text-md-end">Marca</label>
                        <div class="col-md-10">
                            <select class="form-select brand_select" name="brand_id" id="active_equipment_type_brand_id" required>
                                <option value="{{ $object->brand_id }}">{{ $object->brand->name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="active_equipment_type_type" class="col-md-2 col-form-label form-label  text-md-end">Tipo</label>
                        <div class="col-md-10">
                            <select class="form-select" id="active_equipment_type_type" name="type" required>
                                <option value=""></option>
                                @foreach ($activeEquipmentTypes as $activeEquipmentType)
                                    <option {{ $object->type == $activeEquipmentType ? 'selected' : '' }} value="{{ $activeEquipmentType }}">{{ $activeEquipmentType }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="active_equipment_type_ethernet_ports" class="col-md-2 col-form-label form-label  text-md-end">Puertos Eternet</label>
                        <div class="col-md-10">
                            <input class="form-control" type="text" id="active_equipment_type_ethernet_ports" name="ethernet_ports" value="{{ $object->ethernet_ports }}">
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="active_equipment_type_sfp_ports" class="col-md-2 col-form-label form-label  text-md-end">Puertos SFP</label>
                        <div class="col-md-10">
                            <input class="form-control" type="text" id="active_equipment_type_sfp_ports" name="sfp_ports" value="{{ $object->sfp_ports }}">
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="active_equipment_type_sfp_plus_ports" class="col-md-2 col-form-label form-label  text-md-end">Puertos SFP +</label>
                        <div class="col-md-10">
                            <input class="form-control" type="text" id="active_equipment_type_sfp_plus_ports" name="sfp_plus_ports" value="{{ $object->sfp_plus_ports }}">
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="active_equipment_type_cards" class="col-md-2 col-form-label form-label  text-md-end">Tarjetas</label>
                        <div class="col-md-10">
                            <input class="form-control" type="text" id="active_equipment_type_cards" name="cards" value="{{ $object->cards }}">
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
