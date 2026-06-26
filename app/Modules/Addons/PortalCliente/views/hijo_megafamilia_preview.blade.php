@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Vista previa — ' . $profile->name)

@section('content')
@php
    $devEmoji = ['smartphone' => '📱', 'tablet' => '📲', 'pc' => '💻', 'otro' => '🔌'];
    $devLabel = ['smartphone' => 'Smartphone', 'tablet' => 'Tablet', 'pc' => 'PC', 'otro' => 'Otro'];
    $diasCorto = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];
    $catalogo  = $recompensas->whereNull('granted_at');
    $canjeadas = $recompensas->whereNotNull('granted_at');
    $pendientes = $tareas->where('status', 'pending');
    $completadas = $tareas->where('status', 'completed');
@endphp

<div class="page-header">
    <div>
        <h1>👁️ Vista previa de la app</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Así vería {{ $profile->name }} la app MegaFamilia (solo lectura)
        </p>
    </div>
    <a href="{{ route('portal.megafamilia') }}" class="btn btn-outline btn-sm">← MegaFamilia</a>
</div>

{{-- Marco tipo teléfono --}}
<div style="max-width:430px; margin:0 auto">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:1.25rem; padding:1rem; overflow:hidden">

        {{-- HEADER: nombre + balance --}}
        <div style="text-align:center; margin-bottom:1.5rem; padding:1.5rem; background:linear-gradient(135deg, var(--pcolor), var(--pcolor-dark)); color:#fff; border-radius:1rem">
            <h2 style="margin:0">{{ $profile->name }}</h2>
            <p style="font-size:.875rem; opacity:.9; margin:.25rem 0 0">{{ $profile->age ? $profile->age . ' años' : '' }}</p>
            <div style="margin-top:1rem; font-size:2rem; font-weight:bold">💰 {{ $balance }} pts</div>
            <p style="font-size:.75rem; opacity:.8; margin-top:.25rem">Balance actual</p>
        </div>

        {{-- TABS --}}
        <div style="display:flex; gap:.25rem; border-bottom:2px solid var(--border); margin-bottom:1rem; overflow-x:auto">
            <button class="hp-tab active" data-tab="tareas" style="padding:.6rem .7rem; cursor:pointer; background:none; border:none; border-bottom:3px solid var(--pcolor); color:var(--pcolor); font-size:.82rem; white-space:nowrap">🎯 Mis tareas</button>
            <button class="hp-tab" data-tab="recompensas" style="padding:.6rem .7rem; cursor:pointer; background:none; border:none; border-bottom:3px solid transparent; color:var(--text); font-size:.82rem; white-space:nowrap">🎁 Recompensas</button>
            <button class="hp-tab" data-tab="dispositivos" style="padding:.6rem .7rem; cursor:pointer; background:none; border:none; border-bottom:3px solid transparent; color:var(--text); font-size:.82rem; white-space:nowrap">📱 Dispositivos</button>
            <button class="hp-tab" data-tab="horarios" style="padding:.6rem .7rem; cursor:pointer; background:none; border:none; border-bottom:3px solid transparent; color:var(--text); font-size:.82rem; white-space:nowrap">⏰ Horarios</button>
        </div>

        {{-- TAB TAREAS --}}
        <div class="hp-content active" id="hp-tareas">
            <h3 style="font-size:.95rem; margin:0 0 .75rem">Tareas pendientes</h3>
            @forelse($pendientes as $a)
                <div style="background:var(--card); padding:1rem; border-radius:.5rem; margin-bottom:.75rem; border-left:4px solid var(--warning)">
                    <strong>{{ optional($a->task)->title }}</strong>
                    @if(optional($a->task)->description)<p style="font-size:.85rem; color:var(--text-muted); margin:.25rem 0 0">{{ $a->task->description }}</p>@endif
                    <div style="margin-top:.5rem; display:flex; justify-content:space-between; align-items:center">
                        <span><span class="badge badge-warning">Pendiente</span> <span style="font-weight:600; color:var(--success); margin-left:.4rem">+{{ (int) optional($a->task)->points }} pts</span></span>
                        <button class="btn btn-primary btn-sm" disabled style="opacity:.5; cursor:not-allowed">✓ Completar (en app)</button>
                    </div>
                </div>
            @empty
                <p style="color:var(--text-muted); text-align:center; padding:1.5rem">✅ ¡No hay tareas pendientes!</p>
            @endforelse

            <h3 style="font-size:.95rem; margin:1.25rem 0 .75rem">Tareas completadas</h3>
            @forelse($completadas as $a)
                <div style="background:var(--card); padding:1rem; border-radius:.5rem; margin-bottom:.75rem; border-left:4px solid var(--success); opacity:.75">
                    <div style="display:flex; justify-content:space-between; align-items:start; gap:.5rem">
                        <div>
                            <strong>{{ optional($a->task)->title }}</strong>
                            <p style="font-size:.82rem; color:var(--text-muted); margin:.25rem 0 0">Completada el {{ optional($a->completed_at)->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="badge badge-success">+{{ (int) optional($a->task)->points }} pts</span>
                    </div>
                </div>
            @empty
                <p style="color:var(--text-muted); text-align:center; padding:1.5rem">Aún no has completado tareas.</p>
            @endforelse
        </div>

        {{-- TAB RECOMPENSAS --}}
        <div class="hp-content" id="hp-recompensas" style="display:none">
            <h3 style="font-size:.95rem; margin:0 0 .75rem">Catálogo de recompensas</h3>
            @forelse($catalogo as $r)
                <div style="background:var(--card); padding:1rem; border-radius:.5rem; margin-bottom:.75rem; display:flex; justify-content:space-between; align-items:center; gap:.5rem">
                    <div><strong>{{ $r->detail }}</strong><p style="font-size:.82rem; color:var(--text-muted); margin:.25rem 0 0">Cuesta {{ (int) $r->value }} pts</p></div>
                    <button class="btn btn-primary btn-sm" disabled style="opacity:.5; cursor:not-allowed">🔁 Canjear (en app)</button>
                </div>
            @empty
                <p style="color:var(--text-muted); text-align:center; padding:1.5rem">No hay recompensas disponibles.</p>
            @endforelse

            <h3 style="font-size:.95rem; margin:1.25rem 0 .75rem">Recompensas canjeadas</h3>
            @forelse($canjeadas as $r)
                <div style="background:var(--card); padding:1rem; border-radius:.5rem; margin-bottom:.75rem; border-left:4px solid var(--success)">
                    <strong>{{ $r->detail }}</strong>
                    <p style="font-size:.82rem; color:var(--text-muted); margin:.25rem 0 0">Canjeada el {{ optional($r->granted_at)->format('d/m/Y H:i') }}</p>
                </div>
            @empty
                <p style="color:var(--text-muted); text-align:center; padding:1.5rem">Aún no has canjeado recompensas.</p>
            @endforelse
        </div>

        {{-- TAB DISPOSITIVOS --}}
        <div class="hp-content" id="hp-dispositivos" style="display:none">
            <h3 style="font-size:.95rem; margin:0 0 .75rem">Mis dispositivos</h3>
            @forelse($dispositivos as $d)
                <div style="background:var(--card); padding:1rem; border-radius:.5rem; margin-bottom:.75rem; display:flex; align-items:center; gap:1rem">
                    <div style="font-size:1.5rem">{{ $devEmoji[$d->model] ?? '🔌' }}</div>
                    <div><strong>{{ $d->name }}</strong><p style="font-size:.82rem; color:var(--text-muted); margin:0">{{ $devLabel[$d->model] ?? ($d->model ?: 'Dispositivo') }}</p></div>
                </div>
            @empty
                <p style="color:var(--text-muted); text-align:center; padding:1.5rem">Aún no tienes dispositivos registrados.</p>
            @endforelse
        </div>

        {{-- TAB HORARIOS --}}
        <div class="hp-content" id="hp-horarios" style="display:none">
            <h3 style="font-size:.95rem; margin:0 0 .75rem">Mis horarios de internet</h3>
            @forelse($horarios as $h)
                <div style="background:var(--card); padding:1rem; border-radius:.5rem; margin-bottom:.75rem">
                    <strong>{{ $h->name ?? 'Horario' }}</strong>
                    <p style="font-size:.82rem; color:var(--text-muted); margin:.25rem 0 0">
                        {{ collect($h->days ?? [])->map(fn ($d) => $diasCorto[$d] ?? $d)->implode(', ') }}
                        · {{ substr((string) $h->start_time, 0, 5) }} – {{ substr((string) $h->end_time, 0, 5) }}
                    </p>
                    <div style="margin-top:.5rem"><span class="badge {{ $h->active ? 'badge-success' : 'badge-secondary' }}">{{ $h->active ? 'Activo' : 'Inactivo' }}</span></div>
                </div>
            @empty
                <p style="color:var(--text-muted); text-align:center; padding:1.5rem">No hay horarios configurados.</p>
            @endforelse
        </div>

        {{-- FOOTER --}}
        <div style="background:var(--pcolor); color:#fff; padding:1rem; border-radius:.5rem; text-align:center; margin-top:1.25rem">
            <p style="font-size:.82rem; margin:0">👁️ Vista previa de cómo {{ $profile->name }} vería la app MegaFamilia. Las acciones se realizan desde el dispositivo del hijo.</p>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.hp-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var name = this.getAttribute('data-tab');
            document.querySelectorAll('.hp-tab').forEach(function (b) { b.style.borderBottomColor = 'transparent'; b.style.color = 'var(--text)'; });
            document.querySelectorAll('.hp-content').forEach(function (c) { c.style.display = 'none'; });
            this.style.borderBottomColor = 'var(--pcolor)'; this.style.color = 'var(--pcolor)';
            document.getElementById('hp-' + name).style.display = 'block';
        });
    });
</script>
@endsection
