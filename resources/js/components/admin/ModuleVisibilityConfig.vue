<template>
    <q-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" persistent>
        <q-card class="mvc-card">

            <!-- Header -->
            <q-card-section class="row items-center q-pb-sm">
                <q-icon :name="headerIcon" size="28px" class="q-mr-sm text-primary" />
                <div class="col">
                    <div class="text-h6 q-mb-none">Visibilidad — {{ headerLabel }}</div>
                    <div class="text-caption text-grey-7"><code>{{ moduleKey }}</code></div>
                </div>
                <q-chip v-if="config" dense color="green-2" text-color="green-9" icon="check_circle">Instalado</q-chip>
                <q-btn flat round dense icon="close" @click="close" />
            </q-card-section>

            <q-separator />

            <!-- Loading -->
            <q-card-section v-if="loading" class="text-center q-pa-xl">
                <q-spinner color="primary" size="36px" />
                <div class="text-grey-7 q-mt-sm">Cargando configuración…</div>
            </q-card-section>

            <q-card-section v-else class="mvc-body scroll">

                <!-- ===== Sección 1: mostrar en sidebar ===== -->
                <div class="section-title">1. Mostrar el módulo en el sidebar</div>
                <div class="toggle-row">
                    <q-toggle
                        v-model="showInSidebar"
                        size="sm"
                        :disable="isCore"
                        color="primary"
                    >
                        <q-tooltip v-if="isCore">Este es un módulo base, no se puede ocultar del sidebar.</q-tooltip>
                    </q-toggle>
                    <div class="toggle-row-text">
                        <div class="toggle-row-label">
                            Mostrar este módulo en el sidebar
                            <q-badge v-if="isCore" color="grey" class="q-ml-sm">Core (no editable)</q-badge>
                        </div>
                        <div class="toggle-row-help">
                            {{ showInSidebar
                                ? 'Encendido — la operación aparece en el sidebar'
                                : ('Apagado — la operación se mueve a Administración › ' + (adminSection || '…')) }}
                        </div>
                    </div>
                </div>
                <div v-if="isCore" class="text-caption text-orange-8 q-mt-xs">
                    <q-icon name="lock" size="14px" /> Módulo base: siempre visible en el sidebar.
                </div>

                <!-- Bloque sidebar ON -->
                <div v-if="showInSidebar" class="q-mt-md mvc-block">
                    <div class="form-label">Ubicación</div>
                    <div class="radio-group">
                        <label
                            class="radio-option"
                            :class="{ selected: sidebarLocation === 'direct' }"
                            @click="sidebarLocation = 'direct'"
                        >
                            <q-radio v-model="sidebarLocation" val="direct" size="sm" color="primary" />
                            <div class="radio-content">
                                <div class="radio-title">Directo en el sidebar</div>
                                <div class="radio-help">Top-level, junto a Dashboard, Clientes, Flotas.</div>
                            </div>
                        </label>
                        <label
                            class="radio-option"
                            :class="{ selected: sidebarLocation === 'sub_item' }"
                            @click="sidebarLocation = 'sub_item'"
                        >
                            <q-radio v-model="sidebarLocation" val="sub_item" size="sm" color="primary" />
                            <div class="radio-content">
                                <div class="radio-title">Como sub-ítem de otro módulo</div>
                                <div class="radio-help">Aparece dentro del submenú de un módulo padre.</div>
                            </div>
                        </label>
                    </div>

                    <div v-if="sidebarLocation === 'sub_item'" class="form-field q-mt-sm">
                        <label class="form-label">Módulo padre</label>
                        <q-select
                            v-model="sidebarParent"
                            :options="parentOptions"
                            option-value="value"
                            option-label="label"
                            emit-value
                            map-options
                            outlined dense
                            :error="!!errors.sidebar_parent"
                            :error-message="firstError('sidebar_parent')"
                        />
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label class="form-label">Sección del sidebar</label>
                            <q-select
                                v-model="sidebarSection"
                                :options="[
                                    { label: 'Menu', value: 'menu' },
                                    { label: 'Módulos', value: 'modulos' },
                                ]"
                                emit-value map-options
                                outlined dense
                            />
                        </div>
                        <div class="form-field">
                            <label class="form-label">Posición (1-50)</label>
                            <q-input
                                v-model.number="sidebarPosition"
                                type="number" min="1" max="50"
                                outlined dense
                                :error="!!errors.sidebar_position"
                                :error-message="firstError('sidebar_position')"
                            />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label class="form-label">Ícono (ej. fa-truck)</label>
                            <q-input
                                v-model="sidebarIcon"
                                outlined dense
                            />
                        </div>
                        <div class="form-field">
                            <label class="form-label">Etiqueta visible</label>
                            <q-input
                                v-model="sidebarLabel"
                                outlined dense
                                :error="!!errors.sidebar_label"
                                :error-message="firstError('sidebar_label')"
                            />
                        </div>
                    </div>

                    <div class="form-field">
                        <label class="form-label">URL del módulo</label>
                        <q-input
                            v-model="sidebarUrl"
                            outlined dense
                            placeholder="/cobranza/campanas"
                            hint="Ruta a la que navega el item del sidebar (sobre todo si es sub-ítem de otro módulo)."
                            :error="!!errors.sidebar_url"
                            :error-message="firstError('sidebar_url')"
                        />
                    </div>
                </div>

                <!-- Bloque sidebar OFF -->
                <div v-else class="q-mt-md mvc-block">
                    <div class="form-field">
                        <label class="form-label">Sección de Administración</label>
                        <q-select
                            v-model="adminSection"
                            :options="adminSectionSuggestions"
                            outlined dense
                            use-input
                            new-value-mode="add-unique"
                            hide-dropdown-icon
                            input-debounce="0"
                            @new-value="(val, done) => done(val)"
                            :error="!!errors.admin_section"
                            :error-message="firstError('admin_section')"
                            hint="Escribe libre o elige una sugerencia."
                        />
                    </div>
                    <div class="form-field">
                        <label class="form-label">Subsección dentro de Configuración</label>
                        <q-input
                            v-model="configuracionSubsection"
                            outlined dense
                            :error="!!errors.configuracion_subsection"
                            :error-message="firstError('configuracion_subsection')"
                            hint="Como el módulo sale del sidebar, su configuración vivirá aquí."
                        />
                    </div>
                </div>

                <!-- ===== Sección 2: ubicación de la configuración (solo sidebar ON) ===== -->
                <template v-if="showInSidebar">
                    <div class="subsection-divider"></div>
                    <div class="section-title">2. Ubicación de la configuración del módulo</div>
                    <div class="toggle-row">
                        <q-toggle v-model="configMoved" size="sm" color="primary" />
                        <div class="toggle-row-text">
                            <div class="toggle-row-label">Mover la configuración a la sección Configuración</div>
                            <div class="toggle-row-help">
                                {{ configMoved
                                    ? 'Encendido — la configuración vive en la sección Configuración'
                                    : 'Apagado — la configuración vive dentro del módulo' }}
                            </div>
                        </div>
                    </div>
                    <div v-if="configMoved" class="q-mt-sm mvc-block">
                        <div class="form-field">
                            <label class="form-label">Subsección dentro de Configuración</label>
                            <q-input
                                v-model="configuracionSubsection"
                                outlined dense
                                :error="!!errors.configuracion_subsection"
                                :error-message="firstError('configuracion_subsection')"
                            />
                        </div>
                    </div>
                </template>

                <!-- ===== LIVE PREVIEWS ===== -->
                <div class="subsection-divider"></div>
                <div class="section-help">
                    Vistas previas aproximadas — confirma visualmente a dónde irá el módulo.
                </div>

                <!-- Preview Sidebar -->
                <div v-if="showInSidebar" class="mvc-preview mvc-preview--sidebar">
                    <div class="mvc-preview-title">Sidebar</div>
                    <ul class="mvc-side">
                        <li><i class="fa fa-comments"></i> Mensajes</li>
                        <li><i class="fa fa-ticket-alt"></i> Tickets</li>
                        <template v-if="sidebarLocation === 'sub_item'">
                            <li>
                                <i class="fa fa-folder"></i> {{ parentLabel || 'Módulo padre' }}
                                <ul>
                                    <li class="mvc-hl"><i class="fa" :class="iconClass"></i> {{ sidebarLabel || headerLabel }}</li>
                                </ul>
                            </li>
                        </template>
                        <li v-else class="mvc-hl"><i class="fa" :class="iconClass"></i> {{ sidebarLabel || headerLabel }}</li>
                    </ul>
                </div>

                <!-- Preview Administración -->
                <div v-if="!showInSidebar" class="mvc-preview mvc-preview--admin">
                    <div class="mvc-preview-title">Administración › {{ adminSection || '…' }}</div>
                    <div class="mvc-tiles">
                        <div class="mvc-tile">Usuarios</div>
                        <div class="mvc-tile">Roles</div>
                        <div class="mvc-tile">Auditoría</div>
                        <div class="mvc-tile mvc-tile--op">{{ headerLabel }}</div>
                    </div>
                </div>

                <!-- Preview Configuración -->
                <div v-if="configMoved || !showInSidebar" class="mvc-preview mvc-preview--config">
                    <div class="mvc-preview-title">Configuración › {{ configuracionSubsection || '…' }}</div>
                    <div class="mvc-tiles">
                        <div class="mvc-tile">General</div>
                        <div class="mvc-tile">Notificaciones</div>
                        <div class="mvc-tile">Integraciones</div>
                        <div class="mvc-tile mvc-tile--cfg">{{ headerLabel }}</div>
                    </div>
                </div>

            </q-card-section>

            <q-separator />

            <!-- Footer -->
            <q-card-actions align="right" class="q-pa-md">
                <q-btn flat label="Cancelar" color="grey-8" @click="close" :disable="saving" />
                <q-btn
                    unelevated
                    color="primary"
                    label="Guardar configuración"
                    icon="save"
                    :loading="saving"
                    :disable="saving || loading"
                    @click="save"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script>
export default {
    name: 'ModuleVisibilityConfig',
    props: {
        modelValue: { type: Boolean, default: false },
        moduleKey:  { type: String,  default: '' },
    },
    emits: ['update:modelValue', 'saved'],
    data() {
        return {
            config: null,
            parentOptions: [],
            loading: false,
            saving: false,
            errors: {},

            showInSidebar: true,
            sidebarLocation: 'direct',
            sidebarParent: null,
            sidebarSection: 'modulos',
            sidebarPosition: 99,
            sidebarIcon: '',
            sidebarLabel: '',
            sidebarUrl: '',
            configMoved: false,
            adminSection: '',
            configuracionSubsection: '',
            isCore: false,

            adminSectionSuggestions: [
                'Operaciones', 'Cobranza', 'Red e infraestructura',
                'Recursos humanos', 'Finanzas', 'Soporte', 'Marketing',
            ],
        };
    },
    computed: {
        headerLabel() {
            return this.sidebarLabel || this.config?.sidebar_label || this.moduleKey;
        },
        headerIcon() {
            // ícono Quasar para el header (material) — independiente del fa- del item
            return this.showInSidebar ? 'visibility' : 'visibility_off';
        },
        iconClass() {
            const ic = (this.sidebarIcon || '').trim();
            return ic || 'fa-cube';
        },
        parentLabel() {
            const opt = this.parentOptions.find(o => o.value === this.sidebarParent);
            return opt ? opt.label : null;
        },
    },
    watch: {
        modelValue(open) {
            if (open && this.moduleKey) this.loadConfig();
        },
    },
    methods: {
        firstError(key) {
            const e = this.errors[key];
            return Array.isArray(e) ? e[0] : (e || '');
        },
        close() {
            this.$emit('update:modelValue', false);
        },
        async loadConfig() {
            this.loading = true;
            this.errors = {};
            try {
                const { data } = await axios.get(`/admin/modules/visibility/${this.moduleKey}`);
                this.config = data;
                this.showInSidebar           = !!data.show_in_sidebar;
                this.sidebarLocation         = data.sidebar_location || 'direct';
                this.sidebarParent           = data.sidebar_parent || null;
                this.sidebarSection          = data.sidebar_section || 'modulos';
                this.sidebarPosition         = data.sidebar_position ?? 99;
                this.sidebarIcon             = data.sidebar_icon || '';
                this.sidebarLabel            = data.sidebar_label || '';
                this.sidebarUrl              = data.sidebar_url || '';
                this.configMoved             = !!data.config_moved;
                this.adminSection            = data.admin_section || '';
                this.configuracionSubsection = data.configuracion_subsection || '';
                this.isCore                  = !!data.is_core;

                const { data: all } = await axios.get('/admin/modules/visibility');
                // #132: solo parents con sub-menú (can_host_children) pueden recibir hijos
                // dinámicos; los links planos (mapas, olts, administracion, etc.) quedan fuera.
                this.parentOptions = (all.data || [])
                    .filter(m => m.show_in_sidebar && m.sidebar_location === 'direct' && m.module_key !== this.moduleKey && m.can_host_children)
                    .map(m => ({ label: m.sidebar_label || m.module_key, value: m.module_key }));
            } catch (e) {
                this.notify('negative', 'Error al cargar configuración: ' + (e.response?.data?.message || e.message));
                this.close();
            } finally {
                this.loading = false;
            }
        },
        async save() {
            this.saving = true;
            this.errors = {};
            try {
                const payload = {
                    show_in_sidebar:  this.showInSidebar,
                    sidebar_location: this.sidebarLocation,
                    sidebar_parent:   this.sidebarLocation === 'sub_item' ? this.sidebarParent : null,
                    sidebar_section:  this.sidebarSection,
                    sidebar_position: this.sidebarPosition,
                    sidebar_icon:     this.sidebarIcon,
                    sidebar_label:    this.sidebarLabel,
                    sidebar_url:      this.sidebarUrl || null,
                    config_moved:     this.configMoved,
                    admin_section:    !this.showInSidebar ? this.adminSection : null,
                    configuracion_subsection: (this.configMoved || !this.showInSidebar) ? this.configuracionSubsection : null,
                };
                await axios.put(`/admin/modules/visibility/${this.moduleKey}`, payload);
                this.notify('positive', 'Configuración guardada. Refresca la página para ver cambios en el sidebar.');
                this.$emit('saved');
                this.close();
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                    this.notify('negative', 'Hay errores en el formulario.');
                } else {
                    this.notify('negative', 'Error al guardar: ' + (e.response?.data?.message || e.message));
                }
            } finally {
                this.saving = false;
            }
        },
        notify(type, message) {
            if (this.$q?.notify) {
                this.$q.notify({ message, color: type === 'positive' ? 'green-7' : 'red-7', position: 'top-right', timeout: 5000 });
            } else {
                alert(message);
            }
        },
    },
};
</script>

<style scoped>
.mvc-card { width: 720px; max-width: 95vw; }
.mvc-body { max-height: 70vh; }
.mvc-block { padding: 10px 12px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; }

/* previews */
.mvc-preview { border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
.mvc-preview-title { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; margin-bottom: 6px; }
.mvc-preview--sidebar { background: #f1f3f5; }
.mvc-preview--admin   { background: #e7f1ff; }
.mvc-preview--config  { background: #f3e8ff; }

.mvc-side { list-style: none; margin: 0; padding: 0; font-size: 13px; }
.mvc-side li { padding: 4px 8px; border-radius: 6px; color: #495057; }
.mvc-side li i { width: 18px; text-align: center; margin-right: 6px; color: #868e96; }
.mvc-side ul { list-style: none; margin: 2px 0 0 18px; padding: 0; }
.mvc-hl { background: #d0ebff; color: #1971c2 !important; font-weight: 600; }
.mvc-hl i { color: #1971c2 !important; }

.mvc-tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.mvc-tile { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 14px 6px; text-align: center; font-size: 12px; color: #495057; }
.mvc-tile--op  { background: #1971c2; border-color: #1971c2; color: #fff; font-weight: 600; }
.mvc-tile--cfg { background: #7048e8; border-color: #7048e8; color: #fff; font-weight: 600; }

/* mockup v4 tokens */
.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 12px;
}
.form-label {
  font-size: 13px;
  color: #6c757d;
  font-weight: 500;
}
.section-title {
  font-size: 14px;
  font-weight: 500;
  margin: 1.25rem 0 0.25rem;
}
.section-help {
  font-size: 13px;
  color: #6c757d;
  margin-bottom: 1rem;
}
.radio-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}
.radio-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border: 0.5px solid rgba(0,0,0,0.15);
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  background: white;
  transition: all 0.15s ease;
}
.radio-option:hover {
  border-color: rgba(0,0,0,0.3);
}
.radio-option.selected {
  border-color: #185FA5;
  background: #E6F1FB;
  color: #0C447C;
}
.radio-title {
  font-weight: 500;
}
.radio-help {
  font-size: 12px;
  color: #6c757d;
  margin-top: 2px;
}
.toggle-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
}
.toggle-row-text {
  flex: 1;
}
.toggle-row-label {
  font-size: 14px;
}
.toggle-row-help {
  font-size: 12px;
  color: #6c757d;
  margin-top: 2px;
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 12px;
}
.subsection-divider {
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 0.5px dashed rgba(0,0,0,0.15);
}

/* dark mode (Medussa data-layout-mode) */
[data-layout-mode=dark] .mvc-block { background:#2a2d3e; border-color:#3a3d50; }
[data-layout-mode=dark] .radio-option { background:#252836; border-color:#3a3d50; color:#adb5bd; }
[data-layout-mode=dark] .radio-option.selected { background:#13243a; border-color:#185FA5; color:#cfe3f7; }
[data-layout-mode=dark] .radio-help,
[data-layout-mode=dark] .form-label,
[data-layout-mode=dark] .toggle-row-help,
[data-layout-mode=dark] .section-help { color:#868e96; }
[data-layout-mode=dark] .subsection-divider { border-top-color: rgba(255,255,255,0.12); }
[data-layout-mode=dark] .mvc-preview--sidebar { background:#262b38; }
[data-layout-mode=dark] .mvc-preview--admin   { background:#13243a; }
[data-layout-mode=dark] .mvc-preview--config  { background:#241a3a; }
[data-layout-mode=dark] .mvc-side li { color:#adb5bd; }
[data-layout-mode=dark] .mvc-tile { background:#252836; border-color:#3a3d50; color:#adb5bd; }
[data-layout-mode=dark] .mvc-hl { background:#1864ab; color:#fff !important; }
</style>
