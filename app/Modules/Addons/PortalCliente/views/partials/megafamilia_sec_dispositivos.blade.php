{{-- G2 Dispositivos del perfil. Espera $perfil, $devTipoLabels. --}}
@if($perfil->devices->isEmpty())
    <p style="color:var(--text-muted); font-size:.85rem; margin-bottom:.6rem">Sin dispositivos registrados.</p>
@else
    <div class="table-responsive">
        <table class="portal-table">
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Estado</th><th style="text-align:right">Acciones</th></tr></thead>
            <tbody>
                @foreach($perfil->devices as $dev)
                    <tr>
                        <td><strong>{{ $dev->name }}</strong></td>
                        <td>{{ $devTipoLabels[$dev->model] ?? ($dev->model ?: '—') }}</td>
                        <td>@if($dev->status === 'online')<span class="badge badge-success">En línea</span>@else<span class="badge badge-secondary">Desconectado</span>@endif</td>
                        <td style="text-align:right; white-space:nowrap">
                            <a href="{{ route('portal.megafamilia.dispositivos.edit', $dev->id) }}" class="btn btn-outline btn-sm">✏️</a>
                            <form method="POST" action="{{ route('portal.megafamilia.dispositivos.destroy', $dev->id) }}" style="display:inline"
                                  onsubmit="return confirm('¿Eliminar el dispositivo «{{ $dev->name }}»?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<details style="margin-top:.6rem">
    <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">➕ Agregar dispositivo</summary>
    <form method="POST" action="{{ route('portal.megafamilia.dispositivos.store') }}" style="margin-top:.75rem">
        @csrf
        <input type="hidden" name="profile_id" value="{{ $perfil->id }}">
        <div class="kpi-grid" style="margin-bottom:.75rem">
            <div class="form-group" style="margin-bottom:0">
                <label>Nombre <span style="color:var(--danger)">*</span></label>
                <input type="text" name="nombre" class="form-control" maxlength="100" required placeholder="Ej. iPhone de Juan">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Tipo</label>
                <select name="tipo" class="form-control">
                    <option value="">— Sin especificar —</option>
                    @foreach($devTipoLabels as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Guardar dispositivo</button>
    </form>
</details>
