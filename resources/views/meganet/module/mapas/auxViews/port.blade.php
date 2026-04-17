<form id="equipment_links_store">
    <input type="text" name="input_id" value="{{ $object->id }}" hidden>
    <div class="row mb-2">
        <label for="equipment_type" class="col-md-3 col-form-label form-label text-md-end">Tipo de equipo</label>
        <div class="col-md-9">
            <select class="form-select" id="equipment_type" name="output_type" onchange="changeEquipmetType(this)" required>
                @if (!empty($equipmentLinked))
                    @if (in_array($equipmentLinked::class, [App\Models\Card::class, App\Models\Transceiver::class]))
                        <option value="active_equipment">equipo activo</option>
                        <option value="passive_equipment">equipo pasivo</option>
                        <option value=""></option>
                    @else
                        <option value="passive_equipment">equipo pasivo</option>
                        <option value="active_equipment">equipo activo</option>
                        <option value=""></option>
                    @endif
                @else
                    <option value=""></option>
                    <option value="active_equipment">equipo activo</option>
                    <option value="passive_equipment">equipo pasivo</option>
                @endif
            </select>
        </div>
    </div>
    <div class="row mb-2">
        <label for="equipment_search" class="col-md-3 col-form-label form-label  text-md-end">Equipo</label>
        <div class="col-md-{{ empty($objectLinked)?9:8 }}">
            <select class="form-select" id="equipment_search" name="equipment_id" required>
                @if (!empty($objectLinked))
                    @if ($equipmentLinked::class === App\Models\Card::class)
                        <option value="{{ $equipmentLinked->activeEquipment->id }}">
                            {{ $equipmentLinked->activeEquipment->name }}
                        </option>
                    @elseif ($equipmentLinked::class === App\Models\Transceiver::class)
                        <option value="{{ $equipmentLinked->card->activeEquipment->id }}">
                            {{ $equipmentLinked->card->activeEquipment->name }}
                        </option>
                    @else
                        <option value="{{ $equipmentLinked->id }}">
                            {{ ($equipmentLinked::class === App\Models\Box::class?$equipmentLinked->nomenclature:$equipmentLinked->name) }}
                        </option>
                    @endif
                @endif
            </select>
        </div>
        @if (!empty($objectLinked))
            <div class="col-md-1 ps-0">
            @if ($equipmentLinked::class === App\Models\Card::class)
                <button type="button" class="btn btn-info" onclick="backObject('{{ route('maps.getDataFormById') }}', {{ $equipmentLinked->activeEquipment->id }}, 'active_equipment')">ver</button>
            @elseif ($equipmentLinked::class === App\Models\Transceiver::class)
                <button type="button" class="btn btn-info" onclick="backObject('{{ route('maps.getDataFormById') }}', {{ $equipmentLinked->card->activeEquipment->id }}, 'active_equipment')">ver</button>
            @else
                <button type="button" class="btn btn-info" onclick="backObject('{{ route('maps.getDataFormById') }}', {{ $equipmentLinked->id }}, '{{ $equipmentLinked->infoTable()->type }}'">ver</button>
            @endif
            </div>
        @endif
    </div>
    <div class="row mb-2">
        <label for="card_search" class="col-md-3 col-form-label form-label text-md-end">Tarjeta</label>
        <div class="col-md-{{ empty($objectLinked)?9:8 }}">
            <select class="form-select" id="card_search" name="card_id" required>
                @if (!empty($objectLinked))
                    @if ($equipmentLinked::class === App\Models\Card::class)
                        <option value="{{ $equipmentLinked->id }}">
                            {{ $equipmentLinked->name }}
                        </option>
                    @elseif ($equipmentLinked::class === App\Models\Transceiver::class)
                        <option value="{{ $equipmentLinked->card->id }}">
                            {{ $equipmentLinked->card->name }}
                        </option>
                    @endif
                @endif
            </select>
        </div>
        @if (!empty($objectLinked))
            <div class="col-md-1 ps-0">
                @if ($equipmentLinked::class === App\Models\Card::class)
                    <button type="button" class="btn btn-info" onclick="backObject('{{ route('maps.getDataFormById') }}', {{ $equipmentLinked->id }}, 'card')">ver</button>
                @elseif ($equipmentLinked::class === App\Models\Transceiver::class)
                    <button type="button" class="btn btn-info" onclick="backObject('{{ route('maps.getDataFormById') }}', {{ $equipmentLinked->card->id }}, 'card')">ver</button>
                @endif
            </div>
        @endif
    </div>
    <div class="row mb-2">
        <label for="transceiver_search" class="col-md-3 col-form-label form-label text-md-end">Transiver</label>
        <div class="col-md-{{ empty($objectLinked)?9:8 }}">
            <select class="form-select" id="transceiver_search" name="transceiver_id" required>
                @if (!empty($objectLinked))
                    @if ($equipmentLinked::class === App\Models\Transceiver::class)
                        <option value="{{ $equipmentLinked->id }}">
                            {{ $equipmentLinked->description }}
                        </option>
                    @endif
                @endif
            </select>
        </div>
        @if (!empty($objectLinked))
            <div class="col-md-1 ps-0">
                <button type="button" class="btn btn-info" onclick="backObject('{{ route('maps.getDataFormById') }}', {{ $equipmentLinked->id }}, 'transceiver')">ver</button>
            </div>
        @endif
    </div>
    <div class="row mb-2">
        <label for="equipment_port" class="col-md-3 col-form-label form-label  text-md-end">Puerto</label>
        <div class="col-md-{{ empty($objectLinked)?9:8 }}">
            <select class="form-select" name="output_id" id="equipment_port" required>
                @if (!empty($objectLinked))
                    <option value="{{ $objectLinked->id }}">
                        {{ $objectLinked::class === App\Models\Port::class?$objectLinked->number:$objectLinked->name }}
                    </option>
                @endif
            </select>
        </div>
        @if (!empty($objectLinked))
            <div class="col-md-1 ps-0">
                <button type="button" class="btn btn-info" onclick="backObject('{{ route('maps.getDataFormById') }}', {{ $objectLinked->id }}, '{{ $objectLinkedTable->type }}')">ver</button>
            </div>
        @endif
    </div>
    <div class="d-grid gap-2">
        <button type="submit" class="btn btn-success">Conectar!</button>
    </div>
</form>
