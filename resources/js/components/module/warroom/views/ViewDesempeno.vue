<template>
    <div class="wr-view wr-view-desempeno">

        <!-- ── Guard: Talento no disponible ─────────────────────────────────── -->
        <div v-if="data && !data.available && !loading" class="wr-empty wr-empty-muted">
            <i class="ti ti-users me-1"></i>
            Módulo Talento no disponible en esta instancia.
        </div>

        <template v-else>

            <!-- ── Controles ────────────────────────────────────────────────── -->
            <div class="wr-desemp-controls">
                <!-- Rango de fechas -->
                <div class="wr-desemp-ctrl-group">
                    <label class="wr-ctrl-label"><i class="ti ti-calendar me-1"></i>Desde</label>
                    <input type="date" class="wr-date-input" v-model="from" @change="load" />
                </div>
                <div class="wr-desemp-ctrl-group">
                    <label class="wr-ctrl-label">Hasta</label>
                    <input type="date" class="wr-date-input" v-model="to" @change="load" />
                </div>

                <!-- Selector de Equipo / Departamento (tabla) -->
                <div class="wr-desemp-ctrl-group">
                    <label class="wr-ctrl-label"><i class="ti ti-building me-1"></i>Equipo</label>
                    <select class="wr-select" v-model="selectedDepartment" @change="onDepartmentChange">
                        <option value="">Todos los equipos</option>
                        <option v-for="d in availableDepartments" :key="d" :value="d">{{ d }}</option>
                        <option v-if="hasSinEquipo" value="__none__">Sin equipo</option>
                    </select>
                </div>

                <!-- Multi-select colaboradores -->
                <div class="wr-desemp-ctrl-group wr-ctrl-grow">
                    <label class="wr-ctrl-label"><i class="ti ti-users me-1"></i>Colaboradores</label>
                    <div class="wr-multiselect-wrapper">
                        <div class="wr-multiselect-trigger" @click="toggleColSelector">
                            <span>{{ selectedColLabel }}</span>
                            <i class="ti ti-chevron-down ms-auto"></i>
                        </div>
                        <div v-if="colSelectorOpen" class="wr-multiselect-dropdown">
                            <label class="wr-ms-item" v-for="col in visibleColaboradores" :key="col.colaborador_id">
                                <input type="checkbox"
                                    :value="col.colaborador_id"
                                    v-model="selectedColIds"
                                    @change="onColSelectionChange" />
                                <span>{{ col.name }}</span>
                                <span v-if="col.department" class="wr-ms-badge">{{ col.department }}</span>
                            </label>
                            <div class="wr-ms-actions">
                                <button class="wr-ms-action-btn" @click="selectAllCols">Todos</button>
                                <button class="wr-ms-action-btn" @click="clearCols">Ninguno</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Refresh -->
                <button class="wr-btn-refresh" @click="load" :disabled="loading" title="Actualizar">
                    <i :class="['ti', loading ? 'ti-loader-2 wr-spin' : 'ti-refresh']"></i>
                </button>
            </div>

            <!-- ── Evolución por equipo ──────────────────────────────────────── -->
            <div class="wr-panel mt-3">
                <warroom-line-series
                    :series="serieSeries"
                    :labels="serieLabels"
                    :loading="serieLoading"
                    title="Evolución por equipo"
                    v-model:granularidad="serieGranularidad"
                    :show-controls="true"
                >
                    <template #controls>
                        <!-- Equipo -->
                        <div class="wr-desemp-ctrl-group">
                            <label class="wr-ctrl-label"><i class="ti ti-building me-1"></i>Equipo</label>
                            <select class="wr-select" v-model="serieDepartment" @change="loadSerie">
                                <option value="">Todos</option>
                                <option v-for="d in availableDepartments" :key="d" :value="d">{{ d }}</option>
                                <option v-if="hasSinEquipo" value="__none__">Sin equipo</option>
                            </select>
                        </div>
                        <!-- Factor -->
                        <div class="wr-desemp-ctrl-group">
                            <label class="wr-ctrl-label"><i class="ti ti-chart-line me-1"></i>Actividad</label>
                            <select class="wr-select" v-model="serieFactor" @change="loadSerie">
                                <option value="total">Total (todas)</option>
                                <optgroup label="Órdenes de trabajo">
                                    <option value="ots">OTs validadas</option>
                                </optgroup>
                                <optgroup label="Actividades">
                                    <option value="asistencias">Asistencias</option>
                                    <option value="inspecciones_caja">Inspecciones caja</option>
                                    <option value="bonos_salud_red">Bonos salud red</option>
                                    <option value="activaciones_olt">Activaciones OLT</option>
                                    <option value="evaluaciones">Evaluaciones</option>
                                    <option value="extensiones_turno">Extensiones de turno</option>
                                </optgroup>
                                <optgroup label="Incidencias / riesgo">
                                    <option value="penalizaciones">Penalizaciones</option>
                                    <option value="desvios_ruta">Desvíos de ruta</option>
                                    <option value="incidencias_ot">Incidencias de OT</option>
                                </optgroup>
                            </select>
                        </div>
                    </template>
                </warroom-line-series>
            </div>

            <!-- ── Tabla comparativa ─────────────────────────────────────────── -->
            <div class="wr-panel mt-3" v-if="!loading && filteredData.length">
                <div class="wr-section-title mb-2">
                    <i class="ti ti-table me-1"></i>
                    Todas las métricas del período
                </div>
                <div class="table-responsive">
                    <table class="table table-sm wr-desemp-table">
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th v-if="!selectedDepartment">Equipo</th>
                                <th>Nivel</th>
                                <th>OTs val.</th>
                                <th>Pts OTs</th>
                                <th>Proy pts</th>
                                <th>Asist.</th>
                                <th>Insp.</th>
                                <th>Bono red</th>
                                <th>Activ. OLT</th>
                                <th>Penaliz.</th>
                                <th>Desvíos</th>
                                <th>Incid.</th>
                                <th>Ext.turno</th>
                                <th>Eval.</th>
                                <th class="wr-score-col">Score</th>
                                <th>★</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="selectedDepartment">
                                <!-- Vista plana intra-equipo -->
                                <tr v-for="col in filteredData" :key="col.colaborador_id">
                                    <td class="fw-semibold">{{ col.name }}</td>
                                    <td class="text-muted small">{{ col.level ?? '—' }}</td>
                                    <td>{{ col.metrics.ots_validadas.count }}</td>
                                    <td>{{ col.metrics.ots_validadas.pts }}</td>
                                    <td>{{ col.metrics.actividad_proyecto }}</td>
                                    <td>{{ col.metrics.asistencias }}</td>
                                    <td>{{ col.metrics.inspecciones_caja.total }}<span v-if="col.metrics.inspecciones_caja.total > 0" class="wr-pass-rate"> {{ passRate(col.metrics.inspecciones_caja) }}%</span></td>
                                    <td>{{ col.metrics.bonos_salud_red.eligible }}</td>
                                    <td>{{ col.metrics.activaciones_olt }}</td>
                                    <td :class="col.metrics.penalizaciones > 0 ? 'text-danger' : ''">{{ col.metrics.penalizaciones }}</td>
                                    <td :class="col.metrics.desvios_ruta > 0 ? 'text-warning' : ''">{{ col.metrics.desvios_ruta }}</td>
                                    <td>{{ col.metrics.incidencias_ot }}</td>
                                    <td>{{ col.metrics.extensiones_turno }}</td>
                                    <td>{{ col.metrics.evaluaciones }}</td>
                                    <td class="wr-score-col"><span :class="scoreClass(col.score)">{{ col.score }}</span></td>
                                    <td>{{ '★'.repeat(col.stars) }}</td>
                                </tr>
                            </template>
                            <template v-else>
                                <!-- Vista agrupada por equipo (Todos) -->
                                <template v-for="group in groupedData" :key="group.department">
                                    <tr class="wr-dept-header">
                                        <td colspan="17" class="wr-dept-header-cell">
                                            <i class="ti ti-building me-1"></i>
                                            {{ group.department }}
                                            <span class="wr-dept-count ms-2">{{ group.items.length }} colaborador{{ group.items.length !== 1 ? 'es' : '' }}</span>
                                        </td>
                                    </tr>
                                    <tr v-for="col in group.items" :key="col.colaborador_id">
                                        <td class="fw-semibold">{{ col.name }}</td>
                                        <td>
                                            <span class="wr-dept-badge">{{ col.department || 'Sin equipo' }}</span>
                                        </td>
                                        <td class="text-muted small">{{ col.level ?? '—' }}</td>
                                        <td>{{ col.metrics.ots_validadas.count }}</td>
                                        <td>{{ col.metrics.ots_validadas.pts }}</td>
                                        <td>{{ col.metrics.actividad_proyecto }}</td>
                                        <td>{{ col.metrics.asistencias }}</td>
                                        <td>{{ col.metrics.inspecciones_caja.total }}<span v-if="col.metrics.inspecciones_caja.total > 0" class="wr-pass-rate"> {{ passRate(col.metrics.inspecciones_caja) }}%</span></td>
                                        <td>{{ col.metrics.bonos_salud_red.eligible }}</td>
                                        <td>{{ col.metrics.activaciones_olt }}</td>
                                        <td :class="col.metrics.penalizaciones > 0 ? 'text-danger' : ''">{{ col.metrics.penalizaciones }}</td>
                                        <td :class="col.metrics.desvios_ruta > 0 ? 'text-warning' : ''">{{ col.metrics.desvios_ruta }}</td>
                                        <td>{{ col.metrics.incidencias_ot }}</td>
                                        <td>{{ col.metrics.extensiones_turno }}</td>
                                        <td>{{ col.metrics.evaluaciones }}</td>
                                        <td class="wr-score-col"><span :class="scoreClass(col.score)">{{ col.score }}</span></td>
                                        <td>{{ '★'.repeat(col.stars) }}</td>
                                    </tr>
                                </template>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Nota fuera de alcance -->
                <p class="wr-oos-note mt-2">
                    <i class="ti ti-info-circle me-1"></i>
                    <strong>Fuera de alcance:</strong> tickets cerrados (tabla <code>tasks</code> no
                    tiene <code>closed_by</code>) y activaciones manuales de ONU sin OT asociada.
                </p>
            </div>


        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick, onUnmounted } from 'vue';
import axios from 'axios';
import WarroomLineSeries from './WarroomLineSeries.vue';

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    period: { type: String, required: true },
});

// ── Estado ────────────────────────────────────────────────────────────────────
const now   = new Date();
const from  = ref(props.period + '-01');
const to    = ref(now.toISOString().slice(0, 10));

const loading            = ref(false);
const data               = ref(null);
const allColaboradores   = ref([]);
const selectedColIds     = ref([]);
const colSelectorOpen    = ref(false);
const selectedDepartment = ref('');  // '' = Todos, '__none__' = sin equipo

// ── Serie temporal ────────────────────────────────────────────────────────────
const serieLoading       = ref(false);
const serieLabels        = ref([]);
const serieSeries        = ref([]);
const serieDepartment    = ref('');
const serieFactor        = ref('total');
const serieGranularidad  = ref('semana');

// ── Departamentos disponibles ─────────────────────────────────────────────────
const availableDepartments = computed(() => data.value?.departments ?? []);
const hasSinEquipo = computed(() =>
    allColaboradores.value.some(c => !c.department)
);

// ── Colaboradores visibles en el multi-select (filtrados por equipo) ──────────
const visibleColaboradores = computed(() => {
    if (!selectedDepartment.value) return allColaboradores.value;
    if (selectedDepartment.value === '__none__') return allColaboradores.value.filter(c => !c.department);
    return allColaboradores.value.filter(c => c.department === selectedDepartment.value);
});

// ── Datos filtrados por colaboradores seleccionados ───────────────────────────
const filteredData = computed(() => {
    if (!data.value?.colaboradores) return [];
    let pool = data.value.colaboradores;
    // Filtro por departamento
    if (selectedDepartment.value === '__none__') {
        pool = pool.filter(c => !c.department);
    } else if (selectedDepartment.value) {
        pool = pool.filter(c => c.department === selectedDepartment.value);
    }
    // Filtro por colaborador multi-select
    if (selectedColIds.value.length && selectedColIds.value.length < pool.length) {
        pool = pool.filter(c => selectedColIds.value.includes(c.colaborador_id));
    }
    return pool;
});

// ── Datos agrupados por departamento para vista "Todos" ───────────────────────
const groupedData = computed(() => {
    const groups = {};
    for (const col of filteredData.value) {
        const key = col.department || '__none__';
        if (!groups[key]) groups[key] = { department: col.department || 'Sin equipo', items: [] };
        groups[key].items.push(col);
    }
    // Ordenar grupos: named departments primero, "Sin equipo" al final
    return Object.values(groups).sort((a, b) => {
        if (a.department === 'Sin equipo') return 1;
        if (b.department === 'Sin equipo') return -1;
        return a.department.localeCompare(b.department);
    });
});

// ── Label del multi-select ─────────────────────────────────────────────────
const selectedColLabel = computed(() => {
    const n = selectedColIds.value.length;
    const t = allColaboradores.value.length;
    if (n === 0 || n === t) return 'Todos los colaboradores';
    return `${n} seleccionado${n > 1 ? 's' : ''}`;
});

// ── Carga de datos ────────────────────────────────────────────────────────────
async function load() {
    loading.value = true;
    try {
        const { data: resp } = await axios.get('/warroom/api/desempeno', {
            params: { from: from.value, to: to.value },
        });
        data.value = resp;
        if (resp.available && resp.colaboradores?.length) {
            allColaboradores.value = resp.colaboradores;
            // Primera carga: seleccionar el primer equipo con colaboradores como default
            if (selectedColIds.value.length === 0) {
                const firstDept = resp.departments?.[0] ?? '';
                selectedDepartment.value = firstDept;
                serieDepartment.value    = firstDept;
                const pool = firstDept
                    ? resp.colaboradores.filter(c => c.department === firstDept)
                    : resp.colaboradores;
                selectedColIds.value = pool.map(c => c.colaborador_id);
            }
        }
        loadSerie();
    } catch {
        data.value = null;
    } finally {
        loading.value = false;
    }
}

function passRate(insp) {
    return insp.total > 0 ? Math.round((insp.passed / insp.total) * 100) : 0;
}

function scoreClass(score) {
    if (score >= 75) return 'text-success fw-bold';
    if (score >= 45) return 'text-warning fw-semibold';
    return 'text-danger';
}

// ── Department selector ───────────────────────────────────────────────────────
function onDepartmentChange() {
    // Actualizar selección de colaboradores al equipo elegido
    if (selectedDepartment.value) {
        selectedColIds.value = visibleColaboradores.value.map(c => c.colaborador_id);
    } else {
        selectedColIds.value = allColaboradores.value.map(c => c.colaborador_id);
    }
}

// ── Multi-select helpers ──────────────────────────────────────────────────────
function toggleColSelector() {
    colSelectorOpen.value = !colSelectorOpen.value;
}
function selectAllCols() {
    selectedColIds.value = visibleColaboradores.value.map(c => c.colaborador_id);
    colSelectorOpen.value = false;
}
function clearCols() {
    selectedColIds.value = [];
    colSelectorOpen.value = false;
}
function onColSelectionChange() {
    // keep dropdown open; table updates reactively via filteredData
}

// ── Click fuera cierra el dropdown ────────────────────────────────────────────
function handleOutsideClick(e) {
    if (!e.target.closest?.('.wr-multiselect-wrapper')) {
        colSelectorOpen.value = false;
    }
}

// ── Carga de serie temporal ────────────────────────────────────────────────────
async function loadSerie() {
    serieLoading.value = true;
    try {
        const { data: resp } = await axios.get('/warroom/api/desempeno/serie', {
            params: {
                department:    serieDepartment.value,
                factor:        serieFactor.value,
                granularidad:  serieGranularidad.value,
                from:          from.value,
                to:            to.value,
            },
        });
        if (resp.available) {
            serieLabels.value = resp.labels ?? [];
            serieSeries.value = resp.series ?? [];
        } else {
            serieLabels.value = [];
            serieSeries.value = [];
        }
    } catch {
        serieLabels.value = [];
        serieSeries.value = [];
    } finally {
        serieLoading.value = false;
    }
}

watch(serieGranularidad, () => loadSerie());

// Sync from/to cuando cambia el period de la pestaña padre
watch(() => props.period, (val) => {
    from.value = val + '-01';
    load();
});

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);
    load();
});
onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
});
</script>

<style scoped>
/* ── Controles ────────────────────────────────────────────────────────────── */
.wr-desemp-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    padding: 10px 14px;
}

.wr-desemp-ctrl-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 120px;
}
.wr-ctrl-grow { flex: 1; min-width: 160px; }

.wr-ctrl-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--wr-text-muted, #9999b0);
}

.wr-date-input,
.wr-select {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 6px;
    color: var(--wr-text, #e8e8f0);
    font-size: 12px;
    padding: 5px 8px;
    outline: none;
    transition: border-color 0.15s;
}
.wr-date-input:focus,
.wr-select:focus { border-color: rgba(83,74,183,0.7); }
.wr-select option,
.wr-select optgroup { background: #1a1a2e; color: #e8e8f0; }

.wr-btn-refresh {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: var(--wr-text-muted, #9999b0);
    padding: 5px 10px;
    cursor: pointer;
    align-self: flex-end;
    transition: all 0.15s;
    font-size: 15px;
}
.wr-btn-refresh:hover { background: rgba(255,255,255,0.09); color: #e8e8f0; }
.wr-btn-refresh:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Multi-select dropdown ───────────────────────────────────────────────── */
.wr-multiselect-wrapper { position: relative; }

.wr-multiselect-trigger {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 6px;
    color: var(--wr-text, #e8e8f0);
    font-size: 12px;
    padding: 5px 8px;
    cursor: pointer;
    user-select: none;
    transition: border-color 0.15s;
    white-space: nowrap;
}
.wr-multiselect-trigger:hover { border-color: rgba(83,74,183,0.5); }

.wr-multiselect-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    min-width: 220px;
    background: #1a1a2e;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    padding: 6px 0;
    z-index: 500;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    max-height: 240px;
    overflow-y: auto;
}

.wr-ms-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 12px;
    color: var(--wr-text, #e8e8f0);
    transition: background 0.12s;
}
.wr-ms-item:hover { background: rgba(255,255,255,0.06); }
.wr-ms-item input[type="checkbox"] { accent-color: #534AB7; flex-shrink: 0; }
.wr-ms-badge {
    margin-left: auto;
    font-size: 10px;
    background: rgba(83,74,183,0.3);
    color: #c5bfff;
    border-radius: 4px;
    padding: 1px 5px;
}

.wr-ms-actions {
    display: flex;
    gap: 6px;
    padding: 6px 12px;
    border-top: 1px solid rgba(255,255,255,0.08);
    margin-top: 4px;
}
.wr-ms-action-btn {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    color: var(--wr-text-muted, #9999b0);
    font-size: 11px;
    padding: 3px 8px;
    cursor: pointer;
}
.wr-ms-action-btn:hover { background: rgba(255,255,255,0.1); color: #e8e8f0; }

/* ── Tabla ────────────────────────────────────────────────────────────────── */
.wr-desemp-table {
    font-size: 12px;
    color: var(--wr-text, #e8e8f0);
    border-color: rgba(255,255,255,0.07);
}
.wr-desemp-table thead th {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--wr-text-muted, #9999b0);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    white-space: nowrap;
    padding: 5px 7px;
    background: rgba(255,255,255,0.02);
}
.wr-desemp-table tbody tr:hover { background: rgba(255,255,255,0.04); }
.wr-desemp-table td { padding: 5px 7px; border-color: rgba(255,255,255,0.05); }

.wr-score-col { font-weight: 600; }
.wr-pass-rate { font-size: 10px; color: var(--wr-text-muted, #9999b0); margin-left: 2px; }

/* ── Agrupación por departamento ─────────────────────────────────────────── */
.wr-dept-header td {
    background: rgba(83,74,183,0.12) !important;
    border-top: 1px solid rgba(83,74,183,0.3) !important;
    border-bottom: 1px solid rgba(83,74,183,0.2) !important;
    padding: 5px 10px;
}
.wr-dept-header-cell {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #a5bfff;
}
.wr-dept-count {
    font-weight: 400;
    font-size: 10px;
    color: #7a8ab0;
    text-transform: none;
}
.wr-dept-badge {
    font-size: 10px;
    background: rgba(83,74,183,0.2);
    color: #c5bfff;
    border-radius: 4px;
    padding: 1px 6px;
    white-space: nowrap;
}

/* ── Nota fuera de alcance ────────────────────────────────────────────────── */
.wr-oos-note {
    font-size: 11px;
    color: var(--wr-text-dim, #6b6b85);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding-top: 8px;
}
.wr-oos-note code {
    background: rgba(255,255,255,0.06);
    border-radius: 3px;
    padding: 1px 4px;
    color: #9999b0;
}

/* ── Animación spin ───────────────────────────────────────────────────────── */
.wr-spin {
    animation: wr-spin 0.8s linear infinite;
    display: inline-block;
}
@keyframes wr-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
