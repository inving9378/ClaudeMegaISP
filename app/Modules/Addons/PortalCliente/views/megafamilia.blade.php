@extends('addon-portal-cliente::layouts.portal')
@section('title', 'MegaFamilia')

@section('content')
<div class="page-header">
    <div>
        <h1>👨‍👩‍👧 MegaFamilia</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cliente #{{ $cmi->client_id }} — Control parental de la familia
        </p>
    </div>
</div>

@if(! ($active ?? false))
    {{-- Estado vacío --}}
    <div class="card" style="text-align:center; padding:2.5rem 1.5rem">
        <div style="font-size:3rem; margin-bottom:.5rem">🛡️</div>
        <h2 style="margin-bottom:.5rem">Aún no tienes MegaFamilia activo</h2>
        <p style="color:var(--text-muted); max-width:520px; margin:0 auto 1.25rem">
            Protege a tu familia: perfiles por hijo, control de dispositivos, reglas de
            tiempo y geocercas. Activa MegaFamilia desde tus servicios.
        </p>
        <a href="{{ route('portal.marketplace') }}" class="btn btn-primary">Activa MegaFamilia</a>
    </div>
@else
    @php
        $estados = [
            'active'    => ['Activa', 'badge-success'],
            'suspended' => ['Suspendida', 'badge-warning'],
            'cancelled' => ['Cancelada', 'badge-secondary'],
        ];
        $tipoLabels  = ['nino' => 'Niño', 'preadolescente' => 'Preadolescente', 'adolescente' => 'Adolescente'];
        $nivelLabels = ['primaria' => 'Primaria', 'secundaria' => 'Secundaria', 'preparatoria' => 'Preparatoria'];
        $devTipoLabels = ['smartphone' => '📱 Smartphone', 'tablet' => '📲 Tablet', 'pc' => '💻 PC', 'otro' => '🔌 Otro'];
        $diasLargo = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
        $diasCorto = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];
        $taskEstados = [
            'pending' => ['Pendiente', 'badge-warning'], 'completed' => ['Completada', 'badge-info'],
            'approved' => ['Aprobada', 'badge-success'], 'rejected' => ['Rechazada', 'badge-danger'],
        ];
        $perfiles = $cuentas->flatMap(fn ($c) => $c->profiles);
    @endphp

    {{-- KPIs --}}
    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-icon">🏠</div><div class="kpi-label">Cuentas</div><div class="kpi-value">{{ (int) $standing['cuentas'] }}</div><div class="kpi-sub">De tu familia</div></div>
        <div class="kpi-card"><div class="kpi-icon">🧒</div><div class="kpi-label">Perfiles</div><div class="kpi-value">{{ (int) $standing['perfiles'] }}</div><div class="kpi-sub">Hijos registrados</div></div>
        <div class="kpi-card"><div class="kpi-icon">📱</div><div class="kpi-label">Dispositivos</div><div class="kpi-value">{{ (int) $standing['dispositivos'] }}</div><div class="kpi-sub">Vinculados</div></div>
        <div class="kpi-card"><div class="kpi-icon">⏱️</div><div class="kpi-label">Reglas</div><div class="kpi-value">{{ (int) $standing['reglas'] }}</div><div class="kpi-sub">Configuradas</div></div>
    </div>

    {{-- ══════════ TABS PRINCIPALES ══════════ --}}
    <div class="mf-tabs">
        <div class="mf-tabs-nav">
            <button class="mf-tab-btn active" data-tab="cuentas">📦 Mis cuentas</button>
            <button class="mf-tab-btn" data-tab="perfiles">🧒 Perfiles de hijos</button>
            <button class="mf-tab-btn" data-tab="solicitudes">🔔 Solicitudes
                @if(($solicitudesPendientes ?? 0) > 0)<span class="badge badge-danger" style="margin-left:.4rem">{{ $solicitudesPendientes }}</span>@endif
            </button>
        </div>

        {{-- ── TAB 1: MIS CUENTAS ── --}}
        <div class="mf-tab-content active" id="mf-tab-cuentas">
            <div class="card">
                <div class="card-title">📦 Mis cuentas ({{ $cuentas->count() }})</div>
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead><tr><th>Cuenta</th><th>Estado</th><th>Perfiles</th><th>Dispositivos</th></tr></thead>
                        <tbody>
                            @foreach($cuentas as $cuenta)
                                @php [$estLabel, $estCls] = $estados[$cuenta->status] ?? [ucfirst($cuenta->status), 'badge-secondary']; @endphp
                                <tr>
                                    <td><strong>Cuenta #{{ $cuenta->id }}</strong></td>
                                    <td><span class="badge {{ $estCls }}">{{ $estLabel }}</span></td>
                                    <td>{{ (int) $cuenta->profiles_count }}</td>
                                    <td>{{ (int) $cuenta->devices_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── TAB 2: PERFILES DE HIJOS (sub-tabs por hijo) ── --}}
        <div class="mf-tab-content" id="mf-tab-perfiles">
            @if($perfiles->isEmpty())
                <div class="card">
                    <p style="color:var(--text-muted); margin-bottom:1rem">Aún no has registrado perfiles. Agrega el primero:</p>
                    @include('addon-portal-cliente::partials.megafamilia_form_perfil', ['cuentas' => $cuentas, 'tipoLabels' => $tipoLabels, 'nivelLabels' => $nivelLabels])
                </div>
            @else
                {{-- Sub-tabs por perfil --}}
                <div class="mf-subtabs-nav">
                    @foreach($perfiles as $idx => $perfil)
                        <button class="mf-subtab-btn {{ $idx === 0 ? 'active' : '' }}" data-subtab="{{ $perfil->id }}">{{ $perfil->name }}</button>
                    @endforeach
                    <button class="mf-subtab-btn" data-subtab="nuevo">➕ Agregar</button>
                </div>

                @foreach($perfiles as $idx => $perfil)
                    <div class="mf-subtab-content {{ $idx === 0 ? 'active' : '' }}" id="mf-subtab-{{ $perfil->id }}">
                        <div class="card mf-profile-card">
                            {{-- Cabecera del perfil --}}
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; margin-bottom:1rem">
                                <div>
                                    <strong style="font-size:1.1rem">{{ $perfil->name }}</strong>
                                    <span style="color:var(--text-muted); font-size:.85rem; margin-left:.5rem">
                                        {{ $perfil->age ? $perfil->age.' años' : '—' }} · {{ $tipoLabels[$perfil->profile_type] ?? '—' }}
                                        @if($perfil->school_level) · {{ $nivelLabels[$perfil->school_level] ?? '' }}@endif
                                    </span>
                                    @php $bal = (int) ($balances[$perfil->id] ?? 0); $balCls = $bal >= 100 ? 'badge-success' : ($bal >= 50 ? 'badge-warning' : 'badge-danger'); @endphp
                                    <div style="margin-top:.5rem"><span class="badge {{ $balCls }}" style="font-size:.85rem">💰 Balance: {{ $bal }} pts</span></div>
                                </div>
                                <div style="white-space:nowrap">
                                    <a href="{{ route('portal.megafamilia.perfiles.edit', $perfil->id) }}" class="btn btn-outline btn-sm">✏️ Editar</a>
                                    <form method="POST" action="{{ route('portal.megafamilia.perfiles.destroy', $perfil->id) }}" style="display:inline"
                                          onsubmit="return confirm('¿Eliminar el perfil «{{ $perfil->name }}»? Se eliminará todo su contenido.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">🗑️ Eliminar</button>
                                    </form>
                                </div>
                            </div>

                            {{-- Sub-sub-tabs internas --}}
                            <div class="mf-subtabs2-nav">
                                <button class="mf-subtab2-btn active" data-profile="{{ $perfil->id }}" data-sec="disp-{{ $perfil->id }}">📱 Dispositivos</button>
                                <button class="mf-subtab2-btn" data-profile="{{ $perfil->id }}" data-sec="bloqueos-{{ $perfil->id }}">🚫 Bloqueos</button>
                                <button class="mf-subtab2-btn" data-profile="{{ $perfil->id }}" data-sec="horarios-{{ $perfil->id }}">⏰ Horarios</button>
                                <button class="mf-subtab2-btn" data-profile="{{ $perfil->id }}" data-sec="geocercas-{{ $perfil->id }}">📍 Geocercas</button>
                                <button class="mf-subtab2-btn" data-profile="{{ $perfil->id }}" data-sec="tareas-{{ $perfil->id }}">🎯 Tareas</button>
                            </div>

                            {{-- G2 Dispositivos --}}
                            <div class="mf-subtab2-content active" id="mf-sec-disp-{{ $perfil->id }}">
                                @include('addon-portal-cliente::partials.megafamilia_sec_dispositivos', compact('perfil', 'devTipoLabels'))
                            </div>
                            {{-- G3 Bloqueos --}}
                            <div class="mf-subtab2-content" id="mf-sec-bloqueos-{{ $perfil->id }}">
                                @include('addon-portal-cliente::partials.megafamilia_sec_bloqueos', compact('perfil'))
                            </div>
                            {{-- G4 Horarios --}}
                            <div class="mf-subtab2-content" id="mf-sec-horarios-{{ $perfil->id }}">
                                @include('addon-portal-cliente::partials.megafamilia_sec_horarios', compact('perfil', 'diasLargo', 'diasCorto'))
                            </div>
                            {{-- G5 Geocercas --}}
                            <div class="mf-subtab2-content" id="mf-sec-geocercas-{{ $perfil->id }}">
                                @include('addon-portal-cliente::partials.megafamilia_sec_geocercas', compact('perfil'))
                            </div>
                            {{-- G6 Tareas y recompensas --}}
                            <div class="mf-subtab2-content" id="mf-sec-tareas-{{ $perfil->id }}">
                                @include('addon-portal-cliente::partials.megafamilia_sec_tareas', compact('perfil', 'taskEstados', 'bal'))
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Sub-tab "Agregar perfil" --}}
                <div class="mf-subtab-content" id="mf-subtab-nuevo">
                    <div class="card">
                        <div class="card-title">➕ Agregar perfil nuevo</div>
                        @include('addon-portal-cliente::partials.megafamilia_form_perfil', ['cuentas' => $cuentas, 'tipoLabels' => $tipoLabels, 'nivelLabels' => $nivelLabels])
                    </div>
                </div>
            @endif
        </div>

        {{-- ── TAB 3: SOLICITUDES ── --}}
        <div class="mf-tab-content" id="mf-tab-solicitudes">
            @include('addon-portal-cliente::partials.megafamilia_solicitudes')
        </div>
    </div>

    <style>
        .mf-tabs-nav { border-bottom:2px solid var(--border); display:flex; gap:.5rem; margin-bottom:1.25rem; flex-wrap:wrap }
        .mf-tab-btn { cursor:pointer; padding:.55rem 1rem; background:transparent; border:none; border-bottom:3px solid transparent; color:var(--text); font-size:.9rem; font-weight:500 }
        .mf-tab-btn.active { border-bottom-color:var(--pcolor); color:var(--pcolor) }
        .mf-tab-content { display:none } .mf-tab-content.active { display:block }
        .mf-subtabs-nav { border-bottom:2px solid var(--border); display:flex; gap:.4rem; margin-bottom:1rem; overflow-x:auto }
        .mf-subtab-btn { cursor:pointer; padding:.5rem .8rem; background:transparent; border:none; border-bottom:3px solid transparent; color:var(--text); font-size:.875rem; white-space:nowrap }
        .mf-subtab-btn.active { border-bottom-color:var(--pcolor); color:var(--pcolor); font-weight:600 }
        .mf-subtab-content { display:none } .mf-subtab-content.active { display:block }
        .mf-subtabs2-nav { border-bottom:1px solid var(--border); display:flex; gap:.5rem; margin-bottom:1rem; font-size:.85rem; overflow-x:auto }
        .mf-subtab2-btn { cursor:pointer; padding:.45rem .6rem; background:transparent; border:none; border-bottom:3px solid transparent; color:var(--text); white-space:nowrap }
        .mf-subtab2-btn.active { border-bottom-color:var(--pcolor); color:var(--pcolor); font-weight:600 }
        .mf-subtab2-content { display:none } .mf-subtab2-content.active { display:block }
    </style>

@push('scripts')
    <link rel="stylesheet" href="/assets/libs/leaflet/leaflet.css">
    <script src="/assets/libs/leaflet/leaflet.js"></script>
    <script>
    // Inicializa (perezosamente) el mapa de geocercas de un perfil. Modo CÍRCULO:
    // clic fija el centro (lat/lng), el slider ajusta el radio. circleMarker+circle
    // (sin iconos de marcador → evita el bug de imágenes rotas de Leaflet+webpack).
    window.mfGeoMaps = window.mfGeoMaps || {};
    window.mfInitGeoMap = function (pid) {
        var el = document.getElementById('mf-geomap-' + pid);
        if (!el || typeof L === 'undefined') return;
        if (window.mfGeoMaps[pid]) { window.mfGeoMaps[pid].invalidateSize(); return; }
        var lat = parseFloat(el.getAttribute('data-lat')) || 19.4326;
        var lng = parseFloat(el.getAttribute('data-lng')) || -99.1332;
        var rad = parseInt(el.getAttribute('data-radius')) || 200;
        var map = L.map(el).setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
        var center = L.circleMarker([lat, lng], { radius: 6, color: '#0057a8', fillColor: '#0057a8', fillOpacity: 1 }).addTo(map);
        var circle = L.circle([lat, lng], { radius: rad, color: '#0057a8', fillColor: '#0057a8', fillOpacity: 0.15 }).addTo(map);
        var latI = document.getElementById('geo-lat-' + pid), lngI = document.getElementById('geo-lng-' + pid),
            radI = document.getElementById('geo-radio-' + pid), radV = document.getElementById('geo-radio-val-' + pid);
        // Prefill desde inputs (modo edición) si ya traen valor.
        if (latI && latI.value && lngI && lngI.value) {
            var la = parseFloat(latI.value), lo = parseFloat(lngI.value);
            center.setLatLng([la, lo]); circle.setLatLng([la, lo]); map.setView([la, lo], 15);
        }
        map.on('click', function (e) {
            if (latI) latI.value = e.latlng.lat.toFixed(7);
            if (lngI) lngI.value = e.latlng.lng.toFixed(7);
            center.setLatLng(e.latlng); circle.setLatLng(e.latlng);
        });
        if (radI) radI.addEventListener('input', function () {
            var r = parseInt(this.value) || 0; circle.setRadius(r); if (radV) radV.textContent = r;
        });
        window.mfGeoMaps[pid] = map;
        setTimeout(function () { map.invalidateSize(); }, 60);
    };

    (function () {
        var ss = window.sessionStorage;
        function show(list, contentSel, id, prefix) {
            document.querySelectorAll(contentSel).forEach(function (c) { c.classList.remove('active'); });
            var el = document.getElementById(prefix + id);
            if (el) el.classList.add('active');
            list.forEach(function (b) { b.classList.remove('active'); });
        }
        // Main tabs
        document.querySelectorAll('.mf-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-tab');
                show(document.querySelectorAll('.mf-tab-btn'), '.mf-tab-content', name, 'mf-tab-');
                this.classList.add('active'); ss.setItem('mf_tab', name);
            });
        });
        // Profile sub-tabs
        document.querySelectorAll('.mf-subtab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-subtab');
                show(document.querySelectorAll('.mf-subtab-btn'), '.mf-subtab-content', name, 'mf-subtab-');
                this.classList.add('active'); ss.setItem('mf_subtab', name);
            });
        });
        // Section sub-sub-tabs (scoped to the profile card)
        document.querySelectorAll('.mf-subtab2-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sec = this.getAttribute('data-sec'), prof = this.getAttribute('data-profile'), card = this.closest('.mf-profile-card');
                card.querySelectorAll('.mf-subtab2-btn').forEach(function (b) { b.classList.remove('active'); });
                card.querySelectorAll('.mf-subtab2-content').forEach(function (c) { c.classList.remove('active'); });
                this.classList.add('active');
                var el = document.getElementById('mf-sec-' + sec); if (el) el.classList.add('active');
                ss.setItem('mf_sec_' + prof, sec);
                if (sec.indexOf('geocercas-') === 0 && window.mfInitGeoMap) window.mfInitGeoMap(prof);
            });
        });
        // Restore on load (hash wins for main tab; else sessionStorage)
        function clickByData(sel, attr, val) { var b = document.querySelector(sel + '[' + attr + '="' + val + '"]'); if (b) b.click(); }
        var hash = (location.hash || '').replace('#', '');
        if (hash) clickByData('.mf-tab-btn', 'data-tab', hash);
        else if (ss.getItem('mf_tab')) clickByData('.mf-tab-btn', 'data-tab', ss.getItem('mf_tab'));
        if (ss.getItem('mf_subtab')) clickByData('.mf-subtab-btn', 'data-subtab', ss.getItem('mf_subtab'));
        document.querySelectorAll('.mf-profile-card').forEach(function (card) {
            var btn = card.querySelector('.mf-subtab2-btn'); if (!btn) return;
            var prof = btn.getAttribute('data-profile'), sec = ss.getItem('mf_sec_' + prof);
            if (sec) { var t = card.querySelector('.mf-subtab2-btn[data-sec="' + sec + '"]'); if (t) t.click(); }
        });
    })();
    </script>
@endpush
@endif
@endsection
