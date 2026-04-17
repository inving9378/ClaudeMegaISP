@if ($mapRoutes->isNotEmpty())
    <div class="row mb-2">
        <label for="continue_map_route_id" class="col-md-3 col-form-label form-label text-md-end">Rutas</label>
        <div class="col-md-9">
            <select class="form-select" id="continue_map_route_id" name="map_route_id" onchange="changeMapRoute(this)" required>
                <option value=""></option>
                @foreach ($mapRoutes as $mapRoute)
                    <option value="{{ $mapRoute->id }}">{{ $mapRoute->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
@endif
<div class="row mb-2">
    <label for="map_route_name" class="col-md-3 col-form-label form-label text-md-end">Nombre</label>
    <div class="col-md-9">
        <input type="text" class="form-control map_route_element" id="map_route_name" name="name" onchange="changeMapRouteElement()" required>
    </div>
</div>
<div class="row mb-2">
    <label for="map_route_description" class="col-md-3 col-form-label form-label text-md-end">Descripción</label>
    <div class="col-md-9">
        <input type="text" class="form-control map_route_element" id="map_route_description" name="description" onchange="changeMapRouteElement()" required>
    </div>
</div>
<div class="row mb-2">
    <label for="fibers_amount" class="col-md-3 col-form-label form-label text-md-end">Fibras</label>
    <div class="col-md-9">
        <select class="form-select map_route_element" id="fibers_amount" name="fibers_amount" onchange="changeMapRouteElement()" required>
            <option value=""></option>
            <option value="1">Unifibra</option>
            <option value="2">Bifibra</option>
            <option value="4">4</option>
            <option value="6">6</option>
            <option value="12">12</option>
            <option value="24">24</option>
            <option value="36">36</option>
            <option value="48">48</option>
            <option value="72">72</option>
            <option value="96">96</option>
            <option value="144">144</option>
            <option value="288">288</option>
        </select>
    </div>
</div>
<div class="row mb-2">
    <label for="color_id" class="col-md-3 col-form-label form-label text-md-end">Colors</label>
    <div class="col-md-9">
        <select class="form-select map_route_element" id="color_id" name="color_id" onchange="changeMapRouteElement()" required>
            <option value=""></option>
            @foreach ($colors as $color)
                <option value="{{ $color->id }}">{{ $color->name }}</option>
            @endforeach
        </select>
    </div>
</div>
