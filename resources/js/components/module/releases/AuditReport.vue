<template>
    <div class="audit-wrap">

        <!-- ── Header ── -->
        <div class="audit-header">
            <div>
                <h5 class="audit-title">
                    <i class="bi bi-clipboard-data me-2"></i>Reporte del sistema
                </h5>
                <p class="audit-subtitle" v-if="generadoAt">
                    Última generación: {{ generadoAt }}
                </p>
                <p class="audit-subtitle text-muted" v-else>
                    Presiona el botón para generar un diagnóstico actualizado.
                </p>
            </div>
            <button class="btn btn-primary px-4" @click="generarReporte" :disabled="loadingReport">
                <span v-if="loadingReport" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="bi bi-arrow-clockwise me-2"></i>
                {{ loadingReport ? 'Analizando...' : 'Generar reporte' }}
            </button>
        </div>

        <!-- ── Tarjetas de métricas ── -->
        <div v-if="metricas.length" class="audit-grid">
            <div
                v-for="m in metricas"
                :key="m.titulo"
                class="audit-card"
                :class="`audit-card-${m.estado}`"
            >
                <div class="audit-card-top">
                    <span class="audit-card-icon">
                        <i :class="iconoEstado(m.estado)"></i>
                    </span>
                    <span class="audit-card-titulo">{{ m.titulo }}</span>
                    <span class="audit-badge" :class="`badge-${m.estado}`">{{ m.valor }}</span>
                </div>
                <p class="audit-card-detalle">{{ m.detalle }}</p>
                <p class="audit-card-sub" v-if="m.subtitulo">{{ m.subtitulo }}</p>
            </div>
        </div>

        <!-- ── Skeleton mientras carga ── -->
        <div v-else-if="loadingReport" class="audit-grid">
            <div v-for="i in 6" :key="i" class="audit-card audit-card-skeleton">
                <div class="skel skel-sm mb-2"></div>
                <div class="skel skel-lg"></div>
            </div>
        </div>

        <!-- ── Separador ── -->
        <hr class="audit-divider" />

        <!-- ── Plan de acción ── -->
        <div class="audit-plan-header">
            <h5 class="audit-title">
                <i class="bi bi-list-check me-2"></i>Plan de acción
            </h5>
            <span class="audit-plan-progress">
                {{ planItems.filter(i => i.completado).length }}/{{ planItems.length }} completados
            </span>
        </div>

        <!-- Barra progreso global -->
        <div class="audit-progress-wrap" v-if="planItems.length">
            <div
                class="audit-progress-bar"
                :style="{ width: progresoGlobal + '%' }"
                :class="progresoGlobal === 100 ? 'bg-success' : 'bg-primary'"
            ></div>
        </div>

        <!-- Skeleton plan -->
        <div v-if="loadingPlan" class="mt-3">
            <div v-for="i in 4" :key="i" class="plan-item-skeleton mb-2">
                <div class="skel skel-lg"></div>
            </div>
        </div>

        <!-- Items del plan -->
        <div v-else class="audit-plan-list">
            <div
                v-for="item in planItems"
                :key="item.id"
                class="plan-item"
                :class="{ 'plan-item-done': item.completado }"
            >
                <!-- Checkbox + título -->
                <div class="plan-item-top">
                    <input
                        type="checkbox"
                        class="plan-checkbox"
                        :checked="item.completado"
                        @change="toggleItem(item)"
                    />
                    <div class="plan-item-body">
                        <div class="plan-item-title">
                            <span class="plan-badge" :class="`plan-prio-${item.prioridad}`">
                                {{ item.prioridad }}
                            </span>
                            <span class="plan-badge plan-cat">{{ item.categoria }}</span>
                            {{ item.titulo }}
                        </div>
                        <p class="plan-item-desc">{{ item.descripcion }}</p>
                        <p class="plan-item-fecha" v-if="item.completado && item.completado_at">
                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                            Completado: {{ formatDate(item.completado_at) }}
                        </p>
                    </div>
                    <!-- Toggle notas -->
                    <button
                        class="btn btn-sm btn-link text-muted plan-nota-btn"
                        @click="toggleNota(item)"
                        title="Agregar nota"
                    >
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>

                <!-- Área de notas (expandible) -->
                <div v-if="notaAbierta === item.id" class="plan-nota-area">
                    <textarea
                        class="plan-nota-input"
                        v-model="notaTexto"
                        placeholder="Escribe una nota sobre este punto..."
                        rows="2"
                    ></textarea>
                    <div class="plan-nota-actions">
                        <button class="btn btn-sm btn-success" @click="guardarNota(item)">
                            Guardar
                        </button>
                        <button class="btn btn-sm btn-light ms-2" @click="notaAbierta = null">
                            Cancelar
                        </button>
                    </div>
                </div>

                <!-- Nota guardada -->
                <div v-else-if="item.notas" class="plan-nota-saved">
                    <i class="bi bi-chat-left-text me-1"></i>{{ item.notas }}
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'AuditReport',
    setup() {
        const metricas      = ref([]);
        const planItems     = ref([]);
        const generadoAt    = ref('');
        const loadingReport = ref(false);
        const loadingPlan   = ref(false);
        const notaAbierta   = ref(null);
        const notaTexto     = ref('');

        const progresoGlobal = computed(() => {
            if (!planItems.value.length) return 0;
            return Math.round(planItems.value.filter(i => i.completado).length / planItems.value.length * 100);
        });

        const iconoEstado = (estado) => ({
            ok:      'bi bi-check-circle-fill text-success',
            warning: 'bi bi-exclamation-triangle-fill text-warning',
            error:   'bi bi-x-circle-fill text-danger',
        }[estado] || 'bi bi-circle text-muted');

        const formatDate = (d) => d
            ? new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
            : '';

        async function generarReporte() {
            loadingReport.value = true;
            try {
                const { data } = await axios.get('/releases/audit/report');
                metricas.value   = data.metricas;
                generadoAt.value = data.generado_at;
            } catch (e) {
                console.error('Error generando reporte:', e);
            } finally {
                loadingReport.value = false;
            }
        }

        async function cargarPlan() {
            loadingPlan.value = true;
            try {
                const { data } = await axios.get('/releases/audit/plan');
                planItems.value = data;
            } catch (e) {
                console.error('Error cargando plan:', e);
            } finally {
                loadingPlan.value = false;
            }
        }

        async function toggleItem(item) {
            try {
                const { data } = await axios.post(`/releases/audit/plan/${item.id}/toggle`);
                const idx = planItems.value.findIndex(i => i.id === item.id);
                if (idx !== -1) planItems.value[idx] = data;
            } catch (e) {
                console.error('Error actualizando item:', e);
            }
        }

        function toggleNota(item) {
            if (notaAbierta.value === item.id) {
                notaAbierta.value = null;
            } else {
                notaAbierta.value = item.id;
                notaTexto.value   = item.notas || '';
            }
        }

        async function guardarNota(item) {
            try {
                const { data } = await axios.post(`/releases/audit/plan/${item.id}/note`, { notas: notaTexto.value });
                const idx = planItems.value.findIndex(i => i.id === item.id);
                if (idx !== -1) planItems.value[idx] = data;
                notaAbierta.value = null;
            } catch (e) {
                console.error('Error guardando nota:', e);
            }
        }

        onMounted(() => {
            cargarPlan();
        });

        return {
            metricas, planItems, generadoAt, loadingReport, loadingPlan,
            notaAbierta, notaTexto, progresoGlobal,
            generarReporte, toggleItem, toggleNota, guardarNota,
            iconoEstado, formatDate,
        };
    },
};
</script>

<style scoped>
.audit-wrap { padding: 24px 0; }

.audit-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.audit-title  { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.audit-subtitle { font-size: 13px; color: #6c757d; margin: 0; }

/* ── Grid de métricas ── */
.audit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}

.audit-card {
    border-radius: 10px;
    padding: 14px 16px;
    border-left: 4px solid transparent;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.audit-card-ok      { border-color: #198754; background: #f0fdf4; }
.audit-card-warning { border-color: #ffc107; background: #fffdf0; }
.audit-card-error   { border-color: #dc3545; background: #fff5f5; }
.audit-card-skeleton { background: #f6f7f8; border-color: #e0e0e0; }

.audit-card-top {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}
.audit-card-icon  { font-size: 16px; flex-shrink: 0; }
.audit-card-titulo { font-size: 13px; font-weight: 600; color: #212529; flex: 1; }

.audit-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.badge-ok      { background: #d1e7dd; color: #0a3622; }
.badge-warning { background: #fff3cd; color: #664d03; }
.badge-error   { background: #f8d7da; color: #58151c; }

.audit-card-detalle { font-size: 12px; color: #495057; margin: 0 0 2px; }
.audit-card-sub     { font-size: 11px; color: #6c757d; margin: 0; }

/* Skeleton */
.skel { background: linear-gradient(90deg, #e8e8e8 25%, #f0f0f0 50%, #e8e8e8 75%); background-size: 200%; animation: shimmer 1.4s infinite; border-radius: 4px; }
.skel-sm { height: 12px; width: 60%; }
.skel-lg { height: 20px; width: 100%; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

.audit-divider { margin: 28px 0; }

/* ── Plan ── */
.audit-plan-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.audit-plan-progress { font-size: 13px; color: #6c757d; font-weight: 600; }

.audit-progress-wrap {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    margin-bottom: 20px;
    overflow: hidden;
}
.audit-progress-bar {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.audit-plan-list { display: flex; flex-direction: column; gap: 10px; }

.plan-item {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 14px 16px;
    transition: opacity 0.2s;
}
.plan-item-done { opacity: 0.55; }
.plan-item-top  { display: flex; align-items: flex-start; gap: 12px; }

.plan-checkbox {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: #0d6efd;
}

.plan-item-body { flex: 1; min-width: 0; }
.plan-item-title {
    font-size: 14px;
    font-weight: 600;
    color: #212529;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.plan-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 1px 7px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    flex-shrink: 0;
}
.plan-prio-urgente { background: #f8d7da; color: #58151c; }
.plan-prio-alta    { background: #fff3cd; color: #664d03; }
.plan-prio-media   { background: #d1ecf1; color: #0c5460; }
.plan-prio-baja    { background: #e2e3e5; color: #383d41; }
.plan-cat          { background: #e8d5f5; color: #5a189a; }

.plan-item-desc {
    font-size: 12px;
    color: #6c757d;
    margin: 0;
    line-height: 1.5;
}
.plan-item-fecha { font-size: 11px; color: #198754; margin: 4px 0 0; }

.plan-nota-btn { padding: 2px 6px; flex-shrink: 0; }

.plan-nota-area {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #dee2e6;
}
.plan-nota-input {
    width: 100%;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 7px 10px;
    font-size: 12px;
    resize: vertical;
}
.plan-nota-actions { margin-top: 6px; }

.plan-nota-saved {
    margin-top: 8px;
    font-size: 12px;
    color: #6c757d;
    font-style: italic;
}

.plan-item-skeleton { height: 60px; border-radius: 8px; }
</style>
