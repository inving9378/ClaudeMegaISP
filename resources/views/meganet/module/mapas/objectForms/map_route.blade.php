<div class="modal-header">
    <h1 class="modal-title fs-5" id="exampleModalLabel">Nueva Ruta</h1>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
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
            <form id='create_map_route_form'>
                <div class="mb-3 px-2">
                    @include('meganet.module.mapas.auxViews.map_route')
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
