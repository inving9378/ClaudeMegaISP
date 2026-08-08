@extends('core-layout::master')

@section('title')
    Embajadores Meganet · Métricas de Performance
@endsection

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-chart-line mr-2"></i>Métricas de Performance — Embajadores Meganet</h4>
        <div>
            <select id="days-select" class="form-control form-control-sm d-inline-block w-auto mr-2">
                <option value="7">Últimos 7 días</option>
                <option value="30" selected>Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadMetrics()">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
        </div>
    </div>

    {{-- Infraestructura --}}
    <div class="row mb-4" id="infra-cards">
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center">
                    <div class="text-muted small">Closure Table</div>
                    <div class="h3 mb-0 text-primary" id="infra-closures">—</div>
                    <div class="text-muted small">filas en referral_closures</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center">
                    <div class="text-muted small">Stats Nocturnos</div>
                    <div class="h3 mb-0 text-success" id="infra-stats">—</div>
                    <div class="text-muted small">días con snapshot</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center">
                    <div class="text-muted small">Período analizado</div>
                    <div class="h3 mb-0 text-info" id="infra-period">—</div>
                    <div class="text-muted small">días</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center">
                    <div class="text-muted small">Worker Queue</div>
                    <div class="h3 mb-0 text-warning">PM2</div>
                    <div class="text-muted small">2 instancias activas</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Jobs --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-bold">Telemetría de Jobs</div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Job</th>
                        <th class="text-center">Ejecuciones</th>
                        <th class="text-center">Avg (ms)</th>
                        <th class="text-center">Max (ms)</th>
                        <th class="text-center">Registros</th>
                        <th class="text-center">Errores</th>
                    </tr>
                </thead>
                <tbody id="jobs-tbody">
                    <tr><td colspan="6" class="text-center text-muted py-3">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Daily stats trend --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold">Tendencia diaria</div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Fecha</th>
                        <th class="text-center">Embajadores</th>
                        <th class="text-center">Activos</th>
                        <th class="text-center">Nuevos referidos</th>
                        <th class="text-right">Comisiones gen.</th>
                        <th class="text-right">Comisiones aplic.</th>
                    </tr>
                </thead>
                <tbody id="stats-tbody">
                    <tr><td colspan="6" class="text-center text-muted py-3">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function loadMetrics() {
    const days = document.getElementById('days-select').value;
    fetch(`/embajadores/metrics/data?days=${days}`)
        .then(r => r.json())
        .then(data => {
            // Infra cards
            document.getElementById('infra-closures').textContent = data.infra.closure_rows.toLocaleString();
            document.getElementById('infra-stats').textContent    = data.infra.stats_rows.toLocaleString();
            document.getElementById('infra-period').textContent   = data.infra.period_days;

            // Jobs table
            const jobsTbody = document.getElementById('jobs-tbody');
            if (!data.job_summary.length) {
                jobsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin datos de telemetría aún</td></tr>';
            } else {
                jobsTbody.innerHTML = data.job_summary.map(j => {
                    const errClass = j.failures > 0 ? 'text-danger font-weight-bold' : 'text-success';
                    return `<tr>
                        <td><code>${j.job_class}</code></td>
                        <td class="text-center">${j.total_runs}</td>
                        <td class="text-center">${Math.round(j.avg_ms)}</td>
                        <td class="text-center">${j.max_ms}</td>
                        <td class="text-center">${(j.total_records||0).toLocaleString()}</td>
                        <td class="text-center ${errClass}">${j.failures}</td>
                    </tr>`;
                }).join('');
            }

            // Daily stats table
            const statsTbody = document.getElementById('stats-tbody');
            if (!data.daily_stats.length) {
                statsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin snapshots diarios aún (se generan a las 02:30)</td></tr>';
            } else {
                statsTbody.innerHTML = [...data.daily_stats].reverse().map(s => `<tr>
                    <td>${s.stat_date}</td>
                    <td class="text-center">${s.total_embajadores}</td>
                    <td class="text-center">${s.active_embajadores}</td>
                    <td class="text-center">${s.new_referrals}</td>
                    <td class="text-right">$${parseFloat(s.commissions_generated).toLocaleString('es-MX',{minimumFractionDigits:2})}</td>
                    <td class="text-right">$${parseFloat(s.commissions_applied).toLocaleString('es-MX',{minimumFractionDigits:2})}</td>
                </tr>`).join('');
            }
        })
        .catch(() => {
            document.getElementById('jobs-tbody').innerHTML = '<tr><td colspan="6" class="text-danger text-center">Error al cargar métricas</td></tr>';
        });
}

document.getElementById('days-select').addEventListener('change', loadMetrics);
loadMetrics();
</script>
@endpush
@endsection
