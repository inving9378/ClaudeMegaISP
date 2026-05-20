<template>
    <div class="megafamilia-perfiles mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">Perfiles</h5>
            <button class="btn btn-primary btn-sm" @click="openCreate">
                <i class="fa fa-plus"></i> Nuevo perfil
            </button>
        </div>

        <div v-if="loading && profiles.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="profiles.length === 0" class="text-muted">No hay perfiles. Crea el primero arriba.</div>

        <div v-else class="row">
            <div v-for="p in profiles" :key="p.id" class="col-md-4 col-lg-3 mb-3">
                <div class="card profile-card h-100" :class="{ inactive: !p.active }">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="avatar mx-auto" :style="{ background: avatarColor(p.id) }">
                            <span v-if="!p.photo">{{ initials(p.name) }}</span>
                            <img v-else :src="p.photo" :alt="p.name" />
                        </div>
                        <h5 class="mt-2 mb-0">{{ p.name }}</h5>
                        <div class="text-muted small">
                            {{ p.age ? `${p.age} años` : '—' }} · {{ typeLabel(p.profile_type) }}
                        </div>
                        <div v-if="p.school_level" class="text-muted small">
                            <i class="fa fa-graduation-cap"></i> {{ schoolLabel(p.school_level) }}
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-info">
                                <i class="fa fa-mobile-screen"></i> {{ p.devices_count || 0 }} dispositivos
                            </span>
                        </div>
                        <div class="mt-auto pt-3 d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm btn-outline-primary" @click="openEdit(p)">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" :disabled="acting === p.id" @click="destroy(p)">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="editing" class="modal-overlay" @click.self="closeEdit">
            <div class="modal-panel">
                <div class="modal-panel-header">
                    <h5 class="m-0">{{ editing.id ? 'Editar perfil' : 'Nuevo perfil' }}</h5>
                    <button class="btn-close" @click="closeEdit"></button>
                </div>
                <div class="modal-panel-body">
                    <div class="mb-2">
                        <label class="form-label">Nombre</label>
                        <input v-model="editing.name" class="form-control" />
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Edad</label>
                            <input v-model.number="editing.age" type="number" min="0" max="25" class="form-control" />
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tipo</label>
                            <select v-model="editing.profile_type" class="form-select">
                                <option value="nino">Niño</option>
                                <option value="preadolescente">Preadolescente</option>
                                <option value="adolescente">Adolescente</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nivel escolar</label>
                            <select v-model="editing.school_level" class="form-select">
                                <option value="">—</option>
                                <option value="primaria">Primaria</option>
                                <option value="secundaria">Secundaria</option>
                                <option value="preparatoria">Preparatoria</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-panel-footer">
                    <button class="btn btn-outline-secondary" @click="closeEdit">Cancelar</button>
                    <button class="btn btn-primary" :disabled="saving" @click="save">
                        {{ saving ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const AVATAR_COLORS = ['#5b8def', '#38c172', '#ff8c00', '#e91e63', '#9b59b6', '#16a085'];

export default {
    name: 'MegaFamiliaPerfiles',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { profiles: [], loading: false, errorMsg: '', editing: null, saving: false, acting: null }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try { const { data } = await axios.get(`${this.baseUrl}/perfiles/data`); this.profiles = data.profiles || []; }
            catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        openCreate() { this.editing = { name: '', age: null, profile_type: 'nino', school_level: '' }; },
        openEdit(p) { this.editing = { ...p }; },
        closeEdit() { this.editing = null; },
        async save() {
            this.saving = true;
            const payload = { ...this.editing };
            if (payload.school_level === '') payload.school_level = null;
            try {
                if (this.editing.id) {
                    await axios.put(`${this.baseUrl}/perfiles/${this.editing.id}`, payload, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                } else {
                    await axios.post(`${this.baseUrl}/perfiles`, payload, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                }
                this.closeEdit();
                await this.fetch();
            } catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.saving = false; }
        },
        async destroy(p) {
            if (! confirm(`¿Eliminar el perfil de ${p.name}?`)) return;
            this.acting = p.id;
            try { await axios.delete(`${this.baseUrl}/perfiles/${p.id}`, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); await this.fetch(); }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        initials(name) { return (name || '?').split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase(); },
        avatarColor(id) { return AVATAR_COLORS[id % AVATAR_COLORS.length]; },
        typeLabel(t) { return ({ nino: 'Niño', preadolescente: 'Preadolescente', adolescente: 'Adolescente' })[t] || t; },
        schoolLabel(s) { return ({ primaria: 'Primaria', secundaria: 'Secundaria', preparatoria: 'Preparatoria' })[s] || s; },
    },
};
</script>

<style scoped>
.profile-card { transition: transform 0.15s; }
.profile-card:hover { transform: translateY(-2px); }
.profile-card.inactive { opacity: 0.5; }
.avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 24px; font-weight: 700;
    overflow: hidden;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.modal-panel { background: #fff; border-radius: 8px; width: min(520px, 92vw); display: flex; flex-direction: column; }
.modal-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.modal-panel-body { padding: 1rem 1.25rem; }
.modal-panel-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 0.5rem; }
</style>
