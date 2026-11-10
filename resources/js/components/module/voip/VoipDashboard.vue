<template>
    <div class="voip-dash" :class="{ 'vd-dark': darkMode }">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="mb-0"><i class="fa fa-phone-alt me-2 text-primary"></i>Llamadas</h4>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <div class="btn-group btn-group-sm" role="group" aria-label="Rango rápido">
                    <button v-for="r in rangosRapidos" :key="r.dias" type="button" class="btn btn-outline-secondary"
                            :class="{ active: rangoActivo === r.dias }" @click="rangoRapido(r.dias)">{{ r.label }}</button>
                </div>
                <input type="date" class="form-control form-control-sm vd-fecha" v-model="desde" @change="recargarTodo">
                <span class="text-muted">a</span>
                <input type="date" class="form-control form-control-sm vd-fecha" v-model="hasta" @change="recargarTodo">
                <button class="btn btn-outline-secondary btn-sm" @click="recargarTodo" :disabled="cargando">
                    <i class="fa fa-sync-alt me-1" :class="{ 'fa-spin': cargando }"></i>Actualizar
                </button>
            </div>
        </div>

        <div v-if="datos && datos.central === false" class="alert alert-warning">
            <i class="fa fa-exclamation-triangle me-1"></i>
            La central de llamadas no está instalada en este servidor o no responde, así que todavía no hay
            llamadas que mostrar. Se instala desde Configuración → Add-ons → VoIP → Dependencias del servidor.
        </div>

        <div v-else-if="cargando && ! datos" class="text-center py-5 text-muted">
            <i class="fa fa-spinner fa-spin me-1"></i> Cargando…
        </div>

        <template v-else-if="datos">
            <!-- Resumen -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-4 col-xl-2" v-for="t in tarjetas" :key="t.label">
                    <div class="card h-100 mb-0">
                        <div class="card-body py-3">
                            <div class="small text-muted mb-1">{{ t.label }}</div>
                            <div class="fs-4 fw-semibold">{{ t.valor }}</div>
                            <div v-if="t.nota" class="small text-muted">{{ t.nota }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Por día -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Llamadas entrantes por día</span>
                    <span class="small text-muted">{{ fechaCorta(datos.desde) }} – {{ fechaCorta(datos.hasta) }}</span>
                </div>
                <div class="card-body">
                    <div v-if="totalEntrantes === 0" class="text-center text-muted py-4">Sin llamadas entrantes en el rango.</div>
                    <apexchart v-else type="bar" height="260" :options="opcionesGrafica" :series="seriesGrafica"></apexchart>
                </div>
            </div>

            <!-- Rendimiento -->
            <div class="card mb-3">
                <div class="card-header">
                    Rendimiento por trabajador
                    <span class="small text-muted ms-1">— llamadas de clientes; «con ticket» = levantó un ticket en los {{ datos.minutos_ticket }} min después de colgar</span>
                </div>
                <div class="card-body p-0">
                    <div v-if="! datos.trabajadores.length" class="text-center py-4 text-muted">Nadie atendió llamadas de clientes en el rango.</div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Trabajador</th>
                                    <th class="text-end">Contestadas</th>
                                    <th class="text-end" title="Le timbró una llamada que nadie contestó">Sin contestar</th>
                                    <th class="text-end">Salientes</th>
                                    <th class="text-end">Tiempo hablado</th>
                                    <th class="text-end">Promedio</th>
                                    <th class="text-end">Con ticket</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in datos.trabajadores" :key="t.user_id">
                                    <td>{{ t.nombre }}</td>
                                    <td class="text-end">{{ t.contestadas }}</td>
                                    <td class="text-end">{{ t.no_contestadas }}</td>
                                    <td class="text-end">{{ t.salientes }}</td>
                                    <td class="text-end">{{ duracion(t.segundos_total) }}</td>
                                    <td class="text-end">{{ duracion(t.segundos_prom) }}</td>
                                    <td class="text-end">
                                        <template v-if="t.ticket_pct !== null">{{ t.con_ticket }} <span class="text-muted">({{ t.ticket_pct }}%)</span></template>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-link btn-sm p-0" @click="verDeTrabajador(t.user_id)">Ver llamadas</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Historial -->
            <div class="card mb-0" ref="historial">
                <div class="card-header">Historial de llamadas</div>
                <div class="card-body pb-2">
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-md-3">
                            <input type="search" class="form-control form-control-sm" placeholder="Número, cliente o trabajador"
                                   v-model="filtros.buscar" @input="buscarConPausa">
                        </div>
                        <div class="col-6 col-md-2">
                            <select class="form-select form-select-sm" v-model="filtros.direccion" @change="cargarLlamadas(1)">
                                <option value="">Todas</option>
                                <option value="entrante">Entrantes</option>
                                <option value="saliente">Salientes</option>
                                <option value="interna">Internas</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <select class="form-select form-select-sm" v-model="filtros.estado" @change="cargarLlamadas(1)">
                                <option value="">Cualquier estado</option>
                                <option value="contestada">Contestada</option>
                                <option value="asistente">Atendió el asistente</option>
                                <option value="no_atendida">No atendida</option>
                                <option value="sin_respuesta">Sin respuesta</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <select class="form-select form-select-sm" v-model="filtros.trabajador" @change="cargarLlamadas(1)">
                                <option value="">Todos los trabajadores</option>
                                <option v-for="t in datos.trabajadores" :key="t.user_id" :value="t.user_id">{{ t.nombre }}</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2 d-flex align-items-center">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="vd-con-grab" v-model="filtros.con_grabacion" @change="cargarLlamadas(1)">
                                <label class="form-check-label small" for="vd-con-grab">Solo con grabación</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Llamada</th>
                                <th>Cliente</th>
                                <th>Atendió</th>
                                <th class="text-end">Espera</th>
                                <th class="text-end">Duración</th>
                                <th>Estado</th>
                                <th class="text-end">Grabación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="cargandoLlamadas"><td colspan="8" class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin me-1"></i> Cargando…</td></tr>
                            <tr v-else-if="! llamadas.length"><td colspan="8" class="text-center text-muted py-4">Sin llamadas con estos filtros.</td></tr>
                            <template v-else v-for="l in llamadas" :key="l.id">
                                <tr>
                                    <td class="text-nowrap">{{ fechaHora(l.inicio) }}</td>
                                    <td class="text-nowrap">
                                        <i class="fa me-1 text-muted" :class="iconoDireccion(l.direccion)" :title="nombreDireccion(l.direccion)"></i>
                                        <template v-if="l.direccion === 'interna'">{{ l.origen }} → {{ l.numero }}</template>
                                        <template v-else>{{ formatoNumero(l.numero) }}</template>
                                    </td>
                                    <td>
                                        <a v-if="l.cliente_id" :href="`/cliente/editar/${l.cliente_id}`">{{ l.cliente }}</a>
                                        <span v-else-if="l.direccion !== 'interna'" class="text-muted">No es cliente</span>
                                    </td>
                                    <td>{{ l.trabajador || (l.estado === 'asistente' ? 'Asistente de voz' : '—') }}</td>
                                    <td class="text-end">{{ duracion(l.espera) }}</td>
                                    <td class="text-end">{{ l.segundos ? duracion(l.segundos) : '—' }}</td>
                                    <td><span class="badge" :class="claseEstado(l.estado)">{{ nombreEstado(l.estado) }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <template v-if="l.grabacion && puedeEscuchar">
                                            <button class="btn btn-sm btn-outline-primary" @click="alternarAudio(l)" :title="audioAbierto === l.id ? 'Cerrar' : 'Escuchar'">
                                                <i class="fa" :class="audioAbierto === l.id ? 'fa-times' : 'fa-play'"></i>
                                            </button>
                                            <a class="btn btn-sm btn-outline-secondary ms-1" :href="urlGrabacion(l, true)" title="Descargar">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        </template>
                                        <span v-else-if="l.tiene_grabacion" class="text-muted small">Grabada</span>
                                    </td>
                                </tr>
                                <tr v-if="audioAbierto === l.id" class="vd-audio-fila">
                                    <td colspan="8">
                                        <audio class="w-100" controls autoplay preload="none" :src="urlGrabacion(l, false)"
                                               @error="errorAudio = l.id"></audio>
                                        <div v-if="errorAudio === l.id" class="small text-danger mt-1">
                                            La grabación no está disponible en el servidor.
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2" v-if="totalLlamadas > 0">
                    <span class="small text-muted">{{ totalLlamadas }} llamada{{ totalLlamadas === 1 ? '' : 's' }}</span>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" :disabled="pagina <= 1" @click="cargarLlamadas(pagina - 1)">Anterior</button>
                        <button class="btn btn-outline-secondary" disabled>{{ pagina }} / {{ paginas }}</button>
                        <button class="btn btn-outline-secondary" :disabled="pagina >= paginas" @click="cargarLlamadas(pagina + 1)">Siguiente</button>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="text-center py-5 text-muted">
            <i class="fa fa-chart-bar fa-2x mb-2 d-block"></i>
            No se pudo cargar el tablero.
        </div>
    </div>
</template>

<script>
import { darkMode } from '../../../hook/appConfig.js';

// Paleta categórica validada (dataviz: claro sobre superficie clara, oscuro sobre oscura).
// Orden fijo por serie: contestadas, no atendidas, asistente.
const COLORES = {
    claro:  ['#2a78d6', '#eb6834', '#1baf7a'],
    oscuro: ['#3987e5', '#d95926', '#199e70'],
};

function isoLocal(fecha) {
    const d = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);
    return d.toISOString().slice(0, 10);
}

export default {
    name: 'VoipDashboard',

    props: {
        baseUrl:       { type: String, required: true },
        puedeEscuchar: { type: Boolean, default: false },
    },

    setup() {
        return { darkMode };
    },

    data() {
        const hoy = new Date();
        const hace7 = new Date(hoy); hace7.setDate(hoy.getDate() - 6);
        return {
            desde: isoLocal(hace7),
            hasta: isoLocal(hoy),
            rangoActivo: 7,
            rangosRapidos: [{ dias: 1, label: 'Hoy' }, { dias: 7, label: '7 días' }, { dias: 30, label: '30 días' }],
            cargando: false,
            datos: null,
            cargandoLlamadas: false,
            llamadas: [],
            totalLlamadas: 0,
            pagina: 1,
            porPagina: 25,
            filtros: { buscar: '', direccion: '', estado: '', trabajador: '', con_grabacion: false },
            audioAbierto: null,
            errorAudio: null,
            _pausa: null,
        };
    },

    computed: {
        tarjetas() {
            const r = this.datos?.resumen;
            if (! r) return [];
            return [
                { label: 'Entrantes', valor: r.entrantes },
                { label: 'Contestadas', valor: r.contestadas, nota: r.asistente ? `+${r.asistente} el asistente` : null },
                { label: 'No atendidas', valor: r.no_atendidas, nota: `${r.abandono_pct}% de abandono` },
                { label: 'Espera promedio', valor: this.duracion(r.espera_prom) },
                { label: 'Duración promedio', valor: this.duracion(r.duracion_prom) },
                { label: 'Grabadas', valor: r.con_grabacion, nota: `${r.salientes} salientes · ${r.internas} internas` },
            ];
        },
        totalEntrantes() {
            return this.datos?.resumen?.entrantes || 0;
        },
        seriesGrafica() {
            const d = this.datos?.por_dia || [];
            return [
                { name: 'Contestadas', data: d.map(x => x.contestadas) },
                { name: 'No atendidas', data: d.map(x => x.no_atendidas) },
                { name: 'Atendió el asistente', data: d.map(x => x.asistente) },
            ];
        },
        opcionesGrafica() {
            const oscuro = this.darkMode;
            const tinta = oscuro ? '#c3c2b7' : '#52514e';
            const superficie = oscuro ? '#2a3042' : '#ffffff';
            return {
                chart: { stacked: true, toolbar: { show: false }, fontFamily: 'inherit', background: 'transparent', animations: { enabled: false } },
                theme: { mode: oscuro ? 'dark' : 'light' },
                colors: oscuro ? COLORES.oscuro : COLORES.claro,
                plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' } },
                stroke: { show: true, width: 2, colors: [superficie] },
                dataLabels: { enabled: false },
                legend: { position: 'top', horizontalAlign: 'left', labels: { colors: tinta }, markers: { size: 6 } },
                grid: { borderColor: oscuro ? '#3a4055' : '#ebebe8', strokeDashArray: 0, yaxis: { lines: { show: true } } },
                xaxis: {
                    categories: (this.datos?.por_dia || []).map(x => this.fechaCorta(x.fecha)),
                    labels: { style: { colors: tinta } }, axisBorder: { show: false }, axisTicks: { show: false },
                    tickAmount: Math.min(10, (this.datos?.por_dia || []).length),
                },
                yaxis: { labels: { style: { colors: tinta }, formatter: v => Math.round(v) }, forceNiceScale: true, min: 0 },
                tooltip: { shared: true, intersect: false, theme: oscuro ? 'dark' : 'light' },
                states: { hover: { filter: { type: 'darken', value: 0.9 } } },
            };
        },
        paginas() {
            return Math.max(1, Math.ceil(this.totalLlamadas / this.porPagina));
        },
    },

    mounted() {
        this.recargarTodo();
    },

    beforeUnmount() {
        clearTimeout(this._pausa);
    },

    methods: {
        params(extra = {}) {
            return new URLSearchParams({ desde: this.desde, hasta: this.hasta, ...extra });
        },

        async pedir(ruta, params) {
            const r = await fetch(`${this.baseUrl}/${ruta}?${params}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (! r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        },

        recargarTodo() {
            this.cargar();
            this.cargarLlamadas(1);
        },

        async cargar() {
            this.cargando = true;
            try {
                this.datos = await this.pedir('dashboard/data', this.params());
            } catch {
                this.datos = null;
            } finally {
                this.cargando = false;
            }
        },

        async cargarLlamadas(pagina) {
            this.cargandoLlamadas = true;
            this.audioAbierto = null;
            const f = this.filtros;
            const extra = { pagina, por_pagina: this.porPagina };
            if (f.buscar.trim()) extra.buscar = f.buscar.trim();
            if (f.direccion) extra.direccion = f.direccion;
            if (f.estado) extra.estado = f.estado;
            if (f.trabajador) extra.trabajador = f.trabajador;
            if (f.con_grabacion) extra.con_grabacion = 1;
            try {
                const r = await this.pedir('dashboard/llamadas', this.params(extra));
                this.llamadas = r.llamadas || [];
                this.totalLlamadas = r.total || 0;
                this.pagina = r.pagina || 1;
            } catch {
                this.llamadas = [];
                this.totalLlamadas = 0;
            } finally {
                this.cargandoLlamadas = false;
            }
        },

        buscarConPausa() {
            clearTimeout(this._pausa);
            this._pausa = setTimeout(() => this.cargarLlamadas(1), 350);
        },

        rangoRapido(dias) {
            const hoy = new Date();
            const inicio = new Date(hoy); inicio.setDate(hoy.getDate() - (dias - 1));
            this.desde = isoLocal(inicio);
            this.hasta = isoLocal(hoy);
            this.rangoActivo = dias;
            this.recargarTodo();
        },

        verDeTrabajador(userId) {
            this.filtros.trabajador = userId;
            this.cargarLlamadas(1);
            this.$refs.historial?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        alternarAudio(l) {
            this.errorAudio = null;
            this.audioAbierto = this.audioAbierto === l.id ? null : l.id;
        },

        urlGrabacion(l, descargar) {
            return `${this.baseUrl}/grabaciones/${encodeURIComponent(l.grabacion)}` + (descargar ? '?descargar=1' : '');
        },

        duracion(seg) {
            if (seg === null || seg === undefined) return '—';
            const s = Math.round(seg);
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const ss = String(s % 60).padStart(2, '0');
            return h ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`;
        },

        fechaCorta(iso) {
            if (! iso) return '';
            const [a, m, d] = iso.slice(0, 10).split('-');
            return `${d}/${m}`;
        },

        fechaHora(iso) {
            if (! iso) return '';
            const [f, h] = iso.split(' ');
            const [a, m, d] = f.split('-');
            return `${d}/${m}/${a} ${(h || '').slice(0, 5)}`;
        },

        formatoNumero(n) {
            const d = String(n || '').replace(/\D/g, '');
            if (d.length === 10) return `${d.slice(0, 2)} ${d.slice(2, 6)} ${d.slice(6)}`;
            return n || '—';
        },

        iconoDireccion(d) {
            return { entrante: 'fa-arrow-down', saliente: 'fa-arrow-up', interna: 'fa-exchange-alt' }[d] || 'fa-phone';
        },

        nombreDireccion(d) {
            return { entrante: 'Entrante', saliente: 'Saliente', interna: 'Interna' }[d] || d;
        },

        nombreEstado(e) {
            return {
                contestada: 'Contestada', asistente: 'Asistente', no_atendida: 'No atendida', sin_respuesta: 'Sin respuesta',
            }[e] || e;
        },

        claseEstado(e) {
            return {
                contestada: 'bg-success-subtle text-success', asistente: 'bg-info-subtle text-info',
                no_atendida: 'bg-danger-subtle text-danger', sin_respuesta: 'bg-secondary-subtle text-secondary',
            }[e] || 'bg-secondary-subtle text-secondary';
        },
    },
};
</script>

<style scoped>
.vd-fecha { width: auto; }
.vd-audio-fila td { background: transparent; }
.vd-dark .vd-audio-fila td { border-color: #3a4055; }
</style>
