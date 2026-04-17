<div class="card mb-3" style="max-width: 100%;">
    <div class="card-body rounded-4 px-0">
        <form id='create_map_link_form'>
            <div class="collapse" id="map_links_table_collapse">
                <div class="mb-3 px-2">
                    <input type="text" name="input_id" value="{{ $object->id }}" hidden>
                    <input type="text" name="input_type" value="{{ $objectTable->type }}" hidden>
                    @include('meganet.module.mapas.auxViews.map_route')
                    <div class="row mb-2">
                        <label for="select_out_type" class="col-md-3 col-form-label form-label  text-md-end">Tipo</label>
                        <div class="col-md-9">
                            <select class="form-select" id="select_out_type" name="output_type" required>
                                <option value=""></option>
                                @foreach ($positionableObjects as $positionableObject)
                                    <option value="{{ $positionableObject->type }}">{{ $positionableObject->label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="select_output_id" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                        <div class="col-md-9">
                            <select class="form-select" id="select_output_id" name="output_id" required></select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="select_box_input_id" class="col-md-3 col-form-label form-label  text-md-end">Entrada</label>
                        <div class="col-md-9">
                            <select class="form-select" id="select_box_input_id" name="box_input_id" disabled></select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="site_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end"><strong>Proyecto</strong></label>
                        <div class="col-md-9">
                            <select class="form-select" name="map_proyect_id" id="rack_map_proyect_id" required>
                                @foreach ($mapProyects as $mapProyect)
                                    <option value="{{ $mapProyect->id }}">{{ $mapProyect->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">Guardar</button>
                    </div>
                </div>
            </div>
        </form>
        <table class="table table-striped table-hover mb-0 datatable border-top" id="map_links_table">
            <thead>
                <tr>
                    <th class="d-none">id</th>
                    <th>Nombre</th>
                    <th>Typo</th>
                    <th>Proyecto</th>
                    <th>Borrar</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>
