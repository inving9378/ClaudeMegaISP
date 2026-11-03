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
                <button
                    v-if="canManageVisibility"
                    class="btn btn-sm btn-outline-primary"
                    title="Reordenar módulos del sidebar (drag-and-drop)"
                    @click="reorderModal.show = true"
                >
                    <i class="fa fa-arrows-alt-v me-1"></i>Reordenar sidebar
                </button>
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
                                    <div class="fw-semibold">
                                        {{ m.name }}
                                        <span v-if="m.system" class="badge bg-light text-secondary border ms-1"
                                              title="Instala programas en el servidor (ver detalle)">
                                            <i class="fa fa-server"></i>
                                        </span>
                                        <span v-if="m.system_last_run && isRunActive(m.system_last_run)"
                                              class="badge bg-warning text-dark ms-1 sys-link" title="Ver la corrida en curso"
                                              @click.stop="openSysRun(m, m.system_last_run.id)">
                                            <i class="fa fa-spinner fa-spin me-1"></i>{{ statusLabel(m.system_last_run.status) }}
                                        </span>
                                    </div>
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
                                        <!-- Configurar visibilidad -->
                                        <button
                                            v-if="canManageVisibility && m.active"
                                            class="btn btn-sm btn-outline-secondary"
                                            :disabled="busy === m.slug"
                                            title="Configurar visibilidad"
                                            @click="openVisibilityConfig(m)"
                                        >
                                            <i class="fa fa-eye me-1"></i>Visibilidad
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
                                        <div class="col-12" v-if="m.system">
                                            <div class="sys-box">
                                                <div class="small text-muted mb-1"><i class="fa fa-server me-1"></i>Dependencias del servidor</div>
                                                <div class="small mb-2">{{ m.system.description || 'Este módulo instala programas en el servidor.' }}</div>
                                                <div v-if="m.system_last_run" class="small mb-2">
                                                    Última corrida: {{ actionLabel(m.system_last_run.action) }} ·
                                                    <span :class="statusClass(m.system_last_run.status)">{{ statusLabel(m.system_last_run.status) }}</span>
                                                    · {{ formatDate(m.system_last_run.finished_at || m.system_last_run.started_at || m.system_last_run.created_at) }}
                                                    <span v-if="m.system_last_run.started_by"> · por {{ m.system_last_run.started_by }}</span>
                                                    · <a href="#" @click.prevent="openSysRun(m, m.system_last_run.id)">ver salida</a>
                                                </div>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <button v-if="m.system.install" class="btn btn-sm btn-outline-primary"
                                                            :disabled="sysBusy(m)" @click="startSys(m, 'install')">
                                                        <i class="fa fa-download me-1"></i>Instalar dependencias
                                                    </button>
                                                    <button v-if="m.system.purge" class="btn btn-sm btn-outline-danger"
                                                            :disabled="sysBusy(m)" @click="confirmPurge(m)">
                                                        <i class="fa fa-broom me-1"></i>Purgar dependencias
                                                    </button>
                                                </div>
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
                <div v-if="modal.module.system && modal.module.system.install" class="alert alert-info py-2 small">
                    <i class="fa fa-server me-1"></i>
                    Primero se instalan sus dependencias del servidor
                    ({{ modal.module.system.description || 'programas que necesita' }}).
                    Puede tardar varios minutos y pedir una contraseña; el módulo se instala al terminar.
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
                <div v-if="modal.module.system && modal.module.system.purge" class="alert alert-secondary py-2 small mt-2">
                    <i class="fa fa-server me-1"></i>
                    <span v-if="modal.keepData">Las dependencias del servidor se conservan (puedes purgarlas después desde el detalle).</span>
                    <span v-else>También se purgan sus dependencias del servidor: {{ modal.module.system.description }}</span>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.show=false">Cancelar</button>
                    <button class="btn btn-sm btn-danger" :disabled="busy === modal.module.slug" @click="doUninstall(modal.module)">
                        <i class="fa fa-trash me-1"></i>Confirmar desinstalación
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación: purgar solo las dependencias del servidor -->
        <div v-if="modal.show && modal.action === 'purge'" class="modal-overlay" @click.self="modal.show=false">
            <div class="modal-box">
                <h5>Purgar dependencias de <strong>{{ modal.module.name }}</strong></h5>
                <p class="small mb-2">{{ modal.module.system.description }}</p>
                <div class="alert alert-warning py-2 small">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    Se borra del servidor lo que instaló este módulo, incluidos sus datos.
                    El módulo en sí (tablas, permisos, menú) no se toca.
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-secondary" @click="modal.show=false">Cancelar</button>
                    <button class="btn btn-sm btn-danger" @click="modal.show=false; startSys(modal.module, 'purge')">
                        <i class="fa fa-broom me-1"></i>Purgar
                    </button>
                </div>
            </div>
        </div>

        <!-- Corrida de dependencias del servidor: salida en vivo + contraseña -->
        <div v-if="sysRun.show" class="modal-overlay" @click.self="closeSysRun">
            <div class="modal-box modal-box-wide">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5><i class="fa fa-server me-1"></i>{{ actionLabel(sysRun.run && sysRun.run.action) }} dependencias</h5>
                        <p class="text-muted small mb-2">{{ sysRun.module.name }} · <code>{{ sysRun.run ? sysRun.run.script : '' }}</code></p>
                    </div>
                    <span v-if="sysRun.run" class="badge" :class="statusBadge(sysRun.run.status)">
                        <i v-if="isRunActive(sysRun.run)" class="fa fa-spinner fa-spin me-1"></i>{{ statusLabel(sysRun.run.status) }}
                    </span>
                </div>

                <pre ref="sysLog" class="sys-log">{{ sysRun.run ? sysRun.run.log : 'Cargando…' }}</pre>
                <div v-if="sysRun.run && sysRun.run.log_truncated" class="small text-muted">Se muestra solo el final de la salida.</div>
                <div v-if="sysRun.run && sysRun.run.error" class="alert alert-danger py-2 small mt-2">{{ sysRun.run.error }}</div>

                <form v-if="sysPasswordFor" class="sys-pw mt-2"
                      autocomplete="off" @submit.prevent="sendPassword">
                    <label class="form-label small mb-1" for="sys-pw-input">
                        <i class="fa fa-key me-1"></i>
                        <template v-if="sysPasswordFor.via === 'su'">Contraseña de <strong>root</strong> de este servidor</template>
                        <template v-else>Contraseña de <strong>{{ sysPasswordFor.user }}</strong> (para sudo)</template>
                        <span v-if="sysPasswordFor.attempt > 1" class="text-danger"> · intento {{ sysPasswordFor.attempt }} de 3</span>
                    </label>
                    <div class="d-flex gap-2">
                        <input id="sys-pw-input" ref="sysPw" v-model="sysRun.password" type="password"
                               class="form-control form-control-sm" autocomplete="new-password" :disabled="sysRun.sending">
                        <button type="submit" class="btn btn-sm btn-primary" :disabled="!sysRun.password || sysRun.sending">Enviar</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="sysRun.sending" @click="cancelSysRun">Cancelar</button>
                    </div>
                    <div class="small text-muted mt-1">Se usa solo para esta corrida; no se guarda.</div>
                </form>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-secondary" @click="closeSysRun">
                        {{ sysRun.run && isRunActive(sysRun.run) ? 'Seguir en segundo plano' : 'Cerrar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de visibilidad (Fase 3) -->
        <module-visibility-config
            v-model="visibilityModal.show"
            :module-key="visibilityModal.moduleKey"
        />

        <!-- Modal de reorden por drag-and-drop (#168) -->
        <sidebar-reorder v-model="reorderModal.show" />

    </div>
</template>

<script>
import { darkMode } from "../../../../hook/appConfig.js";
import ModuleVisibilityConfig from "../../../admin/ModuleVisibilityConfig.vue";
import SidebarReorder from "../../../admin/SidebarReorder.vue";

export default {
    name: 'ModuleManager',
    components: {
        ModuleVisibilityConfig,
        SidebarReorder,
    },
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
            visibilityModal: { show: false, moduleKey: '' },
            reorderModal: { show: false },
            sysRun: { show: false, module: null, runId: null, run: null, password: '', sending: false,
                      answered: null, startedHere: false, notified: false, timer: null },
        };
    },
    beforeUnmount() {
        this.stopSysPoll();
    },
    computed: {
        /** A quién pide contraseña la corrida abierta (null si no está esperando, o si ya se contestó). */
        sysPasswordFor() {
            const run = this.sysRun.run;
            if (!run || run.status !== 'awaiting_password' || !run.password_for) return null;
            return this.passwordKey(run.password_for) === this.sysRun.answered ? null : run.password_for;
        },
        canManageVisibility() {
            return this.$store?.getters?.hasPermission
                ? this.$store.getters.hasPermission('module.visibility.manage')
                : false;
        },
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
        // Los manifiestos usan slug con prefijo (addon-/core-), pero la tabla
        // module_sidebar_config usa la clave sin prefijo (ej. addon-flotas -> flotas).
        moduleKeyFromSlug(slug) {
            return (slug || '').replace(/^(addon-|core-)/, '');
        },
        openVisibilityConfig(m) {
            this.visibilityModal = { show: true, moduleKey: this.moduleKeyFromSlug(m.slug) };
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
                if (data.success && data.action === 'system_run') {
                    // Primero corren sus dependencias del servidor; el módulo se instala al terminar.
                    this.showSysRun(m, data.run.id, data.run, true);
                } else if (data.success) {
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
                    if (data.system_run) this.showSysRun(m, data.system_run.id, data.system_run, true);
                    if (data.system_error) this.notify(data.system_error, 'negative');
                } else {
                    this.notify(data.error || 'Error al desinstalar', 'negative');
                }
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.busy = null;
            }
        },
        // ── Dependencias del servidor (module.json → "system") ──────────────
        isRunActive(run) {
            return !!run && ['starting', 'running', 'awaiting_password'].includes(run.status);
        },
        statusLabel(s) {
            return ({ starting: 'Iniciando', running: 'En curso', awaiting_password: 'Esperando contraseña',
                      success: 'Terminó bien', failed: 'Falló' })[s] || s || '—';
        },
        statusClass(s) {
            return s === 'success' ? 'text-success' : (s === 'failed' ? 'text-danger' : 'text-warning');
        },
        statusBadge(s) {
            return s === 'success' ? 'bg-success' : (s === 'failed' ? 'bg-danger' : 'bg-warning text-dark');
        },
        actionLabel(a) {
            return a === 'purge' ? 'Purgar' : 'Instalar';
        },
        formatDate(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return isNaN(d) ? iso : d.toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
        },
        passwordKey(p) {
            return p ? `${p.via}:${p.user}:${p.attempt}` : null;
        },
        sysBusy(m) {
            return this.busy === m.slug || this.isRunActive(m.system_last_run);
        },
        confirmPurge(m) {
            this.modal = { show: true, action: 'purge', module: m, preview: null, keepData: true };
        },
        async startSys(m, action) {
            this.busy = m.slug;
            try {
                const { data } = await axios.post(
                    `/admin/modules/${m.slug}/system/${action}`,
                    {},
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } }
                );
                if (data.success) this.showSysRun(m, data.run.id, data.run, true);
                else this.notify(data.error || 'No se pudo iniciar la corrida', 'negative');
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.busy = null;
            }
        },
        openSysRun(m, runId) {
            this.showSysRun(m, runId, null, false);
        },
        showSysRun(m, runId, run, startedHere) {
            this.stopSysPoll();
            this.sysRun = { show: true, module: m, runId, run: null, password: '', sending: false,
                            answered: null, startedHere, notified: false, timer: null };
            if (run) this.applySysRun(m, run);
            this.pollSysRun();
        },
        async pollSysRun() {
            const { module: m, runId } = this.sysRun;
            if (!this.sysRun.show || !runId) return;
            try {
                const { data } = await axios.get(`/admin/modules/${m.slug}/system/runs/${runId}`);
                if (data.success && this.sysRun.runId === runId) this.applySysRun(m, data.run);
            } catch (e) { /* se reintenta en el siguiente ciclo */ }
            const sigue = !this.sysRun.run || this.isRunActive(this.sysRun.run);
            if (this.sysRun.show && this.sysRun.runId === runId && sigue) {
                this.sysRun.timer = setTimeout(() => this.pollSysRun(), 1500);
            }
        },
        applySysRun(m, run) {
            const prev = this.sysRun.run;
            const pegado = this.logAtBottom();
            this.sysRun.run = run;
            const { log, log_truncated, ...resumen } = run;
            m.system_last_run = resumen;

            const terminoAhora = !this.isRunActive(run) && (this.sysRun.startedHere || (prev && this.isRunActive(prev)));
            if (terminoAhora && !this.sysRun.notified) {
                this.sysRun.notified = true;
                this.onSysRunFinished(m, run);
            }
            if (this.sysPasswordFor) this.$nextTick(() => this.$refs.sysPw?.focus());
            if (pegado) {
                this.$nextTick(() => {
                    const el = this.$refs.sysLog;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            }
        },
        logAtBottom() {
            const el = this.$refs.sysLog;
            return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 40;
        },
        onSysRunFinished(m, run) {
            if (run.status !== 'success') {
                this.notify(`${m.name}: la corrida de dependencias falló. Revisa la salida.`, 'negative');
                return;
            }
            if (run.action === 'install' && run.lifecycle === 'installed') {
                m.active = true;
                m.installed_version = m.manifest_version;
                this.notify(`${m.name}: dependencias instaladas y módulo instalado.`, 'positive');
            } else {
                this.notify(`${m.name}: dependencias ${run.action === 'purge' ? 'purgadas' : 'instaladas'}.`, 'positive');
            }
        },
        async sendPassword() {
            const { module: m, runId } = this.sysRun;
            const pedida = this.sysPasswordFor;
            if (!pedida || !this.sysRun.password) return;
            this.sysRun.sending = true;
            try {
                await axios.post(
                    `/admin/modules/${m.slug}/system/runs/${runId}/password`,
                    { password: this.sysRun.password },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } }
                );
                // Que el formulario no reaparezca con el aviso ya contestado mientras el script lo procesa.
                this.sysRun.answered = this.passwordKey(pedida);
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.sysRun.password = '';
                this.sysRun.sending = false;
            }
        },
        async cancelSysRun() {
            const { module: m, runId } = this.sysRun;
            const pedida = this.sysPasswordFor;
            this.sysRun.sending = true;
            try {
                await axios.post(
                    `/admin/modules/${m.slug}/system/runs/${runId}/cancel`,
                    {},
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } }
                );
                this.sysRun.answered = this.passwordKey(pedida);
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.sysRun.password = '';
                this.sysRun.sending = false;
            }
        },
        stopSysPoll() {
            if (this.sysRun && this.sysRun.timer) clearTimeout(this.sysRun.timer);
        },
        closeSysRun() {
            this.stopSysPoll();
            this.sysRun.show = false;
            this.sysRun.password = '';
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
.modal-box-wide { width: 860px; }

/* Dependencias del servidor */
.sys-box { border: 1px solid #e9ecef; border-radius: 8px; padding: 10px 12px; background: #fff; }
.sys-link { cursor: pointer; }
.sys-log {
    background: #0f1117; color: #d4d4d4;
    font-size: 12px; line-height: 1.45;
    height: 360px; max-height: 55vh; overflow: auto;
    padding: 10px 12px; border-radius: 6px; margin: 0;
    white-space: pre-wrap; word-break: break-word;
}
.sys-pw { border: 1px solid #ffe69c; background: #fff8e1; border-radius: 8px; padding: 10px 12px; }

/* Dark mode — responde al toggle de Medussa ([data-layout-mode=dark]), no al SO */
[data-layout-mode=dark] .kpi-chip            { background:#2a2d3e; border-color:#3a3d50; }
[data-layout-mode=dark] .kpi-chip.kpi-success{ background:#0d2218; border-color:#1a4731; }
[data-layout-mode=dark] .kpi-chip.kpi-warning{ background:#231a00; border-color:#4d3800; }
[data-layout-mode=dark] .expanded-row        { background:#1e2433; }
[data-layout-mode=dark] .expanded-row td     { border-color:#2e3547; }
[data-layout-mode=dark] .modal-box           { background:#252836; color:#cdd6f4; border:1px solid #3a3d50; }
[data-layout-mode=dark] .sys-box             { background:#1e2433; border-color:#2e3547; }
[data-layout-mode=dark] .sys-pw              { background:#2a2410; border-color:#5c4a12; }
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
