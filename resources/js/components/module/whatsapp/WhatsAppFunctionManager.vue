<template>
    <div class="wfm-shell">
        <div class="wfm-header">
            <h3><i class="bi bi-diagram-3"></i> Funciones de WhatsApp</h3>
            <button class="wfm-btn primary" @click="openCreate">
                <i class="bi bi-plus-lg"></i> Nueva función
            </button>
        </div>

        <p class="wfm-hint">
            Una <strong>función</strong> es lo que sabe hacer una línea (Ventas, Cobranza…). Una función
            <strong>exclusiva</strong> vive en una sola línea a la vez; una <strong>compartida</strong> puede estar en varias.
            La asignación función↔línea se hace en <a :href="baseUrl + '/instances'">Líneas</a>.
        </p>

        <div v-if="loading" class="wfm-empty">Cargando…</div>
        <div v-else-if="!functions.length" class="wfm-empty">
            No hay funciones. Crea la primera con “Nueva función”.
        </div>

        <table v-else class="wfm-table">
            <thead>
                <tr>
                    <th>Función</th>
                    <th>Tipo</th>
                    <th>Líneas</th>
                    <th>Estado</th>
                    <th class="wfm-actions-col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="fn in functions" :key="fn.id">
                    <td>
                        <strong>{{ fn.name }}</strong>
                        <code class="wfm-slug">{{ fn.slug }}</code>
                        <div v-if="fn.description" class="wfm-desc">{{ fn.description }}</div>
                    </td>
                    <td>
                        <button
                            class="wfm-chip"
                            :class="fn.exclusive ? 'exclusive' : 'shared'"
                            :disabled="togglingId === fn.id"
                            @click="toggleExclusive(fn)"
                            :title="'Clic para cambiar a ' + (fn.exclusive ? 'compartida' : 'exclusiva')"
                        >
                            {{ fn.exclusive ? 'Exclusiva' : 'Compartida' }}
                        </button>
                    </td>
                    <td>
                        <span v-if="fn.instances && fn.instances.length" class="wfm-lines">
                            {{ fn.instances.map(i => i.name).join(', ') }}
                        </span>
                        <span v-else class="wfm-noline" title="Nadie atiende esta función todavía — estado válido">
                            <i class="bi bi-pause-circle"></i> Sin línea
                        </span>
                    </td>
                    <td>
                        <span class="wfm-status" :class="fn.active ? 'on' : 'off'">
                            {{ fn.active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="wfm-actions-col">
                        <button class="wfm-btn sm" @click="openEdit(fn)" title="Editar"><i class="bi bi-pencil"></i></button>
                        <button class="wfm-btn sm danger" @click="remove(fn)" title="Borrar"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Modal crear/editar -->
        <div v-if="showForm" class="wfm-modal-backdrop" @click.self="showForm = false">
            <div class="wfm-modal">
                <h4>{{ form.id ? 'Editar función' : 'Nueva función' }}</h4>
                <div class="wfm-form">
                    <label>Nombre <input v-model="form.name" @input="autoSlug" placeholder="Ventas, Cobranza…" /></label>
                    <label>Slug (identificador estable)
                        <input v-model="form.slug" placeholder="ventas" :disabled="!!form.id" />
                        <small v-if="form.id" class="wfm-muted">El slug no se cambia una vez creado.</small>
                    </label>
                    <label>Descripción <input v-model="form.description" placeholder="(opcional)" /></label>
                    <label class="wfm-checkbox">
                        <input type="checkbox" v-model="form.exclusive" />
                        Exclusiva (una sola línea a la vez)
                    </label>
                    <label class="wfm-checkbox">
                        <input type="checkbox" v-model="form.active" />
                        Activa
                    </label>
                </div>
                <div v-if="formError" class="wfm-error">{{ formError }}</div>
                <div class="wfm-modal-actions">
                    <button class="wfm-btn" @click="showForm = false">Cancelar</button>
                    <button class="wfm-btn primary" @click="save" :disabled="saving">
                        {{ saving ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="toast" class="wfm-toast" :class="toast.type">{{ toast.msg }}</div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'WhatsAppFunctionManager',

    data() {
        return {
            baseUrl: '/whatsapp',
            functions: [],
            loading: false,
            showForm: false,
            saving: false,
            togglingId: null,
            formError: '',
            form: this.blankForm(),
            toast: null,
        };
    },

    methods: {
        blankForm() {
            return { id: null, name: '', slug: '', description: '', exclusive: true, active: true };
        },

        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/whatsapp/api/functions');
                this.functions = Array.isArray(data) ? data : [];
            } catch (e) {
                this.functions = [];
            } finally {
                this.loading = false;
            }
        },

        openCreate() {
            this.form = this.blankForm();
            this.formError = '';
            this.showForm = true;
        },

        openEdit(fn) {
            this.form = {
                id: fn.id, name: fn.name, slug: fn.slug,
                description: fn.description || '', exclusive: !!fn.exclusive, active: !!fn.active,
            };
            this.formError = '';
            this.showForm = true;
        },

        autoSlug() {
            if (this.form.id) return; // slug fijo en edición
            this.form.slug = (this.form.name || '')
                .toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        },

        async save() {
            this.formError = '';
            this.saving = true;
            try {
                if (this.form.id) {
                    await axios.patch(`/whatsapp/api/functions/${this.form.id}`, this.form);
                } else {
                    await axios.post('/whatsapp/api/functions', this.form);
                }
                this.showForm = false;
                await this.load();
                this.showToast('Función guardada', 'ok');
            } catch (e) {
                this.formError = this.errorMsg(e);
            } finally {
                this.saving = false;
            }
        },

        async toggleExclusive(fn) {
            this.togglingId = fn.id;
            try {
                const { data } = await axios.patch(`/whatsapp/api/functions/${fn.id}/exclusive`);
                fn.exclusive = !!data.exclusive;
            } catch (e) {
                this.showToast(this.errorMsg(e), 'error'); // p.ej. 422 "está en N líneas…"
            } finally {
                this.togglingId = null;
            }
        },

        async remove(fn) {
            if (!confirm(`¿Borrar la función «${fn.name}»? Se quitará de las líneas que la tengan.`)) return;
            try {
                await axios.delete(`/whatsapp/api/functions/${fn.id}`);
                await this.load();
                this.showToast('Función borrada', 'ok');
            } catch (e) {
                this.showToast(this.errorMsg(e), 'error');
            }
        },

        errorMsg(e) {
            return e.response?.data?.message
                || Object.values(e.response?.data?.errors || {}).flat().join(', ')
                || 'Ocurrió un error.';
        },

        showToast(msg, type) {
            this.toast = { msg, type };
            setTimeout(() => { this.toast = null; }, 3500);
        },
    },

    mounted() {
        this.load();
    },
};
</script>

<style scoped>
.wfm-shell {
    --wa-brand: #25d366;
    padding: 20px;
    max-width: 1100px;
    margin: 0 auto;
    color: var(--text-primary);
}
.wfm-header {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
}
.wfm-header h3 { margin: 0; color: var(--wa-brand); }
.wfm-hint { font-size: 13px; color: var(--text-secondary); margin: 0 0 16px; }
.wfm-hint a { color: var(--accent); }

.wfm-table {
    width: 100%; border-collapse: collapse;
    background: var(--bg-surface); border: 1px solid var(--border-default);
    border-radius: 8px; overflow: hidden; box-shadow: var(--shadow-card);
}
.wfm-table th, .wfm-table td {
    text-align: left; padding: 10px 12px; font-size: 13px;
    border-bottom: 1px solid var(--border-default);
}
.wfm-table th { color: var(--text-secondary); font-weight: 600; background: var(--bg-hover); }
.wfm-table tr:last-child td { border-bottom: none; }
.wfm-slug {
    margin-left: 8px; background: var(--bg-primary); color: var(--success);
    padding: 1px 6px; border-radius: 3px; font-size: 12px;
}
.wfm-desc { color: var(--text-secondary); font-size: 12px; margin-top: 2px; }
.wfm-lines { color: var(--text-primary); }
.wfm-muted { color: var(--text-secondary); }
.wfm-noline {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--bg-hover); color: var(--text-secondary);
    border: 1px solid var(--border-default); border-radius: 12px;
    padding: 2px 10px; font-size: 12px; font-weight: 600;
}
.wfm-actions-col { white-space: nowrap; text-align: right; }

.wfm-chip {
    border: 1px solid var(--border-default); border-radius: 12px;
    padding: 2px 10px; font-size: 12px; font-weight: 600; cursor: pointer;
}
.wfm-chip.exclusive { background: var(--warning); color: #1f2937; border-color: transparent; }
.wfm-chip.shared { background: var(--bg-hover); color: var(--text-secondary); }
.wfm-chip:disabled { opacity: .5; cursor: wait; }

.wfm-status { padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.wfm-status.on { background: var(--success); color: #fff; }
.wfm-status.off { background: var(--bg-hover); color: var(--text-secondary); }

/* Modo oscuro: chips y estados con línea inferior (texto blanco + color abajo, sin fondo).
   Solo dark → el modo claro conserva su pastilla de color. */
[data-layout-mode="dark"] .wfm-chip,
[data-layout-mode="dark"] .wfm-status {
    background: transparent !important;
    border: 0 !important;
    border-bottom: 2px solid var(--text-secondary) !important;
    border-radius: 0 !important;
    color: #fff !important;
    padding: 2px 0;
}
[data-layout-mode="dark"] .wfm-chip.exclusive { border-bottom-color: var(--warning) !important; }
[data-layout-mode="dark"] .wfm-status.on { border-bottom-color: var(--success) !important; }
[data-layout-mode="dark"] .wfm-chip.shared,
[data-layout-mode="dark"] .wfm-status.off { border-bottom-color: var(--text-secondary) !important; }

.wfm-btn {
    background: var(--bg-hover); color: var(--text-primary);
    border: 1px solid var(--border-default); border-radius: 6px;
    padding: 7px 12px; font-size: 13px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
}
.wfm-btn.sm { padding: 5px 9px; }
.wfm-btn.primary { background: var(--wa-brand); color: #06251a; border-color: var(--wa-brand); font-weight: 600; }
.wfm-btn.danger { color: var(--danger); border-color: var(--danger); }
.wfm-btn:disabled { opacity: .5; cursor: not-allowed; }

.wfm-empty {
    color: var(--text-secondary); text-align: center; padding: 32px;
    background: var(--bg-surface); border-radius: 8px; border: 1px solid var(--border-default);
}

.wfm-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.6);
    display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.wfm-modal {
    background: var(--bg-surface); padding: 20px; border-radius: 8px;
    max-width: 460px; width: 90%; color: var(--text-primary); box-shadow: var(--shadow-card);
}
.wfm-modal h4 { margin-top: 0; color: var(--wa-brand); }
.wfm-form { display: flex; flex-direction: column; gap: 10px; margin: 14px 0; }
.wfm-form label { display: flex; flex-direction: column; font-size: 12px; color: var(--text-secondary); gap: 4px; }
.wfm-form input {
    background: var(--bg-primary); color: var(--text-primary);
    border: 1px solid var(--border-default); border-radius: 4px; padding: 7px 10px; font-size: 13px;
}
.wfm-checkbox { flex-direction: row !important; align-items: center; gap: 8px; }
.wfm-checkbox input { width: auto; }
.wfm-error { background: var(--bg-hover); color: var(--danger); padding: 8px 12px; border-radius: 4px; font-size: 12px; margin-bottom: 10px; }
.wfm-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }

.wfm-toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600;
    z-index: 10001; box-shadow: var(--shadow-card);
}
.wfm-toast.ok { background: var(--success); color: #fff; }
.wfm-toast.error { background: var(--danger); color: #fff; }
</style>
