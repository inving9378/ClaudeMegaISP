{{-- Tab "Asignación de tareas" (account-level). Hereda $accountTasks, $cuentas. --}}
@php
    $cuentaAsig   = $cuentas->first();
    $perfilesAsig = $cuentaAsig ? $cuentaAsig->profiles : collect();
    $tipoAsigLbl  = ['cada_uno' => 'Cada uno', 'solo_uno' => 'Solo uno'];
@endphp

<div class="card">
    <div class="card-title">📋 Tareas de la familia</div>

    @if($accountTasks->isEmpty())
        <p style="color:var(--text-muted); font-size:.9rem; margin-bottom:1rem">Aún no has creado tareas. Crea la primera abajo.</p>
    @else
        <div class="table-responsive">
            <table class="portal-table">
                <thead><tr><th>Tarea</th><th>Descripción</th><th>Pts</th><th>Asignada a</th><th>Tipo</th><th style="text-align:right">Acciones</th></tr></thead>
                <tbody>
                    @foreach($accountTasks as $task)
                        <tr>
                            <td><strong>{{ $task->title }}</strong></td>
                            <td style="color:var(--text-muted); font-size:.85rem">{{ $task->description ?: '—' }}</td>
                            <td><span class="badge badge-info">{{ (int) $task->points }}</span></td>
                            <td style="font-size:.85rem">{{ $task->assignments->map(fn ($a) => optional($a->profile)->name)->filter()->implode(', ') ?: '—' }}</td>
                            <td><span class="badge badge-secondary">{{ $tipoAsigLbl[$task->assignment_type] ?? $task->assignment_type }}</span></td>
                            <td style="text-align:right; white-space:nowrap">
                                <form method="POST" action="{{ route('portal.megafamilia.tareas.destroy', $task->id) }}" style="display:inline"
                                      onsubmit="return confirm('¿Eliminar la tarea «{{ $task->title }}» y todas sus asignaciones?')">
                                    @csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-sm">🗑️ Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($perfilesAsig->isEmpty())
        <p style="color:var(--text-muted); font-size:.85rem; margin-top:1rem">Crea un perfil de hijo antes de asignar tareas.</p>
    @else
        <details style="margin-top:1rem">
            <summary style="cursor:pointer; font-weight:600; font-size:.9rem; color:var(--pcolor)">➕ Crear tarea</summary>
            <form method="POST" action="{{ route('portal.megafamilia.tareas.store') }}" style="margin-top:1rem">
                @csrf
                <input type="hidden" name="account_id" value="{{ $cuentaAsig->id }}">
                <div class="kpi-grid" style="margin-bottom:.75rem">
                    <div class="form-group" style="margin-bottom:0">
                        <label>Título <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="titulo" class="form-control" maxlength="100" required placeholder="Ej. Tender la cama">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label>Descripción (opcional)</label>
                        <input type="text" name="descripcion" class="form-control" maxlength="1000">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label>Puntos</label>
                        <input type="number" name="puntos" class="form-control" min="0" max="500" placeholder="0 a 500">
                    </div>
                </div>

                <div class="form-group">
                    <label>Tipo de asignación</label>
                    <div style="display:flex; gap:1.25rem">
                        <label style="display:flex; align-items:center; gap:.4rem; font-weight:400; margin:0"><input type="radio" name="assignment_type" value="cada_uno" checked> Cada uno</label>
                        <label style="display:flex; align-items:center; gap:.4rem; font-weight:400; margin:0"><input type="radio" name="assignment_type" value="solo_uno"> Solo uno</label>
                    </div>
                    <span style="font-size:.78rem; color:var(--text-muted)">«Solo uno»: marca un único perfil.</span>
                </div>

                <div class="form-group">
                    <label>Asignar a <span style="color:var(--danger)">*</span></label>
                    <div style="display:flex; gap:1rem; flex-wrap:wrap">
                        @foreach($perfilesAsig as $perfilAsig)
                            <label style="display:flex; align-items:center; gap:.4rem; font-weight:400; font-size:.9rem; margin:0">
                                <input type="checkbox" name="profiles[]" value="{{ $perfilAsig->id }}"> {{ $perfilAsig->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Crear tarea</button>
            </form>
        </details>
    @endif
</div>
