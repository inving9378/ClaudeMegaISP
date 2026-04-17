<div class="modal-header">
    <div class="row align-items-center d-flex justify-content-between col-12">
        <div class="col">
            <h1 class="modal-title fs-5" >Actualizar equipos pasivos</h1>
        </div>
        <div class="col text-end px-0">
            <button type="button" class="btn btn-primary" onclick="loadCatalogModal('passive_equipment_type', 'types.passive_equipment_type')">Regresar</button>
        </div>
    </div>
</div>
<div class="modal-body">
    <div class="card mb-2" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='update_passive_equipment_type_form'>
                <div class="mb-2 px-2">
                    <div class="row mb-2">
                        <label for="passive_equipment_type_model" class="col-md-2 col-form-label form-label  text-md-end">Model</label>
                        <div class="col-md-10">
                            <input type="text" class="form-control" id="passive_equipment_type_model" name="model" value="{{ $object->model }}" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="passive_equipment_type_brand_id" class="col-md-2 col-form-label form-label  text-md-end">Marca</label>
                        <div class="col-md-10">
                            <select class="form-select brand_select" name="brand_id" id="passive_equipment_type_brand_id" required>
                                <option value="{{ $object->brand_id }}">{{ $object->brand->name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="passive_equipment_type_type" class="col-md-2 col-form-label form-label  text-md-end">Tipo</label>
                        <div class="col-md-10">
                            <select class="form-select" id="passive_equipment_type_type" name="type" required>
                                <option value=""></option>
                                @foreach ($passiveEquipmentTypes as $passiveEquipmentType)
                                    <option {{ $object->type == $passiveEquipmentType ? 'selected' : '' }} value="{{ $passiveEquipmentType }}">{{ $passiveEquipmentType }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="passive_equipment_type_ports" class="col-md-2 col-form-label form-label  text-md-end">Puertos</label>
                        <div class="col-md-10">
                            <input class="form-control" type="text" id="passive_equipment_type_ports" name="ports" value="{{ $object->ports }}" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="passive_equipment_type_trays" class="col-md-2 col-form-label form-label  text-md-end">Charolas</label>
                        <div class="col-md-10">
                            <input class="form-control" type="text" id="passive_equipment_type_trays" name="trays" value="{{ $object->trays }}" required>
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
