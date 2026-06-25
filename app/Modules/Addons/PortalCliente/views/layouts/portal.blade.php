<!DOCTYPE html>
<html lang="es" :class="darkMode ? 'dark' : ''" x-data="{ darkMode: window.localStorage.getItem('portalDark')==='1' }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Cliente') — Meganet</title>

    {{-- Quasar UMD + Vue 3 --}}
    <link href="/plugins/quasar/js/quasar.umd.prod.js" rel="preload" as="script">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">

    <style>
        :root {
            --pcolor: #0057a8;
            --pcolor-dark: #003d78;
            --surface: #f4f6fa;
            --card: #ffffff;
            --text: #1a1a2e;
            --text-muted: #6c757d;
            --border: #dee2e6;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
        }
        html.dark {
            --surface: #121218;
            --card: #1e1e2e;
            --text: #e4e6f0;
            --text-muted: #9ba3af;
            --border: #2e2e42;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--surface);
            color: var(--text);
            min-height: 100vh;
            transition: background .2s, color .2s;
        }
        a { color: var(--pcolor); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ── Top bar ── */
        .portal-topbar {
            background: var(--pcolor);
            color: #fff;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
        }
        .portal-topbar .logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .portal-topbar .logo img { height: 32px; }
        .portal-topbar .spacer { flex: 1; }
        .portal-topbar .client-name {
            font-size: .875rem;
            opacity: .9;
            margin-right: 1rem;
        }
        .dark-toggle {
            background: rgba(255,255,255,.15);
            border: none;
            color: #fff;
            border-radius: 50%;
            width: 36px; height: 36px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            margin-right: .5rem;
        }
        .logout-btn {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.3);
            color: #fff;
            border-radius: 6px;
            padding: .35rem .85rem;
            cursor: pointer;
            font-size: .85rem;
            text-decoration: none;
        }
        .logout-btn:hover { background: rgba(255,255,255,.25); text-decoration: none; }

        /* ── Layout ── */
        .portal-layout {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        .portal-sidebar {
            width: 220px;
            background: var(--card);
            border-right: 1px solid var(--border);
            padding: 1rem 0;
            flex-shrink: 0;
        }
        .portal-sidebar a {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .65rem 1.25rem;
            color: var(--text);
            font-size: .875rem;
            border-radius: 0;
            transition: background .15s, color .15s;
            border-left: 3px solid transparent;
        }
        .portal-sidebar a:hover, .portal-sidebar a.active {
            background: rgba(0,87,168,.08);
            color: var(--pcolor);
            border-left-color: var(--pcolor);
            text-decoration: none;
        }
        .portal-sidebar .nav-section {
            font-size: .7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: .75rem 1.25rem .25rem;
        }
        .portal-main {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
        }

        /* ── KPI Cards ── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .kpi-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: .4rem;
        }
        .kpi-card .kpi-label {
            font-size: .75rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .kpi-card .kpi-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }
        .kpi-card .kpi-sub {
            font-size: .8rem;
            color: var(--text-muted);
        }
        .kpi-card .kpi-icon {
            font-size: 1.5rem;
            margin-bottom: .25rem;
        }

        /* ── Page header ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .page-header h1 {
            font-size: 1.375rem;
            font-weight: 600;
        }

        /* ── Card ── */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* ── Table ── */
        .portal-table { width: 100%; border-collapse: collapse; }
        .portal-table th {
            background: var(--surface);
            padding: .6rem 1rem;
            text-align: left;
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 1px solid var(--border);
        }
        .portal-table td {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--border);
            font-size: .875rem;
            color: var(--text);
        }
        .portal-table tr:last-child td { border-bottom: none; }
        .portal-table tr:hover td { background: rgba(0,87,168,.04); }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: .2rem .6rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger  { background: #f8d7da; color: #721c24; }
        .badge-info    { background: #d1ecf1; color: #0c5460; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }

        /* ── Alerts ── */
        .alert {
            padding: .85rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: .875rem;
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-danger  { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-info    { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }

        /* ── Forms ── */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: .85rem;
            font-weight: 500;
            color: var(--text);
            margin-bottom: .4rem;
        }
        .form-control {
            width: 100%;
            padding: .6rem .9rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--card);
            color: var(--text);
            font-size: .9rem;
            transition: border .15s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--pcolor);
            box-shadow: 0 0 0 3px rgba(0,87,168,.12);
        }
        .form-error { color: var(--danger); font-size: .8rem; margin-top: .3rem; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .55rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-size: .875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s, transform .1s;
        }
        .btn:hover { text-decoration: none; transform: translateY(-1px); }
        .btn-primary { background: var(--pcolor); color: #fff; }
        .btn-primary:hover { background: var(--pcolor-dark); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-outline:hover { background: var(--surface); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-sm { padding: .35rem .85rem; font-size: .8rem; }

        /* ── Service cards (marketplace) ── */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }
        .service-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .service-card .svc-icon { font-size: 2.5rem; }
        .service-card h3 { font-size: 1.1rem; font-weight: 600; }
        .service-card p { font-size: .875rem; color: var(--text-muted); line-height: 1.5; }

        /* ── Tables: scroll horizontal en móvil ── */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        /* ── Dark mode: badges y alerts ── */
        html.dark .badge-success  { background: #1a3a22; color: #6dd08a; }
        html.dark .badge-warning  { background: #3a2e00; color: #ffd966; }
        html.dark .badge-danger   { background: #3a1219; color: #f08090; }
        html.dark .badge-info     { background: #0a2a30; color: #67cfe0; }
        html.dark .badge-secondary{ background: #2a2a3a; color: #b0b8c8; }
        html.dark .alert-success  { background: #1a3a22; color: #6dd08a; border-left-color: #28a745; }
        html.dark .alert-danger   { background: #3a1219; color: #f08090; border-left-color: #dc3545; }
        html.dark .alert-info     { background: #0a2a30; color: #67cfe0; border-left-color: #17a2b8; }
        html.dark .alert-warning  { background: #3a2e00; color: #ffd966; border-left-color: #ffc107; }

        /* ── Mobile ── */
        @media (max-width: 768px) {
            .portal-layout { flex-direction: column; }
            .portal-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border);
                display: flex;
                overflow-x: auto;
                padding: .5rem 0;
            }
            .portal-sidebar a {
                white-space: nowrap;
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            .portal-sidebar a.active, .portal-sidebar a:hover {
                border-left-color: transparent;
                border-bottom-color: var(--pcolor);
            }
            .portal-sidebar .nav-section { display: none; }
            .portal-main { padding: 1rem; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
            /* Grid de dirección en perfil: de 3 columnas a 1 */
            .addr-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

{{-- Top bar --}}
<div class="portal-topbar" x-data="{ dark: window.localStorage.getItem('portalDark')==='1' }">
    <a href="{{ route('portal.dashboard') }}" class="logo">
        @php
            $logo = public_path('img/logo.png');
            $logoAlt = public_path('images/logo.png');
        @endphp
        @if(file_exists($logo))
            <img src="/img/logo.png" alt="Meganet">
        @elseif(file_exists($logoAlt))
            <img src="/images/logo.png" alt="Meganet">
        @else
            🌐
        @endif
        Portal Cliente
    </a>
    <div class="spacer"></div>
    @auth('cliente')
        <span class="client-name">
            {{ auth('cliente')->user()->name ?? 'Cliente' }}
            <span style="opacity:.65; font-size:.8em">#{{ auth('cliente')->user()->client_id }}</span>
        </span>
    @endauth
    <button class="dark-toggle" title="Modo oscuro"
        x-on:click="dark = !dark; localStorage.setItem('portalDark', dark ? '1' : '0'); document.documentElement.classList.toggle('dark', dark)">
        <span x-show="!dark">🌙</span>
        <span x-show="dark">☀️</span>
    </button>
    @auth('cliente')
    <form method="POST" action="{{ route('portal.logout') }}" style="display:inline">
        @csrf
        <button type="submit" class="logout-btn">Salir</button>
    </form>
    @endauth
</div>

<div class="portal-layout">
    {{-- Sidebar --}}
    @auth('cliente')
    <nav class="portal-sidebar">
        <div class="nav-section">Principal</div>
        <a href="{{ route('portal.dashboard') }}" class="{{ request()->routeIs('portal.dashboard*') ? 'active' : '' }}">
            🏠 Dashboard
        </a>
        <div class="nav-section">Mi Servicio</div>
        <a href="{{ route('portal.plan') }}" class="{{ request()->routeIs('portal.plan') ? 'active' : '' }}">
            📡 Mi Plan
        </a>
        <a href="{{ route('portal.facturas') }}" class="{{ request()->routeIs('portal.facturas*') ? 'active' : '' }}">
            📄 Facturas
        </a>
        <a href="{{ route('portal.pagos') }}" class="{{ request()->routeIs('portal.pagos') ? 'active' : '' }}">
            💳 Pagos y CLABE
        </a>
        <a href="{{ route('portal.consumo') }}" class="{{ request()->routeIs('portal.consumo') ? 'active' : '' }}">
            📊 Consumo
        </a>
        <div class="nav-section">Soporte</div>
        <a href="{{ route('portal.tickets') }}" class="{{ request()->routeIs('portal.tickets*') ? 'active' : '' }}">
            🎫 Mis Tickets
        </a>
        <div class="nav-section">Cuenta</div>
        <a href="{{ route('portal.perfil') }}" class="{{ request()->routeIs('portal.perfil') ? 'active' : '' }}">
            👤 Mi Perfil
        </a>
        <a href="{{ route('portal.marketplace') }}" class="{{ request()->routeIs('portal.marketplace') ? 'active' : '' }}">
            🛒 Servicios
        </a>
        <a href="{{ route('portal.embajadores') }}" class="{{ request()->routeIs('portal.embajadores') ? 'active' : '' }}">
            🤝 Embajadores Meganet
        </a>
        <a href="{{ route('portal.flotas') }}" class="{{ request()->routeIs('portal.flotas') ? 'active' : '' }}">
            🚗 Mi Flota
        </a>
        <a href="{{ route('portal.megafamilia') }}" class="{{ request()->routeIs('portal.megafamilia') ? 'active' : '' }}">
            👨‍👩‍👧 MegaFamilia
        </a>
        @php
            $domiciliacionActiva = false;
            try { $domiciliacionActiva = \App\Modules\Core\ModuleManager\Services\ModuleManagerService::instance()->isActive('addon-domiciliacion'); } catch (\Throwable) {}
        @endphp
        @if($domiciliacionActiva)
        <a href="{{ route('portal.domiciliacion.tarjeta') }}" class="{{ request()->routeIs('portal.domiciliacion.*') ? 'active' : '' }}">
            🔄 Pago Automático
        </a>
        @endif
    </nav>
    @endauth

    {{-- Main content --}}
    <main class="portal-main">
        @if(session('success'))
            <div class="alert alert-success">✅ {{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info">ℹ️ {{ session('info') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin:0;padding-left:1.2rem">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

{{-- Alpine.js para dark mode toggle --}}
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
    // Aplica dark mode al cargar sin flash
    (function() {
        if (localStorage.getItem('portalDark') === '1') {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
@stack('scripts')
</body>
</html>
