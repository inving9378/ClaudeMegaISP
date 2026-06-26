{{-- G3 Bloqueos (apps + webs) del perfil. Espera $perfil. --}}
<div class="kpi-grid" style="margin-bottom:0">
    {{-- Apps --}}
    <div>
        <div style="font-size:.78rem; color:var(--text-muted); margin-bottom:.35rem">Aplicaciones</div>
        @if($perfil->appBlocks->isEmpty())
            <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:.5rem">Sin apps bloqueadas.</p>
        @else
            <ul style="list-style:none; padding:0; margin:0 0 .5rem">
                @foreach($perfil->appBlocks as $ab)
                    <li style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.35rem 0; border-bottom:1px solid var(--border)">
                        <span>📵 <strong>{{ $ab->app_name }}</strong>@if($ab->package_name)<span style="color:var(--text-muted); font-size:.78rem">({{ $ab->package_name }})</span>@endif</span>
                        <form method="POST" action="{{ route('portal.megafamilia.appblocks.destroy', [$perfil->id, $ab->id]) }}" style="display:inline"
                              onsubmit="return confirm('¿Quitar el bloqueo de «{{ $ab->app_name }}»?')">
                            @csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        <details>
            <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">➕ Bloquear app</summary>
            <form method="POST" action="{{ route('portal.megafamilia.appblocks.store', $perfil->id) }}" style="margin-top:.6rem">
                @csrf
                <div class="form-group" style="margin-bottom:.6rem">
                    <label>Nombre de la app <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="app_name" class="form-control" maxlength="100" required placeholder="Ej. Instagram">
                </div>
                <div class="form-group" style="margin-bottom:.6rem">
                    <label>Paquete (opcional)</label>
                    <input type="text" name="package_name" class="form-control" maxlength="200" placeholder="com.instagram.android">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Bloquear app</button>
            </form>
        </details>
    </div>

    {{-- Webs --}}
    <div>
        <div style="font-size:.78rem; color:var(--text-muted); margin-bottom:.35rem">Sitios web</div>
        @if($perfil->webBlocks->isEmpty())
            <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:.5rem">Sin sitios bloqueados.</p>
        @else
            <ul style="list-style:none; padding:0; margin:0 0 .5rem">
                @foreach($perfil->webBlocks as $wb)
                    <li style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.35rem 0; border-bottom:1px solid var(--border)">
                        <span>🌐 <strong>{{ $wb->domain }}</strong>@if($wb->category)<span style="color:var(--text-muted); font-size:.78rem">· {{ $wb->category }}</span>@endif</span>
                        <form method="POST" action="{{ route('portal.megafamilia.webblocks.destroy', [$perfil->id, $wb->id]) }}" style="display:inline"
                              onsubmit="return confirm('¿Quitar el bloqueo de «{{ $wb->domain }}»?')">
                            @csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        <details>
            <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">➕ Bloquear sitio</summary>
            <form method="POST" action="{{ route('portal.megafamilia.webblocks.store', $perfil->id) }}" style="margin-top:.6rem">
                @csrf
                <div class="form-group" style="margin-bottom:.6rem">
                    <label>Dirección web <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="url" class="form-control" maxlength="255" required placeholder="Ej. instagram.com">
                </div>
                <div class="form-group" style="margin-bottom:.6rem">
                    <label>Categoría (opcional)</label>
                    <input type="text" name="category" class="form-control" maxlength="100" placeholder="Redes sociales">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Bloquear sitio</button>
            </form>
        </details>
    </div>
</div>
