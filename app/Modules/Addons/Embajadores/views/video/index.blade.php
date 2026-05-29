@extends('core-layout::master')

@section('title')
    Embajadores · Video explicativo
@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-xl-10">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="mb-0"><i class="fas fa-play-circle mr-2 text-primary"></i>Programa Embajadores Meganet</h4>
                    <small class="text-muted">Video explicativo — 18 minutos | Voz: OpenAI nova (español neutral)</small>
                </div>
                @if($videoExists)
                <a href="{{ $videoUrl }}" download="embajadores-meganet.mp4" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-download mr-1"></i>Descargar ({{ $videoSize }} MB)
                </a>
                @endif
            </div>

            @if($videoExists)
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-0" style="background:#000; border-radius:inherit;">
                    <video
                        id="embajadores-video"
                        controls
                        preload="metadata"
                        style="width:100%; max-height:600px; border-radius:inherit; display:block;"
                    >
                        <source src="{{ $videoUrl }}" type="video/mp4">
                        Tu navegador no soporta reproducción de video HTML5.
                        <a href="{{ $videoUrl }}">Descargar el video</a>
                    </video>
                </div>
            </div>
            @else
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                El archivo de video no está disponible todavía.
                Ruta esperada: <code>public/storage/embajadores/video.mp4</code>
            </div>
            @endif

            {{-- Índice de secciones con timestamps --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">Índice del video</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:100px">Tiempo</th>
                                <th>Sección</th>
                                <th style="width:80px" class="text-center">Duración</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $sections = [
                                ['time' => 0,    'label' => 'Bienvenida',                          'duration' => '1:04',  'for' => ''],
                                ['time' => 64,   'label' => 'Concepto del programa',               'duration' => '2:02',  'for' => ''],
                                ['time' => 186,  'label' => 'Para el administrador de Meganet',    'duration' => '3:27',  'for' => 'admin'],
                                ['time' => 413,  'label' => 'Para el cliente embajador',           'duration' => '3:54',  'for' => 'cliente'],
                                ['time' => 654,  'label' => 'Ciclo de vida completo',              'duration' => '2:04',  'for' => ''],
                                ['time' => 782,  'label' => 'Para desarrolladores',                'duration' => '1:13',  'for' => 'dev'],
                                ['time' => 898,  'label' => 'Cierre y resumen',                    'duration' => '1:42',  'for' => ''],
                            ];
                            @endphp
                            @foreach($sections as $s)
                            <tr class="section-row" style="cursor:pointer;" data-time="{{ $s['time'] }}">
                                <td>
                                    <span class="badge badge-secondary font-monospace">
                                        {{ sprintf('%d:%02d', floor($s['time']/60), $s['time']%60) }}
                                    </span>
                                </td>
                                <td>
                                    {{ $s['label'] }}
                                    @if($s['for'] === 'admin')
                                        <span class="badge badge-info ml-1">Admin</span>
                                    @elseif($s['for'] === 'cliente')
                                        <span class="badge badge-success ml-1">Cliente</span>
                                    @elseif($s['for'] === 'dev')
                                        <span class="badge badge-warning ml-1">Dev</span>
                                    @endif
                                </td>
                                <td class="text-center text-muted">{{ $s['duration'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.section-row').forEach(function(row) {
    row.addEventListener('click', function() {
        var video = document.getElementById('embajadores-video');
        if (!video) return;
        var t = parseInt(this.dataset.time);
        video.currentTime = t;
        video.play();
        video.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
});
</script>
@endpush
@endsection
