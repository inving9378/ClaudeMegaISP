<template>
    <q-dialog v-model="open" persistent maximized-mobile>
        <q-card class="wr-setup-card">

            <!-- Header -->
            <q-card-section class="wr-setup-header">
                <div class="wr-setup-title">
                    <i class="ti ti-broadcast me-2"></i>
                    Iniciar junta
                </div>
                <q-btn flat dense icon="ti ti-x" @click="$emit('cancel')" class="wr-setup-close" />
            </q-card-section>

            <q-card-section class="wr-setup-body">
                <div class="wr-setup-grid">

                    <!-- ── Col izquierda ─────────────────────────────────── -->
                    <div class="wr-setup-col">

                        <!-- Sección 1: Datos generales -->
                        <div class="wr-setup-section">
                            <div class="wr-setup-section-title">
                                <i class="ti ti-info-circle me-1"></i>Datos generales
                            </div>
                            <div class="wr-setup-field">
                                <label class="wr-setup-label">Nombre de la junta</label>
                                <input v-model="form.name" class="wr-setup-input" type="text" maxlength="120" />
                            </div>
                            <div class="wr-setup-field-row">
                                <div class="wr-setup-field">
                                    <label class="wr-setup-label">Fecha y hora</label>
                                    <input v-model="form.scheduled_at" class="wr-setup-input" type="datetime-local" />
                                </div>
                                <div class="wr-setup-field">
                                    <label class="wr-setup-label">Duración estimada</label>
                                    <div class="wr-setup-duration">{{ totalMinutes }} min</div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 4: Configuración -->
                        <div class="wr-setup-section">
                            <div class="wr-setup-section-title">
                                <i class="ti ti-settings me-1"></i>Configuración
                            </div>
                            <div class="wr-setup-toggle-row">
                                <label class="wr-setup-toggle-label">Sugerencias IA del moderador</label>
                                <q-toggle v-model="form.settings.ai_suggestions" color="purple" dense />
                            </div>
                            <div class="wr-setup-toggle-row">
                                <label class="wr-setup-toggle-label">Sonidos de alerta</label>
                                <q-toggle v-model="form.settings.sound_alerts" color="amber" dense />
                            </div>
                            <div class="wr-setup-toggle-row">
                                <label class="wr-setup-toggle-label">Enviar minutas por WhatsApp al cerrar</label>
                                <q-toggle v-model="form.settings.send_minutes_whatsapp" color="green" dense />
                            </div>
                        </div>

                    </div>

                    <!-- ── Col derecha ───────────────────────────────────── -->
                    <div class="wr-setup-col">

                        <!-- Sección 2: Asistentes -->
                        <div class="wr-setup-section">
                            <div class="wr-setup-section-title">
                                <i class="ti ti-users me-1"></i>Asistentes ({{ presentCount }}/{{ users.length }})
                            </div>
                            <div v-if="loadingUsers" class="wr-setup-loading">Cargando...</div>
                            <div v-else class="wr-attendee-list">
                                <div v-for="u in users" :key="u.id" class="wr-attendee-row">
                                    <div class="wr-attendee-info">
                                        <div class="wr-attendee-avatar">{{ u.name[0] }}</div>
                                        <span class="wr-attendee-name">{{ u.name }}</span>
                                    </div>
                                    <q-toggle
                                        v-model="form.attendees[u.id]"
                                        color="green"
                                        dense
                                    />
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- ── Sección 3: Agenda editable (full width) ─────────── -->
                <div class="wr-setup-section mt-3">
                    <div class="wr-setup-section-title">
                        <i class="ti ti-list-check me-1"></i>Agenda
                        <span class="wr-setup-section-hint">{{ activeSections }} secciones activas</span>
                    </div>
                    <div class="wr-agenda-table">
                        <div class="wr-agenda-header">
                            <span>Sección</span>
                            <span>Presentador</span>
                            <span>Minutos</span>
                            <span>Incluir</span>
                        </div>
                        <div
                            v-for="(sec, idx) in form.sections"
                            :key="sec.key"
                            class="wr-agenda-row"
                            :class="{ 'wr-agenda-row-disabled': !sec.active }"
                        >
                            <div class="wr-agenda-name">
                                <i :class="`ti ${sec.icon} me-1`" :style="{ color: sec.color }"></i>
                                {{ sec.label }}
                            </div>
                            <select v-model="sec.presenter_id" class="wr-setup-select" :disabled="!sec.active">
                                <option :value="null">— Sin asignar —</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                            <input
                                v-model.number="sec.time_minutes"
                                type="number" min="1" max="60"
                                class="wr-setup-input-sm"
                                :disabled="!sec.active"
                            />
                            <q-toggle v-model="sec.active" color="purple" dense />
                        </div>
                    </div>
                </div>

            </q-card-section>

            <!-- Footer -->
            <q-card-actions class="wr-setup-footer">
                <q-btn flat label="Cancelar" @click="$emit('cancel')" class="wr-setup-btn-cancel" />
                <q-btn
                    unelevated
                    label="Iniciar junta"
                    icon="ti ti-broadcast"
                    :loading="loading"
                    :disable="activeSections === 0"
                    @click="submit"
                    class="wr-setup-btn-start"
                />
            </q-card-actions>

        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'started', 'cancel']);

const open = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
});

// ── Estado ───────────────────────────────────────────────────────────────────
const users       = ref([]);
const loadingUsers = ref(false);
const loading     = ref(false);

const now = new Date();
const defaultScheduled = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
    .toISOString().slice(0, 16);

const SECTION_META = {
    resumen:     { label: 'Resumen ejecutivo',     icon: 'ti-target-arrow',    color: '#534AB7' },
    finanzas:    { label: 'Finanzas',              icon: 'ti-coin',            color: '#1D9E75' },
    operaciones: { label: 'Operaciones',           icon: 'ti-tools',           color: '#BA7517' },
    ventas:      { label: 'Ventas y Embajadores',  icon: 'ti-trending-up',     color: '#185FA5' },
    red:         { label: 'Red e Infraestructura', icon: 'ti-network',         color: '#1D9E75' },
    marketing:   { label: 'Marketing',             icon: 'ti-brand-whatsapp',  color: '#D4537E' },
    talento:     { label: 'Talento',               icon: 'ti-users',           color: '#EF9F27' },
};

const DEFAULT_TIMES = { resumen: 7, finanzas: 10, operaciones: 12, ventas: 10, red: 8, marketing: 5, talento: 8 };

const form = ref({
    name:         '',
    scheduled_at: defaultScheduled,
    attendees:    {},
    sections: Object.entries(SECTION_META).map(([key, meta]) => ({
        key,
        ...meta,
        time_minutes: DEFAULT_TIMES[key] ?? 5,
        presenter_id: null,
        active: true,
    })),
    settings: {
        ai_suggestions:        true,
        sound_alerts:          false,
        send_minutes_whatsapp: true,
    },
});

// ── Computeds ────────────────────────────────────────────────────────────────
const activeSections = computed(() => form.value.sections.filter(s => s.active).length);
const totalMinutes   = computed(() => form.value.sections.filter(s => s.active).reduce((s, sec) => s + (sec.time_minutes || 0), 0));
const presentCount   = computed(() => Object.values(form.value.attendees).filter(Boolean).length);

// ── Carga de datos ───────────────────────────────────────────────────────────
async function loadUsers() {
    loadingUsers.value = true;
    try {
        const { data } = await axios.get('/warroom/api/users');
        users.value = data;
        const attendees = {};
        data.forEach(u => { attendees[u.id] = true; });
        form.value.attendees = attendees;
    } catch {
        // fallback silencioso
    } finally {
        loadingUsers.value = false;
    }
}

// Regenerar nombre de junta con fecha al abrir
watch(() => props.modelValue, v => {
    if (v) {
        const d = new Date();
        form.value.name = `Junta operativa ${d.getDate()}/${d.getMonth() + 1}`;
        if (!users.value.length) loadUsers();
    }
});

onMounted(() => {
    const d = new Date();
    form.value.name = `Junta operativa ${d.getDate()}/${d.getMonth() + 1}`;
});

// ── Submit ───────────────────────────────────────────────────────────────────
async function submit() {
    loading.value = true;
    try {
        const payload = {
            name:         form.value.name,
            scheduled_at: form.value.scheduled_at,
            settings:     form.value.settings,
            attendee_ids: Object.entries(form.value.attendees)
                .filter(([, present]) => present)
                .map(([id]) => parseInt(id)),
            sections: form.value.sections
                .filter(s => s.active)
                .map((s, i) => ({
                    key:          s.key,
                    time_minutes: s.time_minutes,
                    presenter_id: s.presenter_id,
                    order:        i + 1,
                })),
        };
        const { data } = await axios.post('/warroom/api/meetings/start', payload);
        emit('started', data);
        open.value = false;
    } catch (err) {
        console.error('Error al iniciar junta:', err);
    } finally {
        loading.value = false;
    }
}
</script>

<style>
/* ── Setup dialog ─────────────────────────────────────────────────────────── */
.wr-setup-card {
    background: #0f0f1a;
    color: #e8e8f0;
    border: 1px solid rgba(255,255,255,0.08);
    max-width: 860px;
    width: 100%;
    border-radius: 10px;
}

.wr-setup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 14px 20px;
}

.wr-setup-title {
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
}

.wr-setup-close { color: #9999b0; }

.wr-setup-body {
    padding: 16px 20px;
    max-height: calc(100vh - 160px);
    overflow-y: auto;
}

.wr-setup-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.wr-setup-section {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
}

.wr-setup-section-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #9999b0;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.wr-setup-section-hint {
    margin-left: auto;
    font-size: 10px;
    color: #534AB7;
    text-transform: none;
    letter-spacing: 0;
}

.wr-setup-field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.wr-setup-field-row { display: flex; gap: 10px; }
.wr-setup-field-row .wr-setup-field { flex: 1; }
.wr-setup-label { font-size: 11px; color: #9999b0; }

.wr-setup-input {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #e8e8f0;
    font-size: 13px;
    padding: 6px 10px;
    width: 100%;
    outline: none;
}

.wr-setup-input:focus { border-color: #534AB7; }
.wr-setup-input-sm {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #e8e8f0;
    font-size: 12px;
    padding: 4px 8px;
    width: 60px;
    text-align: center;
    outline: none;
}

.wr-setup-select {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #e8e8f0;
    font-size: 12px;
    padding: 4px 6px;
    flex: 1;
    outline: none;
}

.wr-setup-duration {
    font-size: 20px;
    font-weight: 600;
    color: #534AB7;
    padding-top: 4px;
}

/* Toggles */
.wr-setup-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.wr-setup-toggle-row:last-child { border-bottom: none; }
.wr-setup-toggle-label { font-size: 12px; color: #e8e8f0; }

/* Attendees */
.wr-attendee-list { display: flex; flex-direction: column; gap: 3px; }
.wr-attendee-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.wr-attendee-row:last-child { border-bottom: none; }
.wr-attendee-info { display: flex; align-items: center; gap: 8px; }
.wr-attendee-avatar {
    width: 26px; height: 26px;
    border-radius: 50%;
    background: rgba(83,74,183,0.3);
    color: #a89ff5;
    font-size: 12px;
    font-weight: 600;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.wr-attendee-name { font-size: 12px; color: #e8e8f0; }

/* Agenda */
.wr-agenda-table { display: flex; flex-direction: column; gap: 6px; }
.wr-agenda-header {
    display: grid;
    grid-template-columns: 2fr 2fr 80px 60px;
    gap: 8px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #6b6b85;
    padding: 0 4px;
}
.wr-agenda-row {
    display: grid;
    grid-template-columns: 2fr 2fr 80px 60px;
    gap: 8px;
    align-items: center;
    padding: 5px 4px;
    border-radius: 5px;
    transition: background 0.12s;
}
.wr-agenda-row:hover { background: rgba(255,255,255,0.03); }
.wr-agenda-row-disabled { opacity: 0.4; }
.wr-agenda-name { font-size: 12px; color: #e8e8f0; display: flex; align-items: center; }

/* Footer */
.wr-setup-footer {
    border-top: 1px solid rgba(255,255,255,0.08);
    padding: 12px 20px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.wr-setup-btn-cancel { color: #9999b0; }
.wr-setup-btn-start {
    background: #534AB7 !important;
    color: #fff !important;
    font-weight: 500;
    border-radius: 6px;
}

.wr-setup-loading { font-size: 12px; color: #6b6b85; padding: 8px 0; }
</style>
