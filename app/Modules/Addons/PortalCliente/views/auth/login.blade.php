<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#04112C">
    <title>Iniciar Sesión — Portal Meganet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    @php
        $logoEmpresa = \App\Modules\Addons\PortalCliente\Support\LogoEmpresa::url();

        // Imagen de fondo (fachada corporativa). No viene en el repo: se activa sola al colocar
        // un archivo autorizado en public/images/portal/fachada-meganet.webp (o .jpg). Mientras
        // no exista, el fondo es el degradado de .pl-fondo.
        $fachada = null;
        foreach (['images/portal/fachada-meganet.webp', 'images/portal/fachada-meganet.jpg'] as $archivoFachada) {
            if (is_file(public_path($archivoFachada))) {
                $fachada = '/' . $archivoFachada . '?v=' . filemtime(public_path($archivoFachada));
                break;
            }
        }
    @endphp
    <style>
        :root {
            --azul: #0759B8;
            --azul-profundo: #061C48;
            --azul-noche: #04112C;
            --electrico: #008CFF;
            --cyan: #00D5FF;
            --tinta: #0b1b3a;
            --tinta-suave: #4a5d7e;
            --borde-campo: #c6d4ea;
            --error: #d93a4a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: #fff;
            background: var(--azul-noche);
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Fondo: degradado nocturno + retícula tecnológica (o la fachada, si existe) ── */
        .pl-fondo {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(60% 50% at 80% 20%, rgba(0, 213, 255, .20), transparent 70%),
                radial-gradient(55% 60% at 10% 90%, rgba(0, 140, 255, .26), transparent 70%),
                radial-gradient(45% 45% at 98% 98%, rgba(7, 89, 184, .45), transparent 70%),
                linear-gradient(140deg, #04112C 0%, #061C48 48%, #0a3474 100%);
        }
        .pl-fondo::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(0, 213, 255, .06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 213, 255, .06) 1px, transparent 1px);
            background-size: 56px 56px;
            -webkit-mask-image: radial-gradient(ellipse 75% 70% at 32% 45%, #000 15%, transparent 75%);
                    mask-image: radial-gradient(ellipse 75% 70% at 32% 45%, #000 15%, transparent 75%);
        }
        .pl-fondo::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(115deg, transparent 38%, rgba(0, 213, 255, .07) 47%, transparent 55%),
                linear-gradient(115deg, transparent 58%, rgba(0, 140, 255, .06) 64%, transparent 71%);
        }
        .con-fachada .pl-fondo {
            background:
                linear-gradient(90deg, rgba(4, 17, 44, .94) 0%, rgba(4, 17, 44, .80) 38%, rgba(6, 28, 72, .50) 62%, rgba(4, 17, 44, .70) 100%),
                var(--fachada) center / cover no-repeat,
                var(--azul-noche);
        }
        .con-fachada .pl-fondo::before { opacity: .5; }

        /* ── Composición: identidad (izquierda) + acceso (derecha) ── */
        .pl-shell {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            min-height: 100dvh;
            max-width: 1560px;
            margin: 0 auto;
            padding: clamp(28px, 4.5vh, 64px) clamp(20px, 4.5vw, 80px);
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(360px, 1fr);
            /* La 3ª fila (vacía) equilibra el espacio: logo arriba, mensaje + tarjetas al centro. */
            grid-template-rows: 1fr auto minmax(0, .35fr);
            grid-template-areas:
                "hero acceso"
                "servicios acceso"
                ". acceso";
            column-gap: clamp(32px, 6vw, 112px);
            row-gap: clamp(24px, 4vh, 44px);
        }

        .pl-hero { grid-area: hero; display: flex; flex-direction: column; }
        .pl-marca img { display: block; height: clamp(56px, 6vw, 84px); width: auto; }
        .pl-mensaje { margin-top: auto; padding-top: clamp(28px, 6vh, 72px); max-width: 660px; }
        .pl-mensaje::before {
            content: "";
            display: block;
            width: 56px;
            height: 4px;
            border-radius: 4px;
            margin-bottom: 22px;
            background: linear-gradient(90deg, var(--cyan), var(--electrico));
            box-shadow: 0 0 18px rgba(0, 213, 255, .6);
        }
        .pl-mensaje h1 {
            font-size: clamp(2.25rem, 1.1rem + 2.9vw, 4.1rem);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -.025em;
            text-shadow: 0 4px 30px rgba(0, 0, 0, .25);
        }
        .pl-mensaje h1 span { color: var(--cyan); }
        @supports ((-webkit-background-clip: text) or (background-clip: text)) {
            .pl-mensaje h1 span {
                background: linear-gradient(90deg, var(--cyan) 10%, var(--electrico) 100%);
                -webkit-background-clip: text;
                        background-clip: text;
                color: transparent;
                text-shadow: none;
            }
        }
        .pl-mensaje p {
            margin-top: 18px;
            max-width: 540px;
            font-size: clamp(1rem, .9rem + .4vw, 1.25rem);
            line-height: 1.55;
            color: rgba(220, 233, 255, .84);
        }

        /* ── Tarjetas de servicios (informativas) ── */
        .pl-servicios { grid-area: servicios; }
        .pl-servicios ul {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            max-width: 740px;
        }
        .pl-svc {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            height: 100%;
            padding: 18px 20px;
            border-radius: 18px;
            background: linear-gradient(160deg, rgba(10, 40, 98, .62), rgba(6, 24, 62, .50));
            border: 1px solid rgba(0, 213, 255, .16);
            box-shadow: 0 10px 30px rgba(2, 8, 28, .35), inset 0 1px 0 rgba(255, 255, 255, .05);
            -webkit-backdrop-filter: blur(10px);
                    backdrop-filter: blur(10px);
            transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
        }
        .pl-svc:hover {
            transform: translateY(-3px);
            border-color: rgba(0, 213, 255, .42);
            box-shadow: 0 16px 36px rgba(0, 110, 255, .25), inset 0 1px 0 rgba(255, 255, 255, .08);
        }
        .pl-svc-icono {
            flex: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #fff;
            background: var(--g);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, .05), 0 8px 22px var(--sombra);
        }
        .pl-svc-icono svg { width: 24px; height: 24px; }
        .pl-svc--azul    { --g: linear-gradient(135deg, #00D5FF, #008CFF); --sombra: rgba(0, 170, 255, .45); }
        .pl-svc--verde   { --g: linear-gradient(135deg, #34d399, #059669); --sombra: rgba(16, 185, 129, .40); }
        .pl-svc--naranja { --g: linear-gradient(135deg, #fbbf24, #f97316); --sombra: rgba(249, 115, 22, .40); }
        .pl-svc--violeta { --g: linear-gradient(135deg, #c084fc, #7c3aed); --sombra: rgba(139, 92, 246, .45); }
        .pl-svc h3 { font-size: 1rem; font-weight: 700; line-height: 1.3; }
        .pl-svc p { margin-top: 4px; font-size: .875rem; line-height: 1.45; color: rgba(205, 222, 250, .76); }

        /* ── Tarjeta de acceso (glassmorphism) ── */
        .pl-acceso { grid-area: acceso; align-self: center; justify-self: end; width: 100%; max-width: 460px; }
        .pl-card {
            padding: clamp(28px, 3vw, 44px);
            border-radius: 24px;
            color: var(--tinta);
            background: linear-gradient(160deg, rgba(255, 255, 255, .86), rgba(226, 238, 255, .74));
            border: 1px solid rgba(255, 255, 255, .55);
            box-shadow: 0 30px 80px rgba(2, 10, 35, .55), inset 0 1px 0 rgba(255, 255, 255, .7);
            -webkit-backdrop-filter: blur(18px) saturate(140%);
                    backdrop-filter: blur(18px) saturate(140%);
        }
        .pl-card-logo img { display: block; margin: 0 auto; height: 64px; width: auto; }
        .pl-card h2 {
            margin-top: 14px;
            text-align: center;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -.015em;
            color: var(--azul-profundo);
        }
        .pl-sub { margin-top: 4px; text-align: center; font-size: .9rem; color: var(--tinta-suave); }

        .pl-alerta {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 20px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid;
            font-size: .875rem;
            line-height: 1.45;
        }
        .pl-alerta svg { flex: none; width: 18px; height: 18px; margin-top: 1px; }
        .pl-alerta--ok    { background: rgba(16, 185, 129, .12); border-color: rgba(16, 185, 129, .38); color: #065f46; }
        .pl-alerta--info  { background: rgba(0, 140, 255, .10);  border-color: rgba(0, 140, 255, .32);  color: #0b3d7a; }
        .pl-alerta--error { background: rgba(217, 58, 74, .10);  border-color: rgba(217, 58, 74, .36);  color: #8f1d2a; }

        .pl-form { margin-top: 6px; }
        .pl-campo { margin-top: 18px; }
        .pl-campo label { display: block; margin-bottom: 8px; font-size: .85rem; font-weight: 600; color: var(--tinta); }
        .pl-control { position: relative; }
        .pl-control .pl-ico {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: #6b7fa3;
            pointer-events: none;
        }
        .pl-control input {
            width: 100%;
            height: 52px;
            padding: 0 16px 0 46px;
            border: 1.5px solid var(--borde-campo);
            border-radius: 12px;
            background: rgba(255, 255, 255, .92);
            color: var(--tinta);
            font: inherit;
            font-size: 1rem;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .pl-control input::placeholder { color: #8a9bb8; }
        .pl-control input:focus {
            outline: none;
            border-color: var(--electrico);
            box-shadow: 0 0 0 4px rgba(0, 140, 255, .20);
        }
        .pl-control input[aria-invalid="true"] { border-color: var(--error); }
        .pl-control--pass input { padding-right: 54px; }
        .pl-ojo {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #5b6f94;
            cursor: pointer;
            transition: color .2s ease, background .2s ease;
        }
        .pl-ojo:hover { color: var(--azul); background: rgba(0, 140, 255, .08); }
        .pl-ojo:focus-visible { outline: 3px solid rgba(0, 140, 255, .6); outline-offset: -3px; }
        .pl-ojo svg { width: 20px; height: 20px; }
        .pl-ojo .pl-ico-ocultar,
        .pl-ojo[aria-pressed="true"] .pl-ico-ver { display: none; }
        .pl-ojo[aria-pressed="true"] .pl-ico-ocultar { display: block; }

        .pl-btn {
            position: relative;
            overflow: hidden;
            width: 100%;
            height: 54px;
            margin-top: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--electrico) 0%, var(--azul) 100%);
            color: #fff;
            font: inherit;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 26px rgba(0, 110, 230, .38);
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }
        .pl-btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, .28) 50%, transparent 70%);
            transform: translateX(-120%);
            transition: transform .7s ease;
        }
        .pl-btn:hover { filter: brightness(1.07); transform: translateY(-1px); box-shadow: 0 14px 34px rgba(0, 140, 255, .5); }
        .pl-btn:hover::after { transform: translateX(120%); }
        .pl-btn:focus-visible { outline: 3px solid var(--azul-profundo); outline-offset: 3px; }
        .pl-btn svg { width: 20px; height: 20px; transition: transform .2s ease; }
        .pl-btn:hover svg { transform: translateX(3px); }
        .pl-btn:disabled { cursor: progress; transform: none; filter: saturate(.85); }
        .pl-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2.5px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: pl-gira .8s linear infinite;
        }
        .pl-btn.is-loading .pl-spinner { display: block; }
        .pl-btn.is-loading .pl-flecha { display: none; }

        .pl-enlaces {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid rgba(7, 89, 184, .15);
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 4px 20px;
            font-size: .9rem;
        }
        .pl-enlaces a {
            padding: 8px 2px;
            border-radius: 6px;
            color: var(--azul);
            font-weight: 600;
            text-decoration: none;
        }
        .pl-enlaces a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .pl-enlaces a:focus-visible { outline: 3px solid rgba(0, 140, 255, .6); outline-offset: 2px; }

        .pl-oculto {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
        }

        /* ── Animaciones discretas ── */
        @keyframes pl-sube { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        @keyframes pl-gira { to { transform: rotate(360deg); } }
        .pl-anim { animation: pl-sube .7s cubic-bezier(.2, .7, .2, 1) both; animation-delay: var(--d, 0s); }
        /* Si la página vuelve con un aviso o error, se muestra de inmediato: el mensaje no espera a la animación. */
        .sin-entrada .pl-anim { animation: none; }

        /* ── Laptop de poca altura (1366×768 y similares) ── */
        @media (min-width: 1024px) and (max-height: 820px) {
            .pl-shell { padding-top: 28px; padding-bottom: 28px; row-gap: 22px; }
            .pl-marca img { height: 56px; }
            .pl-mensaje { padding-top: 20px; }
            .pl-mensaje::before { margin-bottom: 16px; }
            .pl-mensaje h1 { font-size: clamp(2.25rem, 1.2rem + 2.2vw, 3.3rem); }
            .pl-mensaje p { margin-top: 12px; font-size: 1.05rem; }
            .pl-svc { padding: 14px 16px; gap: 14px; }
            .pl-svc-icono { width: 42px; height: 42px; }
            .pl-svc-icono svg { width: 21px; height: 21px; }
            .pl-card { padding: 30px 34px; }
            .pl-card-logo img { height: 54px; }
            .pl-card h2 { margin-top: 10px; font-size: 1.6rem; }
            .pl-campo { margin-top: 14px; }
            .pl-btn { margin-top: 22px; }
        }

        /* ── Tablet: una sola columna, el acceso antes que los servicios ── */
        @media (max-width: 1023px) {
            /* Aquí sí hay scroll: el fondo acompaña a toda la página en vez de quedarse del alto de la pantalla. */
            body { position: relative; }
            .pl-fondo { position: absolute; }
            .pl-shell {
                max-width: 760px;
                grid-template-columns: minmax(0, 1fr);
                grid-template-rows: auto;
                grid-template-areas:
                    "hero"
                    "acceso"
                    "servicios";
                row-gap: 32px;
            }
            .pl-hero { align-items: center; text-align: center; }
            .pl-mensaje { margin-top: 0; padding-top: 28px; }
            .pl-mensaje::before { margin-left: auto; margin-right: auto; }
            .pl-mensaje p { margin-left: auto; margin-right: auto; }
            .pl-acceso { justify-self: center; max-width: 480px; }
            .pl-servicios ul { max-width: none; }
            .con-fachada .pl-fondo {
                background:
                    linear-gradient(180deg, rgba(4, 17, 44, .82) 0%, rgba(4, 17, 44, .93) 55%, rgba(4, 17, 44, .97) 100%),
                    var(--fachada) center top / cover no-repeat,
                    var(--azul-noche);
            }
        }

        /* ── Teléfono: el formulario manda; servicios compactos ── */
        @media (max-width: 600px) {
            .pl-shell { padding: 22px 16px 32px; row-gap: 22px; }
            .pl-marca img { height: 46px; }
            .pl-mensaje { padding-top: 18px; }
            .pl-mensaje::before { width: 44px; margin-bottom: 14px; }
            .pl-mensaje h1 { font-size: 1.8rem; line-height: 1.12; }
            .pl-mensaje p { margin-top: 10px; font-size: .95rem; line-height: 1.5; }
            .pl-card { padding: 24px 20px; border-radius: 20px; }
            .pl-card-logo { display: none; }
            .pl-card h2 { margin-top: 0; font-size: 1.5rem; }
            .pl-servicios ul { gap: 12px; }
            .pl-svc { flex-direction: column; align-items: center; gap: 10px; padding: 16px 10px; text-align: center; }
            .pl-svc-icono { width: 44px; height: 44px; }
            .pl-svc h3 { font-size: .9rem; }
            .pl-svc p { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body class="{{ $fachada ? 'con-fachada' : '' }} {{ ($errors->any() || session('success') || session('info')) ? 'sin-entrada' : '' }}"
    @if($fachada) style="--fachada: url('{{ $fachada }}')" @endif>
<div class="pl-fondo" aria-hidden="true"></div>

<main class="pl-shell">
    {{-- Identidad corporativa --}}
    <section class="pl-hero">
        @if($logoEmpresa)
            <div class="pl-marca pl-anim">
                <img src="{{ $logoEmpresa }}" alt="Meganet Telecomunicaciones — Te conecta">
            </div>
        @endif
        <div class="pl-mensaje pl-anim" style="--d: .08s">
            <h1>Gracias por ser parte de <span>Meganet</span></h1>
            <p>Tu confianza nos impulsa a seguir conectando personas, hogares y negocios con un mejor futuro.</p>
        </div>
    </section>

    {{-- Acceso al portal: misma ruta, mismos campos y mismo CSRF que siempre --}}
    <section class="pl-acceso pl-anim" style="--d: .15s" aria-labelledby="pl-titulo">
        <div class="pl-card">
            @if($logoEmpresa)
                <div class="pl-card-logo"><img src="{{ $logoEmpresa }}" alt=""></div>
            @endif
            <h2 id="pl-titulo">Portal Cliente</h2>
            <p class="pl-sub">Meganet Telecomunicaciones</p>

            @if(session('success'))
                <div class="pl-alerta pl-alerta--ok" role="status">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif
            @if(session('info'))
                <div class="pl-alerta pl-alerta--info" role="status">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>{{ session('info') }}</div>
                </div>
            @endif
            @if($errors->any())
                <div class="pl-alerta pl-alerta--error" id="pl-errores" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div>@foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
                </div>
            @endif

            <form class="pl-form" id="pl-form" method="POST" action="{{ route('portal.login.post') }}">
                @csrf
                <div class="pl-campo">
                    <label for="pl-identificador">Número de cliente o Email</label>
                    <div class="pl-control">
                        <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="pl-identificador" name="identificador" value="{{ old('identificador') }}"
                            placeholder="Ej: 1234 o correo@ejemplo.com" autocomplete="username" required autofocus
                            @if($errors->has('identificador')) aria-invalid="true" aria-describedby="pl-errores" @endif>
                    </div>
                </div>
                <div class="pl-campo">
                    <label for="pl-contrasena">Contraseña</label>
                    <div class="pl-control pl-control--pass">
                        <svg class="pl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="pl-contrasena" name="contrasena" placeholder="Tu contraseña del portal"
                            autocomplete="current-password" required
                            @if($errors->has('contrasena')) aria-invalid="true" aria-describedby="pl-errores" @endif>
                        <button type="button" class="pl-ojo" id="pl-ojo" aria-controls="pl-contrasena" aria-pressed="false" aria-label="Mostrar contraseña">
                            <svg class="pl-ico-ver" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="pl-ico-ocultar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="pl-btn" id="pl-btn">
                    <span class="pl-btn-texto">Iniciar Sesión</span>
                    <svg class="pl-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    <span class="pl-spinner" aria-hidden="true"></span>
                </button>
            </form>

            <nav class="pl-enlaces" aria-label="Otras opciones de acceso">
                <a href="{{ route('portal.registro') }}">¿Primera vez? Crear cuenta</a>
                <a href="{{ route('portal.recuperar') }}">Olvidé mi contraseña</a>
            </nav>
        </div>
    </section>

    {{-- Lo que el cliente encuentra dentro (informativo, sin enlaces) --}}
    <section class="pl-servicios" aria-labelledby="pl-servicios-titulo">
        <h2 class="pl-oculto" id="pl-servicios-titulo">Lo que puedes hacer en tu portal</h2>
        <ul>
            <li class="pl-anim" style="--d: .25s">
                <div class="pl-svc pl-svc--azul">
                    <span class="pl-svc-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg></span>
                    <div>
                        <h3>Consulta tu internet</h3>
                        <p>Consulta el estado de tu servicio y mantente al tanto de tu conexión.</p>
                    </div>
                </div>
            </li>
            <li class="pl-anim" style="--d: .32s">
                <div class="pl-svc pl-svc--verde">
                    <span class="pl-svc-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                    <div>
                        <h3>Descarga tus facturas</h3>
                        <p>Consulta y descarga tus comprobantes cuando los necesites.</p>
                    </div>
                </div>
            </li>
            <li class="pl-anim" style="--d: .39s">
                <div class="pl-svc pl-svc--naranja">
                    <span class="pl-svc-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                    <div>
                        <h3>Administra tus servicios</h3>
                        <p>Gestiona tus servicios y mantén tu información actualizada.</p>
                    </div>
                </div>
            </li>
            <li class="pl-anim" style="--d: .46s">
                <div class="pl-svc pl-svc--violeta">
                    <span class="pl-svc-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg></span>
                    <div>
                        <h3>Soporte en línea</h3>
                        <p>Estamos contigo cuando nos necesitas.</p>
                    </div>
                </div>
            </li>
        </ul>
    </section>
</main>

<script>
    (function () {
        var form = document.getElementById('pl-form');
        var btn = document.getElementById('pl-btn');
        var texto = btn.querySelector('.pl-btn-texto');
        var pass = document.getElementById('pl-contrasena');
        var ojo = document.getElementById('pl-ojo');

        ojo.addEventListener('click', function () {
            var mostrar = pass.type === 'password';
            pass.type = mostrar ? 'text' : 'password';
            ojo.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
            ojo.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });

        // Un solo envío: el submit sólo llega aquí si los campos requeridos ya pasaron la validación del navegador.
        form.addEventListener('submit', function (e) {
            if (form.dataset.enviando === '1') { e.preventDefault(); return; }
            form.dataset.enviando = '1';
            pass.type = 'password';
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.classList.add('is-loading');
            texto.textContent = 'Ingresando…';
        });

        // Al volver con "Atrás" el navegador puede restaurar la página con el botón bloqueado.
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) { return; }
            form.dataset.enviando = '';
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
            btn.classList.remove('is-loading');
            texto.textContent = 'Iniciar Sesión';
        });
    })();
</script>
</body>
</html>
