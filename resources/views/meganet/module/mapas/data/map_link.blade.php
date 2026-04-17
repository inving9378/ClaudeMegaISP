<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">Ruta</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

{{-- data-object --}}
<input type="text" value="{{ $mapLink->id }}" id="object_id" hidden>
<input type="text" value="map_link" id="object_type" hidden>

<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="mb-0">Información</h6>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="map_route_form">
                <input type="text" name="map_route_id" value="{{ $mapLink->mapRoute->id }}" hidden>
                <div class="row mb-2">
                    <label for="map_route_name" class="col-md-3 col-form-label form-label  text-md-end">Nombre</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="map_route_name" name="name" value="{{ $mapLink->mapRoute->name }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="map_route_length" class="col-md-3 col-form-label form-label  text-md-end">Largo (Mts)</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="map_route_length" name="length" value="{{ $mapLink->mapRoute->length() }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="map_route_description" class="col-md-3 col-form-label form-label  text-md-end">Descripción</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="map_route_description" name="description" value="{{ $mapLink->mapRoute->description }}" required>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="map_route_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                    <div class="col-md-9">
                        <select class="form-select" name="map_proyect_id" id="map_route_map_proyect_id" required>
                            <option value="{{ $mapLink->mapRoute->map_proyect_id }}">{{ $mapLink->mapRoute->mapProyect->name }}</option>
                            @foreach ($mapProyects as $mapProyect)
                                @if ($mapProyect->id !== $mapLink->mapRoute->map_proyect_id)
                                    <option value="{{ $mapProyect->id }}">{{ $mapProyect->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="color_id" class="col-md-3 col-form-label form-label text-md-end">Colors</label>
                    <div class="col-md-9">
                        <select class="form-select map_route_element" id="color_id" name="color_id" onchange="changeMapRouteElement()" required>
                            <option value=""></option>
                            @foreach ($colors as $color)
                                <option {{ $color->id == $mapLink->mapRoute->color_id?'selected':null }} value="{{ $color->id }}">{{ $color->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success">Actualizar!</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card mb-3" style="max-width: 100%;" id="equipment_links_card">
        <div class="card-body rounded-4 px-0">
            <table class="table table-striped table-hover mb-0 datatable border-top" id="equipment_links_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Bufer</th>
                        <th>Fibra</th>
                        <th>Entrada</th>
                        <th>Puerto</th>
                        <th>Salida</th>
                        <th>Puerto</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
