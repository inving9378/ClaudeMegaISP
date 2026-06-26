@extends('addon-portal-cliente::layouts.portal')
@section('title', 'MegaFamilia')

@section('content')
<div class="page-header">
    <div>
        <h1>👨‍👩‍👧 MegaFamilia</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cliente #{{ $cmi->client_id }} — Control parental de tu familia
        </p>
    </div>
    @if($active ?? false)
        <a href="{{ route('portal.megafamilia.solicitudes') }}" class="btn btn-outline btn-sm">
            🔔 Solicitudes
            @if(($solicitudesPendientes ?? 0) > 0)
                <span class="badge badge-danger">{{ $solicitudesPendientes }}</span>
            @endif
        </a>
    @endif
</div>

@if(! $active)
    {{-- Estado vacío: el cliente no tiene MegaFamilia activa --}}
    <div class="card" style="text-align:center; padding:2.5rem 1.5rem">
        <div style="font-size:3rem; margin-bottom:.5rem">🛡️</div>
        <h2 style="margin-bottom:.5rem">Aún no tienes MegaFamilia activo</h2>
        <p style="color:var(--text-muted); max-width:520px; margin:0 auto 1.25rem">
            Protege a tu familia: perfiles por hijo, control de dispositivos, reglas de
            tiempo y geocercas. Activa MegaFamilia desde tus servicios.
        </p>
        <a href="{{ route('portal.marketplace') }}" class="btn btn-primary">Activa MegaFamilia</a>
    </div>
@else
    @php
        $estados = [
            'active'    => ['Activa', 'badge-success'],
            'suspended' => ['Suspendida', 'badge-warning'],
            'cancelled' => ['Cancelada', 'badge-secondary'],
        ];
        $tipoLabels = [
            'nino'           => 'Niño',
            'preadolescente' => 'Preadolescente',
            'adolescente'    => 'Adolescente',
        ];
        $nivelLabels = [
            'primaria'     => 'Primaria',
            'secundaria'   => 'Secundaria',
            'preparatoria' => 'Preparatoria',
        ];
        $devTipoLabels = [
            'smartphone' => '📱 Smartphone',
            'tablet'     => '📲 Tablet',
            'pc'         => '💻 PC',
            'otro'       => '🔌 Otro',
        ];
        $diasLargo = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
        $diasCorto = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];
    @endphp

    {{-- KPI Cards --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">🏠</div>
            <div class="kpi-label">Cuentas</div>
            <div class="kpi-value">{{ (int) $standing['cuentas'] }}</div>
            <div class="kpi-sub">De tu familia</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🧒</div>
            <div class="kpi-label">Perfiles</div>
            <div class="kpi-value">{{ (int) $standing['perfiles'] }}</div>
            <div class="kpi-sub">Hijos registrados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📱</div>
            <div class="kpi-label">Dispositivos</div>
            <div class="kpi-value">{{ (int) $standing['dispositivos'] }}</div>
            <div class="kpi-sub">Vinculados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">⏱️</div>
            <div class="kpi-label">Reglas</div>
            <div class="kpi-value">{{ (int) $standing['reglas'] }}</div>
            <div class="kpi-sub">Configuradas</div>
        </div>
    </div>

    {{-- Resumen de cuentas --}}
    <div class="card">
        <div class="card-title">Mis cuentas ({{ (int) $standing['cuentas'] }})</div>
        <div class="kpi-grid" style="margin-top:.5rem">
            @foreach($cuentas as $cuenta)
                @php [$estLabel, $estCls] = $estados[$cuenta->status] ?? [ucfirst($cuenta->status), 'badge-secondary']; @endphp
                <div class="kpi-card">
                    <div class="kpi-label">Cuenta #{{ $cuenta->id }}</div>
                    <div class="kpi-value" style="font-size:1rem; margin:.25rem 0">
                        <span class="badge {{ $estCls }}">{{ $estLabel }}</span>
                    </div>
                    <div class="kpi-sub">
                        🧒 {{ (int) $cuenta->profiles_count }} perfiles ·
                        📱 {{ (int) $cuenta->devices_count }} dispositivos
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Perfiles de hijos (por cuenta) — G1 + G2 ──────────────────────── --}}
    @foreach($cuentas as $cuenta)
        <div class="card">
            <div class="card-title">🧒 Perfiles de hijos — Cuenta #{{ $cuenta->id }}</div>

            @if($cuenta->profiles->isEmpty())
                <p style="color:var(--text-muted); font-size:.875rem; margin-bottom:1rem">
                    Aún no has registrado perfiles en esta cuenta. Agrega el primero abajo.
                </p>
            @endif

            @foreach($cuenta->profiles as $perfil)
                <div style="border:1px solid var(--border); border-radius:10px; padding:1.1rem; margin-bottom:1rem">
                    {{-- Cabecera del perfil (G1) --}}
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap">
                        <div>
                            <strong style="font-size:1.05rem">{{ $perfil->name }}</strong>
                            <div style="margin-top:.4rem; display:flex; gap:.4rem; flex-wrap:wrap; align-items:center">
                                @php
                                    $bal    = (int) ($balances[$perfil->id] ?? 0);
                                    $balCls = $bal >= 100 ? 'badge-success' : ($bal >= 50 ? 'badge-warning' : 'badge-danger');
                                @endphp
                                <span class="badge {{ $balCls }}" style="font-size:.82rem">💰 Balance: {{ $bal }} pts</span>
                                @if($perfil->age)
                                    <span class="badge badge-info">{{ $perfil->age }} años</span>
                                @endif
                                @if($perfil->profile_type)
                                    <span class="badge badge-secondary">{{ $tipoLabels[$perfil->profile_type] ?? $perfil->profile_type }}</span>
                                @endif
                                @if($perfil->school_level)
                                    <span class="badge badge-secondary">{{ $nivelLabels[$perfil->school_level] ?? $perfil->school_level }}</span>
                                @endif
                                @if($perfil->active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </div>
                        </div>
                        <div style="white-space:nowrap">
                            <a href="{{ route('portal.megafamilia.perfiles.edit', $perfil->id) }}"
                               class="btn btn-outline btn-sm">✏️ Editar</a>
                            <form method="POST"
                                  action="{{ route('portal.megafamilia.perfiles.destroy', $perfil->id) }}"
                                  style="display:inline"
                                  onsubmit="return confirm('¿Eliminar el perfil «{{ $perfil->name }}»? Se eliminarán también sus dispositivos, reglas y horarios.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">🗑️ Eliminar</button>
                            </form>
                        </div>
                    </div>

                    {{-- G2: Dispositivos del perfil --}}
                    <div style="margin-top:1rem">
                        <div style="font-weight:600; font-size:.85rem; margin-bottom:.5rem">📱 Dispositivos</div>

                        @if($perfil->devices->isEmpty())
                            <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:.6rem">
                                Sin dispositivos registrados.
                            </p>
                        @else
                            <div class="table-responsive">
                                <table class="portal-table">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th style="text-align:right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($perfil->devices as $dev)
                                            <tr>
                                                <td><strong>{{ $dev->name }}</strong></td>
                                                <td>{{ $devTipoLabels[$dev->model] ?? ($dev->model ?: '—') }}</td>
                                                <td>
                                                    @if($dev->status === 'online')
                                                        <span class="badge badge-success">En línea</span>
                                                    @else
                                                        <span class="badge badge-secondary">Desconectado</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:right; white-space:nowrap">
                                                    <a href="{{ route('portal.megafamilia.dispositivos.edit', $dev->id) }}"
                                                       class="btn btn-outline btn-sm">✏️</a>
                                                    <form method="POST"
                                                          action="{{ route('portal.megafamilia.dispositivos.destroy', $dev->id) }}"
                                                          style="display:inline"
                                                          onsubmit="return confirm('¿Eliminar el dispositivo «{{ $dev->name }}»?')">
                                                        @csrf
                                                        @method('DELETE')
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
                            <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">
                                ➕ Agregar dispositivo
                            </summary>
                            <form method="POST" action="{{ route('portal.megafamilia.dispositivos.store') }}" style="margin-top:.75rem">
                                @csrf
                                <input type="hidden" name="profile_id" value="{{ $perfil->id }}">
                                <div class="kpi-grid" style="margin-bottom:.75rem">
                                    <div class="form-group" style="margin-bottom:0">
                                        <label>Nombre <span style="color:var(--danger)">*</span></label>
                                        <input type="text" name="nombre" class="form-control" maxlength="100" required
                                               placeholder="Ej. iPhone de Juan">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0">
                                        <label>Tipo</label>
                                        <select name="tipo" class="form-control">
                                            <option value="">— Sin especificar —</option>
                                            @foreach($devTipoLabels as $val => $lbl)
                                                <option value="{{ $val }}">{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Guardar dispositivo</button>
                            </form>
                        </details>
                    </div>

                    {{-- G3: Bloqueos (apps + webs) del perfil --}}
                    <div style="margin-top:1.1rem">
                        <div style="font-weight:600; font-size:.85rem; margin-bottom:.5rem">🚫 Bloqueos</div>
                        <div class="kpi-grid" style="margin-bottom:0">
                            {{-- Apps bloqueadas --}}
                            <div>
                                <div style="font-size:.78rem; color:var(--text-muted); margin-bottom:.35rem">Aplicaciones</div>
                                @if($perfil->appBlocks->isEmpty())
                                    <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:.5rem">Sin apps bloqueadas.</p>
                                @else
                                    <ul style="list-style:none; padding:0; margin:0 0 .5rem">
                                        @foreach($perfil->appBlocks as $ab)
                                            <li style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.35rem 0; border-bottom:1px solid var(--border)">
                                                <span>📵 <strong>{{ $ab->app_name }}</strong>
                                                    @if($ab->package_name)<span style="color:var(--text-muted); font-size:.78rem">({{ $ab->package_name }})</span>@endif
                                                </span>
                                                <form method="POST" action="{{ route('portal.megafamilia.appblocks.destroy', [$perfil->id, $ab->id]) }}"
                                                      style="display:inline" onsubmit="return confirm('¿Quitar el bloqueo de «{{ $ab->app_name }}»?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
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

                            {{-- Sitios web bloqueados --}}
                            <div>
                                <div style="font-size:.78rem; color:var(--text-muted); margin-bottom:.35rem">Sitios web</div>
                                @if($perfil->webBlocks->isEmpty())
                                    <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:.5rem">Sin sitios bloqueados.</p>
                                @else
                                    <ul style="list-style:none; padding:0; margin:0 0 .5rem">
                                        @foreach($perfil->webBlocks as $wb)
                                            <li style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.35rem 0; border-bottom:1px solid var(--border)">
                                                <span>🌐 <strong>{{ $wb->domain }}</strong>
                                                    @if($wb->category)<span style="color:var(--text-muted); font-size:.78rem">· {{ $wb->category }}</span>@endif
                                                </span>
                                                <form method="POST" action="{{ route('portal.megafamilia.webblocks.destroy', [$perfil->id, $wb->id]) }}"
                                                      style="display:inline" onsubmit="return confirm('¿Quitar el bloqueo de «{{ $wb->domain }}»?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
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
                    </div>

                    {{-- G4: Horarios de internet del perfil --}}
                    <div style="margin-top:1.1rem">
                        <div style="font-weight:600; font-size:.85rem; margin-bottom:.5rem">⏰ Horarios de internet</div>

                        @if($perfil->schedules->isEmpty())
                            <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:.6rem">Sin horarios configurados.</p>
                        @else
                            <div class="table-responsive">
                                <table class="portal-table">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Días</th>
                                            <th>Horario</th>
                                            <th>Acción</th>
                                            <th>Estado</th>
                                            <th style="text-align:right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($perfil->schedules as $sch)
                                            <tr>
                                                <td><strong>{{ $sch->name }}</strong></td>
                                                <td>{{ collect($sch->days ?? [])->map(fn ($d) => $diasCorto[$d] ?? $d)->implode(', ') ?: '—' }}</td>
                                                <td>{{ substr((string) $sch->start_time, 0, 5) }} – {{ substr((string) $sch->end_time, 0, 5) }}</td>
                                                <td>
                                                    @if($sch->action === 'allow')
                                                        <span class="badge badge-success">Permite</span>
                                                    @else
                                                        <span class="badge badge-danger">Bloquea</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($sch->active)
                                                        <span class="badge badge-success">Activo</span>
                                                    @else
                                                        <span class="badge badge-secondary">Inactivo</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:right; white-space:nowrap">
                                                    <a href="{{ route('portal.megafamilia.horarios.edit', [$perfil->id, $sch->id]) }}"
                                                       class="btn btn-outline btn-sm">✏️</a>
                                                    <form method="POST" action="{{ route('portal.megafamilia.horarios.destroy', [$perfil->id, $sch->id]) }}"
                                                          style="display:inline" onsubmit="return confirm('¿Eliminar el horario «{{ $sch->name }}»?')">
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
                                    <div class="form-group" style="margin-bottom:0">
                                        <label>Hora inicio <span style="color:var(--danger)">*</span></label>
                                        <input type="time" name="hora_inicio" class="form-control" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0">
                                        <label>Hora fin <span style="color:var(--danger)">*</span></label>
                                        <input type="time" name="hora_fin" class="form-control" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0">
                                        <label>Acción</label>
                                        <select name="action" class="form-control">
                                            <option value="block">Bloquear internet</option>
                                            <option value="allow">Permitir internet</option>
                                        </select>
                                    </div>
                                </div>
                                <label style="display:flex; align-items:center; gap:.5rem; font-weight:400; font-size:.85rem; margin-bottom:.75rem">
                                    <input type="hidden" name="active" value="0">
                                    <input type="checkbox" name="active" value="1" checked> Horario activo
                                </label>
                                <button type="submit" class="btn btn-primary btn-sm">Guardar horario</button>
                            </form>
                        </details>
                    </div>

                    {{-- G6: Tareas y recompensas del perfil --}}
                    @php
                        $taskEstados = [
                            'pending'   => ['Pendiente', 'badge-warning'],
                            'completed' => ['Completada', 'badge-info'],
                            'approved'  => ['Aprobada', 'badge-success'],
                            'rejected'  => ['Rechazada', 'badge-danger'],
                        ];
                    @endphp
                    <div style="margin-top:1.1rem">
                        <div style="font-weight:600; font-size:.85rem; margin-bottom:.5rem">🎯 Tareas y recompensas</div>
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
                                                                @csrf
                                                                <button type="submit" class="btn btn-success btn-sm" title="Marcar completada">✓</button>
                                                            </form>
                                                        @endif
                                                        <form method="POST" action="{{ route('portal.megafamilia.tareas.destroy', [$perfil->id, $tarea->id]) }}"
                                                              style="display:inline" onsubmit="return confirm('¿Eliminar la tarea «{{ $tarea->title }}»?')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                                        </form>
                                                    </span>
                                                </div>
                                                @if($tarea->description)
                                                    <div style="color:var(--text-muted); font-size:.78rem; margin-top:.2rem">{{ $tarea->description }}</div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <details>
                                    <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">➕ Agregar tarea</summary>
                                    <form method="POST" action="{{ route('portal.megafamilia.tareas.store', $perfil->id) }}" style="margin-top:.6rem">
                                        @csrf
                                        <div class="form-group" style="margin-bottom:.6rem">
                                            <label>Título <span style="color:var(--danger)">*</span></label>
                                            <input type="text" name="titulo" class="form-control" maxlength="100" required placeholder="Ej. Tender la cama">
                                        </div>
                                        <div class="form-group" style="margin-bottom:.6rem">
                                            <label>Descripción (opcional)</label>
                                            <input type="text" name="descripcion" class="form-control" maxlength="1000">
                                        </div>
                                        <div class="form-group" style="margin-bottom:.6rem">
                                            <label>Puntos</label>
                                            <input type="number" name="puntos" class="form-control" min="0" max="500" placeholder="0 a 500">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">Guardar tarea</button>
                                    </form>
                                </details>
                            </div>

                            {{-- Recompensas --}}
                            <div>
                                <div style="font-size:.78rem; color:var(--text-muted); margin-bottom:.35rem">Recompensas</div>
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
                                                <span>🎁 <strong>{{ $rec->detail }}</strong>
                                                    <span class="badge badge-secondary">Cuesta {{ (int) $rec->value }} pts</span>
                                                </span>
                                                <span style="white-space:nowrap; display:flex; gap:.3rem">
                                                    <form method="POST" action="{{ route('portal.megafamilia.recompensas.canjear', $rec->id) }}" style="display:inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm"
                                                                @if($bal < (int) $rec->value) disabled title="Balance insuficiente ({{ $bal }} pts)" @else title="Canjear por {{ (int) $rec->value }} pts" @endif>
                                                            🔁 Canjear
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('portal.megafamilia.recompensas.destroy', [$perfil->id, $rec->id]) }}"
                                                          style="display:inline" onsubmit="return confirm('¿Eliminar la recompensa «{{ $rec->detail }}»?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                                    </form>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if($canjeadas > 0)
                                    <p style="color:var(--text-muted); font-size:.76rem; margin-bottom:.5rem">🎉 {{ $canjeadas }} recompensa(s) ya canjeada(s).</p>
                                @endif
                                <details>
                                    <summary style="cursor:pointer; font-weight:600; font-size:.82rem; color:var(--pcolor)">➕ Agregar recompensa</summary>
                                    <form method="POST" action="{{ route('portal.megafamilia.recompensas.store', $perfil->id) }}" style="margin-top:.6rem">
                                        @csrf
                                        <div class="form-group" style="margin-bottom:.6rem">
                                            <label>Título <span style="color:var(--danger)">*</span></label>
                                            <input type="text" name="titulo" class="form-control" maxlength="100" required placeholder="Ej. 1 hora extra de juego">
                                        </div>
                                        <div class="form-group" style="margin-bottom:.6rem">
                                            <label>Costo en puntos</label>
                                            <input type="number" name="costo_puntos" class="form-control" min="0" max="500" placeholder="0 a 500">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">Guardar recompensa</button>
                                    </form>
                                </details>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Form de alta de perfil (G1) --}}
            <details style="margin-top:.5rem">
                <summary style="cursor:pointer; font-weight:600; font-size:.9rem; color:var(--pcolor)">
                    ➕ Agregar perfil
                </summary>
                <form method="POST" action="{{ route('portal.megafamilia.perfiles.store') }}" style="margin-top:1rem">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $cuenta->id }}">
                    <div class="kpi-grid" style="margin-bottom:1rem">
                        <div class="form-group" style="margin-bottom:0">
                            <label>Nombre <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="nombre" class="form-control" maxlength="100" required
                                   placeholder="Ej. Juan">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label>Edad</label>
                            <input type="number" name="edad" class="form-control" min="1" max="17"
                                   placeholder="1 a 17">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label>Tipo de perfil</label>
                            <select name="profile_type" class="form-control">
                                <option value="">— Sin especificar —</option>
                                @foreach($tipoLabels as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label>Nivel escolar</label>
                            <select name="school_level" class="form-control">
                                <option value="">— Sin especificar —</option>
                                @foreach($nivelLabels as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Guardar perfil</button>
                </form>
            </details>
        </div>
    @endforeach

    {{-- ── Geocercas familiares (por cuenta) — G5 ─────────────────────────── --}}
    @foreach($cuentas as $cuenta)
        @php $geos = $cuenta->profiles->flatMap(fn ($p) => $p->geofences); @endphp
        <div class="card">
            <div class="card-title">📍 Geocercas familiares — Cuenta #{{ $cuenta->id }}</div>
            <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:1rem">
                El mapa interactivo es una mejora futura; por ahora ingresa latitud, longitud y radio manualmente.
            </p>

            @if($geos->isEmpty())
                <p style="color:var(--text-muted); font-size:.875rem; margin-bottom:1rem">Sin geocercas configuradas.</p>
            @else
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Perfil</th>
                                <th>Coordenadas</th>
                                <th>Radio</th>
                                <th>Alertas</th>
                                <th>Estado</th>
                                <th style="text-align:right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cuenta->profiles as $perfil)
                                @foreach($perfil->geofences as $geo)
                                    <tr>
                                        <td><strong>{{ $geo->name }}</strong>
                                            @if($geo->address)<div style="color:var(--text-muted); font-size:.78rem">{{ $geo->address }}</div>@endif
                                        </td>
                                        <td>{{ $perfil->name }}</td>
                                        <td style="font-size:.8rem">{{ $geo->lat }}, {{ $geo->lng }}</td>
                                        <td><span class="badge badge-info">{{ (int) $geo->radius_meters }} m</span></td>
                                        <td style="font-size:.78rem">
                                            @if($geo->alert_on_enter)<span title="Avisar al entrar">➡️ Entra</span>@endif
                                            @if($geo->alert_on_exit)<span title="Avisar al salir">⬅️ Sale</span>@endif
                                            @if(! $geo->alert_on_enter && ! $geo->alert_on_exit)—@endif
                                        </td>
                                        <td>
                                            @if($geo->active)
                                                <span class="badge badge-success">Activa</span>
                                            @else
                                                <span class="badge badge-secondary">Inactiva</span>
                                            @endif
                                        </td>
                                        <td style="text-align:right; white-space:nowrap">
                                            <a href="{{ route('portal.megafamilia.geocercas.edit', $geo->id) }}" class="btn btn-outline btn-sm">✏️</a>
                                            <form method="POST" action="{{ route('portal.megafamilia.geocercas.destroy', $geo->id) }}"
                                                  style="display:inline" onsubmit="return confirm('¿Eliminar la geocerca «{{ $geo->name }}»?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($cuenta->profiles->isEmpty())
                <p style="color:var(--text-muted); font-size:.82rem; margin-top:.5rem">
                    Crea un perfil antes de agregar geocercas.
                </p>
            @else
                <details style="margin-top:.75rem">
                    <summary style="cursor:pointer; font-weight:600; font-size:.9rem; color:var(--pcolor)">➕ Agregar geocerca</summary>
                    <form method="POST" action="{{ route('portal.megafamilia.geocercas.store') }}" style="margin-top:1rem">
                        @csrf
                        <div class="kpi-grid" style="margin-bottom:.75rem">
                            <div class="form-group" style="margin-bottom:0">
                                <label>Perfil <span style="color:var(--danger)">*</span></label>
                                <select name="profile_id" class="form-control" required>
                                    @foreach($cuenta->profiles as $perfil)
                                        <option value="{{ $perfil->id }}">{{ $perfil->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label>Nombre <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="nombre" class="form-control" maxlength="100" required placeholder="Ej. Casa">
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label>Latitud <span style="color:var(--danger)">*</span></label>
                                <input type="number" step="any" name="latitud" class="form-control" min="-90" max="90" required placeholder="19.4326">
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label>Longitud <span style="color:var(--danger)">*</span></label>
                                <input type="number" step="any" name="longitud" class="form-control" min="-180" max="180" required placeholder="-99.1332">
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label>Radio (metros) <span style="color:var(--danger)">*</span></label>
                                <input type="number" name="radio_metros" class="form-control" min="50" max="10000" required placeholder="50 a 10000">
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label>Dirección (opcional)</label>
                                <input type="text" name="direccion" class="form-control" maxlength="255" placeholder="Calle, colonia…">
                            </div>
                        </div>
                        <div style="display:flex; gap:1.25rem; flex-wrap:wrap; margin-bottom:.75rem">
                            <label style="display:flex; align-items:center; gap:.4rem; font-weight:400; font-size:.85rem; margin:0">
                                <input type="hidden" name="alert_on_enter" value="0">
                                <input type="checkbox" name="alert_on_enter" value="1" checked> Avisar al entrar
                            </label>
                            <label style="display:flex; align-items:center; gap:.4rem; font-weight:400; font-size:.85rem; margin:0">
                                <input type="hidden" name="alert_on_exit" value="0">
                                <input type="checkbox" name="alert_on_exit" value="1" checked> Avisar al salir
                            </label>
                            <label style="display:flex; align-items:center; gap:.4rem; font-weight:400; font-size:.85rem; margin:0">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" name="active" value="1" checked> Geocerca activa
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar geocerca</button>
                    </form>
                </details>
            @endif
        </div>
    @endforeach
@endif
@endsection
