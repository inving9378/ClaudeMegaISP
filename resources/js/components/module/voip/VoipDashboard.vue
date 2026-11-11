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
            <div class="card vd-card mb-3">
                <div class="vd-card-head">
                    <div>
                        <div class="vd-card-titulo">Rendimiento por trabajador</div>
                        <div class="vd-card-sub">Llamadas de clientes · «con ticket» = levantó un ticket en los {{ datos.minutos_ticket }} min después de colgar</div>
                    </div>
                </div>
                <div v-if="! datos.trabajadores.length" class="vd-vacio">
                    <i class="fa fa-headset"></i>
                    <div>Nadie atendió llamadas de clientes en el rango.</div>
                </div>
                <div v-else class="table-responsive">
                    <table class="vd-tabla">
                        <thead>
                            <tr>
                                <th>Trabajador</th>
                                <th class="vd-num">Contestadas</th>
                                <th class="vd-num" title="Le timbró una llamada que nadie contestó">Sin contestar</th>
                                <th class="vd-num">Salientes</th>
                                <th class="vd-num">Tiempo hablado</th>
                                <th class="vd-num">Promedio</th>
                                <th>Con ticket</th>
                                <th class="vd-acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in datos.trabajadores" :key="t.user_id">
                                <td>
                                    <div class="vd-persona">
                                        <span class="vd-avatar">{{ iniciales(t.nombre) }}</span>
                                        <span class="vd-persona-nombre" :title="t.nombre">{{ t.nombre }}</span>
                                    </div>
                                </td>
                                <td class="vd-num vd-fuerte">{{ t.contestadas }}</td>
                                <td class="vd-num" :class="{ 'vd-alerta': t.no_contestadas > 0 }">{{ t.no_contestadas }}</td>
                                <td class="vd-num">{{ t.salientes }}</td>
                                <td class="vd-num">{{ duracion(t.segundos_total) }}</td>
                                <td class="vd-num">{{ duracion(t.segundos_prom) }}</td>
                                <td>
                                    <div v-if="t.ticket_pct !== null" class="vd-avance">
                                        <div class="vd-avance-barra"><span :style="{ width: t.ticket_pct + '%' }"></span></div>
                                        <span class="vd-avance-txt">{{ t.con_ticket }} de {{ t.contestadas }} · {{ t.ticket_pct }}%</span>
                                    </div>
                                    <span v-else class="vd-tenue">—</span>
                                </td>
                                <td class="vd-acciones">
                                    <button class="vd-boton-chico" @click="verDeTrabajador(t.user_id)">
                                        <i class="fa fa-list-ul"></i> Ver llamadas
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historial -->
            <div class="card vd-card mb-0" ref="historial">
                <div class="vd-card-head">
                    <div>
                        <div class="vd-card-titulo">Historial de llamadas</div>
                        <div class="vd-card-sub">{{ totalLlamadas }} llamada{{ totalLlamadas === 1 ? '' : 's' }} en el rango</div>
                    </div>
                </div>
                <div class="vd-filtros">
                    <div class="vd-buscar">
                        <i class="fa fa-search"></i>
                        <input type="search" class="form-control form-control-sm" placeholder="Número, cliente o trabajador"
                               v-model="filtros.buscar" @input="buscarConPausa">
                    </div>
                    <select class="form-select form-select-sm" v-model="filtros.direccion" @change="cargarLlamadas(1)">
                        <option value="">Todas</option>
                        <option value="entrante">Entrantes</option>
                        <option value="saliente">Salientes</option>
                        <option value="interna">Internas</option>
                    </select>
                    <select class="form-select form-select-sm" v-model="filtros.estado" @change="cargarLlamadas(1)">
                        <option value="">Cualquier estado</option>
                        <option value="contestada">Contestada</option>
                        <option value="asistente">Atendió el asistente</option>
                        <option value="no_atendida">No atendida</option>
                        <option value="sin_respuesta">Sin respuesta</option>
                    </select>
                    <select class="form-select form-select-sm" v-model="filtros.trabajador" @change="cargarLlamadas(1)">
                        <option value="">Todos los trabajadores</option>
                        <option v-for="t in datos.trabajadores" :key="t.user_id" :value="t.user_id">{{ t.nombre }}</option>
                    </select>
                    <label class="vd-check">
                        <input class="form-check-input" type="checkbox" v-model="filtros.con_grabacion" @change="cargarLlamadas(1)">
                        Solo con grabación
                    </label>
                </div>
                <div class="table-responsive">
                    <table class="vd-tabla">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Llamada</th>
                                <th>Cliente</th>
                                <th>Atendió</th>
                                <th class="vd-num">Duración</th>
                                <th>Estado</th>
                                <th class="vd-acciones">Grabación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="cargandoLlamadas"><td colspan="7" class="vd-vacio"><i class="fa fa-spinner fa-spin"></i><div>Cargando…</div></td></tr>
                            <tr v-else-if="! llamadas.length"><td colspan="7" class="vd-vacio"><i class="fa fa-phone-slash"></i><div>Sin llamadas con estos filtros.</div></td></tr>
                            <template v-else v-for="l in llamadas" :key="l.id">
                                <tr :class="{ 'vd-fila-abierta': audioAbierto === l.id }">
                                    <td class="vd-nowrap">
                                        <div class="vd-fecha-dia">{{ soloFecha(l.inicio) }}</div>
                                        <div class="vd-tenue">{{ soloHora(l.inicio) }}</div>
                                    </td>
                                    <td class="vd-nowrap">
                                        <div class="vd-llamada">
                                            <span class="vd-dir" :class="'vd-dir--' + l.direccion" :title="nombreDireccion(l.direccion)">
                                                <i class="fa" :class="iconoDireccion(l.direccion)"></i>
                                            </span>
                                            <span v-if="l.direccion === 'interna'">{{ l.origen }} → {{ l.numero }}</span>
                                            <span v-else class="vd-numero">{{ formatoNumero(l.numero) }}</span>
                                        </div>
                                    </td>
                                    <td class="vd-cliente">
                                        <a v-if="l.cliente_id" :href="`/cliente/editar/${l.cliente_id}`" :title="l.cliente">{{ l.cliente }}</a>
                                        <span v-else-if="l.direccion !== 'interna'" class="vd-tenue vd-italica">No es cliente</span>
                                        <span v-else class="vd-tenue">—</span>
                                    </td>
                                    <td>
                                        <div v-if="l.trabajador" class="vd-persona">
                                            <span class="vd-avatar">{{ iniciales(nombreTrabajador(l)) }}</span>
                                            <div>
                                                <div class="vd-persona-nombre" :title="nombreTrabajador(l)">{{ nombreTrabajador(l) }}</div>
                                                <div class="vd-tenue">Ext. {{ String(l.extension || '').replace(/^web/, '') }}</div>
                                            </div>
                                        </div>
                                        <div v-else-if="l.estado === 'asistente'" class="vd-persona">
                                            <span class="vd-avatar vd-avatar--bot"><i class="fa fa-robot"></i></span>
                                            <span class="vd-persona-nombre">Asistente de voz</span>
                                        </div>
                                        <span v-else class="vd-tenue">—</span>
                                    </td>
                                    <td class="vd-num vd-nowrap">
                                        <div class="vd-fuerte">{{ l.segundos ? duracion(l.segundos) : '—' }}</div>
                                        <div v-if="l.espera > 0" class="vd-tenue">espera {{ duracion(l.espera) }}</div>
                                    </td>
                                    <td>
                                        <span class="vd-pill" :class="'vd-pill--' + l.estado">
                                            <i class="fa" :class="iconoEstado(l.estado)"></i>{{ nombreEstado(l.estado) }}
                                        </span>
                                    </td>
                                    <td class="vd-acciones vd-nowrap">
                                        <template v-if="l.grabacion && puedeEscuchar">
                                            <button class="vd-icono" :class="{ 'vd-icono--activo': audioAbierto === l.id }" @click="alternarAudio(l)"
                                                    :title="audioAbierto === l.id ? 'Cerrar' : 'Escuchar'">
                                                <i class="fa" :class="audioAbierto === l.id ? 'fa-times' : 'fa-play'"></i>
                                            </button>
                                            <a class="vd-icono" :href="urlGrabacion(l, true)" title="Descargar">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        </template>
                                        <span v-else-if="l.tiene_grabacion" class="vd-tenue"><i class="fa fa-microphone"></i> Grabada</span>
                                    </td>
                                </tr>
                                <tr v-if="audioAbierto === l.id" class="vd-audio-fila">
                                    <td colspan="7">
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
                <div class="vd-pie" v-if="totalLlamadas > 0">
                    <span class="vd-tenue">Página {{ pagina }} de {{ paginas }} · {{ totalLlamadas }} llamada{{ totalLlamadas === 1 ? '' : 's' }}</span>
                    <div class="vd-paginas">
                        <button class="vd-boton-chico" :disabled="pagina <= 1" @click="cargarLlamadas(pagina - 1)">‹ Anterior</button>
                        <button class="vd-boton-chico" :disabled="pagina >= paginas" @click="cargarLlamadas(pagina + 1)">Siguiente ›</button>
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

        iconoEstado(e) {
            return { contestada: 'fa-check', asistente: 'fa-robot', no_atendida: 'fa-phone-slash', sin_respuesta: 'fa-minus' }[e] || 'fa-minus';
        },

        // El backend manda «Nombre Apellido (1001)»: el nombre sin la extensión.
        nombreTrabajador(l) {
            return String(l.trabajador || '').replace(/\s*\([^)]*\)\s*$/, '');
        },

        iniciales(nombre) {
            const partes = String(nombre || '').trim().split(/\s+/).filter(Boolean);
            return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?';
        },

        soloFecha(iso) {
            if (! iso) return '';
            const [a, m, d] = iso.slice(0, 10).split('-');
            return `${d}/${m}/${a}`;
        },

        soloHora(iso) {
            return iso ? iso.slice(11, 16) : '';
        },

        nombreEstado(e) {
            return {
                contestada: 'Contestada', asistente: 'Asistente', no_atendida: 'No atendida', sin_respuesta: 'Sin respuesta',
            }[e] || e;
        },
    },
};
</script>

<style scoped>
.vd-fecha { width: auto; }
.vd-fecha-dia { font-weight: 600; }
.vd-card { overflow: hidden; }
.vd-card-head { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--vd-borde); }
.vd-card-titulo { font-weight: 600; font-size: 15px; color: var(--vd-tinta); }
.vd-card-sub { font-size: 12px; color: var(--vd-tenue); margin-top: 2px; }

.voip-dash {
    --vd-tinta: #1f2937; --vd-tenue: #6b7280; --vd-borde: #eceef2; --vd-cabecera: #f8f9fb;
    --vd-hover: #f5f7fa; --vd-acento: #2a78d6; --vd-acento-suave: #e8f1fc;
}
.voip-dash.vd-dark {
    --vd-tinta: #e5e7eb; --vd-tenue: #9ca3af; --vd-borde: rgba(255,255,255,.08); --vd-cabecera: rgba(255,255,255,.03);
    --vd-hover: rgba(255,255,255,.04); --vd-acento: #6ea8f0; --vd-acento-suave: rgba(57,135,229,.16);
}

/* Tabla */
.vd-tabla { width: 100%; border-collapse: collapse; font-size: 13.5px; color: var(--vd-tinta); }
.vd-tabla thead th {
    background: var(--vd-cabecera); color: var(--vd-tenue); font-size: 11px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em; padding: 10px; white-space: nowrap;
    border-bottom: 1px solid var(--vd-borde);
}
.vd-tabla tbody td { padding: 12px 10px; border-bottom: 1px solid var(--vd-borde); vertical-align: middle; height: 60px; }
.vd-tabla tbody tr:last-child td { border-bottom: none; }
.vd-tabla tbody tr:hover td { background: var(--vd-hover); }
.vd-tabla th:first-child, .vd-tabla td:first-child { padding-left: 20px; }
.vd-tabla th:last-child, .vd-tabla td:last-child { padding-right: 20px; }
.vd-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.vd-fuerte { font-weight: 600; }
.vd-alerta { color: #c2410c; font-weight: 600; }
.vd-tenue { color: var(--vd-tenue); font-size: 12px; }
.vd-italica { font-style: italic; }
.vd-nowrap { white-space: nowrap; }
.vd-acciones { text-align: right; white-space: nowrap; }

/* Personas */
.vd-persona { display: flex; align-items: center; gap: 10px; }
.vd-persona > div { min-width: 0; }
.vd-persona-nombre { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
.vd-avatar {
    width: 30px; height: 30px; border-radius: 50%; flex: none;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--vd-acento-suave); color: var(--vd-acento); font-size: 11.5px; font-weight: 700;
}
.vd-avatar--bot { background: rgba(27,175,122,.14); color: #15936a; }

/* Llamada */
.vd-llamada { display: flex; align-items: center; gap: 10px; }
.vd-numero { font-variant-numeric: tabular-nums; font-weight: 500; }
.vd-dir {
    width: 26px; height: 26px; border-radius: 50%; flex: none; font-size: 11px;
    display: inline-flex; align-items: center; justify-content: center;
}
.vd-dir--entrante { background: var(--vd-acento-suave); color: var(--vd-acento); }
.vd-dir--saliente { background: rgba(98,80,214,.12); color: #6250d6; }
.vd-dir--interna  { background: rgba(107,114,128,.12); color: var(--vd-tenue); }
.vd-cliente a {
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    max-width: 190px; overflow-wrap: anywhere; line-height: 1.35; font-weight: 500; color: var(--vd-acento);
}

/* Estado */
.vd-pill {
    display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px;
    font-size: 12px; font-weight: 600; white-space: nowrap;
}
.vd-pill i { font-size: 10px; }
.vd-pill--contestada    { background: #dcfce7; color: #166534; }
.vd-pill--asistente     { background: #dbeafe; color: #1e40af; }
.vd-pill--no_atendida   { background: #fee2e2; color: #991b1b; }
.vd-pill--sin_respuesta { background: #f1f5f9; color: #475569; }
.vd-dark .vd-pill--contestada    { background: rgba(34,197,94,.16);  color: #86efac; }
.vd-dark .vd-pill--asistente     { background: rgba(59,130,246,.18); color: #93c5fd; }
.vd-dark .vd-pill--no_atendida   { background: rgba(239,68,68,.18);  color: #fca5a5; }
.vd-dark .vd-pill--sin_respuesta { background: rgba(148,163,184,.16); color: #cbd5e1; }

/* Botones */
.vd-icono {
    width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--vd-borde);
    background: transparent; color: var(--vd-acento); display: inline-flex; align-items: center;
    justify-content: center; font-size: 12px; cursor: pointer; text-decoration: none; margin-left: 4px;
    transition: background .15s, border-color .15s;
}
.vd-icono:hover { background: var(--vd-acento-suave); border-color: transparent; }
.vd-icono--activo { background: var(--vd-acento); color: #fff; border-color: transparent; }
.vd-boton-chico {
    border: 1px solid var(--vd-borde); background: transparent; color: var(--vd-tinta);
    border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 500; cursor: pointer;
}
.vd-boton-chico:hover:not(:disabled) { background: var(--vd-hover); border-color: var(--vd-acento); color: var(--vd-acento); }
.vd-boton-chico:disabled { opacity: .45; cursor: default; }
.vd-boton-chico i { margin-right: 4px; font-size: 11px; }

/* Con ticket */
.vd-avance { display: flex; align-items: center; gap: 10px; }
.vd-avance-barra { width: 70px; height: 6px; border-radius: 3px; background: var(--vd-borde); overflow: hidden; flex: none; }
.vd-avance-barra span { display: block; height: 100%; background: var(--vd-acento); border-radius: 3px; }
.vd-avance-txt { font-size: 12px; color: var(--vd-tenue); white-space: nowrap; font-variant-numeric: tabular-nums; }

/* Filtros, vacío, audio, pie */
.vd-filtros {
    display: grid; grid-template-columns: minmax(200px, 1.6fr) repeat(3, minmax(150px, 1fr)) auto;
    gap: 10px; align-items: center; padding: 14px 20px; border-bottom: 1px solid var(--vd-borde);
}
@media (max-width: 991px) { .vd-filtros { grid-template-columns: 1fr 1fr; } }
.vd-buscar { position: relative; }
.vd-buscar i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--vd-tenue); font-size: 12px; }
.vd-buscar input { padding-left: 30px; }
.vd-check { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--vd-tinta); white-space: nowrap; margin: 0; cursor: pointer; }
.vd-check .form-check-input { margin: 0; }
.vd-vacio { text-align: center; color: var(--vd-tenue); padding: 36px 16px !important; height: auto !important; }
.vd-vacio i { font-size: 22px; opacity: .55; margin-bottom: 6px; }
.vd-fila-abierta td { background: var(--vd-hover); }
.vd-audio-fila td { background: var(--vd-cabecera) !important; height: auto !important; padding: 12px 20px !important; }
.vd-pie { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 12px 20px; border-top: 1px solid var(--vd-borde); }
.vd-paginas { display: flex; gap: 6px; }
</style>
