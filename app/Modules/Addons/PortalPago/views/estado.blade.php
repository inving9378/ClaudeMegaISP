<!DOCTYPE html>
<html lang="es" data-spa-skip>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de tu pago — Meganet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; color: #222; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem 1rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.25rem 2rem; max-width: 440px; width: 100%; text-align: center; }
        .card > img { max-height: 44px; margin-bottom: 1.5rem; }
        .icon { font-size: 3rem; line-height: 1; margin-bottom: .75rem; }
        h1 { font-size: 1.2rem; margin-bottom: .6rem; }
        p.msg { font-size: .95rem; color: #555; line-height: 1.55; }
        .banner { border-radius: 10px; padding: 1.25rem 1rem; margin-bottom: 1.25rem; }
        .banner.ok    { background: #e9f9ef; border: 1px solid #b6e6c8; }
        .banner.wait  { background: #fff6e5; border: 1px solid #f3d79b; }
        .banner.err   { background: #fdecec; border: 1px solid #f5c2c2; }
        .ok h1 { color: #07703a; } .wait h1 { color: #8a5a00; } .err h1 { color: #b42318; }
        .ref { font-size: .8rem; color: #999; margin-top: 1rem; }
        .soporte { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #eef0f3; font-size: .85rem; color: #666; }
        .soporte a { display: inline-block; margin-top: .5rem; background: #25D366; color: #fff; text-decoration: none; padding: .55rem 1rem; border-radius: 8px; font-weight: 600; font-size: .9rem; }
        .aviso { background: #eef4ff; border: 1px solid #c9dcff; color: #1d4ed8; border-radius: 8px; padding: .7rem .9rem; font-size: .85rem; margin-bottom: 1rem; }
    </style>
</head>
<body data-spa-skip>
    <div class="card">
        <img src="{{ asset('images/logo_meganet_oficial.png') }}" alt="Meganet">

        @if(session('aviso'))
            <div class="aviso">{{ session('aviso') }}</div>
        @endif

        @if($estadoVisual === 'conciliado')
            <div class="banner ok">
                <div class="icon">✅</div>
                <h1>Tu pago fue recibido y tu servicio está activo</h1>
                <p class="msg">Gracias por tu pago. Si tu servicio estaba suspendido, se reactivará en unos minutos.</p>
            </div>
        @elseif($estadoVisual === 'rechazado')
            <div class="banner err">
                <div class="icon">❌</div>
                <h1>Hubo un problema con tu comprobante</h1>
                <p class="msg">No pudimos validar tu pago. Por favor contacta a soporte para ayudarte a resolverlo.</p>
            </div>
        @else
            <div class="banner wait">
                <div class="icon">🕐</div>
                <h1>Tu reporte fue recibido</h1>
                <p class="msg">Estamos verificando tu pago. En un momento lo confirmamos.</p>
            </div>
        @endif

        <div class="ref">Referencia: {{ $link->referencia_unica }}</div>

        <div class="soporte">
            ¿Necesitas ayuda?<br>
            <a href="https://wa.me/{{ $soporteWa }}" target="_blank" rel="noopener">WhatsApp soporte: {{ $soporteDisplay }}</a>
        </div>
    </div>
</body>
</html>
