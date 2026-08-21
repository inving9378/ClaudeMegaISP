<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Manual General de la Empresa</title>
<style>
    body{font-family: DejaVu Sans, sans-serif; font-size:12px; color:#1c2434; line-height:1.5;}
    .letterhead{border-bottom:2px solid #1f6feb; padding-bottom:14px; margin-bottom:22px; overflow:hidden;}
    .letterhead img{max-height:60px; float:left; margin-right:16px;}
    .letterhead h1{font-size:20px; margin:0 0 4px;}
    .letterhead .company{font-size:12px; color:#444; margin:0;}
    h2{font-size:15px; color:#1f6feb; margin:22px 0 6px; border-bottom:1px solid #e2e7ee; padding-bottom:4px;}
    h3{font-size:13px; margin:14px 0 4px;}
    p{margin:0 0 8px;}
    .pend{background:#fff6e5; border:1px dashed #f0d9a8; color:#8a5a00; padding:6px 9px; font-size:11px;}
    .docref{display:block; margin-top:4px; font-size:11px; color:#555;}
    .foot{margin-top:30px; font-size:10px; color:#888; text-align:center;}
    mark{background:transparent;}
</style>
</head>
<body>
    <div class="letterhead">
        @if($company && $company->url_logo)
            <img src="{{ $company->url_logo }}" alt="Logo">
        @endif
        <h1>Manual General de la Empresa</h1>
        <p class="company">
            {{ $company->company_name ?? 'Meganet Telecomunicaciones' }}
            @if($company && $company->rfc) · RFC {{ $company->rfc }} @endif
            @if($company && $company->company_street)
                · {{ $company->company_street }} {{ $company->company_external_number }}
                @if($company->municipality_name), {{ $company->municipality_name }}@endif
                @if($company->state_name), {{ $company->state_name }}@endif
            @endif
        </p>
        <p class="company">Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @forelse($chapters as $chapter)
        <h2>{{ $chapter->title }}</h2>
        @forelse($chapter->sections as $section)
            <h3>{{ $section->title }}</h3>
            @if($section->publishedVersion)
                {!! $section->publishedVersion->content !!}
            @else
                <div class="pend">Esta sección todavía no tiene una versión publicada.</div>
            @endif
        @empty
            <p>(Capítulo sin secciones.)</p>
        @endforelse
    @empty
        <p>El manual todavía no tiene capítulos.</p>
    @endforelse

    <div class="foot">Meganet Telecomunicaciones · Manual General de la Empresa · documento interno</div>
</body>
</html>
