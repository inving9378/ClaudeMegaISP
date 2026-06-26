{{-- G5 Geocercas del perfil — círculo o polígono, con mapa Leaflet. Espera $perfil. --}}
@if($perfil->geofences->isEmpty())
    <p style="color:var(--text-muted); font-size:.85rem; margin-bottom:.6rem">Sin geocercas configuradas.</p>
@else
    <div class="table-responsive">
        <table class="portal-table">
            <thead><tr><th>Nombre</th><th>Forma</th><th>Alertas</th><th>Estado</th><th style="text-align:right">Acciones</th></tr></thead>
            <tbody>
                @foreach($perfil->geofences as $geo)
                    <tr>
                        <td><strong>{{ $geo->name }}</strong>@if($geo->address)<div style="color:var(--text-muted); font-size:.78rem">{{ $geo->address }}</div>@endif</td>
                        <td style="font-size:.82rem">
                            @if($geo->type === 'polygon')
                                🔷 Polígono <span class="badge badge-info">{{ count($geo->coordinates ?? []) }} vért.</span>
                            @else
                                ⭕ Círculo <span class="badge badge-info">{{ (int) $geo->radius_meters }} m</span>
                            @endif
                        </td>
                        <td style="font-size:.78rem">
                            @if($geo->alert_on_enter)<span title="Avisar al entrar">➡️ Entra</span>@endif
                            @if($geo->alert_on_exit)<span title="Avisar al salir">⬅️ Sale</span>@endif
                            @if(! $geo->alert_on_enter && ! $geo->alert_on_exit)—@endif
                        </td>
                        <td>@if($geo->active)<span class="badge badge-success">Activa</span>@else<span class="badge badge-secondary">Inactiva</span>@endif</td>
                        <td style="text-align:right; white-space:nowrap">
                            <a href="{{ route('portal.megafamilia.geocercas.edit', $geo->id) }}" class="btn btn-outline btn-sm">✏️</a>
                            <form method="POST" action="{{ route('portal.megafamilia.geocercas.destroy', $geo->id) }}" style="display:inline"
                                  onsubmit="return confirm('¿Eliminar la geocerca «{{ $geo->name }}»?')">
                                @csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Alta de geocerca: form (izq) + mapa (der) --}}
<div style="margin-top:1rem">
    <div style="font-weight:600; font-size:.82rem; color:var(--pcolor); margin-bottom:.75rem">➕ Agregar geocerca</div>
    <div class="mf-geo-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; align-items:start">

        {{-- COLUMNA IZQUIERDA: Formulario --}}
        <div>
            <form method="POST" action="{{ route('portal.megafamilia.geocercas.store') }}" id="form-geocerca-{{ $perfil->id }}">
                @csrf
                <input type="hidden" name="profile_id" value="{{ $perfil->id }}">
                <input type="hidden" id="geo-coords-{{ $perfil->id }}" name="coordinates" value="">

                <div class="form-group">
                    <label>Nombre <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="nombre" class="form-control" maxlength="100" required placeholder="Ej. Casa">
                </div>

                {{-- Toggle círculo / polígono --}}
                <div class="form-group">
                    <label>Tipo de geocerca</label>
                    <div style="display:flex; gap:1rem">
                        <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; margin:0"><input type="radio" name="tipo" value="circle" checked onchange="toggleGeoTipo('{{ $perfil->id }}','circle')"> ⭕ Círculo</label>
                        <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; margin:0"><input type="radio" name="tipo" value="polygon" onchange="toggleGeoTipo('{{ $perfil->id }}','polygon')"> 🔷 Polígono</label>
                    </div>
                </div>

                {{-- Campos círculo --}}
                <div id="circle-fields-{{ $perfil->id }}">
                    <div class="form-group">
                        <label>Latitud <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="geo-lat-{{ $perfil->id }}" name="latitud" class="form-control" readonly placeholder="Clic en el mapa">
                    </div>
                    <div class="form-group">
                        <label>Longitud <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="geo-lng-{{ $perfil->id }}" name="longitud" class="form-control" readonly placeholder="Clic en el mapa">
                    </div>
                    <div class="form-group">
                        <label>Radio: <span id="radio-value-{{ $perfil->id }}">500</span> m</label>
                        <input type="range" id="geo-radio-{{ $perfil->id }}" name="radio_metros" class="form-control" min="50" max="10000" step="50" value="500"
                               oninput="updateRadioDisplay('{{ $perfil->id }}')" style="height:6px; padding:0">
                    </div>
                </div>

                {{-- Campos polígono --}}
                <div id="polygon-fields-{{ $perfil->id }}" style="display:none">
                    <p style="font-size:.82rem; color:var(--text-muted); margin-bottom:.75rem">
                        Haz clic en el mapa para marcar los vértices. Mínimo 3 puntos.
                    </p>
                    <div style="background:var(--surface); padding:.75rem; border-radius:.5rem">
                        <span style="color:var(--text-muted); font-size:.85rem">Puntos marcados: <strong id="polygon-count-{{ $perfil->id }}">0</strong></span>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="clearPolygonPoints('{{ $perfil->id }}')" style="margin-top:.5rem; width:100%">🗑️ Borrar puntos</button>
                </div>

                <div class="form-group" style="margin-top:1.25rem">
                    <label>Dirección (opcional)</label>
                    <input type="text" name="direccion" class="form-control" maxlength="255" placeholder="Calle, colonia…">
                </div>

                <div style="margin:1rem 0; display:flex; flex-direction:column; gap:.5rem">
                    <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; font-size:.85rem; margin:0"><input type="checkbox" name="alert_on_enter" value="1" checked> Avisar al entrar</label>
                    <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; font-size:.85rem; margin:0"><input type="checkbox" name="alert_on_exit" value="1" checked> Avisar al salir</label>
                    <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; font-size:.85rem; margin:0"><input type="checkbox" name="active" value="1" checked> Geocerca activa</label>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">Guardar geocerca</button>
            </form>
        </div>

        {{-- COLUMNA DERECHA: Mapa --}}
        <div style="position:sticky; top:1rem">
            <div class="mf-geomap" id="mf-geomap-{{ $perfil->id }}"
                 style="height:500px; width:100%; border-radius:.5rem; box-shadow:0 2px 8px rgba(0,0,0,.1); border:1px solid var(--border)"></div>
            <p id="map-hint-{{ $perfil->id }}" style="font-size:.75rem; color:var(--text-muted); margin-top:.5rem; text-align:center">
                Haz clic en el mapa para fijar el centro. Ajusta el radio con el slider.
            </p>
        </div>
    </div>
</div>

@once
<style>
    @media (max-width:768px){ .mf-geo-grid{ grid-template-columns:1fr !important } .mf-geo-grid [style*="sticky"]{ position:static !important } }
    .mf-vertex{ width:16px; height:16px; background:#0057a8; border:2px solid #fff; border-radius:50%; box-shadow:0 0 0 1px #0057a8; cursor:move }
</style>
<script>
    // Vértice DRAGGABLE (L.marker + divIcon, sin imágenes → sin iconos rotos).
    window.mfRedrawPoly = function (st, pid) {
        if (st.polygon) st.polygon.removeFrom(st.map);
        st.polygon = null;
        if (st.polygonPoints.length >= 3) {
            st.polygon = L.polygon(st.polygonPoints, { color: '#0057a8', weight: 2, fill: true, fillColor: '#0057a8', fillOpacity: 0.2 }).addTo(st.map);
        }
        var hid = document.getElementById('geo-coords-' + pid); if (hid) hid.value = JSON.stringify(st.polygonPoints);
        var cnt = document.getElementById('polygon-count-' + pid); if (cnt) cnt.textContent = st.polygonPoints.length;
    };
    window.mfAddVertex = function (st, latlng, pid) {
        var marker = L.marker(latlng, { draggable: true, icon: L.divIcon({ className: 'mf-vertex', iconSize: [16, 16], iconAnchor: [8, 8] }) }).addTo(st.map);
        st.polygonPoints.push([latlng.lat, latlng.lng]);
        st.polygonMarkers.push(marker);
        marker.on('drag dragend', function (e) {
            var idx = st.polygonMarkers.indexOf(marker);
            if (idx < 0) return;
            var ll = e.target.getLatLng();
            st.polygonPoints[idx] = [ll.lat, ll.lng];
            window.mfRedrawPoly(st, pid); // redibuja en tiempo real + persiste hidden
        });
        window.mfRedrawPoly(st, pid);
    };

    // Funciones compartidas (perfilId como argumento → una sola definición).
    function toggleGeoTipo(pid, tipo) {
        var st = window['geoState_' + pid];
        document.getElementById('circle-fields-' + pid).style.display  = (tipo === 'circle') ? 'block' : 'none';
        document.getElementById('polygon-fields-' + pid).style.display = (tipo === 'polygon') ? 'block' : 'none';
        var hint = document.getElementById('map-hint-' + pid);
        if (hint) hint.textContent = (tipo === 'circle')
            ? 'Haz clic en el mapa para fijar el centro. Ajusta el radio con el slider.'
            : 'Haz clic en el mapa para marcar los vértices del polígono.';
        // Inputs requeridos según el tipo (evita "required" sobre campos ocultos).
        document.getElementById('geo-lat-' + pid).required = (tipo === 'circle');
        document.getElementById('geo-lng-' + pid).required = (tipo === 'circle');
        if (!st) return;
        st.tipo = tipo;
        if (tipo === 'circle') { if (st.polygon) st.polygon.removeFrom(st.map); if (st.circle) st.circle.addTo(st.map); }
        else { if (st.circle) st.circle.removeFrom(st.map); if (st.polygon) st.polygon.addTo(st.map); }
    }
    function updateRadioDisplay(pid) {
        var slider = document.getElementById('geo-radio-' + pid);
        document.getElementById('radio-value-' + pid).textContent = slider.value;
        var st = window['geoState_' + pid];
        if (st && st.circle && st.circle.setRadius) st.circle.setRadius(parseInt(slider.value));
    }
    function clearPolygonPoints(pid) {
        var st = window['geoState_' + pid];
        if (!st) return;
        st.polygonPoints = [];
        st.polygonMarkers.forEach(function (m) { m.removeFrom(st.map); });
        st.polygonMarkers = [];
        if (st.polygon) { st.polygon.removeFrom(st.map); st.polygon = null; }
        document.getElementById('polygon-count-' + pid).textContent = '0';
        document.getElementById('geo-coords-' + pid).value = '';
    }
</script>
@endonce

<script>
    // Estado + inicializador por perfil. El view principal llama mfGeoInit_<pid>()
    // al revelar la sub-pestaña Geocercas (lazy, idempotente; sin busy-loop).
    window['geoState_{{ $perfil->id }}'] = { tipo: 'circle', map: null, circle: null, polygon: null, polygonPoints: [], polygonMarkers: [] };
    window['mfGeoInit_{{ $perfil->id }}'] = function () {
        var pid = '{{ $perfil->id }}', st = window['geoState_' + pid];
        var el = document.getElementById('mf-geomap-' + pid);
        if (!el || typeof L === 'undefined') return;
        if (st.map) { st.map.invalidateSize(); return; }
        st.map = L.map(el).setView([19.4326, -99.1332], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(st.map);
        st.map.on('click', function (e) {
            if (st.tipo === 'circle') {
                document.getElementById('geo-lat-' + pid).value = e.latlng.lat.toFixed(7);
                document.getElementById('geo-lng-' + pid).value = e.latlng.lng.toFixed(7);
                if (st.circle) st.circle.removeFrom(st.map);
                var r = parseInt(document.getElementById('geo-radio-' + pid).value) || 500;
                st.circle = L.circle(e.latlng, { radius: r, color: '#0057a8', weight: 2, fillColor: '#0057a8', fillOpacity: 0.15 }).addTo(st.map);
            } else {
                window.mfAddVertex(st, e.latlng, pid); // vértice draggable + redibuja
            }
        });
        setTimeout(function () { st.map.invalidateSize(); }, 80);
    };
</script>
