@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Mis Vehículos')

@section('content')
<div class="page-header">
    <div>
        <h1>🚗 Mis Vehículos</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cliente #{{ $cmi->client_id }} — Tus vehículos y mantenimientos
        </p>
    </div>
</div>

@if(! $active)
    {{-- Estado vacío: el cliente no tiene vehículos --}}
    @php
        // NOTA: la columna `cliente_dedicado` NO existe hoy en `clients`/CMI (auditado).
        // Placeholder: por defecto trato al cliente como residencial. Cuando exista la
        // columna, sustituir por: $clienteDedicado = (bool) ($cmi->cliente_dedicado ?? false);
        $clienteDedicado = false;
    @endphp
    <div class="card" style="text-align:center; padding:2.5rem 1.5rem">
        <div style="font-size:3rem; margin-bottom:.5rem">🚙</div>
        <h2 style="margin-bottom:.5rem">
            {{ $clienteDedicado ? 'Gestiona la flota de tu empresa' : 'Administra y cuida tus vehículos' }}
        </h2>
        <p style="color:var(--text-muted); max-width:520px; margin:0 auto 1.25rem">
            Lleva el control de tus vehículos, mantenimientos, documentos y rastreo GPS
            desde un solo lugar.
        </p>
        <a href="{{ route('portal.marketplace') }}" class="btn btn-primary">Activar módulo de Vehículos</a>
    </div>
@else
    @php
        $tipos = [
            'car'        => 'Automóvil',
            'pickup'     => 'Pick-up',
            'truck'      => 'Camión',
            'motorcycle' => 'Motocicleta',
            'other'      => 'Otro',
        ];
        $estados = [
            'active'      => ['Activo', 'badge-success'],
            'in_workshop' => ['En taller', 'badge-warning'],
            'inactive'    => ['Inactivo', 'badge-secondary'],
        ];
        $liveEstados = [
            'moving'  => ['En movimiento', 'badge-success'],
            'stopped' => ['Detenido', 'badge-warning'],
            'idle'    => ['Inactivo', 'badge-secondary'],
            'offline' => ['Sin señal', 'badge-secondary'],
        ];
    @endphp

    {{-- KPI Cards --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">🚗</div>
            <div class="kpi-label">Vehículos</div>
            <div class="kpi-value">{{ (int) $standing['total'] }}</div>
            <div class="kpi-sub">En tu flota</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Activos</div>
            <div class="kpi-value" style="color:var(--success)">{{ (int) $standing['activos'] }}</div>
            <div class="kpi-sub">Operando</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🔧</div>
            <div class="kpi-label">Mantenimientos próximos</div>
            <div class="kpi-value" style="color: {{ $standing['mant_proximos'] > 0 ? 'var(--warning)' : 'var(--success)' }}">
                {{ (int) $standing['mant_proximos'] }}
            </div>
            <div class="kpi-sub">En los próximos 30 días</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📍</div>
            <div class="kpi-label">Geocercas activas</div>
            <div class="kpi-value">{{ (int) $standing['geocercas'] }}</div>
            <div class="kpi-sub">Zonas monitoreadas</div>
        </div>
    </div>

    @if($subscription)
        <div class="card">
            <div class="card-title">Tu suscripción</div>
            <p style="color:var(--text-muted)">
                Plan: <strong>{{ $subscription->plan ?? '—' }}</strong>
                · Estado:
                <span class="badge badge-info">{{ ucfirst($subscription->status ?? '—') }}</span>
            </p>
        </div>
    @endif

    {{-- Mapa overview: última posición conocida de todos los vehículos con GPS --}}
    @php
        $trackedVehicles = $tracking->filter(fn ($t) => $t['position'] !== null)->values();
    @endphp
    <div class="card">
        <div class="card-title">Ubicación de mi flota</div>
        @if($trackedVehicles->isEmpty())
            <p style="color:var(--text-muted)">Aún no hay datos de rastreo GPS para tus vehículos.</p>
        @else
            <div id="flota-map" style="height:400px; width:100%; border-radius:.5rem; box-shadow:0 2px 8px rgba(0,0,0,.1); border:1px solid var(--border)"></div>
            <p style="font-size:.75rem; color:var(--text-muted); margin-top:.5rem">
                Se actualiza automáticamente cada 25 segundos.
            </p>
        @endif
    </div>

    {{-- Vehículos --}}
    <div class="card">
        <div class="card-title">Mis vehículos ({{ (int) $standing['total'] }})</div>
        @if($vehicles->isEmpty())
            <p style="color:var(--text-muted)">Todavía no tienes vehículos registrados.</p>
        @else
            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr><th>Vehículo</th><th>Placa</th><th>Tipo</th><th>Año</th><th>Estado</th><th>Ubicación</th></tr>
                    </thead>
                    <tbody>
                        @foreach($vehicles as $v)
                            @php
                                [$estLabel, $estCls] = $estados[$v->status] ?? [ucfirst($v->status), 'badge-secondary'];
                                $track = $tracking[$v->id] ?? null;
                                $pos   = $track['position'] ?? null;
                                [$liveLabel, $liveCls] = $liveEstados[$track['live_status'] ?? 'offline'] ?? ['Sin GPS', 'badge-secondary'];
                            @endphp
                            <tr>
                                <td>{{ trim($v->brand.' '.$v->model) ?: 'Vehículo' }}</td>
                                <td>{{ $v->plates }}</td>
                                <td>{{ $tipos[$v->vehicle_type] ?? 'Otro' }}</td>
                                <td>{{ $v->year ?: '—' }}</td>
                                <td><span class="badge {{ $estCls }}">{{ $estLabel }}</span></td>
                                <td>
                                    @if(! $track)
                                        <span class="badge badge-secondary">Sin GPS</span>
                                    @else
                                        <span class="badge {{ $liveCls }}">{{ $liveLabel }}</span>
                                        @if($pos)
                                            <div style="font-size:.75rem; color:var(--text-muted); margin-top:.2rem">
                                                {{ \Carbon\Carbon::parse($pos['recorded_at'])->diffForHumans() }}
                                                ·
                                                <a href="https://www.google.com/maps?q={{ $pos['lat'] }},{{ $pos['lng'] }}" target="_blank" rel="noopener">Ver mapa</a>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($trackedVehicles->isNotEmpty())
        @push('scripts')
            <link rel="stylesheet" href="/assets/libs/leaflet/leaflet.css">
            <script src="/assets/libs/leaflet/leaflet.js"></script>
            <script>
            (function () {
                var COLORS = { moving: '#22c55e', stopped: '#f59e0b', idle: '#9ca3af', offline: '#ef4444' };
                var LABELS = { moving: 'En movimiento', stopped: 'Detenido', idle: 'Inactivo', offline: 'Sin señal' };
                var initial = @json($trackedVehicles);
                var map = null, markers = {};

                function popupHtml(v) {
                    var label = LABELS[v.live_status] || 'Sin señal';
                    var name = (v.brand + ' ' + v.model).trim() || v.display_name || ('Vehículo #' + v.vehicle_id);
                    return '<strong>' + name + '</strong><br>' + v.plates + '<br>' + label;
                }

                function upsertMarker(v) {
                    if (!v.position) return;
                    var latlng = [v.position.lat, v.position.lng];
                    var color = COLORS[v.live_status] || COLORS.offline;
                    if (markers[v.vehicle_id]) {
                        markers[v.vehicle_id].setLatLng(latlng).setStyle({ color: '#fff', fillColor: color });
                        markers[v.vehicle_id].setPopupContent(popupHtml(v));
                    } else {
                        markers[v.vehicle_id] = L.circleMarker(latlng, {
                            radius: 9, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1,
                        }).addTo(map).bindPopup(popupHtml(v));
                    }
                }

                function refresh() {
                    fetch('{{ route('portal.flotas.tracking') }}', { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (data) {
                            if (!data || !data.vehicles) return;
                            data.vehicles.forEach(upsertMarker);
                        })
                        .catch(function () { /* silencioso: se reintenta en el próximo ciclo */ });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof L === 'undefined') return;
                    var el = document.getElementById('flota-map');
                    if (!el) return;

                    map = L.map('flota-map');
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);

                    initial.forEach(upsertMarker);

                    var bounds = Object.keys(markers).map(function (id) { return markers[id].getLatLng(); });
                    if (bounds.length === 1) map.setView(bounds[0], 14);
                    else if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30] });

                    setTimeout(function () { map.invalidateSize(); }, 60);
                    setInterval(refresh, 25000);
                });
            })();
            </script>
        @endpush
    @endif
@endif
@endsection
