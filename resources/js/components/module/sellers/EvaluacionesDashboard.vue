<template>
    <div class="evaluaciones-dashboard container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i class="bi bi-graph-up-arrow me-2"></i>
                Evaluaciones Empresariales
            </h2>
            <div>
                <button class="btn btn-outline-secondary btn-sm me-2" @click="cargarEstadisticas">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                </button>
                <a :href="urlExportar" class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Desde</label>
                        <input v-model="filtros.fecha_desde" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Hasta</label>
                        <input v-model="filtros.fecha_hasta" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Categoría</label>
                        <select v-model="filtros.categoria" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <option v-for="c in categorias" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Canal</label>
                        <select v-model="filtros.canal_origen" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-primary btn-sm" @click="cargarListado">
                            <i class="bi bi-funnel"></i> Aplicar
                        </button>
                        <button class="btn btn-link btn-sm" @click="resetFiltros">Limpiar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas resumen -->
        <div class="row g-3 mb-3" v-if="stats">
            <div class="col-md-3">
                <div class="card text-bg-light">
                    <div class="card-body py-3">
                        <div class="small text-muted">Total evaluaciones</div>
                        <div class="h4 mb-0">{{ stats.totales.total_evaluaciones }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-light">
                    <div class="card-body py-3">
                        <div class="small text-muted">Completadas</div>
                        <div class="h4 mb-0">{{ stats.totales.completadas }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-light">
                    <div class="card-body py-3">
                        <div class="small text-muted">Convertidas a lead</div>
                        <div class="h4 mb-0">{{ stats.totales.convertidas_a_lead }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-light">
                    <div class="card-body py-3">
                        <div class="small text-muted">Puntaje promedio</div>
                        <div class="h4 mb-0">{{ stats.totales.promedio_puntaje }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Listado -->
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Contacto</th>
                            <th>Categoría</th>
                            <th class="text-end">Puntaje</th>
                            <th>Canal</th>
                            <th>Vendedor</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="cargando">
                            <td colspan="8" class="text-center py-4">Cargando…</td>
                        </tr>
                        <tr v-else-if="!evaluaciones.length">
                            <td colspan="8" class="text-center py-4 text-muted">Sin resultados</td>
                        </tr>
                        <tr v-else v-for="ev in evaluaciones" :key="ev.id">
                            <td><small>{{ formatear(ev.created_at) }}</small></td>
                            <td>{{ ev.empresa }}</td>
                            <td>
                                {{ ev.nombre_contacto }}
                                <br><small class="text-muted">{{ ev.email_contacto }}</small>
                            </td>
                            <td><span class="badge text-bg-info">{{ ev.categoria }}</span></td>
                            <td class="text-end">{{ ev.puntaje_total }}</td>
                            <td>{{ ev.canal_origen }}</td>
                            <td>{{ ev.vendedor?.name || '—' }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" @click="verDetalle(ev.id)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center" v-if="meta">
                <small class="text-muted">{{ meta.total }} resultados</small>
                <div>
                    <button class="btn btn-sm btn-outline-secondary me-1" :disabled="meta.current_page <= 1" @click="cambiarPagina(meta.current_page - 1)">«</button>
                    <span class="small">Página {{ meta.current_page }} / {{ meta.last_page }}</span>
                    <button class="btn btn-sm btn-outline-secondary ms-1" :disabled="meta.current_page >= meta.last_page" @click="cambiarPagina(meta.current_page + 1)">»</button>
                </div>
            </div>
        </div>

        <!-- Modal detalle (stub) -->
        <div v-if="detalle" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background:rgba(0,0,0,.4); z-index:1050;">
            <div class="card shadow" style="max-width:720px;width:90%;">
                <div class="card-header d-flex justify-content-between">
                    <strong>Detalle de evaluación #{{ detalle.id }}</strong>
                    <button class="btn-close" @click="detalle = null"></button>
                </div>
                <div class="card-body">
                    <pre class="small mb-0">{{ JSON.stringify(detalle, null, 2) }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'EvaluacionesDashboard',

    data() {
        return {
            categorias: ['BASICO', 'PROFESIONAL', 'CORPORATIVO', 'ENTERPRISE'],
            canales:    ['whatsapp', 'email', 'directo', 'vendedor'],
            filtros: {
                fecha_desde:  '',
                fecha_hasta:  '',
                categoria:    '',
                vendedor_id:  '',
                canal_origen: '',
            },
            page:          1,
            evaluaciones:  [],
            meta:          null,
            stats:         null,
            cargando:      false,
            detalle:       null,
        };
    },

    computed: {
        urlExportar() {
            const params = new URLSearchParams();
            Object.entries(this.filtros).forEach(([k, v]) => {
                if (v) params.append(k, v);
            });
            const qs = params.toString();
            return '/admin/evaluaciones/exportar' + (qs ? '?' + qs : '');
        },
    },

    mounted() {
        this.cargarEstadisticas();
        this.cargarListado();
    },

    methods: {
        formatear(dt) {
            if (!dt) return '';
            return new Date(dt).toLocaleString();
        },

        resetFiltros() {
            this.filtros = { fecha_desde: '', fecha_hasta: '', categoria: '', vendedor_id: '', canal_origen: '' };
            this.cargarListado();
        },

        cambiarPagina(p) {
            this.page = p;
            this.cargarListado();
        },

        async cargarListado() {
            this.cargando = true;
            try {
                const params = { ...this.filtros, page: this.page };
                const { data } = await axios.get('/admin/evaluaciones', { params });
                this.evaluaciones = data.data || [];
                this.meta         = data.meta || null;
            } catch (e) {
                this.evaluaciones = [];
            } finally {
                this.cargando = false;
            }
        },

        async cargarEstadisticas() {
            try {
                const { data } = await axios.get('/admin/evaluaciones/estadisticas');
                this.stats = data.data;
            } catch (e) {
                this.stats = null;
            }
        },

        async verDetalle(id) {
            try {
                const { data } = await axios.get(`/admin/evaluaciones/${id}`);
                this.detalle = data.data;
            } catch (e) {
                this.detalle = null;
            }
        },
    },
};
</script>

<style scoped>
table th { font-size: .85rem; text-transform: uppercase; letter-spacing: .03em; }
</style>
