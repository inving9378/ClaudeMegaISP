{{-- G6 "Mis tareas" (asignaciones del perfil) + recompensas. Espera $perfil, $taskEstados, $bal. --}}
<div style="font-weight:600; font-size:.85rem; margin-bottom:.5rem">🎯 Tareas asignadas a {{ $perfil->name }}</div>
@if($perfil->taskAssignments->isEmpty())
    <p style="color:var(--text-muted); font-size:.85rem; margin-bottom:.75rem">Sin tareas asignadas. Asígnale tareas desde «📋 Asignación de tareas».</p>
@else
    <div class="table-responsive">
        <table class="portal-table">
            <thead><tr><th>Tarea</th><th>Descripción</th><th>Pts</th><th>Estado</th><th style="text-align:right">Acciones</th></tr></thead>
            <tbody>
                @foreach($perfil->taskAssignments as $asg)
                    @php [$eLbl, $eCls] = $taskEstados[$asg->status] ?? [ucfirst($asg->status), 'badge-secondary']; @endphp
                    <tr>
                        <td><strong>{{ optional($asg->task)->title }}</strong></td>
                        <td style="color:var(--text-muted); font-size:.85rem">{{ optional($asg->task)->description ?: '—' }}</td>
                        <td><span class="badge badge-info">{{ (int) optional($asg->task)->points }}</span></td>
                        <td>
                            <span class="badge {{ $eCls }}">{{ $eLbl }}</span>
                            @if($asg->status === 'completed' && $asg->completed_at)
                                <div style="color:var(--text-muted); font-size:.74rem; margin-top:.2rem">el {{ $asg->completed_at->format('d/m/Y H:i') }}</div>
                            @endif
                        </td>
                        <td style="text-align:right; white-space:nowrap">
                            @if($asg->status === 'pending')
                                <form method="POST" action="{{ route('portal.megafamilia.asignaciones.completar', $asg->id) }}" style="display:inline">
                                    @csrf<button type="submit" class="btn btn-success btn-sm">✓ Completar</button>
                                </form>
                            @elseif($asg->status === 'completed')
                                <span class="badge badge-success">✓ Completada</span>
                            @else
                                <span class="badge badge-danger">Rechazada</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Recompensas (catálogo) --}}
<div style="font-weight:600; font-size:.85rem; margin:1.25rem 0 .5rem">🎁 Recompensas (canjeables por puntos)</div>
@php
    $catalogo  = $perfil->rewards->whereNull('granted_at');
    $canjeadas = $perfil->rewards->whereNotNull('granted_at')->count();
@endphp
@if($catalogo->isEmpty())
    <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:.5rem">Sin recompensas.</p>
@else
    <ul style="list-style:none; padding:0; margin:0 0 .5rem">
        @foreach($catalogo as $rec)
            <li style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.4rem 0; border-bottom:1px solid var(--border)">
                <span>🎁 <strong>{{ $rec->detail }}</strong> <span class="badge badge-secondary">Cuesta {{ (int) $rec->value }} pts</span></span>
                <span style="white-space:nowrap; display:flex; gap:.3rem">
                    <form method="POST" action="{{ route('portal.megafamilia.recompensas.canjear', $rec->id) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm"
                            @if($bal < (int) $rec->value) disabled title="Necesitas más puntos ({{ $bal }}/{{ (int) $rec->value }})" @else title="Canjear por {{ (int) $rec->value }} pts" @endif>🔁 Canjear</button>
                    </form>
                    <form method="POST" action="{{ route('portal.megafamilia.recompensas.destroy', [$perfil->id, $rec->id]) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar la recompensa «{{ $rec->detail }}»?')">
                        @csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                    </form>
                </span>
            </li>
        @endforeach
    </ul>
@endif
@if($canjeadas > 0)<p style="color:var(--text-muted); font-size:.76rem; margin-bottom:.5rem">🎉 {{ $canjeadas }} recompensa(s) ya canjeada(s).</p>@endif
<details>
    <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">➕ Agregar recompensa</summary>
    <form method="POST" action="{{ route('portal.megafamilia.recompensas.store', $perfil->id) }}" style="margin-top:.6rem">
        @csrf
        <div class="form-group" style="margin-bottom:.6rem"><label>Título <span style="color:var(--danger)">*</span></label><input type="text" name="titulo" class="form-control" maxlength="100" required placeholder="Ej. 1 hora extra de juego"></div>
        <div class="form-group" style="margin-bottom:.6rem"><label>Costo en puntos</label><input type="number" name="costo_puntos" class="form-control" min="0" max="500" placeholder="0 a 500"></div>
        <button type="submit" class="btn btn-primary btn-sm">Guardar recompensa</button>
    </form>
</details>
