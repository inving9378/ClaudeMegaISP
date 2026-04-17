<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $objectTable->label }}</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

{{-- data-object --}}
<input type="text" value="{{ $object->id }}" id="object_id" hidden>
<input type="text" value="{{ $objectTable->type }}" id="object_type" hidden>

<div class="modal-body">
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="mb-0">Información</h6>
                </div>
                <div class="col text-end">
                    <div class="col-auto text-end">
                        <button type="button" class="btn btn-primary" onclick="backObject( '{{ route('maps.getDataFormById') }}',{{ $object->box->id }}, 'box')">Caja</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- update form --}}
        <div class="card-body form-horizontal">
            <form id="site_form">
                <input type="text" name="site_type" value="site" hidden>
                <input type="text" name="site_id" value="{{ $object->id }}" hidden>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label text-lg-end" for="tray_number"><strong>Numero</strong></label>
                    <div class="col-lg-9">
                        <input type="text" class="form-control" id="tray_number" name="number" value="{{ $object->number }}" required>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Fusions --}}
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-body rounded-4 px-0">
            <form id='create_port_form'>
                <div class="collapse" id="ports_table_collapse">
                </div>
            </form>
            <table class="table table-striped table-hover mb-0 datatable border-top" id="ports_table">
                <thead>
                    <tr>
                        <th class="d-none">id</th>
                        <th>Numero</th>
                        <th>Entrada</th>
                        <th>Buffer</th>
                        <th>Hilo</th>
                        <th>Entrada</th>
                        <th>Buffer</th>
                        <th>Hilo</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
