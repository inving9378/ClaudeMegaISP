{{--
    Plantilla compartida de las pantallas de acceso del Portal Cliente (iniciar sesión, crear
    cuenta, recuperar contraseña). Su presentación sale de Configuración → Portal Cliente:
    $ap = AparienciaService::vista(). Todos los textos se pintan escapados; los colores y medidas
    llegan ya validados; los iconos son SVG del catálogo del servidor (AparienciaCatalogo).
    La vista previa del panel usa ESTA misma plantilla con $ap['previa'] = true.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $ap['css']['--azul-noche'] }}">
    @if($ap['previa'])<meta name="robots" content="noindex, nofollow">@endif
    <title>@yield('titulo_pagina') — Portal Meganet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <style>
        :root {
@foreach($ap['css'] as $variable => $valor)
            {{ $variable }}: {{ $valor }};
@endforeach
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

        /* ── Fondo: degradado nocturno + retícula; encima, la fotografía configurada (si hay) ── */
        .pl-fondo {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(60% 50% at 80% 20%, rgba(var(--acento-rgb), .20), transparent 70%),
                radial-gradient(55% 60% at 10% 90%, rgba(var(--electrico-rgb), .26), transparent 70%),
                radial-gradient(45% 45% at 98% 98%, rgba(var(--azul-rgb), .45), transparent 70%),
                linear-gradient(140deg, var(--azul-noche) 0%, var(--azul-profundo) 48%, var(--azul) 160%);
        }
        .pl-fondo-img {
            position: absolute;
            inset: 0;
            background: var(--fondo-img) var(--fondo-pos) / cover no-repeat;
            opacity: var(--img-opacidad);
        }
        .pl-fondo-velo {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(rgba(0, 0, 0, var(--oscurecer)), rgba(0, 0, 0, var(--oscurecer))),
                linear-gradient(90deg,
                    rgba(var(--noche-rgb), var(--velo-1)) 0%,
                    rgba(var(--noche-rgb), var(--velo-2)) 38%,
                    rgba(var(--profundo-rgb), var(--velo-3)) 62%,
                    rgba(var(--noche-rgb), var(--velo-4)) 100%);
        }
        .pl-fondo::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background-image:
                linear-gradient(rgba(var(--acento-rgb), .06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(var(--acento-rgb), .06) 1px, transparent 1px);
            background-size: 56px 56px;
            -webkit-mask-image: radial-gradient(ellipse 75% 70% at 32% 45%, #000 15%, transparent 75%);
                    mask-image: radial-gradient(ellipse 75% 70% at 32% 45%, #000 15%, transparent 75%);
        }
        .pl-fondo::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                linear-gradient(115deg, transparent 38%, rgba(var(--acento-rgb), .07) 47%, transparent 55%),
                linear-gradient(115deg, transparent 58%, rgba(var(--electrico-rgb), .06) 64%, transparent 71%);
        }
        .con-fachada .pl-fondo::before { opacity: .5; }
        @media (min-width: 1024px) {
            .pl-fondo--solo-movil .pl-fondo-img,
            .pl-fondo--solo-movil .pl-fondo-velo { display: none; }
        }

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
        .pl-shell--sin-servicios { grid-template-rows: 1fr; grid-template-areas: "hero acceso"; }
        .pl-shell--sin-servicios .pl-mensaje { margin-bottom: auto; }
        .pl-shell--solo {
            max-width: 560px;
            grid-template-columns: minmax(0, 1fr);
            grid-template-rows: auto auto auto;
            grid-template-areas: "hero" "acceso" "servicios";
            align-content: center;
            row-gap: 28px;
        }
        .pl-shell--solo .pl-hero { align-items: center; }
        .pl-shell--solo .pl-acceso { justify-self: center; }

        .pl-hero { grid-area: hero; display: flex; flex-direction: column; }
        .pl-marca { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .pl-marca img { display: block; height: clamp(56px, 6vw, 84px); width: auto; }
        .pl-eslogan {
            padding-left: 18px;
            border-left: 1px solid rgba(255, 255, 255, .28);
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--cyan);
        }
        .pl-mensaje { margin-top: auto; padding-top: clamp(28px, 6vh, 72px); max-width: 660px; }
        .pl-mensaje::before {
            content: "";
            display: block;
            width: 56px;
            height: 4px;
            border-radius: 4px;
            margin-bottom: 22px;
            background: linear-gradient(90deg, var(--cyan), var(--electrico));
            box-shadow: 0 0 18px rgba(var(--acento-rgb), .6);
        }
        .pl-mensaje h1 {
            font-size: clamp(2.25rem, 1.1rem + 2.9vw, 4.1rem);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -.025em;
            text-shadow: 0 4px 30px rgba(0, 0, 0, .25);
        }
        .pl-mensaje--compacto h1 { font-size: clamp(1.9rem, 1rem + 2vw, 3rem); }
        .pl-mensaje--grande h1 { font-size: clamp(2.5rem, 1.2rem + 3.4vw, 4.8rem); }
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
        .pl-mensaje--centro { text-align: center; margin-left: auto; margin-right: auto; }
        .pl-mensaje--centro::before,
        .pl-mensaje--centro p { margin-left: auto; margin-right: auto; }

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
            border-radius: var(--svc-radio);
            background: linear-gradient(160deg, rgba(var(--profundo-rgb), var(--svc-a1)), rgba(var(--noche-rgb), var(--svc-a2)));
            border: 1px solid rgba(var(--acento-rgb), .16);
            box-shadow: 0 10px 30px rgba(2, 8, 28, .35), inset 0 1px 0 rgba(255, 255, 255, .05);
            -webkit-backdrop-filter: blur(10px);
                    backdrop-filter: blur(10px);
            transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
        }
        .pl-svc:hover {
            transform: translateY(-3px);
            border-color: rgba(var(--acento-rgb), .42);
            box-shadow: 0 16px 36px rgba(var(--electrico-rgb), .25), inset 0 1px 0 rgba(255, 255, 255, .08);
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
        .pl-svc h3 { font-size: 1rem; font-weight: 700; line-height: 1.3; }
        .pl-svc p { margin-top: 4px; font-size: .875rem; line-height: 1.45; color: rgba(205, 222, 250, .76); }

        /* ── Tarjeta de acceso (glassmorphism) ── */
        .pl-acceso { grid-area: acceso; align-self: center; justify-self: end; width: 100%; max-width: 460px; }
        .pl-card {
            padding: clamp(28px, 3vw, 44px);
            border-radius: var(--card-radio);
            color: var(--tinta);
            background: linear-gradient(160deg, rgba(255, 255, 255, var(--card-a1)), rgba(226, 238, 255, var(--card-a2)));
            border: 1px solid rgba(255, 255, 255, .55);
            box-shadow: 0 30px 80px rgba(2, 10, 35, .55), inset 0 1px 0 rgba(255, 255, 255, .7);
            -webkit-backdrop-filter: blur(var(--card-blur)) saturate(140%);
                    backdrop-filter: blur(var(--card-blur)) saturate(140%);
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
        .pl-nota {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            border-left: 4px solid var(--azul);
            background: rgba(var(--electrico-rgb), .08);
            font-size: .84rem;
            line-height: 1.45;
            color: #0b3d7a;
        }

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
        .pl-ayuda { margin-top: 6px; font-size: .78rem; color: var(--tinta-suave); }
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
            box-shadow: 0 0 0 4px rgba(var(--electrico-rgb), .20);
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
        .pl-ojo:hover { color: var(--azul); background: rgba(var(--electrico-rgb), .08); }
        .pl-ojo:focus-visible { outline: 3px solid rgba(var(--electrico-rgb), .6); outline-offset: -3px; }
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
            box-shadow: 0 10px 26px rgba(var(--electrico-rgb), .38);
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
        .pl-btn:hover { filter: brightness(1.07); transform: translateY(-1px); box-shadow: 0 14px 34px rgba(var(--electrico-rgb), .5); }
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
            border-top: 1px solid rgba(var(--azul-rgb), .15);
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
        .pl-enlaces a:focus-visible { outline: 3px solid rgba(var(--electrico-rgb), .6); outline-offset: 2px; }

        .pl-oculto {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
        }

        /* ── Vista previa del panel administrativo ── */
        body.pl-en-previa { padding-top: 44px; }
        .pl-previa-aviso {
            position: fixed;
            top: 12px;
            left: 50%;
            z-index: 50;
            transform: translateX(-50%);
            padding: 8px 16px;
            border-radius: 999px;
            background: rgba(255, 193, 7, .95);
            color: #3a2e00;
            font-size: .8rem;
            font-weight: 700;
            box-shadow: 0 6px 20px rgba(0, 0, 0, .3);
            pointer-events: none;
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
            .pl-mensaje--compacto h1 { font-size: clamp(1.8rem, 1rem + 1.8vw, 2.6rem); }
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
            .pl-fondo-img { background-image: var(--fondo-img-m, var(--fondo-img)); }
            .pl-fondo-velo {
                background:
                    linear-gradient(rgba(0, 0, 0, var(--oscurecer)), rgba(0, 0, 0, var(--oscurecer))),
                    linear-gradient(180deg,
                        rgba(var(--noche-rgb), var(--velo-m1)) 0%,
                        rgba(var(--noche-rgb), var(--velo-m2)) 55%,
                        rgba(var(--noche-rgb), var(--velo-m3)) 100%);
            }
            .pl-shell,
            .pl-shell--sin-servicios {
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
        }

        /* ── Teléfono: el formulario manda; servicios compactos ── */
        @media (max-width: 600px) {
            .pl-shell { padding: 22px 16px 32px; row-gap: 22px; }
            .pl-marca { justify-content: center; }
            .pl-marca img { height: 46px; }
            .pl-eslogan { padding-left: 12px; letter-spacing: .14em; }
            .pl-mensaje { padding-top: 18px; }
            .pl-mensaje::before { width: 44px; margin-bottom: 14px; }
            .pl-mensaje h1,
            .pl-mensaje--grande h1 { font-size: 1.8rem; line-height: 1.12; }
            .pl-mensaje--compacto h1 { font-size: 1.55rem; }
            .pl-mensaje p { margin-top: 10px; font-size: .95rem; line-height: 1.5; }
            .pl-card { padding: 24px 20px; border-radius: min(var(--card-radio), 20px); }
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
@php
    $clasesShell = $ap['mostrar_identidad'] ? (count($ap['tarjetas']) ? '' : 'pl-shell--sin-servicios') : 'pl-shell--solo';
    // Solo hay imagen móvil: en pantallas anchas se oculta y queda el degradado.
    $soloMovil = ! $ap['fondo']['escritorio'] && $ap['fondo']['movil'];
    $estiloFondo = '';
    if ($ap['fondo']['escritorio']) {
        $estiloFondo .= "--fondo-img: url('" . $ap['fondo']['escritorio'] . "');";
    }
    if ($ap['fondo']['movil']) {
        $estiloFondo .= "--fondo-img-m: url('" . $ap['fondo']['movil'] . "');";
    }
    $hayAviso = $errors->any() || session('success') || session('info');
@endphp
<body class="{{ ($ap['fondo']['escritorio'] || $ap['fondo']['movil']) ? 'con-fachada' : '' }} {{ ($hayAviso || $ap['previa']) ? 'sin-entrada' : '' }} {{ $ap['previa'] ? 'pl-en-previa' : '' }}"
    @if($estiloFondo !== '') style="{{ $estiloFondo }}" @endif>
<div class="pl-fondo {{ $soloMovil ? 'pl-fondo--solo-movil' : '' }}" aria-hidden="true">
    @if($ap['fondo']['escritorio'] || $ap['fondo']['movil'])
        <div class="pl-fondo-img"></div>
        <div class="pl-fondo-velo"></div>
    @endif
</div>

@if($ap['previa'])
    <div class="pl-previa-aviso" role="status">Vista previa · los formularios no envían datos</div>
@endif

<main class="pl-shell {{ $clasesShell }}">
    <section class="pl-hero">
        @if($ap['logo'])
            <div class="pl-marca pl-anim">
                <img src="{{ $ap['logo'] }}" alt="Meganet Telecomunicaciones">
                @if($ap['eslogan'] !== '')
                    <span class="pl-eslogan">{{ $ap['eslogan'] }}</span>
                @endif
            </div>
        @endif
        @if($ap['mostrar_identidad'] && ($ap['mensaje']['mostrar_titulo'] || $ap['mensaje']['mostrar_descripcion']))
            <div class="pl-mensaje pl-mensaje--{{ $ap['mensaje']['tamano'] }} {{ $ap['mensaje']['alineacion'] === 'centro' ? 'pl-mensaje--centro' : '' }} pl-anim" style="--d: .08s">
                @if($ap['mensaje']['mostrar_titulo'])
                    <h1>{{ $ap['mensaje']['partes'][0] }}@if($ap['mensaje']['partes'][1] !== '')<span>{{ $ap['mensaje']['partes'][1] }}</span>@endif{{ $ap['mensaje']['partes'][2] }}</h1>
                @endif
                @if($ap['mensaje']['mostrar_descripcion'])
                    <p>{{ $ap['mensaje']['descripcion'] }}</p>
                @endif
            </div>
        @endif
    </section>

    <section class="pl-acceso pl-anim" style="--d: .15s" aria-labelledby="pl-titulo">
        <div class="pl-card">
            @if($ap['logo'])
                <div class="pl-card-logo"><img src="{{ $ap['logo'] }}" alt=""></div>
            @endif
            <h2 id="pl-titulo">{{ $ap['titulo'] }}</h2>
            @if($ap['subtitulo'] !== '')
                <p class="pl-sub">{{ $ap['subtitulo'] }}</p>
            @endif

            @yield('nota')

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

            @yield('formulario')
        </div>
    </section>

    @if(count($ap['tarjetas']))
        <section class="pl-servicios" aria-labelledby="pl-servicios-titulo">
            <h2 class="pl-oculto" id="pl-servicios-titulo">Lo que puedes hacer en tu portal</h2>
            <ul>
                @foreach($ap['tarjetas'] as $i => $tarjeta)
                    <li class="pl-anim" style="--d: {{ number_format(.25 + $i * .07, 2, '.', '') }}s">
                        <div class="pl-svc" style="--g: linear-gradient(135deg, {{ $tarjeta['color']['desde'] }}, {{ $tarjeta['color']['hasta'] }}); --sombra: rgba({{ $tarjeta['color']['sombra'] }}, .42)">
                            <span class="pl-svc-icono">{!! $tarjeta['svg'] !!}</span>
                            <div>
                                <h3>{{ $tarjeta['titulo'] }}</h3>
                                @if($tarjeta['descripcion'] !== '')
                                    <p>{{ $tarjeta['descripcion'] }}</p>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</main>

<script>
    (function () {
        var previa = {{ $ap['previa'] ? 'true' : 'false' }};

        // Mostrar / ocultar contraseña: cada botón .pl-ojo controla el campo de su aria-controls.
        document.querySelectorAll('.pl-ojo').forEach(function (ojo) {
            var campo = document.getElementById(ojo.getAttribute('aria-controls'));
            if (!campo) { return; }
            ojo.addEventListener('click', function () {
                var mostrar = campo.type === 'password';
                campo.type = mostrar ? 'text' : 'password';
                ojo.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
                ojo.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
            });
        });

        document.querySelectorAll('form[data-pl-form]').forEach(function (form) {
            var btn = form.querySelector('.pl-btn');
            var texto = btn ? btn.querySelector('.pl-btn-texto') : null;
            var original = texto ? texto.textContent : '';

            form.addEventListener('submit', function (e) {
                if (previa) { e.preventDefault(); return; }
                // Un solo envío: el submit sólo llega aquí si los campos requeridos ya pasaron la validación del navegador.
                if (form.dataset.plEnviando === '1') { e.preventDefault(); return; }
                form.dataset.plEnviando = '1';
                form.querySelectorAll('input[data-pl-pass]').forEach(function (c) { c.type = 'password'; });
                if (btn) {
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    btn.classList.add('is-loading');
                }
                if (texto) { texto.textContent = form.getAttribute('data-texto-enviando') || original; }
            });

            // Al volver con "Atrás" el navegador puede restaurar la página con el botón bloqueado.
            window.addEventListener('pageshow', function (e) {
                if (!e.persisted) { return; }
                form.dataset.plEnviando = '';
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                    btn.classList.remove('is-loading');
                }
                if (texto) { texto.textContent = original; }
            });
        });
    })();
</script>
</body>
</html>
