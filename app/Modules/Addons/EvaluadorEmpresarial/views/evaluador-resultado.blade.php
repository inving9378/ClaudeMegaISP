@extends('meganet.layout.master-without-nav')

@section('title')
    Resultado de tu Evaluación — MegaNet
@endsection

@section('content')
    <div class="container py-4" style="max-width: 960px;">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h4 mb-3">
                    <i class="bi bi-clipboard-check me-2"></i>
                    Resultado de Evaluación de Servicios
                </h2>

                <p class="text-muted mb-4">
                    Hola <strong>{{ $evaluacion->nombre_contacto }}</strong> de <strong>{{ $evaluacion->empresa }}</strong>,
                    este es el resultado de tu evaluación completada el
                    {{ optional($evaluacion->completado_at)->format('d/m/Y H:i') }}.
                </p>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card text-bg-light">
                            <div class="card-body py-3">
                                <div class="small text-muted">Categoría</div>
                                <div class="h4 mb-0">{{ $evaluacion->categoria }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-bg-light">
                            <div class="card-body py-3">
                                <div class="small text-muted">Puntaje total</div>
                                <div class="h4 mb-0">{{ $evaluacion->puntaje_total }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-bg-light">
                            <div class="card-body py-3">
                                <div class="small text-muted">Plan recomendado</div>
                                <div class="h6 mb-0">{{ $evaluacion->plan_recomendado ?: '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="h6">Scores por área</h3>
                <table class="table table-sm">
                    <tbody>
                        <tr><th>Criticidad</th><td>{{ $evaluacion->score_criticidad }}</td></tr>
                        <tr><th>Redundancia</th><td>{{ $evaluacion->score_redundancia }}</td></tr>
                        <tr><th>Ancho de banda</th><td>{{ $evaluacion->score_ancho_banda }}</td></tr>
                        <tr><th>SLA</th><td>{{ $evaluacion->score_sla }}</td></tr>
                    </tbody>
                </table>

                <div class="mt-4 d-flex flex-wrap gap-2">
                    <a href="https://wa.me/525568175643?text=Hola%20MegaNet,%20completé%20mi%20evaluación%20({{ urlencode($evaluacion->categoria) }})%20y%20quiero%20contratar"
                       class="btn btn-success" target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp"></i> Contactar a MegaNet
                    </a>
                    <a href="/evaluador-empresarial" class="btn btn-outline-secondary">
                        Hacer otra evaluación
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
