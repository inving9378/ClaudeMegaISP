<template>
    <div class="voip-kpis tc-wrap" :class="{ 'tc-dark': darkMode }">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="mb-0">
                <i class="fa fa-chart-line me-2 text-primary"></i>MegaVoz — KPIs de la cola
            </h4>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <input type="date" class="form-control form-control-sm" v-model="desde" @change="cargar">
                <span class="text-muted">a</span>
                <input type="date" class="form-control form-control-sm" v-model="hasta" @change="cargar">
                <button class="btn btn-outline-secondary btn-sm" @click="cargar" :disabled="cargando">
                    <i class="fa fa-sync-alt me-1" :class="{ 'fa-spin': cargando }"></i>Actualizar
                </button>
            </div>
        </div>

        <div v-if="cargando" class="text-center py-5 text-muted">
            <i class="fa fa-spinner fa-spin me-1"></i> Cargando…
        </div>

        <template v-else-if="datos">
            <!-- Resumen -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-lg-2" v-for="kpi in tarjetas" :key="kpi.label">
                    <div class="card h-100">
                        <div class="card-body text-center py-3">
                            <div class="small text-muted mb-1">{{ kpi.label }}</div>
                            <div class="fs-4 fw-bold" :class="kpi.clase">{{ kpi.valor }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Por agente -->
            <div class="card">
                <div class="card-header">Por agente</div>
                <div class="card-body p-0">
                    <div v-if="datos.por_agente.length === 0" class="text-center py-4 text-muted">
                        Sin llamadas atendidas en el rango elegido.
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Agente</th>
                                    <th class="text-end">Llamadas atendidas</th>
                                    <th class="text-end">Tiempo total</th>
                                    <th class="text-end">Promedio por llamada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in datos.por_agente" :key="a.agent">
                                    <td>{{ a.agent }}</td>
                                    <td class="text-end">{{ a.llamadas_atendidas }}</td>
                                    <td class="text-end">{{ formatoDuracion(a.segundos_total) }}</td>
                                    <td class="text-end">{{ formatoDuracion(a.segundos_promedio) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <p class="text-muted small mt-3">
                Cola: <code>{{ datos.cola }}</code> · {{ datos.desde }} a {{ datos.hasta }}.
                El "respaldo UCM" del plan original no aplica: con la regla de oro vigente, el
                asistente siempre contesta primero dentro de esta misma cola.
            </p>
        </template>

        <div v-else class="text-center py-5 text-muted">
            <i class="fa fa-chart-bar fa-2x mb-2 d-block"></i>
            No se pudo cargar el tablero.
        </div>
    </div>
</template>

<script>
import { darkMode } from '../../../hook/appConfig.js';

export default {
    name: 'VoipKpis',

    props: {
        csrfToken: { type: String, required: true },
        baseUrl:   { type: String, required: true },
    },

    setup() {
        return { darkMode };
    },

    data() {
        const hoy = new Date().toISOString().slice(0, 10);
        return {
            cargando: false,
            datos: null,
            desde: hoy,
            hasta: hoy,
        };
    },

    computed: {
        tarjetas() {
            if (! this.datos) return [];
            const r = this.datos.resumen;
            return [
                { label: 'Entraron',          valor: r.entradas },
                { label: 'Atendidas',         valor: r.atendidas, clase: 'text-success' },
                { label: 'Abandonadas',       valor: r.abandonadas, clase: r.abandonadas > 0 ? 'text-danger' : '' },
                { label: '% Abandono',        valor: r.porcentaje_abandono + '%', clase: r.porcentaje_abandono > 10 ? 'text-danger' : 'text-success' },
                { label: 'Espera promedio',   valor: this.formatoDuracion(r.espera_promedio_seg) },
                { label: 'Duración promedio', valor: this.formatoDuracion(r.duracion_promedio_seg) },
            ];
        },
    },

    mounted() {
        this.cargar();
    },

    methods: {
        async cargar() {
            this.cargando = true;
            try {
                const params = new URLSearchParams({ desde: this.desde, hasta: this.hasta });
                const r = await fetch(`${this.baseUrl}/kpis/data?${params}`, {
                    headers: { 'Accept': 'application/json' },
                });
                this.datos = r.ok ? await r.json() : null;
            } catch {
                this.datos = null;
            } finally {
                this.cargando = false;
            }
        },

        formatoDuracion(segundos) {
            if (segundos === null || segundos === undefined) return '—';
            const s = Math.round(segundos);
            const mm = Math.floor(s / 60);
            const ss = String(s % 60).padStart(2, '0');
            return `${mm}:${ss}`;
        },
    },
};
</script>
