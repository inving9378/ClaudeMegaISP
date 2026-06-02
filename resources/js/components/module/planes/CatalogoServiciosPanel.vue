<template>
    <div class="csp-wrap">

        <!-- Header -->
        <div class="csp-header">
            <div>
                <h5 class="csp-title">Catálogo de servicios contratables</h5>
                <p class="csp-subtitle">
                    {{ services.length }} servicio{{ services.length !== 1 ? 's' : '' }}
                    · {{ bundleableCount }} empaquetable{{ bundleableCount !== 1 ? 's' : '' }}
                    · declarados por los módulos activos
                </p>
            </div>
            <button class="csp-btn-primary" :disabled="syncing" @click="sync">
                <i class="bi bi-arrow-repeat me-1" :class="{ 'csp-spin': syncing }"></i>
                {{ syncing ? 'Sincronizando…' : 'Sincronizar desde módulos' }}
            </button>
        </div>

        <div v-if="loading" class="csp-loading">Cargando catálogo…</div>

        <div v-else-if="services.length === 0" class="csp-empty">
            No hay <code>service_type</code> declarados todavía. Cuando un módulo declare
            uno en su <code>module.json</code>, presiona “Sincronizar”.
        </div>

        <template v-else>
            <div class="csp-table-wrap">
                <table class="csp-table">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Módulo</th>
                            <th>Capacidades</th>
                            <th class="csp-right">Precio</th>
                            <th class="csp-center">Activo</th>
                            <th class="csp-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in services" :key="s.key" :class="{ 'csp-row-orphan': s.orphan }">
                            <td>
                                <div class="csp-svc-label">{{ s.label }}</div>
                                <div class="csp-svc-key">{{ s.key }}</div>
                            </td>
                            <td><span class="csp-module">{{ s.module_slug || '—' }}</span></td>
                            <td>
                                <span v-if="s.bundleable" class="csp-badge badge-bundle">Empaquetable</span>
                                <span v-if="s.supports_promotions" class="csp-badge badge-promo">Promociones</span>
                                <span v-if="s.price_configurable" class="csp-badge badge-price">Precio editable</span>
                                <span v-if="!s.bundleable && !s.supports_promotions && !s.price_configurable" class="csp-muted">—</span>
                            </td>
                            <td class="csp-right">
                                <template v-if="s.price_configurable && !s.orphan">
                                    <div class="csp-price-edit">
                                        <span class="csp-currency">$</span>
                                        <input
                                            type="number" min="0" step="0.01"
                                            class="csp-price-input"
                                            v-model.number="draft[s.key]"
                                            @keyup.enter="savePrice(s)"
                                        />
                                        <button
                                            class="csp-btn-mini"
                                            :disabled="savingKey === s.key || draft[s.key] === priceOf(s)"
                                            @click="savePrice(s)"
                                            title="Guardar precio"
                                        >
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </div>
                                </template>
                                <span v-else class="csp-muted">
                                    {{ s.price !== null ? '$' + Number(s.price).toFixed(2) : 'No configurable' }}
                                </span>
                            </td>
                            <td class="csp-center">
                                <label class="csp-switch" v-if="!s.orphan">
                                    <input type="checkbox" :checked="s.active"
                                           :disabled="savingKey === s.key"
                                           @change="toggleActive(s, $event.target.checked)" />
                                    <span class="csp-slider"></span>
                                </label>
                                <span v-else class="csp-muted">—</span>
                            </td>
                            <td class="csp-center">
                                <span v-if="s.orphan" class="csp-state state-orphan" title="El módulo fue desactivado">Huérfano</span>
                                <span v-else-if="!s.synced" class="csp-state state-pending">Sin sincronizar</span>
                                <span v-else-if="s.active" class="csp-state state-ok">Disponible</span>
                                <span v-else class="csp-state state-off">Inactivo</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="csp-foot">
                Las capacidades (empaquetable, promociones, precio editable) las declara cada módulo en su
                <code>module.json</code>. Aquí solo se administra el precio y el estado de contratación.
            </p>
        </template>

        <!-- Toast -->
        <transition name="csp-fade">
            <div v-if="toast.show" :class="['csp-toast', 'toast-' + toast.type]">
                {{ toast.msg }}
            </div>
        </transition>
    </div>
</template>

<script>
export default {
    name: "CatalogoServiciosPanel",
    data() {
        return {
            loading: true,
            syncing: false,
            savingKey: null,
            services: [],
            bundleable: [],
            draft: {},
            toast: { show: false, msg: "", type: "success" },
            toastTimer: null,
        };
    },
    computed: {
        bundleableCount() {
            return this.services.filter(s => s.bundleable && s.active && !s.orphan).length;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        priceOf(s) {
            return s.price !== null && s.price !== undefined ? Number(s.price) : null;
        },
        syncDraft() {
            const d = {};
            this.services.forEach(s => { d[s.key] = this.priceOf(s); });
            this.draft = d;
        },
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get("/planes/catalogo/data");
                this.services = data.services || [];
                this.bundleable = data.bundleable || [];
                this.syncDraft();
            } catch (e) {
                this.notify("No se pudo cargar el catálogo", "error");
            } finally {
                this.loading = false;
            }
        },
        async sync() {
            this.syncing = true;
            try {
                const { data } = await axios.post("/planes/catalogo/sync");
                this.services = data.services || [];
                this.syncDraft();
                const r = data.result || {};
                this.notify(`Sincronizado: ${r.created || 0} nuevo(s), ${r.updated || 0} actualizado(s)`);
            } catch (e) {
                this.notify("Error al sincronizar", "error");
            } finally {
                this.syncing = false;
            }
        },
        async savePrice(s) {
            const price = this.draft[s.key];
            if (price === null || price === "" || isNaN(price) || price < 0) {
                this.notify("Precio inválido", "error");
                return;
            }
            this.savingKey = s.key;
            try {
                await axios.post(`/planes/catalogo/${s.key}`, { price });
                s.price = Number(price);
                this.notify(`Precio de ${s.label} guardado`);
            } catch (e) {
                this.notify(e.response?.data?.error || "No se pudo guardar el precio", "error");
            } finally {
                this.savingKey = null;
            }
        },
        async toggleActive(s, active) {
            this.savingKey = s.key;
            try {
                await axios.post(`/planes/catalogo/${s.key}`, { active });
                s.active = active;
                this.notify(`${s.label} ${active ? "activado" : "desactivado"}`);
            } catch (e) {
                this.notify("No se pudo cambiar el estado", "error");
                await this.load();
            } finally {
                this.savingKey = null;
            }
        },
        notify(msg, type = "success") {
            this.toast = { show: true, msg, type };
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toast.show = false; }, 3000);
        },
    },
};
</script>

<style scoped>
.csp-wrap { padding: 4px 2px 40px; }
.csp-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 16px; margin-bottom: 18px;
}
.csp-title { margin: 0; font-weight: 600; color: #1f2937; }
.csp-subtitle { margin: 2px 0 0; font-size: 13px; color: #6b7280; }
.csp-btn-primary {
    background: #2563eb; color: #fff; border: none; border-radius: 8px;
    padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer;
    white-space: nowrap; transition: background .15s;
}
.csp-btn-primary:hover { background: #1d4ed8; }
.csp-btn-primary:disabled { opacity: .6; cursor: default; }
.csp-spin { display: inline-block; animation: csp-rot 1s linear infinite; }
@keyframes csp-rot { to { transform: rotate(360deg); } }

.csp-loading, .csp-empty {
    padding: 40px; text-align: center; color: #6b7280;
    background: #f9fafb; border-radius: 10px; font-size: 14px;
}
.csp-table-wrap { overflow-x: auto; border: 1px solid #eef0f3; border-radius: 10px; }
.csp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.csp-table thead th {
    text-align: left; padding: 11px 14px; background: #f9fafb;
    color: #6b7280; font-weight: 600; font-size: 11px; text-transform: uppercase;
    letter-spacing: .03em; border-bottom: 1px solid #eef0f3;
}
.csp-table tbody td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.csp-table tbody tr:last-child td { border-bottom: none; }
.csp-row-orphan { opacity: .55; }
.csp-right { text-align: right; }
.csp-center { text-align: center; }
.csp-svc-label { font-weight: 600; color: #1f2937; }
.csp-svc-key { font-size: 11px; color: #9ca3af; font-family: monospace; margin-top: 1px; }
.csp-module { font-family: monospace; font-size: 12px; color: #4b5563; }
.csp-muted { color: #9ca3af; }

.csp-badge {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 11px; font-weight: 500; margin-right: 5px; margin-bottom: 2px;
}
.badge-bundle { background: #eef2ff; color: #4338ca; }
.badge-promo  { background: #ecfdf5; color: #047857; }
.badge-price  { background: #fff7ed; color: #c2410c; }

.csp-price-edit { display: inline-flex; align-items: center; gap: 4px; justify-content: flex-end; }
.csp-currency { color: #6b7280; }
.csp-price-input {
    width: 90px; padding: 5px 8px; border: 1px solid #d1d5db; border-radius: 6px;
    font-size: 13px; text-align: right;
}
.csp-btn-mini {
    border: none; background: #2563eb; color: #fff; border-radius: 6px;
    width: 28px; height: 28px; cursor: pointer; display: inline-flex;
    align-items: center; justify-content: center;
}
.csp-btn-mini:disabled { background: #cbd5e1; cursor: default; }

.csp-switch { position: relative; display: inline-block; width: 38px; height: 21px; }
.csp-switch input { opacity: 0; width: 0; height: 0; }
.csp-slider {
    position: absolute; cursor: pointer; inset: 0; background: #cbd5e1;
    border-radius: 21px; transition: .2s;
}
.csp-slider::before {
    content: ""; position: absolute; height: 15px; width: 15px; left: 3px; bottom: 3px;
    background: #fff; border-radius: 50%; transition: .2s;
}
.csp-switch input:checked + .csp-slider { background: #16a34a; }
.csp-switch input:checked + .csp-slider::before { transform: translateX(17px); }

.csp-state { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.state-ok { background: #dcfce7; color: #166534; }
.state-off { background: #f3f4f6; color: #6b7280; }
.state-pending { background: #fef9c3; color: #854d0e; }
.state-orphan { background: #fee2e2; color: #991b1b; }

.csp-foot { margin-top: 12px; font-size: 12px; color: #9ca3af; }
.csp-foot code, .csp-empty code { background: #f3f4f6; padding: 1px 5px; border-radius: 4px; font-size: 11px; }

.csp-toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    padding: 12px 18px; border-radius: 8px; color: #fff; font-size: 13px;
    box-shadow: 0 6px 20px rgba(0,0,0,.15);
}
.toast-success { background: #16a34a; }
.toast-error { background: #dc2626; }
.csp-fade-enter-active, .csp-fade-leave-active { transition: opacity .25s; }
.csp-fade-enter-from, .csp-fade-leave-to { opacity: 0; }
</style>
