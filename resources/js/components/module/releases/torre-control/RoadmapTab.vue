<template>
    <div class="rdm-wrap" :class="{ 'rdm-dark': darkMode }">

        <!-- KPIs -->
        <div class="rdm-kpis">
            <div class="rdm-kpi rdm-kpi-progress">
                <span class="rdm-kpi-num">{{ counts.in_progress }}</span>
                <span class="rdm-kpi-label">En progreso</span>
            </div>
            <div class="rdm-kpi rdm-kpi-pending">
                <span class="rdm-kpi-num">{{ counts.pending }}</span>
                <span class="rdm-kpi-label">Pendientes</span>
            </div>
            <div class="rdm-kpi rdm-kpi-done">
                <span class="rdm-kpi-num">{{ counts.done }}</span>
                <span class="rdm-kpi-label">Hechos</span>
            </div>
            <div class="rdm-kpi rdm-kpi-cancelled">
                <span class="rdm-kpi-num">{{ counts.cancelled }}</span>
                <span class="rdm-kpi-label">Cancelados</span>
            </div>
        </div>

        <!-- Filtros -->
        <div class="rdm-filters">
            <!-- rdm-filter-row: display:contents en desktop (transparente al flex padre);
                 scroll horizontal en móvil. Patrón responsive Medussa. -->
            <div class="rdm-filter-row">
                <button
                    v-for="f in filters"
                    :key="f.key"
                    class="rdm-filter-btn"
                    :class="{ active: activeFilter === f.key }"
                    @click="activeFilter = f.key"
                >{{ f.label }}</button>
            </div>
            <button class="rdm-add-btn" @click="showAddModal = true">
                <i class="bi bi-plus-lg me-1"></i>Agregar item
            </button>
        </div>

        <!-- Spinner inicial -->
        <div v-if="loading" class="rdm-loading">
            <div class="spinner-border spinner-border-sm text-primary me-2"></div>Cargando...
        </div>

        <!-- Grupos de items -->
        <template v-else>
            <div v-if="!hasVisibleItems" class="rdm-empty rdm-empty-global">
                No hay items que coincidan con este filtro.
            </div>

            <template v-for="group in visibleGroups" :key="group.key">
            <div
                v-if="group.items.length > 0"
                class="rdm-group"
                :class="`rdm-group-${group.key}`"
            >
                <div class="rdm-group-header">
                    <span class="rdm-group-dot" :class="`dot-${group.key}`"></span>
                    {{ group.label }}
                </div>

                <div
                    v-for="(item, idx) in group.items"
                    :key="item.id"
                    class="rdm-item"
                    :class="{
                        'rdm-item-inprogress': item.status === 'in_progress',
                        'rdm-item-done': item.status === 'done',
                        'rdm-item-cancelled': item.status === 'cancelled',
                    }"
                >
                    <!-- Fila principal -->
                    <div class="rdm-item-row">
                        <!-- Círculo de estado -->
                        <button
                            class="rdm-status-btn"
                            :class="`status-${item.status}`"
                            :title="statusLabel(item.status)"
                            @click="cycleStatus(item)"
                            :disabled="item.status === 'done' || item.status === 'cancelled'"
                        >
                            <i :class="statusIcon(item.status)"></i>
                        </button>

                        <!-- Título + tags + último avance
                             rdm-item-text / rdm-item-tags permiten separar texto y tags
                             en dos filas en móvil. Patrón responsive Medussa. -->
                        <div class="rdm-item-title" @click="toggleExpand(item.id)">
                            <span class="rdm-item-id">#{{ item.id }}</span>
                            <span class="rdm-item-text">{{ item.title }}</span>
                            <div class="rdm-item-tags">
                                <span class="rdm-tag rdm-tag-prio"
                                      :class="item.priority ? `prio-${item.priority}` : 'prio-none'">
                                    {{ item.priority || 'Sin prioridad' }}
                                </span>
                                <span v-if="item.target_version" class="rdm-tag rdm-tag-ver">
                                    {{ item.target_version }}
                                </span>
                                <span v-if="lastAdvance(item)" class="rdm-tag rdm-tag-age" :title="lastAdvance(item).full">
                                    <i class="bi bi-clock me-1"></i>{{ lastAdvance(item).rel }}
                                </span>
                                <span v-if="item.eta_minutos && (item.status === 'pending' || item.status === 'in_progress')"
                                      class="rdm-tag rdm-tag-eta"
                                      :title="`Thomas lo estimó ${fullDateTime(item.eta_asignada_at)} · ponlo en tu temporizador`">
                                    <i class="bi bi-stopwatch me-1"></i>ETA ~{{ item.eta_minutos }} min
                                </span>
                            </div>
                        </div>

                        <!-- Botón lanzar -->
                        <button
                            class="rdm-launch-btn"
                            :class="{ 'rdm-launch-active': canLaunch(item, idx, group) }"
                            :title="launchTitle(item, idx, group)"
                            @click="launchItem(item, idx, group)"
                        >
                            <i class="bi bi-arrow-right-circle-fill"></i>
                        </button>
                    </div>

                    <!-- Barra de progreso (solo si tiene sub-tareas) -->
                    <div
                        v-if="item.subtasks && item.subtasks.length > 0"
                        class="rdm-progress-wrap"
                        :title="`${subtasksDone(item)}/${item.subtasks.length} sub-tareas completadas`"
                    >
                        <div
                            class="rdm-progress-bar"
                            :class="{ 'rdm-progress-full': subtasksPct(item) === 100 }"
                            :style="{ width: subtasksPct(item) + '%' }"
                        ></div>
                    </div>

                    <!-- Detalle expandible -->
                    <div v-if="expandedId === item.id" class="rdm-detail">

                        <!-- ── Prompt ── -->
                        <div class="rdm-section-label">Prompt para Claude Code</div>
                        <textarea
                            class="rdm-prompt-input"
                            v-model="editPrompt"
                            placeholder="Escribe el prompt para Claude Code..."
                            rows="6"
                        ></textarea>
                        <div class="rdm-detail-actions mb-3">
                            <button class="btn btn-sm btn-primary me-2" @click="savePrompt(item)">
                                <i class="bi bi-check2 me-1"></i>Guardar
                            </button>
                            <button
                                class="btn btn-sm btn-success"
                                @click="launchFromDetail(item)"
                                :disabled="!editPrompt.trim()"
                            >
                                <i class="bi bi-arrow-right-circle me-1"></i>Lanzar a Claude Code
                            </button>
                            <button class="btn btn-sm btn-link text-muted ms-2" @click="expandedId = null">
                                Cerrar
                            </button>
                        </div>

                    </div>
                </div>
            </div>
            </template>
        </template>

        <!-- Toast -->
        <transition name="rdm-toast-fade">
            <div v-if="toast.visible" class="rdm-toast" :class="`rdm-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>

        <!-- Modal agregar item -->
        <div v-if="showAddModal" class="rdm-modal-backdrop" @click.self="showAddModal = false">
            <div class="rdm-modal">
                <div class="rdm-modal-header">
                    <strong>Agregar item</strong>
                    <button class="btn-close" @click="showAddModal = false"></button>
                </div>
                <div class="rdm-modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Título *</label>
                        <input class="form-control" v-model="newItem.title" placeholder="Qué hay que hacer" />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Prioridad</label>
                            <select class="form-select" v-model="newItem.priority">
                                <option value="">Sin prioridad</option>
                                <option value="alta">Alta</option>
                                <option value="media">Media</option>
                                <option value="baja">Baja</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Versión objetivo</label>
                            <input class="form-control" v-model="newItem.target_version" placeholder="v1.0" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Prompt para Claude Code</label>
                        <textarea class="form-control" v-model="newItem.prompt" rows="4"
                                  placeholder="Instrucciones para retomar esta tarea..."></textarea>
                    </div>
                </div>
                <div class="rdm-modal-footer">
                    <button class="btn btn-secondary me-2" @click="showAddModal = false">Cancelar</button>
                    <button class="btn btn-primary" @click="addItem" :disabled="!newItem.title.trim() || addingItem">
                        {{ addingItem ? 'Agregando…' : 'Agregar' }}
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { darkMode } from '../../../../hook/appConfig.js';

const STATUS_ORDER = ['in_progress', 'pending', 'done', 'cancelled'];
const STATUS_LABELS = {
    in_progress: 'En progreso',
    pending:     'Pendiente',
    done:        'Hecho',
    cancelled:   'Cancelado',
};

function relativeTime(iso) {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const s = Math.floor(diff / 1000);
    if (s < 60)     return 'hace un momento';
    const m = Math.floor(s / 60);
    if (m < 60)     return `hace ${m} min`;
    const h = Math.floor(m / 60);
    if (h < 24)     return `hace ${h} h`;
    const d = Math.floor(h / 24);
    if (d < 30)     return `hace ${d} día${d > 1 ? 's' : ''}`;
    const mo = Math.floor(d / 30);
    return `hace ${mo} mes${mo > 1 ? 'es' : ''}`;
}

function fullDateTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

export default {
    name: 'RoadmapTab',
    setup() {
        const items        = ref([]);
        const loading      = ref(true);
        const expandedId   = ref(null);
        const editPrompt   = ref('');
        const activeFilter = ref('all');
        const showAddModal = ref(false);
        const addingItem   = ref(false); // #858: deshabilita el botón mientras se envía (anti doble-clic)
        const newItem      = ref({ title: '', priority: 'media', target_version: '', prompt: '' });
        const toast        = ref({ visible: false, message: '', type: 'success', icon: '', timer: null });

        const filters = [
            { key: 'all',         label: 'Todos' },
            { key: 'in_progress', label: 'En progreso' },
            { key: 'pending',     label: 'Pendientes' },
            { key: 'done',        label: 'Hechos' },
            { key: 'cancelled',   label: 'Cancelados' },
        ];

        const counts = computed(() => ({
            in_progress: items.value.filter(i => i.status === 'in_progress').length,
            pending:     items.value.filter(i => i.status === 'pending').length,
            done:        items.value.filter(i => i.status === 'done').length,
            cancelled:   items.value.filter(i => i.status === 'cancelled').length,
        }));

        const groups = computed(() =>
            STATUS_ORDER.map(key => ({
                key,
                label: STATUS_LABELS[key],
                items: items.value
                    .filter(i => i.status === key)
                    .sort((a, b) => (a.position ?? 0) - (b.position ?? 0) || a.id - b.id),
            }))
        );

        const visibleGroups = computed(() => {
            if (activeFilter.value === 'all') return groups.value;
            return groups.value.filter(g => g.key === activeFilter.value);
        });

        const hasVisibleItems = computed(() =>
            visibleGroups.value.some(g => g.items.length > 0)
        );

        // ── Sub-tareas helpers ────────────────────────────────────────────────

        function subtasksDone(item) {
            return (item.subtasks || []).filter(s => s.completed).length;
        }

        function subtasksPct(item) {
            const subs = item.subtasks || [];
            if (!subs.length) return 0;
            return Math.round(subtasksDone(item) / subs.length * 100);
        }

        function lastAdvance(item) {
            const subs = item.subtasks || [];
            const dates = subs
                .filter(s => s.completed && s.completed_at)
                .map(s => new Date(s.completed_at).getTime())
                .filter(t => !isNaN(t));
            if (!dates.length) return null;
            const ts = Math.max(...dates);
            return { rel: relativeTime(new Date(ts).toISOString()), full: fullDateTime(new Date(ts).toISOString()) };
        }

        // ── Status helpers ────────────────────────────────────────────────────

        function canLaunch(item, idx, group) {
            return item.status === 'in_progress' || item.status === 'pending';
        }

        function launchTitle(item, idx, group) {
            if (item.status === 'in_progress') return 'Copiar prompt al portapapeles';
            if (item.status === 'pending') return 'Iniciar y copiar prompt al portapapeles';
            return 'Sin acción disponible';
        }

        function statusLabel(s) { return STATUS_LABELS[s] ?? s; }

        function statusIcon(s) {
            return {
                pending:     'bi bi-circle',
                in_progress: 'bi bi-play-circle-fill',
                done:        'bi bi-check-circle-fill',
                cancelled:   'bi bi-x-circle',
            }[s] ?? 'bi bi-circle';
        }

        // ── Ciclo de estado ───────────────────────────────────────────────────

        async function cycleStatus(item) {
            if (item.status === 'done' || item.status === 'cancelled') return;
            if (item.status === 'pending')     await doStart(item);
            else if (item.status === 'in_progress') await doComplete(item);
        }

        async function doStart(item) {
            try {
                const { data } = await axios.post(`/api/roadmap/items/${item.id}/start`);
                replaceItem(data);
                showToast('Tarea iniciada', 'success', 'bi bi-play-circle-fill');
            } catch (e) {
                const msg = e.response?.data?.message ?? 'Error al iniciar la tarea.';
                showToast(msg, 'error', 'bi bi-exclamation-circle-fill');
            }
        }

        async function doComplete(item) {
            try {
                const { data } = await axios.post(`/api/roadmap/items/${item.id}/complete`);
                replaceItem(data);
                showToast('¡Tarea completada! ✓', 'success', 'bi bi-check-circle-fill');
            } catch {
                showToast('Error al completar la tarea.', 'error', 'bi bi-exclamation-circle-fill');
            }
        }

        // ── Lanzar ────────────────────────────────────────────────────────────

        async function launchItem(item, idx, group) {
            if (!canLaunch(item, idx, group)) {
                showToast('Sin acción disponible.', 'warning', 'bi bi-exclamation-triangle-fill');
                return;
            }
            const prompt = item.prompt?.trim() ?? '';
            if (item.status === 'pending') {
                await doStart(item);
                item = items.value.find(i => i.id === item.id) ?? item;
            }
            if (!prompt) {
                showToast('Este item no tiene prompt. Expándelo y escribe uno.', 'warning', 'bi bi-pencil');
                toggleExpand(item.id);
                return;
            }
            try {
                await navigator.clipboard.writeText(prompt);
                showToast('Prompt copiado, pégalo en Claude Code.', 'success', 'bi bi-clipboard-check-fill');
            } catch {
                // Fallback para HTTP sin SSL
                const ta = document.createElement('textarea');
                ta.value = prompt;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast('Prompt copiado, pégalo en Claude Code.', 'success', 'bi bi-clipboard-check-fill');
            }
        }

        // ── Expandir / colapsar ───────────────────────────────────────────────

        function toggleExpand(id) {
            if (expandedId.value === id) {
                expandedId.value = null;
            } else {
                expandedId.value = id;
                const item = items.value.find(i => i.id === id);
                editPrompt.value = item?.prompt ?? '';
            }
        }

        // ── Prompt ────────────────────────────────────────────────────────────

        async function savePrompt(item) {
            try {
                const { data } = await axios.patch(`/api/roadmap/items/${item.id}`, { prompt: editPrompt.value });
                replaceItem(data);
                showToast('Prompt guardado.', 'success', 'bi bi-check2');
            } catch {
                showToast('Error al guardar.', 'error', 'bi bi-exclamation-circle-fill');
            }
        }

        async function launchFromDetail(item) {
            const prompt = editPrompt.value.trim();
            if (!prompt) return;
            const current = items.value.find(i => i.id === item.id);
            if (current?.prompt !== prompt) await savePrompt(item);
            try {
                await navigator.clipboard.writeText(prompt);
                showToast('Prompt copiado, pégalo en Claude Code.', 'success', 'bi bi-clipboard-check-fill');
            } catch {
                // Fallback para HTTP sin SSL
                const ta = document.createElement('textarea');
                ta.value = prompt;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast('Prompt copiado, pégalo en Claude Code.', 'success', 'bi bi-clipboard-check-fill');
            }
        }

        // ── Agregar item ──────────────────────────────────────────────────────

        // #858: clave de idempotencia por intento — un doble clic (mismo intento) reenvía la
        // misma clave y el backend devuelve el item ya creado en vez de duplicarlo.
        function idemKey() {
            if (window.crypto?.randomUUID) return window.crypto.randomUUID();
            return 'idem-' + Date.now() + '-' + Math.random().toString(36).slice(2);
        }

        async function addItem() {
            if (!newItem.value.title.trim() || addingItem.value) return;
            addingItem.value = true;
            try {
                const payload = {
                    title:            newItem.value.title.trim(),
                    priority:         newItem.value.priority || null,
                    target_version:   newItem.value.target_version || null,
                    prompt:           newItem.value.prompt || null,
                    idempotency_key:  idemKey(),
                };
                const { data } = await axios.post('/api/roadmap/items', payload);
                if (!data.idempotente) items.value.push(data.item);
                newItem.value = { title: '', priority: 'media', target_version: '', prompt: '' };
                showAddModal.value = false;
                showToast(data.aviso || 'Item agregado.', 'success', 'bi bi-plus-circle-fill');

                // #861: si entró directo a la cola (aprobado_irving), el despacho a una
                // terminal es ASÍNCRONO (scheduler/picker, no el mismo request) — sondea un
                // ratito el desenlace real y actualiza el toast en vez de dejar el aviso
                // aspiracional como única palabra final.
                if (data.item.estado_aprobacion === 'aprobado_irving') {
                    pollDispatch(data.item.id);
                }
            } catch {
                showToast('Error al agregar el item.', 'error', 'bi bi-exclamation-circle-fill');
            } finally {
                addingItem.value = false;
            }
        }

        // ── Sondeo del desenlace de despacho (#861) ─────────────────────────────
        // Poll corto (cada 2s, ~14s en total) de GET /api/roadmap/items/{id}: si aparece
        // worker_sid, una terminal ya lo tomó -> toast "lanzado a wt-N"; si el tiempo se
        // agota sin worker_sid, sigue en cola por falta de terminal libre. Solo lectura del
        // estado existente, no dispara ningún despacho nuevo.
        async function pollDispatch(itemId) {
            const maxAttempts = 7;
            const intervalMs  = 2000;
            for (let attempt = 0; attempt < maxAttempts; attempt++) {
                await new Promise(resolve => setTimeout(resolve, intervalMs));
                try {
                    const { data } = await axios.get(`/api/roadmap/items/${itemId}`);
                    replaceItem(data);
                    if (data.worker_sid) {
                        showToast(`Item #${itemId} lanzado a ${data.worker_sid}.`, 'success', 'bi bi-rocket-takeoff-fill');
                        return;
                    }
                } catch {
                    return; // item borrado/archivado entre sondeos; no insistir
                }
            }
            showToast(`Item #${itemId}: en cola, sin terminal libre por ahora.`, 'success', 'bi bi-hourglass-split');
        }

        // ── Utilidades ────────────────────────────────────────────────────────

        function replaceItem(updated) {
            const idx = items.value.findIndex(i => i.id === updated.id);
            if (idx !== -1) items.value[idx] = updated;
        }

        function showToast(message, type = 'success', icon = 'bi bi-check2') {
            if (toast.value.timer) clearTimeout(toast.value.timer);
            toast.value = { visible: true, message, type, icon, timer: null };
            toast.value.timer = setTimeout(() => { toast.value.visible = false; }, 3500);
        }

        async function load() {
            loading.value = true;
            try {
                // Pipeline por estado: la Hoja de ruta muestra SOLO el backlog (lo tomado/mergeado
                // vive en Terminales/Integración). Un item tomado DESAPARECE de aquí.
                const { data } = await axios.get('/api/roadmap/items?vista=backlog');
                items.value = data;
            } catch {
                showToast('Error al cargar la hoja de ruta.', 'error', 'bi bi-exclamation-circle-fill');
            } finally {
                loading.value = false;
            }
        }

        onMounted(load);

        return {
            darkMode, items, loading, expandedId, editPrompt,
            activeFilter, filters, counts, groups, visibleGroups, hasVisibleItems,
            showAddModal, addingItem, newItem, toast,
            subtasksDone, subtasksPct, lastAdvance, relativeTime, fullDateTime,
            canLaunch, launchTitle, statusLabel, statusIcon,
            cycleStatus, launchItem, toggleExpand, savePrompt, launchFromDetail,
            addItem,
        };
    },
};
</script>

<style scoped>
/* ── Base ── */
.rdm-wrap { padding: 24px 0; font-size: 14px; }

/* ── KPIs ── */
.rdm-kpis { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.rdm-kpi {
    flex: 1; min-width: 100px; padding: 12px 16px; border-radius: 10px;
    display: flex; flex-direction: column; align-items: center; gap: 2px;
    border: 1px solid transparent;
}
.rdm-kpi-num   { font-size: 26px; font-weight: 700; line-height: 1; }
.rdm-kpi-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; opacity: 0.7; }

.rdm-kpi-progress  { background: #fff7ed; border-color: #fed7aa; }
.rdm-kpi-progress .rdm-kpi-num  { color: #ea580c; }
.rdm-kpi-pending   { background: #f0f9ff; border-color: #bae6fd; }
.rdm-kpi-pending .rdm-kpi-num   { color: #0284c7; }
.rdm-kpi-done      { background: #f0fdf4; border-color: #bbf7d0; }
.rdm-kpi-done .rdm-kpi-num      { color: #16a34a; }
.rdm-kpi-cancelled { background: #fafafa; border-color: #e5e7eb; }
.rdm-kpi-cancelled .rdm-kpi-num { color: #6b7280; }

.rdm-dark .rdm-kpi-progress  { background: rgba(234,88,12,.1);  border-color: rgba(234,88,12,.25); }
.rdm-dark .rdm-kpi-pending   { background: rgba(2,132,199,.1);  border-color: rgba(2,132,199,.25); }
.rdm-dark .rdm-kpi-done      { background: rgba(22,163,74,.1);  border-color: rgba(22,163,74,.25); }
.rdm-dark .rdm-kpi-cancelled { background: rgba(107,114,128,.1);border-color: rgba(107,114,128,.2);}

/* ── Filtros ── */
.rdm-filters { display: flex; gap: 8px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
.rdm-filter-btn {
    padding: 5px 14px; border-radius: 20px; border: 1px solid #d1d5db;
    background: transparent; font-size: 12px; cursor: pointer; transition: all .15s;
    color: inherit;
}
.rdm-filter-btn.active,
.rdm-filter-btn:hover { background: #3b82f6; border-color: #3b82f6; color: #fff; }
.rdm-dark .rdm-filter-btn { border-color: rgba(255,255,255,.15); }

.rdm-add-btn {
    margin-left: auto; padding: 6px 16px; border-radius: 20px;
    background: #16a34a; border: none; color: #fff; font-size: 13px;
    cursor: pointer; font-weight: 600; transition: background .15s;
}
.rdm-add-btn:hover { background: #15803d; }

/* ── Grupos ── */
.rdm-group { margin-bottom: 24px; }
.rdm-group-header {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.7px; color: #6b7280; margin-bottom: 8px;
}
.rdm-dark .rdm-group-header { color: rgba(255,255,255,.45); }

.rdm-group-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot-in_progress { background: #ea580c; }
.dot-pending     { background: #0284c7; }
.dot-done        { background: #16a34a; }
.dot-cancelled   { background: #9ca3af; }

/* ── Items ── */
.rdm-item {
    border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 6px;
    overflow: hidden; background: #fff; transition: box-shadow .15s;
}
.rdm-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,.07); }
.rdm-dark .rdm-item { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.09); }

.rdm-item-inprogress {
    border-color: #fed7aa;
    background: linear-gradient(135deg, #fff7ed 0%, #fff 100%);
}
.rdm-dark .rdm-item-inprogress {
    background: rgba(234,88,12,.07);
    border-color: rgba(234,88,12,.3);
}
.rdm-item-done      { opacity: .65; }
.rdm-item-cancelled { opacity: .45; }

.rdm-item-row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; }

/* Círculo de estado */
.rdm-status-btn {
    flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%;
    border: 2px solid #d1d5db; background: transparent;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all .15s; font-size: 14px; padding: 0;
    color: #9ca3af;
}
.rdm-status-btn.status-in_progress { border-color: #ea580c; color: #ea580c; }
.rdm-status-btn.status-done        { border-color: #16a34a; color: #16a34a; cursor: default; }
.rdm-status-btn.status-cancelled   { border-color: #9ca3af; color: #9ca3af; cursor: default; }
.rdm-status-btn:not(:disabled):hover { transform: scale(1.1); }

/* Título */
.rdm-item-title {
    flex: 1; font-size: 14px; cursor: pointer; display: flex;
    align-items: center; gap: 6px; flex-wrap: wrap; font-weight: 500;
}
.rdm-item-title:hover { color: #3b82f6; }

/* Tags */
.rdm-tag {
    font-size: 10px; font-weight: 700; padding: 2px 7px;
    border-radius: 10px; text-transform: uppercase; letter-spacing: 0.4px; flex-shrink: 0;
}
/* Prioridad — fondos saturados + texto de alto contraste (light) */
.rdm-tag-prio.prio-alta   { background: #dc2626; color: #ffffff; }
.rdm-tag-prio.prio-media  { background: #f59e0b; color: #422006; }
.rdm-tag-prio.prio-baja   { background: #d1d5db; color: #374151; }
.rdm-tag-prio.prio-none   { background: transparent; color: #9ca3af; border: 1px dashed #cbd5e1; font-weight: 600; }
/* Prioridad — modo oscuro (fondos igual de saturados para que salten) */
.rdm-dark .rdm-tag-prio.prio-alta  { background: #ef4444; color: #ffffff; }
.rdm-dark .rdm-tag-prio.prio-media { background: #f59e0b; color: #3a1d00; }
.rdm-dark .rdm-tag-prio.prio-baja  { background: rgba(255,255,255,.16); color: rgba(255,255,255,.82); }
.rdm-dark .rdm-tag-prio.prio-none  { background: transparent; color: rgba(255,255,255,.4); border-color: rgba(255,255,255,.2); }
.rdm-tag-ver              { background: #f3f4f6; color: #4b5563; }
.rdm-dark .rdm-tag-ver    { background: rgba(255,255,255,.1); color: rgba(255,255,255,.5); }
/* Último avance — discreto, sin color de fondo */
.rdm-tag-age {
    background: transparent; color: #9ca3af; font-weight: 400;
    font-size: 11px; letter-spacing: 0; text-transform: none; padding: 0;
}
.rdm-dark .rdm-tag-age { color: rgba(255,255,255,.3); }
/* ETA de Thomas (#480) — discreto como .rdm-tag-age, con acento propio */
.rdm-tag-eta {
    background: transparent; color: #0284c7; font-weight: 600;
    font-size: 11px; letter-spacing: 0; text-transform: none; padding: 0;
}
.rdm-dark .rdm-tag-eta { color: #38bdf8; }

/* Botón lanzar */
.rdm-launch-btn {
    flex-shrink: 0; background: transparent; border: none; cursor: pointer;
    font-size: 20px; color: #d1d5db; padding: 0; transition: color .15s, transform .15s;
}
.rdm-launch-btn:hover { transform: scale(1.15); }
.rdm-launch-active { color: #16a34a !important; }
.rdm-launch-active:hover { color: #15803d !important; }

/* ── Barra de progreso ── */
.rdm-progress-wrap {
    height: 5px; background: #e5e7eb; margin: 0 12px 10px; border-radius: 3px; overflow: hidden;
}
.rdm-dark .rdm-progress-wrap { background: rgba(255,255,255,.1); }
.rdm-progress-bar {
    height: 100%; background: #3b82f6; border-radius: 3px; transition: width .3s ease;
}
.rdm-progress-bar.rdm-progress-full { background: #16a34a; }

/* ── Detalle expandible ── */
.rdm-detail { padding: 0 14px 14px; border-top: 1px solid #f3f4f6; }
.rdm-dark .rdm-detail { border-top-color: rgba(255,255,255,.07); }

.rdm-section-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
    color: #6b7280; margin: 14px 0 6px;
    display: flex; align-items: center; gap: 8px;
}
.rdm-dark .rdm-section-label { color: rgba(255,255,255,.35); }

.rdm-prompt-input {
    width: 100%; margin-top: 6px; border-radius: 6px; font-size: 13px;
    font-family: 'Courier New', monospace; resize: vertical;
    border: 1px solid #d1d5db; padding: 8px 10px; background: #f9fafb; color: inherit;
}
.rdm-dark .rdm-prompt-input { background: rgba(0,0,0,.2); border-color: rgba(255,255,255,.12); color: #e5e7eb; }
.rdm-detail-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-top: 8px; }

/* Empty state */
.rdm-empty  { font-size: 13px; color: #9ca3af; padding: 8px 4px; }
.rdm-empty-global { text-align: center; padding: 32px 4px; }
.rdm-loading { padding: 24px; color: #6b7280; font-size: 13px; }

/* Toast */
.rdm-toast {
    position: fixed; bottom: 28px; right: 28px; z-index: 9999;
    padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
    box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center;
    max-width: 360px;
}
.rdm-toast-success { background: #16a34a; color: #fff; }
.rdm-toast-error   { background: #dc2626; color: #fff; }
.rdm-toast-warning { background: #d97706; color: #fff; }
.rdm-toast-fade-enter-active, .rdm-toast-fade-leave-active { transition: all .25s; }
.rdm-toast-fade-enter-from, .rdm-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }

/* Modal */
.rdm-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1050;
    display: flex; align-items: center; justify-content: center; padding: 16px;
}
.rdm-modal {
    background: #fff; border-radius: 12px; width: 100%; max-width: 540px;
    box-shadow: 0 8px 32px rgba(0,0,0,.18); overflow: hidden;
}
.rdm-dark .rdm-modal { background: #1e1e2e; color: #e5e7eb; }
.rdm-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid #e5e7eb;
}
.rdm-dark .rdm-modal-header { border-bottom-color: rgba(255,255,255,.1); }
.rdm-modal-body   { padding: 20px; }
.rdm-modal-footer { padding: 14px 20px; border-top: 1px solid #e5e7eb; text-align: right; }
.rdm-dark .rdm-modal-footer { border-top-color: rgba(255,255,255,.1); }

/* ── Clases de estructura añadidas para responsive ───────────────────────────
   rdm-filter-row : wrapper de botones de filtro (display:contents en desktop,
                    scroll horizontal en móvil).
   rdm-item-text  : span alrededor del texto del título.
   rdm-item-tags  : div agrupador de los tags de un item.
   Ver patrón completo en /docs/patron-responsive-medussa.md
────────────────────────────────────────────────────────────────────────────── */

/* Desktop/default: rdm-filter-row es transparente al flexbox padre */
.rdm-filter-row { display: contents; }

/* rdm-item-title mantiene flex-row; id, text y tags son sus hijos */
.rdm-item-id {
    font-family: monospace; font-size: 12px; color: #9ca3af;
    margin-right: 6px; flex-shrink: 0;
}
.rdm-dark .rdm-item-id { color: rgba(255,255,255,.35); }
.rdm-item-text { flex: 1; min-width: 0; line-height: 1.4; }
.rdm-item-tags {
    display: flex; flex-wrap: wrap; gap: 4px; align-items: center; flex-shrink: 0;
}

/* add-btn: en desktop sigue usando margin-left:auto para pegarse a la derecha */
.rdm-add-btn { margin-left: auto; }

/* ══════════════════════════════════════════════════════════════════════════════
   RESPONSIVE — Hoja de ruta (molde para el resto del sistema)
   Breakpoints:  Móvil < 640px  |  Tablet 640–1023px  |  Desktop ≥ 1024px
   Regla: solo layout/tamaño. Sin colores hardcodeados.
   Los colores/tema los gestiona el mecanismo data-layout-mode / darkMode.
══════════════════════════════════════════════════════════════════════════════ */

/* ── Tablet (640–1023 px) ─────────────────────────────────────────────────── */
@media (min-width: 640px) and (max-width: 1023px) {
    /* KPIs: 4 en fila → 2 columnas */
    .rdm-kpis {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .rdm-kpi { flex: unset; }

    /* Algo menos de padding exterior */
    .rdm-wrap { padding: 20px 0; }
}

/* ── Móvil (< 640 px) ────────────────────────────────────────────────────── */
@media (max-width: 639px) {
    /* ── Contenedor base ── */
    .rdm-wrap { padding: 12px 0; font-size: 13px; }

    /* ── KPIs: grid 2×2 ── */
    .rdm-kpis { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .rdm-kpi  { flex: unset; padding: 10px 12px; min-width: 0; }
    .rdm-kpi-num   { font-size: 22px; }
    .rdm-kpi-label { font-size: 10px; }

    /* ── Filtros: scroll horizontal + botón en fila propia ── */
    .rdm-filters {
        flex-direction: column;
        gap: 8px;
        align-items: stretch;
        flex-wrap: nowrap;
    }
    .rdm-filter-row {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;        /* Firefox */
        padding-bottom: 2px;          /* margen para outline del botón activo */
    }
    .rdm-filter-row::-webkit-scrollbar { display: none; } /* Chrome/Safari */
    .rdm-filter-btn { flex-shrink: 0; white-space: nowrap; }
    .rdm-add-btn {
        margin-left: 0;
        width: 100%;
        border-radius: 8px;
        text-align: center;
        padding: 10px 16px;
        font-size: 14px;
    }

    /* ── Fila del item: spacing ajustado ── */
    .rdm-item-row { padding: 10px; gap: 8px; }

    /* ── Círculo de estado: tap target real ≥ 44×44 px (guía del patrón) ── */
    .rdm-status-btn {
        width: 44px; height: 44px; font-size: 18px;
    }

    /* ── Botón lanzar: tap target ≥ 44×44 px ── */
    .rdm-launch-btn {
        font-size: 22px;
        min-width: 44px; min-height: 44px;
        display: flex; align-items: center; justify-content: center;
    }

    /* ── Título: texto en fila 1, tags en fila 2 ── */
    .rdm-item-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    .rdm-item-text { flex: unset; }
    .rdm-item-tags { flex-shrink: unset; }

    /* ── Detalle expandible ── */
    .rdm-detail { padding: 0 10px 12px; }

    /* Prompt: altura mínima para escritura cómoda */
    .rdm-prompt-input { min-height: 120px; }

    /* Botones de acción: apilados verticalmente, ancho completo */
    .rdm-detail-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    .rdm-detail-actions .btn {
        width: 100%;
        padding: 10px 16px;
        font-size: 14px;
        margin: 0 !important; /* quitar ms-2 de Bootstrap */
    }

    /* ── Toast: se estira al ancho de pantalla ── */
    .rdm-toast {
        left: 12px; right: 12px; bottom: 16px;
        max-width: unset;
    }

    /* ── Modal: scrollable en móvil ── */
    .rdm-modal { max-height: 90vh; overflow-y: auto; }
    .rdm-modal-body { padding: 16px; }

    /* Bootstrap col-6 dentro del modal → col-12 en móvil */
    .rdm-modal-body .col-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}
</style>
