<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $objectTable->label }}</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

{{-- data-object --}}
<input type="text" value="{{ $object->id }}" id="object_id" hidden>
<input type="text" value="{{ $objectTable->type }}" id="object_type" hidden>
@php
    $fusionLink = $object->fusionLink();
@endphp


<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="mb-0">Información</h6>
                </div>
                <div class="col-auto text-end px-0">
                    <button type="button" class="btn btn-danger" onclick="destroyObject('{{ route('maps.'.$objectTable->type.'.destroy') }}', {{ $object->id }}, true)">
                        <img src="{{ asset('images/maps_icons/delete.svg') }}" alt="">
                    </button>
                </div>
                <div class="col-auto text-end">
                    <button type="button" class="btn btn-primary" onclick="backObject( '{{ route('maps.getDataFormById') }}',{{ $object->portable->id }}, '{{ $object->portable->infoTable()->type }}')">{{ $object->portable->infoTable()->label }}</button>
                </div>
            </div>
        </div>
        <div class="card-body form-horizontal">
            <form id="port_form">
                <input type="text" name="port_id" value="{{ $object->id }}" hidden>
                <div class="row mb-3">
                    <label for="port_number" class="col-md-3 col-form-label form-label  text-md-end">Numero</label>
                    <div class="col-md-9">
                        <input type="number" class="form-control" id="port_number" name="port_number" value="{{ $object->number }}" readonly required>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="port_type" class="col-md-3 col-form-label form-label  text-md-end">Tipo</label>
                    <div class="col-md-9">
                        <select class="form-select" id="port_type" name="port_type" required>
                            <option value="{{ $object->type }}">{{ $object->type }}</option>
                            @foreach ($portTypes as $portType)
                                @if ($object->type !== $portType)
                                    <option value="{{ $portType }}">{{ $portType }}</option>
                                @endif
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

    {{-- conection --}}
    @if (!in_array($object->type,[App\Models\Port::$fusion, App\Models\Port::$fibra, App\Models\Port::$box, App\Models\Port::$splitterIn, App\Models\Port::$splitterOut]))
        <div class="card mb-3" style="max-width: 100%;">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="mb-0">Conexión</h6>
                    </div>
                </div>
            </div>
            <div class="card-body form-horizontal">
                @include('meganet.module.mapas.auxViews.port')
            </div>
        </div>
    @endif

    {{-- box port conection --}}
    @if ($object->type === App\Models\Port::$box)

    @endif

    {{-- splitter out conection --}}
    @if ($object->type === App\Models\Port::$splitterOut)
        <div class="card mb-3" style="max-width: 100%;">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="mb-0">Conexión</h6>
                    </div>
                </div>
            </div>
            <div class="card-body rounded-4 px-0">
                <form id='splitter_out_form'>
                    <input type="text" value="{{ $object->id }}" name="port_id" hidden>
                    <input type="text" value="{{ $object->portable->box_id }}" id="box_id" hidden>
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="connection_type" class="col-md-3 col-form-label form-label text-md-end">Tipo de conexión</label>
                            <div class="col-md-9">
                                <select class="form-select" id="connection_type" name="connection_type" onchange="changeConnectionType(this)" required>
                                    @if ($object->linkedPorts()->isEmpty())
                                        <option value=""></option>
                                        <option value="puerto">Puerto</option>
                                        <option value="fibra">Fibra</option>
                                    @elseif ($object->linkedPorts()->first()->portable_type === App\Models\Box::class)
                                        <option value="puerto">Puerto</option>
                                        <option value="fibra">Fibra</option>
                                        <option value=""></option>
                                    @else
                                        <option value="fibra">Fibra</option>
                                        <option value="puerto">Puerto</option>
                                        <option value=""></option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="output_id" class="col-md-3 col-form-label form-label  text-md-end">Puerto</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="output_id" name="output_id">
                                    @if ($object->linkedPorts()->isNotEmpty())
                                        <option value="{{ $object->linkedPorts()->first()->id }}">{{ $object->linkedPorts()->first()->number }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="box_input_id" class="col-md-3 col-form-label form-label  text-md-end">Entrada</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="box_input_id" name="box_input_id">
                                    @if (!empty($object->findNextToFusion()))
                                        <option value="{{ $object->findNextToFusion()->portable->id }}">{{ $object->findNextToFusion()->portable->number }} ({{ $object->findNextToFusion()->portable->mapLink()->mapRoute->name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="buffer_id" class="col-md-3 col-form-label form-label  text-md-end">Buffer</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="buffer_id" name="buffer_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($object->findNextToFusion()))
                                        <option value="{{ $object->findEquipmentLinNextToFusion()->fiber->buffer->id }}">{{ $object->findEquipmentLinNextToFusion()->fiber->buffer->id }} ({{ $object->findEquipmentLinNextToFusion()->fiber->buffer->color->name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="fiber_id" class="col-md-3 col-form-label form-label  text-md-end">Hilo</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="fiber_id" name="fiber_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($object->findEquipmentLinNextToFusion()))
                                        <option value="{{ $object->findEquipmentLinNextToFusion()->fiber->id }}">{{ $object->findEquipmentLinNextToFusion()->fiber->number }} ({{ $object->findEquipmentLinNextToFusion()->fiber->color->name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="tray_id" class="col-md-3 col-form-label form-label  text-md-end">Charola</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="tray_id" name="tray_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($object->fusionPort()))
                                        <option value="{{ $object->fusionPort()->portable->id }}">{{ $object->fusionPort()->portable->number }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="fusion_port_id" class="col-md-3 col-form-label form-label  text-md-end">fusion</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="fusion_port_id" name="fusion_port_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($object->fusionPort()))
                                        <option value="{{ $object->fusionPort()->id }}">{{ $object->fusionPort()->number }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- splitter in conection --}}
    @if ($object->type === App\Models\Port::$splitterIn)
        <div class="card mb-3" style="max-width: 100%;">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="mb-0">Conexión</h6>
                    </div>
                </div>
            </div>
            <div class="card-body rounded-4 px-0">
                <form id='splitter_in_form'>
                    <input type="text" value="{{ $object->id }}" name="port_id" hidden>
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="box_input_id" class="col-md-3 col-form-label form-label  text-md-end">Entrada</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="box_input_id" name="box_input_id">
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->first_box_input_id }}">{{ $fusionLink->first_box_input_name }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="buffer_id" class="col-md-3 col-form-label form-label  text-md-end">Buffer</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="buffer_id" name="buffer_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->first_buffer_id }}">{{ $fusionLink->first_buffer_id }} ({{ $fusionLink->first_buffer_color_name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="fiber_id" class="col-md-3 col-form-label form-label  text-md-end">Hilo</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="fiber_id" name="fiber_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->first_fiber_id }}">{{ $fusionLink->first_fiber_number }} ({{ $fusionLink->first_fiber_color_name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- fibers conections --}}
    @if ($object->type === App\Models\Port::$fibra)
        <div class="card mb-3" style="max-width: 100%;">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="mb-0">Corte de fibra</h6>
                    </div>
                </div>
            </div>
            <div class="card-body rounded-4 px-2">
                <form id='fiber_cut_form'>
                    <input type="text" name="passive_equipment_id" value="{{ $object->portable->id }}" hidden>
                    <input type="text" name="input_id" value="{{ $object->id }}" hidden>
                    <div class="row mb-2">
                        <label for="fiber_cut_description" class="col-md-3 col-form-label form-label text-md-end">Descripción</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="fiber_cut_description" name="description" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="fiber_cut_date" class="col-md-3 col-form-label form-label text-md-end">Fecha</label>
                        <div class="col-md-9">
                            <input type="date" class="form-control" id="fiber_cut_date" name="date" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="fiber_cut_type" class="col-md-3 col-form-label form-label text-md-end">Tipo</label>
                        <div class="col-md-9">
                            <select class="form-select" name="type" id="fiber_cut_type" required>
                                <option value=""></option>
                                <option value="visible">visible</option>
                                <option value="no visible">no visible</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="fiber_cut_power" class="col-md-3 col-form-label form-label text-md-end">Poder</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="fiber_cut_power" name="power" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <label for="port_map_proyect_id" class="col-md-3 col-form-label form-label text-md-end">Metros</label>
                        <div class="col-md-9">
                            <input type="number" class="form-control" name="meter" required>
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
                        <button type="submit" class="btn btn-success">guardar!</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card mb-3" style="max-width: 100%;">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="mb-0">Hilos</h6>
                    </div>
                </div>
            </div>
            <div class="card-body rounded-4 px-0">
                <form id='fiber_form'>
                    <input type="text" name="input_id" value="{{ $object->id }}" hidden>

                    @php
                        $equipmentLinks = $object->links();
                        if($equipmentLinks->isNotEmpty()){
                            $equipmentLink = $equipmentLinks->first();
                            $fiber = $equipmentLink->fiber;
                            $buffer = $fiber->buffer;
                            $mapRoute = $equipmentLink->mapLink->mapRoute;
                            $mapProyect = $mapRoute->mapProyect;
                        }
                    @endphp

                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="port_map_proyect_id" class="col-md-3 col-form-label form-label  text-md-end">Proyecto</label>
                            <div class="col-md-9 }}">
                                <select class="form-select" name="map_proyect_id" id="port_map_proyect_id" required>
                                    @if ($equipmentLinks->isNotEmpty())
                                        <option value="{{ $mapProyect->id }}">
                                            {{ $mapProyect->name }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="map_route_id" class="col-md-3 col-form-label form-label  text-md-end">Ruta</label>
                            <div class="col-md-9 }}">
                                <select class="form-select" name="map_route_id" id="map_route_id" required>
                                    @if ($equipmentLinks->isNotEmpty())
                                        <option value="{{ $mapRoute->id }}">
                                            {{ $mapRoute->name }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="buffer_id" class="col-md-3 col-form-label form-label  text-md-end">Bufer</label>
                            <div class="col-md-9 }}">
                                <select class="form-select" name="buffer_id" id="buffer_id" required>
                                    @if ($equipmentLinks->isNotEmpty())
                                        <option value="{{ $buffer->id }}">
                                            {{ $buffer->id. " (".$buffer->color->name.")" }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="fiber_id" class="col-md-3 col-form-label form-label  text-md-end">Fibra</label>
                            <div class="col-md-9">
                                <select class="form-select" name="fiber_id" id="fiber_id" required>
                                    @if ($equipmentLinks->isNotEmpty())
                                        <option value="{{ $fiber->id }}">
                                            {{ $fiber->number . " (".$fiber->color->name.")" }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Conectar!</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- fusion connections --}}
    @if ($object->type === App\Models\Port::$fusion)
        <div class="card mb-3" style="max-width: 100%;">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="mb-0">Fusion</h6>
                    </div>
                </div>
            </div>
            <div class="card-body rounded-4 px-0">
                @php
                    $splitterPorts = $object->linkedPorts()->filter(function($link){
                        return $link->portable_type === "App\Models\Splitter";
                    });
                @endphp
                <form id='fusion_form'>
                    <input type="text" value="{{ $object->id }}" name="port_id" hidden>
                    <div class="mb-3 px-2">
                        <div class="row mb-2">
                            <label for="connection_type" class="col-md-3 col-form-label form-label text-md-end">Tipo de conexión</label>
                            <div class="col-md-9">
                                <select class="form-select" id="connection_type" name="connection_type" onchange="changeFusionConnectionType(this)" required>
                                    @if ($object->links()->isEmpty())
                                        <option value=""></option>
                                        <option value="fibra">Fibra</option>
                                        <option value="splitter">Splitter</option>
                                    @elseif($splitterPorts->isNotEmpty())
                                        <option value="splitter">Splitter</option>
                                        <option value="fibra">Fibra</option>
                                    @else
                                        <option value="fibra">Fibra</option>
                                        <option value="splitter">Splitter</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="splitter_id" class="col-md-3 col-form-label form-label  text-md-end">Splittler</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="splitter_id" name="splitter_id">
                                    @if ($splitterPorts->isNotEmpty())
                                        <option value="{{ $splitterPorts->first()->portable->id }}">{{ $splitterPorts->first()->portable->number }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="splitter_port_id" class="col-md-3 col-form-label form-label  text-md-end">Puerto de Splittler</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="splitter_port_id" name="splitter_port_id">
                                    @if ($splitterPorts->isNotEmpty())
                                        <option value="{{ $splitterPorts->first()->id }}">{{ $splitterPorts->first()->number }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="first_box_input_id" class="col-md-3 col-form-label form-label  text-md-end">Primera Entrada</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="first_box_input_id" name="first_box_input_id">
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->first_box_input_id }}">{{ $fusionLink->first_box_input_name }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="first_buffer_id" class="col-md-3 col-form-label form-label  text-md-end">Primer Buffer</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="first_buffer_id" name="first_buffer_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->first_buffer_id }}">{{ $fusionLink->first_buffer_id }} ({{ $fusionLink->first_buffer_color_name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="first_fiber_id" class="col-md-3 col-form-label form-label  text-md-end">Primer Hilo</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="first_fiber_id" name="first_fiber_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->first_fiber_id }}">{{ $fusionLink->first_fiber_number }} ({{ $fusionLink->first_fiber_color_name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="second_box_input_id" class="col-md-3 col-form-label form-label  text-md-end">Second Entrada</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="second_box_input_id" name="second_box_input_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->second_box_input_id }}">{{ $fusionLink->second_box_input_name }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="second_buffer_id" class="col-md-3 col-form-label form-label  text-md-end">Second Buffer</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="second_buffer_id" name="second_buffer_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->second_buffer_id }}">{{ $fusionLink->second_buffer_id }} ({{ $fusionLink->second_buffer_color_name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <label for="second_fiber_id" class="col-md-3 col-form-label form-label  text-md-end">Second Hilo</label>
                            <div class="col-md-9">
                                <select class="form-control" type="text" id="second_fiber_id" name="second_fiber_id" {{ empty($fusionLink)?"disabled":null }}>
                                    @if (!empty($fusionLink))
                                        <option value="{{ $fusionLink->second_fiber_id }}">{{ $fusionLink->second_fiber_number }} ({{ $fusionLink->second_fiber_color_name }})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if (!empty($otherPort))
        <div class="card mb-3" style="max-width: 100%;">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="mb-0">Puerto Contrario</h6>
                    </div>
                </div>
            </div>
            <div class="card-body form-horizontal">
                <div class="row mb-2">
                    <label for="port_number" class="col-md-3 col-form-label form-label  text-md-end">Numero</label>
                    <div class="col-md-9">
                        <input type="number" class="form-control" value="{{ $otherPort->number }}" readonly>
                    </div>
                </div>
                <div class="row mb-2">
                    <label for="port_type" class="col-md-3 col-form-label form-label  text-md-end">Tipo</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" value="{{ $otherPort->type }}" readonly required>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-info" onclick="backObject( '{{ route('maps.getDataFormById') }}',{{ $otherPort->id }}, '{{ $otherPort->infoTable()->type }}')">Ver!</button>
                </div>
            </div>
        </div>
    @endif
</div>
