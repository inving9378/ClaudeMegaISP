{{-- G4 Horarios de internet del perfil. Espera $perfil, $diasLargo, $diasCorto. --}}
@if($perfil->schedules->isEmpty())
    <p style="color:var(--text-muted); font-size:.85rem; margin-bottom:.6rem">Sin horarios configurados.</p>
@else
    <div class="table-responsive">
        <table class="portal-table">
            <thead><tr><th>Nombre</th><th>Días</th><th>Horario</th><th>Acción</th><th>Estado</th><th style="text-align:right">Acciones</th></tr></thead>
            <tbody>
                @foreach($perfil->schedules as $sch)
                    <tr>
                        <td><strong>{{ $sch->name }}</strong></td>
                        <td>{{ collect($sch->days ?? [])->map(fn ($d) => $diasCorto[$d] ?? $d)->implode(', ') ?: '—' }}</td>
                        <td>{{ substr((string) $sch->start_time, 0, 5) }} – {{ substr((string) $sch->end_time, 0, 5) }}</td>
                        <td>@if($sch->action === 'allow')<span class="badge badge-success">Permite</span>@else<span class="badge badge-danger">Bloquea</span>@endif</td>
                        <td>@if($sch->active)<span class="badge badge-success">Activo</span>@else<span class="badge badge-secondary">Inactivo</span>@endif</td>
                        <td style="text-align:right; white-space:nowrap">
                            <a href="{{ route('portal.megafamilia.horarios.edit', [$perfil->id, $sch->id]) }}" class="btn btn-outline btn-sm">✏️</a>
                            <form method="POST" action="{{ route('portal.megafamilia.horarios.destroy', [$perfil->id, $sch->id]) }}" style="display:inline"
                                  onsubmit="return confirm('¿Eliminar el horario «{{ $sch->name }}»?')">
                                @csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<details style="margin-top:.6rem">
    <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">➕ Agregar horario</summary>
    <form method="POST" action="{{ route('portal.megafamilia.horarios.store', $perfil->id) }}" style="margin-top:.75rem">
        @csrf
        <div class="form-group" style="margin-bottom:.7rem">
            <label>Nombre <span style="color:var(--danger)">*</span></label>
            <input type="text" name="nombre" class="form-control" maxlength="100" required placeholder="Ej. Horario escolar">
        </div>
        <div class="form-group" style="margin-bottom:.7rem">
            <label>Días <span style="color:var(--danger)">*</span></label>
            <div style="display:flex; gap:.75rem; flex-wrap:wrap">
                @foreach($diasLargo as $num => $lbl)
                    <label style="display:flex; align-items:center; gap:.3rem; font-weight:400; font-size:.85rem; margin:0">
                        <input type="checkbox" name="days[]" value="{{ $num }}"> {{ $diasCorto[$num] }}
                    </label>
                @endforeach
            </div>
        </div>
        <div class="kpi-grid" style="margin-bottom:.7rem">
            <div class="form-group" style="margin-bottom:0"><label>Hora inicio <span style="color:var(--danger)">*</span></label><input type="time" name="hora_inicio" class="form-control" required></div>
            <div class="form-group" style="margin-bottom:0"><label>Hora fin <span style="color:var(--danger)">*</span></label><input type="time" name="hora_fin" class="form-control" required></div>
            <div class="form-group" style="margin-bottom:0">
                <label>Acción</label>
                <select name="action" class="form-control"><option value="block">Bloquear internet</option><option value="allow">Permitir internet</option></select>
            </div>
        </div>
        <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; font-size:.85rem; margin-bottom:.75rem">
            <input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" checked> Horario activo
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Guardar horario</button>
    </form>
</details>
