{{-- G5 Geocercas del perfil con mapa Leaflet (círculo). Espera $perfil. --}}
@if($perfil->geofences->isEmpty())
    <p style="color:var(--text-muted); font-size:.85rem; margin-bottom:.6rem">Sin geocercas configuradas.</p>
@else
    <div class="table-responsive">
        <table class="portal-table">
            <thead><tr><th>Nombre</th><th>Coordenadas</th><th>Radio</th><th>Alertas</th><th>Estado</th><th style="text-align:right">Acciones</th></tr></thead>
            <tbody>
                @foreach($perfil->geofences as $geo)
                    <tr>
                        <td><strong>{{ $geo->name }}</strong>@if($geo->address)<div style="color:var(--text-muted); font-size:.78rem">{{ $geo->address }}</div>@endif</td>
                        <td style="font-size:.8rem">{{ $geo->lat }}, {{ $geo->lng }}</td>
                        <td><span class="badge badge-info">{{ (int) $geo->radius_meters }} m</span></td>
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

{{-- Alta de geocerca: form (izq) + mapa (der) en 2 columnas --}}
<div style="margin-top:1rem">
    <div style="font-weight:600; font-size:.82rem; color:var(--pcolor); margin-bottom:.75rem">➕ Agregar geocerca</div>
    <div class="mf-geo-grid" style="display:grid; grid-template-columns:2fr 3fr; gap:2rem; align-items:start">

        {{-- COLUMNA IZQUIERDA: Formulario --}}
        <div>
            <form method="POST" action="{{ route('portal.megafamilia.geocercas.store') }}">
                @csrf
                <input type="hidden" name="profile_id" value="{{ $perfil->id }}">

                <div class="form-group">
                    <label>Nombre <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="nombre" class="form-control" maxlength="100" required placeholder="Ej. Casa">
                </div>
                <div class="form-group">
                    <label>Latitud <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="geo-lat-{{ $perfil->id }}" name="latitud" class="form-control" required readonly placeholder="Clic en el mapa">
                </div>
                <div class="form-group">
                    <label>Longitud <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="geo-lng-{{ $perfil->id }}" name="longitud" class="form-control" required readonly placeholder="Clic en el mapa">
                </div>
                <div class="form-group">
                    <label>Radio: <span id="geo-radio-val-{{ $perfil->id }}">500</span> m <span style="color:var(--danger)">*</span></label>
                    <input type="range" id="geo-radio-{{ $perfil->id }}" name="radio_metros" class="form-control" min="50" max="10000" step="50" value="500" required style="height:6px; padding:0">
                </div>
                <div class="form-group">
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
            <div class="mf-geomap" id="mf-geomap-{{ $perfil->id }}" data-lat="19.4326" data-lng="-99.1332" data-radius="500"
                 style="height:500px; width:100%; border-radius:.5rem; box-shadow:0 2px 8px rgba(0,0,0,.1); border:1px solid var(--border)"></div>
            <p style="font-size:.75rem; color:var(--text-muted); margin-top:.5rem; text-align:center">
                Haz clic en el mapa para fijar el centro. Ajusta el radio con el slider.
            </p>
        </div>
    </div>
</div>

@once
<style>@media (max-width:768px){ .mf-geo-grid{ grid-template-columns:1fr !important } .mf-geo-grid [style*="sticky"]{ position:static !important } }</style>
@endonce
