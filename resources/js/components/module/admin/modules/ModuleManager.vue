<template>
    <div class="module-manager">

        <!-- Header + búsqueda -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="mb-0">Gestión de Add-ons</h4>
                <p class="text-muted small mb-0">Instala, actualiza y desinstala módulos del sistema.</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <input
                    v-model="search"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Buscar módulo…"
                    style="width:220px"
                />
                <button class="btn btn-sm btn-outline-secondary" @click="reload">
                    <i class="fa fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
                </button>
            </div>
        </div>

        <!-- KPI chips -->
        <div class="d-flex gap-3 mb-3 flex-wrap">
            <div class="kpi-chip">
                <span class="kpi-val">{{ stats.totalAddons }}</span>
                <span class="kpi-label">Add-ons disponibles</span>
            </div>
            <div class="kpi-chip kpi-success">
                <span class="kpi-val">{{ stats.installed }}</span>
                <span class="kpi-label">Instalados</span>
            </div>
            <div class="kpi-chip kpi-warning" v-if="stats.upgradeable > 0">
                <span class="kpi-val">{{ stats.upgradeable }}</span>
                <span class="kpi-label">Con actualización</span>
            </div>
            <div class="kpi-chip">
                <span class="kpi-val">{{ stats.totalFiles }}</span>
                <span class="kpi-label">Archivos totales</span>
            </div>
        </div>

        <!-- Filtros -->
        <div class="btn-group btn-group-sm mb-3">
            <button
                v-for="f in filters"
                :key="f.key"
                class="btn"
                :class="activeFilter === f.key ? 'btn-primary' : 'btn-outline-secondary'"
                @click="activeFilter = f.key"
            >{{ f.label }} <span class="badge ms-1" :class="activeFilter === f.key ? 'bg-light text-primary' : 'bg-secondary'">{{ f.count }}</span>
            </button>
        </div>

        <!-- Tabla addons -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:28px"></th>
                            <th>Módulo</th>
                            <th>Versión</th>
                            <th>Descripción</th>
                            <th>Archivos</th>
                            <th>Estado</th>
                            <th class="text-end" style="min-width:180px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="m in filteredModules" :key="m.slug">
                            <!-- Fila principal -->
                            <tr :class="{ 'table-success-subtle': m.active }">
                                <td class="chev-cell" @click="toggleExpand(m.slug)">
                                    <i class="fa fa-fw" :class="expanded[m.slug] ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ m.name }}</div>
                                    <code class="text-muted" style="font-size:11px">{{ m.slug }}</code>
                                </td>
                                <td>
                                    <span v-if="m.installed_version" class="badge bg-success">v{{ m.installed_version }}</span>
                                    <span v-else class="badge bg-light text-muted border">v{{ m.manifest_version }}</span>
                                    <span v-if="hasUpgrade(m)" class="badge bg-warning text-dark ms-1" title="Actualización disponible">
                                        ↑ {{ m.manifest_version }}
                                    </span>
                                </td>
                                <td class="text-muted small" style="max-width:300px">
                                    {{ m.description || '—' }}
                                </td>
                                <td class="text-muted small">
                                    <span>{{ m.files_count }} arch.</span><br>
                                    <span>{{ m.dir_size_kb }} KB</span>
                                </td>
                                <td>
                                    <span v-if="m.type === 'core'" class="badge bg-secondary">
                                        <i class="fa fa-lock me-1"></i>Core
                                    </span>
                                    <span v-else-if="m.active" class="badge bg-success">
                                        <i class="fa fa-check-circle me-1"></i>Instalado
                                    </span>
                                    <span v-else class="badge bg-light text-secondary border">
                                        No instalado
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end flex-wrap">
                                        <!-- Instalar -->
                                        <button
                                            v-if="!m.active && m.type !== 'core'"
                                            class="btn btn-sm btn-primary"
                                            :disabled="busy === m.slug"
                                            @click="confirmInstall(m)"
                                        >
                                            <i class="fa fa-download me-1"></i>Instalar
                                        </button>
                                        <!-- Actualizar -->
                                        <button
                                            v-if="m.active && hasUpgrade(m)"
                                            class="btn btn-sm btn-warning"
                                            :disabled="busy === m.slug"
                                            @click="doUpgrade(m)"
                                        >
                                            <i class="fa fa-arrow-up me-1"></i>Actualizar
                                        </button>
                                        <!-- Desinstalar -->
                                        <button
                                            v-if="m.active && m.type !== 'core'"
                                            class="btn btn-sm btn-outline-danger"
                                            :disabled="busy === m.slug"
                                            @click="confirmUninstall(m)"
                                        >
                                            <i class="fa fa-trash me-1"></i>Desinstalar
                                        </button>
                                        <span v-if="busy === m.slug" class="text-muted small align-self-center">
                                            <i class="fa fa-spinner fa-spin"></i>
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Fila expandida — detalles -->
                            <tr v-if="expanded[m.slug]" class="expanded-row">
                                <td></td>
                                <td colspan="6">
                                    <div class="row g-3 py-1">
                                        <div class="col-md-4">
                                            <div class="small text-muted mb-1">Identidad</div>
                                            <div><strong>Slug:</strong> <code>{{ m.slug }}</code></div>
                                            <div><strong>Versión manifiesto:</strong> {{ m.manifest_version }}</div>
                                            <div><strong>Versión instalada:</strong> {{ m.installed_version || '—' }}</div>
                                            <div><strong>Tipo:</strong> {{ m.type }}</div>
                                            <div><strong>Archivos:</strong> {{ m.files_count }} ({{ m.dir_size_kb }} KB)</div>
                                        </div>
                                        <div class="col-md-4" v-if="details[m.slug]">
                                            <div class="small text-muted mb-1">Áreas declaradas</div>
                                            <div><i class="fa fa-fw fa-bars text-muted"></i> Menú: {{ details[m.slug].menu_count }} item(s)</div>
                                            <div><i class="fa fa-fw fa-th-large text-muted"></i> Config: {{ details[m.slug].config_count }} sección(es)</div>
                                            <div><i class="fa fa-fw fa-key text-muted"></i> Permisos: {{ details[m.slug].perm_count }}</div>
                                            <div><i class="fa fa-fw fa-plug text-muted"></i> Endpoints: {{ details[m.slug].endpoint_count }}</div>
                                        </div>
                                        <div class="col-md-4" v-if="details[m.slug] && details[m.slug].dependencies.length">
                                            <div class="small text-muted mb-1">Dependencias</div>
                                            <div v-for="dep in details[m.slug].dependencies" :key="dep.slug" class="small">
                                                <i class="fa fa-link text-muted me-1"></i>
                                                <code>{{ dep.slug }}</code>
                                                <span class="text-muted"> ≥ {{ dep.min_version }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="filteredModules.length === 0">
                            <td colspan="7" class="text-center text-muted py-4">
                                Sin resultados para "{{ search }}"
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sección core (colapsable) -->
        <div class="mt-3">
            <button class="btn btn-sm btn-link text-muted p-0" @click="showCore = !showCore">
                <i class="fa fa-fw" :class="showCore ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                Módulos core del sistema ({{ coreModules.length }})
            </button>
            <div v-if="showCore" class="card mt-2">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Módulo</th>
                                <th>Versión</th>
                                <th>Archivos</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in coreModules" :key="m.slug">
                                <td>
                                    <div class="fw-semibold">{{ m.name }}</div>
                                    <code class="text-muted" style="font-size:11px">{{ m.slug }}</code>
                                </td>
                                <td><span class="badge bg-secondary">v{{ m.installed_version || m.manifest_version }}</span></td>
                                <td class="text-muted small">{{ m.files_count }} arch. · {{ m.dir_size_kb }} KB</td>
                                <td><span class="badge bg-secondary"><i class="fa fa-lock me-1"></i>Core</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación install -->
        <div v-if="modal.show && modal.action === 'install'" class="modal-overlay" @click.self="modal.show=false">
            <div class="modal-box">
                <h5>Instalar <strong>{{ modal.module.name }}</strong></h5>
                <p class="text-muted small">v{{ modal.module.manifest_version }} · {{ modal.module.files_count }} archivos</p>
                <p class="mb-1">{{ modal.module.description }}</p>
                <div v-if="modal.module.dependencies && modal.module.dependencies.length" class="alert alert-info py-2 small">
                    <strong>Dependencias:</strong>
                    <span v-for="d in modal.module.dependencies" :key="d.slug"> <code>{{ d.slug }}</code> ≥ {{ d.min_version }}</span>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.show=false">Cancelar</button>
                    <button class="btn btn-sm btn-primary" :disabled="busy === modal.module.slug" @click="doInstall(modal.module)">
                        <i class="fa fa-download me-1"></i>Confirmar instalación
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación uninstall -->
        <div v-if="modal.show && modal.action === 'uninstall'" class="modal-overlay" @click.self="modal.show=false">
            <div class="modal-box">
                <h5>Desinstalar <strong>{{ modal.module.name }}</strong></h5>
                <div v-if="modal.preview" class="alert alert-warning py-2 small">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    Afectará <strong>{{ modal.preview.roles }}</strong> rol(es) y
                    <strong>{{ modal.preview.users }}</strong> usuario(s).
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" v-model="modal.keepData" id="chk-keep-data">
                    <label class="form-check-label small" for="chk-keep-data">
                        Conservar tablas y datos (recomendado para módulos con datos de producción)
                    </label>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.show=false">Cancelar</button>
                    <button class="btn btn-sm btn-danger" :disabled="busy === modal.module.slug" @click="doUninstall(modal.module)">
                        <i class="fa fa-trash me-1"></i>Confirmar desinstalación
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import { darkMode } from "../../../../hook/appConfig.js";

export default {
    name: 'ModuleManager',
    setup() {
        return { darkMode };
    },
    props: {
        initialModules: { type: Array,  required: true },
        totalModules:   { type: Number, required: true },
        migratedCount:  { type: Number, required: true },
        pendingCount:   { type: Number, required: true },
        totalCostUsd:   { type: [Number, String], required: true },
        csrfToken:      { type: String, required: true },
    },
    data() {
        const mods = this.initialModules.map(m => ({ ...m, dir_size_kb: Number(m.dir_size_kb) || 0 }));
        return {
            modules:      mods,
            search:       '',
            activeFilter: 'addons',
            expanded:     {},
            details:      {},
            showCore:     false,
            busy:         null,
            loading:      false,
            modal: { show: false, action: null, module: null, preview: null, keepData: true },
        };
    },
    computed: {
        addonModules() {
            return this.modules.filter(m => m.type !== 'core');
        },
        coreModules() {
            return this.modules.filter(m => m.type === 'core');
        },
        stats() {
            const addons    = this.addonModules;
            const installed = addons.filter(m => m.active);
            return {
                totalAddons:  addons.length,
                installed:    installed.length,
                upgradeable:  addons.filter(m => this.hasUpgrade(m)).length,
                totalFiles:   addons.reduce((s, m) => s + (m.files_count || 0), 0),
            };
        },
        filters() {
            const addons = this.addonModules;
            return [
                { key: 'addons',       label: 'Add-ons',      count: addons.length },
                { key: 'installed',    label: 'Instalados',   count: addons.filter(m => m.active).length },
                { key: 'available',    label: 'No instalados',count: addons.filter(m => !m.active).length },
                { key: 'upgradeable',  label: 'Actualizar',   count: addons.filter(m => this.hasUpgrade(m)).length },
            ];
        },
        filteredModules() {
            let list = this.addonModules;
            if (this.activeFilter === 'installed')   list = list.filter(m => m.active);
            if (this.activeFilter === 'available')   list = list.filter(m => !m.active);
            if (this.activeFilter === 'upgradeable') list = list.filter(m => this.hasUpgrade(m));
            if (this.search.trim()) {
                const q = this.search.toLowerCase();
                list = list.filter(m =>
                    m.name.toLowerCase().includes(q) ||
                    m.slug.toLowerCase().includes(q) ||
                    (m.description || '').toLowerCase().includes(q)
                );
            }
            return list;
        },
    },
    methods: {
        hasUpgrade(m) {
            if (!m.active || !m.installed_version || !m.manifest_version) return false;
            return this.versionGt(m.manifest_version, m.installed_version);
        },
        versionGt(a, b) {
            const pa = a.split('.').map(Number);
            const pb = b.split('.').map(Number);
            for (let i = 0; i < 3; i++) {
                if ((pa[i] || 0) > (pb[i] || 0)) return true;
                if ((pa[i] || 0) < (pb[i] || 0)) return false;
            }
            return false;
        },
        toggleExpand(slug) {
            this.expanded[slug] = !this.expanded[slug];
            if (this.expanded[slug] && !this.details[slug]) {
                this.loadDetails(slug);
            }
        },
        loadDetails(slug) {
            const m = this.modules.find(x => x.slug === slug);
            if (!m) return;
            this.details[slug] = {
                menu_count:     (m.menu || []).length,
                config_count:   (m.config_sections || []).length,
                perm_count:     (m.permissions || []).length,
                endpoint_count: (m.api_endpoints || []).length,
                dependencies:   m.dependencies || [],
            };
        },
        async confirmInstall(m) {
            this.modal = { show: true, action: 'install', module: m, preview: null, keepData: true };
        },
        async confirmUninstall(m) {
            this.modal = { show: true, action: 'uninstall', module: m, preview: null, keepData: true };
            try {
                const { data } = await axios.get(`/admin/modules/${m.slug}/uninstall/preview`);
                if (data.success) this.modal.preview = data.preview;
            } catch (e) { /* preview opcional */ }
        },
        async doInstall(m) {
            this.busy = m.slug;
            this.modal.show = false;
            try {
                const { data } = await axios.post(
                    `/admin/modules/${m.slug}/install`,
                    {},
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } }
                );
                if (data.success) {
                    m.active = true;
                    m.installed_version = m.manifest_version;
                    this.notify(`${m.name} instalado correctamente.`, 'positive');
                } else {
                    this.notify(data.error || 'Error al instalar', 'negative');
                }
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.busy = null;
            }
        },
        async doUpgrade(m) {
            this.busy = m.slug;
            try {
                const { data } = await axios.post(
                    `/admin/modules/${m.slug}/upgrade`,
                    {},
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } }
                );
                if (data.success || data.action === 'already_up_to_date') {
                    m.installed_version = m.manifest_version;
                    this.notify(`${m.name} actualizado a v${m.manifest_version}.`, 'positive');
                } else {
                    this.notify(data.error || 'Error al actualizar', 'negative');
                }
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.busy = null;
            }
        },
        async doUninstall(m) {
            this.busy = m.slug;
            this.modal.show = false;
            try {
                const { data } = await axios.delete(
                    `/admin/modules/${m.slug}/uninstall`,
                    {
                        data: { keep_data: this.modal.keepData ? '1' : '0' },
                        headers: { 'X-CSRF-TOKEN': this.csrfToken },
                    }
                );
                if (data.success) {
                    m.active = false;
                    if (!this.modal.keepData) m.installed_version = null;
                    this.notify(`${m.name} desinstalado.`, 'positive');
                } else {
                    this.notify(data.error || 'Error al desinstalar', 'negative');
                }
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.busy = null;
            }
        },
        async reload() {
            this.loading = true;
            try {
                window.location.reload();
            } finally {
                this.loading = false;
            }
        },
        notify(message, type) {
            if (this.$q?.notify) {
                this.$q.notify({ message, color: type === 'positive' ? 'green-7' : 'red-7', position: 'top-right', timeout: 4000 });
            } else {
                alert(message);
            }
        },
    },
};
</script>

<style scoped>
/* KPI chips */
.kpi-chip {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 20px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    min-width: 110px;
}
.kpi-chip.kpi-success { border-color: #d1e7dd; background: #f0fdf4; }
.kpi-chip.kpi-warning { border-color: #fff3cd; background: #fffbeb; }
.kpi-val  { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
.kpi-label{ font-size: 11px; color: #6c757d; }

/* Tabla */
.chev-cell { cursor: pointer; width: 28px; color: #adb5bd; }
.table-success-subtle { background: rgba(25, 135, 84, 0.04); }
.expanded-row { background: #f8f9fa; }
.expanded-row td { padding: 12px 16px; }

/* Modal overlay */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
}
.modal-box {
    background: #fff;
    border-radius: 10px;
    padding: 24px;
    width: 440px;
    max-width: 95vw;
    box-shadow: 0 8px 32px rgba(0,0,0,.18);
}
.modal-box h5 { margin-bottom: 4px; }

/* Dark mode — responde al toggle de Medussa ([data-layout-mode=dark]), no al SO */
[data-layout-mode=dark] .kpi-chip            { background:#2a2d3e; border-color:#3a3d50; }
[data-layout-mode=dark] .kpi-chip.kpi-success{ background:#0d2218; border-color:#1a4731; }
[data-layout-mode=dark] .kpi-chip.kpi-warning{ background:#231a00; border-color:#4d3800; }
[data-layout-mode=dark] .expanded-row        { background:#1e2433; }
[data-layout-mode=dark] .expanded-row td     { border-color:#2e3547; }
[data-layout-mode=dark] .modal-box           { background:#252836; color:#cdd6f4; border:1px solid #3a3d50; }
/* thead.table-light no tiene cobertura global — override manual */
[data-layout-mode=dark] thead.table-light    { background:#262b38 !important; color:#adb5bd !important; }
[data-layout-mode=dark] thead.table-light th { border-color:#333a4d !important; color:#8b95a8 !important; background:#262b38 !important; }
/* code slugs */
[data-layout-mode=dark] code                 { background:#1a1e2a; color:#7dd3fc; border-radius:3px; padding:1px 4px; }
/* badge sin color instalado */
[data-layout-mode=dark] .badge.bg-light      { background:#2e3547 !important; color:#adb5bd !important; border-color:#3a4060 !important; }
/* fila activa (verde claro demasiado brillante en oscuro) */
[data-layout-mode=dark] .table-success-subtle{ background:rgba(25,135,84,.08) !important; }
</style>
