@extends('core-layout::master')
@section('title') Documentos huérfanos — CRM @endsection

@section('content')
    <Breadcrumb :list=[{title:"Pagina"},{title:"Crm"},{title:"Documentos huérfanos",active:"active"}]></Breadcrumb>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Documentos huérfanos</h5>
            <p class="card-text">
                Reporte de solo lectura de documentos del CRM sin archivo físico asociado.
                Consulta el JSON en <code>/crm/documentos-huerfanos/data</code> o descarga el
                CSV en <code>/crm/documentos-huerfanos/csv</code>.
            </p>
            <p class="text-muted">La interfaz visual de esta pantalla se agrega en un sub-item aparte.</p>
        </div>
    </div>
@endsection
