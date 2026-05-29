<template>
    <div class="embajadores-tiers">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0 small">
                Los porcentajes se calculan sobre la mensualidad del <strong>referido</strong> (no del embajador).
                El tier se asigna automáticamente según la velocidad del plan del referido.
            </p>
            <button class="btn btn-primary btn-sm" @click="abrirNuevo">
                <i class="fa fa-plus me-1"></i> Nuevo tier
            </button>
        </div>

        <div v-if="loading" class="text-muted">Cargando porcentajes…</div>
        <div v-else-if="tiers.length === 0" class="alert alert-warning">No hay tiers configurados.</div>
        <div v-else class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Velocidad (Mbps)</th>
                        <th class="text-center">Nivel 1</th>
                        <th class="text-center">Nivel 2</th>
                        <th class="text-center">Nivel 3</th>
                        <th class="text-center">Nivel 4</th>
                        <th class="text-center">Nivel 5</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tier in tiers" :key="tier.id">
                        <td><span class="badge" :class="tierBadge(tier.tier_name)">{{ tier.tier_name }}</span></td>
                        <td class="text-muted small">{{ tier.speed_min_mbps }} – {{ tier.speed_max_mbps === 9999 ? '∞' : tier.speed_max_mbps }}</td>
                        <td class="text-center fw-600 text-success">{{ tier.level_1_pct }}%</td>
                        <td class="text-center">{{ tier.level_2_pct }}%</td>
                        <td class="text-center">{{ tier.level_3_pct }}%</td>
                        <td class="text-center">{{ tier.level_4_pct }}%</td>
                        <td class="text-center">{{ tier.level_5_pct }}%</td>
                        <td class="text-center">
                            <span class="badge" :class="tier.active ? 'bg-success' : 'bg-secondary'">
                                {{ tier.active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1" @click="abrirEditar(tier)" title="Editar">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" :class="tier.active ? 'btn-outline-warning' : 'btn-outline-success'"
                                    @click="toggleTier(tier)" :title="tier.active ? 'Desactivar' : 'Activar'">
                                <i class="fa" :class="tier.active ? 'fa-toggle-on' : 'fa-toggle-off'"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal editar / crear -->
        <div v-if="modal.open" class="modal-overlay" @click.self="cerrarModal">
            <div class="modal-box card p-4" style="max-width:560px;width:100%;">
                <h5 class="mb-4">{{ modal.id ? 'Editar tier' : 'Nuevo tier' }}</h5>
                <form @submit.prevent="guardarTier">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600">Nombre del tier</label>
                            <input type="text" class="form-control" v-model="modal.form.tier_name" maxlength="50" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-600">Vel. mín (Mbps)</label>
                            <input type="number" class="form-control" v-model="modal.form.speed_min_mbps" min="0" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-600">Vel. máx (Mbps)</label>
                            <input type="number" class="form-control" v-model="modal.form.speed_max_mbps" min="1" required>
                            <div class="form-text">Usa 9999 para "ilimitado".</div>
                        </div>
                    </div>
                    <div class="row">
                        <div v-for="n in 5" :key="n" class="col mb-3">
                            <label class="form-label fw-600 small">Nivel {{ n }} %</label>
                            <input type="number" class="form-control" v-model="modal.form[`level_${n}_pct`]"
                                   min="0" max="100" step="0.01" required>
                        </div>
                    </div>
                    <div v-if="modal.error" class="alert alert-danger small py-2">{{ modal.error }}</div>
                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-outline-secondary" @click="cerrarModal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" :disabled="modal.saving">
                            {{ modal.saving ? 'Guardando…' : (modal.id ? 'Actualizar' : 'Crear') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
const emptyForm = () => ({
    tier_name: '', speed_min_mbps: 0, speed_max_mbps: 50,
    level_1_pct: 0, level_2_pct: 0, level_3_pct: 0, level_4_pct: 0, level_5_pct: 0,
});

export default {
    name: 'EmbajadoresTiers',
    props: {
        baseUrl:   { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: true,
            tiers:   [],
            modal: { open: false, id: null, saving: false, error: '', form: emptyForm() },
        };
    },
    mounted() {
        this.cargar();
    },
    methods: {
        async cargar() {
            this.loading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/tiers/data`);
                this.tiers = data;
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        abrirNuevo() {
            this.modal = { open: true, id: null, saving: false, error: '', form: emptyForm() };
        },
        abrirEditar(tier) {
            this.modal = {
                open: true, id: tier.id, saving: false, error: '',
                form: {
                    tier_name: tier.tier_name,
                    speed_min_mbps: tier.speed_min_mbps,
                    speed_max_mbps: tier.speed_max_mbps,
                    level_1_pct: tier.level_1_pct,
                    level_2_pct: tier.level_2_pct,
                    level_3_pct: tier.level_3_pct,
                    level_4_pct: tier.level_4_pct,
                    level_5_pct: tier.level_5_pct,
                },
            };
        },
        cerrarModal() {
            this.modal.open = false;
        },
        async guardarTier() {
            this.modal.saving = true;
            this.modal.error  = '';
            try {
                const url    = this.modal.id ? `${this.baseUrl}/tiers/${this.modal.id}` : `${this.baseUrl}/tiers`;
                const method = this.modal.id ? 'put' : 'post';
                await axios[method](url, this.modal.form, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                this.cerrarModal();
                await this.cargar();
            } catch (e) {
                this.modal.error = e.response?.data?.message || 'Error guardando el tier.';
            } finally {
                this.modal.saving = false;
            }
        },
        async toggleTier(tier) {
            try {
                const { data } = await axios.post(`${this.baseUrl}/tiers/${tier.id}/toggle`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                const idx = this.tiers.findIndex((t) => t.id === tier.id);
                if (idx !== -1) this.tiers.splice(idx, 1, data.tier);
            } catch (e) {
                console.error(e);
            }
        },
        tierBadge(name) {
            return { basic: 'bg-secondary', standard: 'bg-info', premium: 'bg-warning text-dark' }[name] || 'bg-light text-dark';
        },
    },
};
</script>

<style scoped>
.fw-600 { font-weight: 600; }
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.45);
    display: flex; align-items: center; justify-content: center; z-index: 1050;
}
</style>
