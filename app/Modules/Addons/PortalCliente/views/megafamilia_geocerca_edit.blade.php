@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Editar geocerca')

@section('content')
@php $esPoly = $geo->type === 'polygon'; @endphp

<div class="page-header">
    <div>
        <h1>📍 Editar geocerca</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">Geocerca #{{ $geo->id }}</p>
    </div>
    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline btn-sm">← Volver</a>
</div>

<div class="card">
    <div class="card-title">Datos de la geocerca</div>
    <div class="mf-geo-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; align-items:start">

        {{-- IZQUIERDA: form --}}
        <div>
            <form method="POST" action="{{ route('portal.megafamilia.geocercas.update', $geo->id) }}">
                @csrf
                @method('PUT')
                <input type="hidden" id="geo-coords-edit" name="coordinates" value="{{ $esPoly ? json_encode($geo->coordinates) : '' }}">

                <div class="form-group">
                    <label>Perfil <span style="color:var(--danger)">*</span></label>
                    <select name="profile_id" class="form-control" required>
                        @foreach($profiles as $p)
                            <option value="{{ $p->id }}" @selected(old('profile_id', $geo->profile_id) == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Nombre <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="nombre" class="form-control" maxlength="100" required value="{{ old('nombre', $geo->name) }}">
                </div>

                <div class="form-group">
                    <label>Tipo de geocerca</label>
                    <div style="display:flex; gap:1rem">
                        <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; margin:0"><input type="radio" name="tipo" value="circle" @checked(! $esPoly) onchange="geoEditToggle('circle')"> ⭕ Círculo</label>
                        <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; margin:0"><input type="radio" name="tipo" value="polygon" @checked($esPoly) onchange="geoEditToggle('polygon')"> 🔷 Polígono</label>
                    </div>
                </div>

                {{-- Círculo --}}
                <div id="circle-fields-edit" style="display:{{ $esPoly ? 'none' : 'block' }}">
                    <div class="form-group">
                        <label>Latitud <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="geo-lat-edit" name="latitud" class="form-control" readonly value="{{ old('latitud', $esPoly ? '' : $geo->lat) }}">
                    </div>
                    <div class="form-group">
                        <label>Longitud <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="geo-lng-edit" name="longitud" class="form-control" readonly value="{{ old('longitud', $esPoly ? '' : $geo->lng) }}">
                    </div>
                    <div class="form-group">
                        <label>Radio: <span id="radio-value-edit">{{ $esPoly ? 500 : (int) $geo->radius_meters }}</span> m</label>
                        <input type="range" id="geo-radio-edit" name="radio_metros" class="form-control" min="50" max="10000" step="50"
                               value="{{ $esPoly ? 500 : (int) $geo->radius_meters }}" oninput="geoEditRadio()" style="height:6px; padding:0">
                    </div>
                </div>

                {{-- Polígono --}}
                <div id="polygon-fields-edit" style="display:{{ $esPoly ? 'block' : 'none' }}">
                    <p style="font-size:.82rem; color:var(--text-muted); margin-bottom:.75rem">Haz clic en el mapa para marcar vértices. Mínimo 3 puntos.</p>
                    <div style="background:var(--surface); padding:.75rem; border-radius:.5rem">
                        <span style="color:var(--text-muted); font-size:.85rem">Puntos marcados: <strong id="polygon-count-edit">{{ $esPoly ? count($geo->coordinates ?? []) : 0 }}</strong></span>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="geoEditClear()" style="margin-top:.5rem; width:100%">🗑️ Borrar puntos</button>
                </div>

                <div class="form-group" style="margin-top:1.25rem">
                    <label>Dirección (opcional)</label>
                    <input type="text" name="direccion" class="form-control" maxlength="255" value="{{ old('direccion', $geo->address) }}">
                </div>

                <div style="margin:1rem 0; display:flex; flex-direction:column; gap:.5rem">
                    <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; font-size:.9rem; margin:0"><input type="checkbox" name="alert_on_enter" value="1" @checked(old('alert_on_enter', $geo->alert_on_enter))> Avisar al entrar</label>
                    <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; font-size:.9rem; margin:0"><input type="checkbox" name="alert_on_exit" value="1" @checked(old('alert_on_exit', $geo->alert_on_exit))> Avisar al salir</label>
                    <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; font-size:.9rem; margin:0"><input type="checkbox" name="active" value="1" @checked(old('active', $geo->active))> Geocerca activa</label>
                </div>

                <div style="display:flex; gap:.75rem; margin-top:1rem">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline">Cancelar</a>
                </div>
            </form>
        </div>

        {{-- DERECHA: mapa --}}
        <div style="position:sticky; top:1rem">
            <div id="mf-geomap-edit" style="height:500px; width:100%; border-radius:.5rem; box-shadow:0 2px 8px rgba(0,0,0,.1); border:1px solid var(--border)"></div>
            <p id="map-hint-edit" style="font-size:.75rem; color:var(--text-muted); margin-top:.5rem; text-align:center">Haz clic en el mapa para ajustar la geocerca.</p>
        </div>
    </div>
</div>

@push('scripts')
    <link rel="stylesheet" href="/assets/libs/leaflet/leaflet.css">
    <script src="/assets/libs/leaflet/leaflet.js"></script>
    <script>
    var GE = { tipo: @json($geo->type === 'polygon' ? 'polygon' : 'circle'), map: null, circle: null, polygon: null, polygonPoints: @json($esPoly ? ($geo->coordinates ?? []) : []), polygonMarkers: [],
               start: [{{ $geo->lat }}, {{ $geo->lng }}], radius: {{ $esPoly ? 500 : (int) $geo->radius_meters }} };

    function geoEditToggle(tipo) {
        GE.tipo = tipo;
        document.getElementById('circle-fields-edit').style.display  = (tipo === 'circle') ? 'block' : 'none';
        document.getElementById('polygon-fields-edit').style.display = (tipo === 'polygon') ? 'block' : 'none';
        document.getElementById('geo-lat-edit').required = (tipo === 'circle');
        document.getElementById('geo-lng-edit').required = (tipo === 'circle');
        var hint = document.getElementById('map-hint-edit');
        hint.textContent = (tipo === 'circle') ? 'Haz clic para fijar el centro; ajusta el radio.' : 'Haz clic para marcar los vértices.';
        if (tipo === 'circle') { if (GE.polygon) GE.polygon.removeFrom(GE.map); if (GE.circle) GE.circle.addTo(GE.map); }
        else { if (GE.circle) GE.circle.removeFrom(GE.map); if (GE.polygon) GE.polygon.addTo(GE.map); }
    }
    function geoEditRadio() {
        var s = document.getElementById('geo-radio-edit');
        document.getElementById('radio-value-edit').textContent = s.value;
        if (GE.circle) GE.circle.setRadius(parseInt(s.value));
    }
    function geoEditDrawPolygon() {
        GE.polygonMarkers.forEach(function (m) { m.removeFrom(GE.map); });
        GE.polygonMarkers = [];
        GE.polygonPoints.forEach(function (pt) {
            GE.polygonMarkers.push(L.circleMarker(pt, { radius: 5, color: '#0057a8', weight: 2, fill: true, fillColor: '#0057a8', fillOpacity: 0.7 }).addTo(GE.map));
        });
        if (GE.polygon) GE.polygon.removeFrom(GE.map);
        if (GE.polygonPoints.length >= 3) {
            GE.polygon = L.polygon(GE.polygonPoints, { color: '#0057a8', weight: 2, fill: true, fillColor: '#0057a8', fillOpacity: 0.2 });
            if (GE.tipo === 'polygon') GE.polygon.addTo(GE.map);
        }
        document.getElementById('geo-coords-edit').value = JSON.stringify(GE.polygonPoints);
        document.getElementById('polygon-count-edit').textContent = GE.polygonPoints.length;
    }
    function geoEditClear() {
        GE.polygonPoints = [];
        GE.polygonMarkers.forEach(function (m) { m.removeFrom(GE.map); });
        GE.polygonMarkers = [];
        if (GE.polygon) { GE.polygon.removeFrom(GE.map); GE.polygon = null; }
        document.getElementById('polygon-count-edit').textContent = '0';
        document.getElementById('geo-coords-edit').value = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof L === 'undefined') return;
        GE.map = L.map('mf-geomap-edit').setView(GE.start, 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(GE.map);

        if (GE.tipo === 'circle') {
            GE.circle = L.circle(GE.start, { radius: GE.radius, color: '#0057a8', weight: 2, fillColor: '#0057a8', fillOpacity: 0.15 }).addTo(GE.map);
        } else {
            geoEditDrawPolygon();
            if (GE.polygonPoints.length) GE.map.fitBounds(GE.polygonPoints);
        }

        GE.map.on('click', function (e) {
            if (GE.tipo === 'circle') {
                document.getElementById('geo-lat-edit').value = e.latlng.lat.toFixed(7);
                document.getElementById('geo-lng-edit').value = e.latlng.lng.toFixed(7);
                if (GE.circle) GE.circle.removeFrom(GE.map);
                var r = parseInt(document.getElementById('geo-radio-edit').value) || 500;
                GE.circle = L.circle(e.latlng, { radius: r, color: '#0057a8', weight: 2, fillColor: '#0057a8', fillOpacity: 0.15 }).addTo(GE.map);
            } else {
                GE.polygonPoints.push([e.latlng.lat, e.latlng.lng]);
                geoEditDrawPolygon();
            }
        });
        setTimeout(function () { GE.map.invalidateSize(); }, 60);
    });
    </script>
@endpush
@endsection
