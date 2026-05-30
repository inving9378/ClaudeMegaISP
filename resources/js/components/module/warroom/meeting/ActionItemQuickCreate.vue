<template>
    <q-dialog v-model="open" @keydown.esc="open = false">
        <q-card class="wr-quick-card">

            <q-card-section class="wr-quick-header">
                <div class="wr-quick-title">
                    <i class="ti ti-checkbox me-2"></i>
                    Nueva tarea
                    <span class="wr-quick-section-badge">{{ sectionLabel }}</span>
                </div>
                <q-btn flat dense icon="ti ti-x" @click="open = false" class="wr-setup-close" />
            </q-card-section>

            <q-card-section class="wr-quick-body">

                <!-- Descripción -->
                <div class="wr-quick-field">
                    <label class="wr-setup-label">Descripción <span class="wr-quick-req">*</span></label>
                    <textarea
                        ref="descRef"
                        v-model="form.description"
                        class="wr-quick-textarea"
                        placeholder="¿Qué hay que hacer?"
                        maxlength="500"
                        rows="3"
                        @keydown.ctrl.enter="submit"
                    ></textarea>
                    <span class="wr-quick-chars">{{ form.description.length }}/500</span>
                </div>

                <!-- Asignar a + Deadline -->
                <div class="wr-quick-row">
                    <div class="wr-quick-field">
                        <label class="wr-setup-label">Asignar a</label>
                        <select v-model="form.assignee_user_id" class="wr-setup-select">
                            <option :value="null">— Sin asignar —</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div class="wr-quick-field">
                        <label class="wr-setup-label">Deadline</label>
                        <input v-model="form.deadline" type="date" class="wr-setup-input" />
                    </div>
                </div>

                <!-- Prioridad -->
                <div class="wr-quick-field">
                    <label class="wr-setup-label">Prioridad</label>
                    <div class="wr-priority-grid">
                        <button
                            v-for="p in PRIORITIES"
                            :key="p.value"
                            type="button"
                            class="wr-priority-btn"
                            :class="{ active: form.priority === p.value, [`prio-${p.value}`]: true }"
                            @click="form.priority = p.value"
                        >{{ p.label }}</button>
                    </div>
                </div>

                <!-- Etiqueta opcional -->
                <div class="wr-quick-field">
                    <label class="wr-setup-label">Etiqueta <span class="wr-quick-opt">(opcional)</span></label>
                    <input v-model="form.priority_label" class="wr-setup-input" type="text"
                        maxlength="50" placeholder="ej: Esta semana, Quick win" />
                </div>

            </q-card-section>

            <q-card-actions class="wr-setup-footer">
                <span class="wr-quick-hint">Ctrl+Enter para guardar rápido</span>
                <q-btn flat label="Cancelar" @click="open = false" class="wr-setup-btn-cancel" />
                <q-btn
                    unelevated
                    label="Crear tarea"
                    icon="ti ti-plus"
                    :loading="loading"
                    :disable="!form.description.trim()"
                    @click="submit"
                    class="wr-setup-btn-start"
                />
            </q-card-actions>

        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue:     { type: Boolean, default: false },
    meeting:        { type: Object,  required: true },
    currentSection: { type: Object,  default: null },
    users:          { type: Array,   default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'created']);

const open = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
});

const PRIORITIES = [
    { value: 'critico',     label: '🔴 Crítico' },
    { value: 'alto',        label: '🟠 Alto' },
    { value: 'medio',       label: '🟡 Medio' },
    { value: 'oportunidad', label: '🟢 Oportunidad' },
    { value: 'estrategico', label: '🔵 Estratégico' },
];

const SECTION_LABELS = {
    resumen:     'Resumen ejecutivo',
    finanzas:    'Finanzas',
    operaciones: 'Operaciones',
    ventas:      'Ventas',
    red:         'Red',
    marketing:   'Marketing',
};

const loading  = ref(false);
const descRef  = ref(null);

function nextFriday() {
    const d = new Date();
    const day = d.getDay(); // 0=dom, 5=vie
    const diff = (5 - day + 7) % 7 || 7;
    d.setDate(d.getDate() + diff);
    return d.toISOString().slice(0, 10);
}

const form = ref({
    description:      '',
    assignee_user_id: props.currentSection?.presenter_user_id ?? null,
    priority:         'medio',
    priority_label:   '',
    deadline:         nextFriday(),
});

const sectionLabel = computed(() =>
    SECTION_LABELS[props.currentSection?.section_key] ?? 'Sección actual'
);

watch(open, async v => {
    if (v) {
        form.value.description      = '';
        form.value.priority         = 'medio';
        form.value.priority_label   = '';
        form.value.deadline         = nextFriday();
        form.value.assignee_user_id = props.currentSection?.presenter_user_id ?? null;
        await nextTick();
        descRef.value?.focus();
    }
});

async function submit() {
    if (!form.value.description.trim() || loading.value) return;
    loading.value = true;
    try {
        const { data } = await axios.post('/warroom/api/action-items', {
            meeting_id:       props.meeting.id,
            section_key:      props.currentSection?.section_key ?? 'resumen',
            description:      form.value.description.trim(),
            assignee_user_id: form.value.assignee_user_id,
            priority:         form.value.priority,
            priority_label:   form.value.priority_label || null,
            deadline:         form.value.deadline || null,
        });
        emit('created', data);
        open.value = false;
    } catch (err) {
        console.error('Error al crear tarea:', err);
    } finally {
        loading.value = false;
    }
}
</script>

<style>
.wr-quick-card {
    background: #0f0f1a;
    color: #e8e8f0;
    border: 1px solid rgba(255,255,255,0.08);
    width: 480px;
    max-width: 96vw;
    border-radius: 10px;
}

.wr-quick-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 12px 16px;
}

.wr-quick-title {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 6px;
}

.wr-quick-section-badge {
    font-size: 10px;
    font-weight: 400;
    background: rgba(83,74,183,0.25);
    border: 1px solid rgba(83,74,183,0.5);
    color: #a89ff5;
    border-radius: 10px;
    padding: 1px 8px;
}

.wr-quick-body { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
.wr-quick-field { display: flex; flex-direction: column; gap: 4px; }
.wr-quick-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.wr-quick-req { color: #e05252; }
.wr-quick-opt { color: #6b6b85; font-size: 10px; }
.wr-quick-hint { font-size: 10px; color: #6b6b85; margin-right: auto; }

.wr-quick-textarea {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #e8e8f0;
    font-size: 13px;
    padding: 8px 10px;
    width: 100%;
    resize: vertical;
    outline: none;
    font-family: inherit;
}
.wr-quick-textarea:focus { border-color: #534AB7; }

.wr-quick-chars { font-size: 10px; color: #6b6b85; text-align: right; }

/* Prioridades */
.wr-priority-grid { display: flex; gap: 6px; flex-wrap: wrap; }
.wr-priority-btn {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #9999b0;
    font-size: 11px;
    padding: 4px 10px;
    cursor: pointer;
    transition: all 0.12s;
}
.wr-priority-btn:hover { background: rgba(255,255,255,0.1); }
.wr-priority-btn.active.prio-critico     { background: rgba(224,82,82,0.2);   border-color: #e05252;  color: #ff8a8a; }
.wr-priority-btn.active.prio-alto        { background: rgba(186,117,23,0.2);  border-color: #EF9F27;  color: #f5c06a; }
.wr-priority-btn.active.prio-medio       { background: rgba(239,159,39,0.15); border-color: #EF9F27;  color: #d4b475; }
.wr-priority-btn.active.prio-oportunidad { background: rgba(29,158,117,0.2);  border-color: #1D9E75;  color: #5ddbb8; }
.wr-priority-btn.active.prio-estrategico { background: rgba(24,95,165,0.2);   border-color: #185FA5;  color: #6ab0f5; }
</style>
