{{-- Lista de solicitudes pendientes (permiso + canje). Espera $solicitudes y $balances. --}}
@php
    $permisoLabels = [
        'time_extra' => 'Tiempo extra',
        'app_unlock' => 'Desbloquear app',
        'web_unlock' => 'Desbloquear sitio',
    ];
@endphp

@if($solicitudes->isEmpty())
    <div class="card" style="text-align:center; padding:2rem 1.5rem">
        <div style="font-size:2.5rem; margin-bottom:.5rem">✅</div>
        <p style="color:var(--text-muted); font-size:.95rem">No hay solicitudes pendientes.</p>
    </div>
@else
    @foreach($solicitudes as $sol)
        @php
            $hijo       = optional($sol->profile)->name ?? 'Tu hijo';
            $esCanje    = $sol->type === 'redemption';
            $balance    = (int) ($balances[$sol->profile_id] ?? 0);
            $costo      = $esCanje ? (int) optional($sol->reward)->value : 0;
            $suficiente = $balance >= $costo;
        @endphp
        <div class="card" style="border-left:4px solid {{ $esCanje ? 'var(--warning)' : 'var(--pcolor)' }}">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap">
                <div style="flex:1; min-width:240px">
                    @if($esCanje)
                        <div style="font-size:.72rem; font-weight:700; color:var(--text-muted); letter-spacing:.05em; text-transform:uppercase">🎁 Solicitud de canje</div>
                        <div style="margin-top:.4rem; font-size:.95rem">
                            <strong>{{ $hijo }}</strong> quiere canjear
                            «<strong>{{ optional($sol->reward)->detail ?? 'recompensa' }}</strong>»
                            <span class="badge badge-secondary">{{ $costo }} pts</span>
                        </div>
                        <div style="margin-top:.5rem; font-size:.85rem">
                            Balance actual: <strong>{{ $balance }} pts</strong>
                            @if($suficiente)
                                <span class="badge badge-success">Suficiente ✅</span>
                            @else
                                <span class="badge badge-danger">Insuficiente</span>
                            @endif
                        </div>
                    @else
                        <div style="font-size:.72rem; font-weight:700; color:var(--text-muted); letter-spacing:.05em; text-transform:uppercase">🔐 Solicitud de permiso</div>
                        <div style="margin-top:.4rem; font-size:.95rem">
                            <strong>{{ $hijo }}</strong> pidió
                            <span class="badge badge-info">{{ $permisoLabels[$sol->type] ?? $sol->type }}</span>
                            @if($sol->detail) — {{ $sol->detail }}@endif
                            @if($sol->device) desde {{ $sol->device->name }}@endif
                        </div>
                        @if($sol->message)
                            <div style="color:var(--text-muted); font-size:.82rem; margin-top:.3rem">“{{ $sol->message }}”</div>
                        @endif
                    @endif
                    <div style="color:var(--text-muted); font-size:.76rem; margin-top:.5rem">
                        {{ optional($sol->created_at)->format('d/m/Y H:i') }}
                    </div>
                </div>
                <div style="display:flex; gap:.5rem; white-space:nowrap">
                    <form method="POST" action="{{ route('portal.megafamilia.solicitudes.aprobar', $sol->id) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm"
                            @if($esCanje && ! $suficiente) disabled title="Balance insuficiente" @endif>✓ Aprobar</button>
                    </form>
                    <form method="POST" action="{{ route('portal.megafamilia.solicitudes.rechazar', $sol->id) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">✕ Rechazar</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
