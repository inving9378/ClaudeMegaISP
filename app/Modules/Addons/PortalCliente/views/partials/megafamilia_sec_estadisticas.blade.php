{{-- Tab "Estadísticas" (últimos 14 días). Hereda $stats (MegaFamiliaStats::forProfiles). --}}
<div class="card">
    <div class="card-title">📊 Uso de la familia — últimos 14 días</div>
    <p style="color:var(--text-muted); font-size:.8rem; margin-bottom:1rem">
        {{ $stats['range']['from'] }} — {{ $stats['range']['to'] }}
    </p>

    <div class="kpi-grid" style="margin-bottom:1.25rem">
        <div class="kpi-card"><div class="kpi-icon">⏱️</div><div class="kpi-label">Tiempo de pantalla</div><div class="kpi-value">{{ (int) $stats['kpis']['total_screen_minutes'] }} min</div><div class="kpi-sub">Total del período</div></div>
        <div class="kpi-card"><div class="kpi-icon">📱</div><div class="kpi-label">App más usada</div><div class="kpi-value" style="font-size:1.1rem">{{ $stats['kpis']['top_app'] }}</div><div class="kpi-sub">De todos los hijos</div></div>
        <div class="kpi-card"><div class="kpi-icon">🚫</div><div class="kpi-label">Intentos bloqueados</div><div class="kpi-value">{{ (int) $stats['kpis']['blocked_attempts'] }}</div><div class="kpi-sub">Sitios web</div></div>
    </div>

    @if($stats['screen_time_by_day']->sum('minutes') == 0 && $stats['top_apps']->isEmpty() && $stats['blocked_sites']->isEmpty())
        <p style="color:var(--text-muted); font-size:.9rem">Aún no hay actividad registrada en este período.</p>
    @else
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem">
            <div>
                <strong style="font-size:.9rem">Tiempo de pantalla por día</strong>
                <table class="portal-table" style="margin-top:.5rem">
                    <thead><tr><th>Día</th><th>Minutos</th></tr></thead>
                    <tbody>
                        @foreach($stats['screen_time_by_day'] as $row)
                            <tr><td>{{ $row['day'] }}</td><td>{{ $row['minutes'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>
                <strong style="font-size:.9rem">Top apps</strong>
                @if($stats['top_apps']->isEmpty())
                    <p style="color:var(--text-muted); font-size:.85rem; margin-top:.5rem">Sin datos de apps aún.</p>
                @else
                    <table class="portal-table" style="margin-top:.5rem">
                        <thead><tr><th>App</th><th>Min</th><th>%</th></tr></thead>
                        <tbody>
                            @foreach($stats['top_apps'] as $row)
                                <tr><td>{{ $row['app'] }}</td><td>{{ $row['minutes'] }}</td><td>{{ $row['percent'] }}%</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <strong style="font-size:.9rem">Sitios bloqueados intentados</strong>
        @if($stats['blocked_sites']->isEmpty())
            <p style="color:var(--text-muted); font-size:.85rem; margin-top:.5rem">Sin intentos de sitios bloqueados en este período.</p>
        @else
            <div class="table-responsive">
                <table class="portal-table" style="margin-top:.5rem">
                    <thead><tr><th>Dominio</th><th>Intentos</th><th>Última vez</th></tr></thead>
                    <tbody>
                        @foreach($stats['blocked_sites'] as $row)
                            <tr><td>{{ $row['domain'] }}</td><td>{{ $row['attempts'] }}</td><td>{{ $row['last_at'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
