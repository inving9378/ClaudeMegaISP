{{-- G6 Tareas y recompensas del perfil. Espera $perfil, $taskEstados, $bal (balance). --}}
<div class="kpi-grid" style="margin-bottom:0">
    {{-- Tareas --}}
    <div>
        <div style="font-size:.78rem; color:var(--text-muted); margin-bottom:.35rem">Tareas</div>
        @if($perfil->tasks->isEmpty())
            <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:.5rem">Sin tareas.</p>
        @else
            <ul style="list-style:none; padding:0; margin:0 0 .5rem">
                @foreach($perfil->tasks as $tarea)
                    @php [$tLbl, $tCls] = $taskEstados[$tarea->status] ?? [ucfirst($tarea->status), 'badge-secondary']; @endphp
                    <li style="padding:.4rem 0; border-bottom:1px solid var(--border)">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:.5rem">
                            <span>✅ <strong>{{ $tarea->title }}</strong>
                                @if($tarea->points)<span class="badge badge-info">{{ (int) $tarea->points }} pts</span>@endif
                                <span class="badge {{ $tCls }}">{{ $tLbl }}</span>
                            </span>
                            <span style="white-space:nowrap">
                                @if($tarea->status === 'pending')
                                    <form method="POST" action="{{ route('portal.megafamilia.tareas.completar', $tarea->id) }}" style="display:inline">
                                        @csrf<button type="submit" class="btn btn-success btn-sm" title="Marcar completada">✓</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('portal.megafamilia.tareas.destroy', [$perfil->id, $tarea->id]) }}" style="display:inline"
                                      onsubmit="return confirm('¿Eliminar la tarea «{{ $tarea->title }}»?')">
                                    @csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </span>
                        </div>
                        @if($tarea->description)<div style="color:var(--text-muted); font-size:.78rem; margin-top:.2rem">{{ $tarea->description }}</div>@endif
                    </li>
                @endforeach
            </ul>
        @endif
        <details>
            <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">➕ Agregar tarea</summary>
            <form method="POST" action="{{ route('portal.megafamilia.tareas.store', $perfil->id) }}" style="margin-top:.6rem">
                @csrf
                <div class="form-group" style="margin-bottom:.6rem"><label>Título <span style="color:var(--danger)">*</span></label><input type="text" name="titulo" class="form-control" maxlength="100" required placeholder="Ej. Tender la cama"></div>
                <div class="form-group" style="margin-bottom:.6rem"><label>Descripción (opcional)</label><input type="text" name="descripcion" class="form-control" maxlength="1000"></div>
                <div class="form-group" style="margin-bottom:.6rem"><label>Puntos</label><input type="number" name="puntos" class="form-control" min="0" max="500" placeholder="0 a 500"></div>
                <button type="submit" class="btn btn-primary btn-sm">Guardar tarea</button>
            </form>
        </details>
    </div>

    {{-- Recompensas (catálogo) --}}
    <div>
        <div style="font-size:.78rem; color:var(--text-muted); margin-bottom:.35rem">Recompensas (canjeables por puntos)</div>
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
    </div>
</div>
