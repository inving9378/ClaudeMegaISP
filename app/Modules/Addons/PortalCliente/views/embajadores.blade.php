@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Embajadores Meganet')

@section('content')
<div class="page-header">
    <div>
        <h1>🤝 Embajadores Meganet</h1>
        <p style="color:var(--text-muted); font-size:.875rem; margin-top:.25rem">
            Cliente #{{ $cmi->client_id }} — Tu programa de referidos
        </p>
    </div>
</div>

@if(! $active)
    {{-- Estado vacío: el cliente aún no es embajador --}}
    <div class="card" style="text-align:center; padding:2.5rem 1.5rem">
        <div style="font-size:3rem; margin-bottom:.5rem">🚀</div>
        <h2 style="margin-bottom:.5rem">Aún no eres Embajador Meganet</h2>
        <p style="color:var(--text-muted); max-width:520px; margin:0 auto 1.25rem">
            Recomienda Meganet a tus conocidos y gana comisiones en cascada (multinivel de
            activaciones). Al cubrir
            <strong>${{ number_format($setting->threshold_amount, 0) }}</strong>
            de consumo referido en una ventana de <strong>{{ (int) $setting->duration_months }} meses</strong>,
            activas tus beneficios.
        </p>
        <a href="{{ route('portal.marketplace') }}" class="btn btn-primary">Conoce el programa</a>
    </div>
@else
    {{-- KPI Cards: standing --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">🏷️</div>
            <div class="kpi-label">Tu código</div>
            <div class="kpi-value" style="font-size:1.2rem">{{ $profile->referral_code }}</div>
            <div class="kpi-sub">Plan: {{ $planLabel }}</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">👥</div>
            <div class="kpi-label">Referidos</div>
            <div class="kpi-value">{{ (int) $standing['referrals_total'] }}</div>
            <div class="kpi-sub">
                <span class="badge {{ $profile->is_eligible ? 'badge-success' : 'badge-secondary' }}">
                    {{ $profile->is_eligible ? 'Elegible' : 'No elegible aún' }}
                </span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">💵</div>
            <div class="kpi-label">Comisiones acumuladas</div>
            <div class="kpi-value" style="color:var(--success)">
                ${{ number_format((float) $standing['commissions_earned'], 2) }}
            </div>
            <div class="kpi-sub">Aprobadas + aplicadas · Recompensas: {{ (int) $standing['rewards_total'] }}</div>
        </div>

        {{-- "Activación" mide algo DISTINTO de las comisiones: cuántos referidos
             cubrieron su umbral de consumo (${{ threshold }} c/u, ventana N meses). --}}
        <div class="kpi-card">
            <div class="kpi-icon">🎯</div>
            <div class="kpi-label">Referidos activados</div>
            <div class="kpi-value" style="font-size:1.4rem">
                {{ (int) $standing['activated_referrals'] }} / {{ (int) $standing['referrals_total'] }}
            </div>
            <div class="kpi-sub">
                Cubrieron su umbral de ${{ number_format($thresholdEach, 0) }} c/u · ventana {{ $windowMonths }} meses
            </div>
            <div style="margin-top:.5rem; height:8px; background:var(--border); border-radius:4px; overflow:hidden">
                <div style="height:100%; width:{{ $standing['activation_pct'] }}%; background:var(--pcolor)"></div>
            </div>
        </div>
    </div>

    {{-- Comisiones por estado --}}
    @php
        $cs = $standing['commissions_by_status'];
        $estados = [
            'pending'   => ['Pendientes', 'badge-warning'],
            'approved'  => ['Aprobadas', 'badge-info'],
            'applied'   => ['Aplicadas', 'badge-success'],
            'cancelled' => ['Canceladas', 'badge-secondary'],
        ];
    @endphp
    <div class="card">
        <div class="card-title">Comisiones por estado</div>
        <div class="kpi-grid" style="margin-top:.5rem">
            @foreach($estados as $k => [$label, $cls])
                <div class="kpi-card">
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="kpi-value" style="font-size:1.2rem">
                        ${{ number_format((float) ($cs[$k] ?? 0), 2) }}
                    </div>
                    <span class="badge {{ $cls }}">{{ ucfirst($k) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Referidos --}}
    <div class="card">
        <div class="card-title">Mis referidos ({{ (int) $standing['referrals_total'] }})</div>
        @if($referrals->isEmpty())
            <p style="color:var(--text-muted)">Todavía no tienes referidos registrados.</p>
        @else
            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr><th>Cliente</th><th>Nivel (cascada)</th><th>Estado</th><th>Comisiones pagadas</th><th>Fecha</th></tr>
                    </thead>
                    <tbody>
                        @foreach($referrals as $r)
                            <tr>
                                <td>{{ $names[$r->referred_client_id] ?? 'Cliente #'.$r->referred_client_id }}</td>
                                <td>{{ $r->chain_depth }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($r->status) }}</span></td>
                                <td>{{ (int) $r->commissions_paid_count }}</td>
                                <td>{{ optional($r->created_at)->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Comisiones recientes --}}
    <div class="card">
        <div class="card-title">Comisiones recientes</div>
        @if($commissions->isEmpty())
            <p style="color:var(--text-muted)">Sin comisiones por el momento.</p>
        @else
            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr><th>Periodo</th><th>Nivel</th><th>Monto</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @foreach($commissions as $c)
                            @php
                                $cls = match($c->status) {
                                    'applied'  => 'badge-success',
                                    'approved' => 'badge-info',
                                    'pending'  => 'badge-warning',
                                    default    => 'badge-secondary',
                                };
                            @endphp
                            <tr>
                                <td>{{ $c->period_month }}/{{ $c->period_year }}</td>
                                <td>{{ $c->level }}</td>
                                <td>${{ number_format((float) $c->commission_amount, 2) }}</td>
                                <td><span class="badge {{ $cls }}">{{ ucfirst($c->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Prospectos y recompensas --}}
    <div class="card">
        <div class="card-title">Prospectos ({{ $prospects->count() }})</div>
        @if($prospects->isEmpty())
            <p style="color:var(--text-muted)">No tienes prospectos en seguimiento.</p>
        @else
            <div class="table-responsive">
                <table class="portal-table">
                    <thead><tr><th>Nombre</th><th>Teléfono</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody>
                        @foreach($prospects as $p)
                            <tr>
                                <td>{{ $p->name }}</td>
                                <td>{{ $p->phone }}</td>
                                <td><span class="badge badge-secondary">{{ ucfirst($p->status) }}</span></td>
                                <td>{{ optional($p->created_at)->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-title">Recompensas ({{ (int) $standing['rewards_total'] }})</div>
        @if($rewards->isEmpty())
            <p style="color:var(--text-muted)">Aún no tienes recompensas.</p>
        @else
            <div class="table-responsive">
                <table class="portal-table">
                    <thead><tr><th>Tipo</th><th>Estado</th><th>Disponible</th><th>Aplicada</th></tr></thead>
                    <tbody>
                        @foreach($rewards as $rw)
                            <tr>
                                <td>{{ $rw->type }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($rw->status) }}</span></td>
                                <td>{{ optional($rw->available_at)->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ optional($rw->applied_at)->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
@endsection
