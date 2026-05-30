<template>
    <div class="module-config-panel">

        <!-- Barra de filtro por categoría -->
        <div class="config-filter-bar mb-3 d-flex flex-wrap gap-2 align-items-center">
            <q-btn
                :class="['btn-filter', activeCategory === null ? 'active' : '']"
                :dark="darkMode"
                @click="activeCategory = null"
                size="sm"
            >Todas</q-btn>
            <q-btn
                v-for="cat in categories"
                :key="cat"
                :class="['btn-filter', activeCategory === cat ? 'active' : '']"
                :dark="darkMode"
                @click="activeCategory = cat"
                size="sm"
            >{{ cat }}</q-btn>
        </div>

        <!-- Estado de carga -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted small">Cargando secciones…</p>
        </div>

        <div v-else-if="visibleGroups.length === 0" class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fa fa-cog fa-3x mb-3 d-block opacity-25"></i>
                No hay secciones de configuración disponibles.
            </div>
        </div>

        <!-- Grupos por type -->
        <template v-else>
            <div
                v-for="group in visibleGroups"
                :key="group.type"
                class="config-group mb-4"
            >
                <!-- Encabezado del grupo -->
                <div class="config-group-header d-flex align-items-center mb-2">
                    <span class="config-group-badge me-2" :style="{ background: typeColor(group.type) }">
                        <i :class="'fa fa-fw fa-' + typeIcon(group.type)"></i>
                    </span>
                    <h6 class="mb-0 fw-semibold text-capitalize">
                        {{ typeLabel(group.type) }}
                        <span class="text-muted fw-normal ms-1 small">({{ group.sections.length }})</span>
                    </h6>
                </div>

                <!-- Tiles de la sección -->
                <div class="row g-2">
                    <div
                        v-for="section in group.sections"
                        :key="section.key"
                        class="col-xl-2 col-lg-3 col-md-4 col-6"
                    >
                        <a
                            :href="section.url || 'javascript:void(0);'"
                            :data-bs-toggle="section.modal ? 'modal' : null"
                            :data-bs-target="section.modal ? '#' + section.modal : null"
                            class="config-tile card text-decoration-none"
                            :title="section.description || ''"
                        >
                            <div class="card-body p-2 d-flex align-items-center gap-2">
                                <h6 class="config-tile-icon m-0" :style="{ color: typeColor(section.type) }">
                                    <i :class="'fa fa-fw fa-' + (section.icon || 'cog')"></i>
                                </h6>
                                <span class="config-tile-label small lh-sm">{{ section.label }}</span>
                            </div>
                            <div v-if="section._module && section._module !== 'core-configuracion'" class="config-tile-module">
                                {{ moduleBadge(section._module) }}
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </template>

    </div>
</template>

<script>
import { darkMode } from "../../../hook/appConfig.js";

const TYPE_META = {
    general:      { label: 'General',        icon: 'cog',          color: '#556ee6' },
    pagos:        { label: 'Pagos',           icon: 'credit-card',  color: '#f1b44c' },
    notificaciones:{ label: 'Notificaciones', icon: 'bell',         color: '#34c38f' },
    scheduling:   { label: 'Programación',   icon: 'clock',        color: '#ffa726' },
    api_key:      { label: 'API / Claves',   icon: 'key',          color: '#50a5f1' },
    herramientas: { label: 'Herramientas',   icon: 'tools',        color: '#74788d' },
    webhook:      { label: 'Webhooks',       icon: 'plug',         color: '#e83e8c' },
};

export default {
    name: 'ModuleConfigPanel',

    props: {
        baseUrl: { type: String, default: '' },
    },

    // Exponer darkMode (ref de appConfig) al template via setup().
    // El resto del componente usa Options API; Vue permite mezclar ambos estilos.
    setup() {
        return { darkMode };
    },

    data() {
        return {
            loading: true,
            sectionsFlat: [],
            activeCategory: null,
        };
    },

    computed: {
        // Categorías únicas, ordenadas
        categories() {
            const cats = [...new Set(this.sectionsFlat.map(s => s.category).filter(Boolean))];
            return cats.sort();
        },

        // Secciones filtradas por categoría activa
        filteredSections() {
            if (!this.activeCategory) return this.sectionsFlat;
            return this.sectionsFlat.filter(s => s.category === this.activeCategory);
        },

        // Agrupadas por type, orden determinista
        visibleGroups() {
            const groups = {};
            for (const section of this.filteredSections) {
                const t = section.type || 'general';
                if (!groups[t]) groups[t] = { type: t, sections: [] };
                groups[t].sections.push(section);
            }
            // Orden preferido de tipos
            const order = ['general', 'pagos', 'notificaciones', 'api_key', 'scheduling', 'herramientas', 'webhook'];
            const sorted = Object.values(groups).sort((a, b) => {
                const ia = order.indexOf(a.type);
                const ib = order.indexOf(b.type);
                return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
            });
            return sorted.filter(g => g.sections.length > 0);
        },
    },

    mounted() {
        this.loadSections();
    },

    methods: {
        async loadSections() {
            try {
                const { data } = await axios.get('/api/modules/config-sections');
                this.sectionsFlat = data.sections_flat || [];
            } catch (e) {
                console.error('ModuleConfigPanel: error cargando secciones', e);
            } finally {
                this.loading = false;
            }
        },

        typeLabel(type) {
            return TYPE_META[type]?.label || type;
        },

        typeIcon(type) {
            return TYPE_META[type]?.icon || 'cog';
        },

        typeColor(type) {
            return TYPE_META[type]?.color || '#74788d';
        },

        moduleBadge(slug) {
            return slug.replace(/^(core|addon)-/, '').replace(/-/g, ' ');
        },
    },
};
</script>

<style scoped>
/* ── Barra de filtro ─────────────────────────────────────────────────────── */
.config-filter-bar {
    border-bottom: 1px solid rgba(0,0,0,.08);
    padding-bottom: 12px;
}
[data-layout-mode=dark] .config-filter-bar {
    border-bottom-color: rgba(255,255,255,.1);
}

/* q-btn de filtro: Quasar aplica tema oscuro via :dark="darkMode" en el markup;
   aquí solo manejamos el estado activo y el borde en modo claro */
.btn-filter {
    background: transparent;
    border: 1px solid rgba(0,0,0,.15);
    border-radius: 20px;
    padding: 3px 12px;
    font-size: 0.78rem;
    transition: all .15s;
}
.btn-filter:hover,
.btn-filter.active {
    background: var(--bs-primary, #556ee6);
    color: #fff !important;
    border-color: transparent;
}
[data-layout-mode=dark] .btn-filter {
    border-color: rgba(255,255,255,.18);
}

/* ── Encabezado del grupo ───────────────────────────────────────────────── */
.config-group-header {
    border-bottom: 1px solid rgba(0,0,0,.06);
    padding-bottom: 6px;
}
[data-layout-mode=dark] .config-group-header {
    border-bottom-color: rgba(255,255,255,.08);
}

.config-group-badge {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.8rem;
    flex-shrink: 0;
}

/* ── Tile de sección ────────────────────────────────────────────────────── */
/* La clase .card del markup garantiza que el fondo lo gestione el CSS global:
   [data-layout-mode=dark] .card { background-color: #202c33 !important }
   NO sobreescribir background aquí. */
.config-tile {
    display: block;
    position: relative;
    /* sin background propio — lo hereda de .card del sistema */
    transition: box-shadow .15s, transform .15s;
    overflow: hidden;
    min-height: 52px;
}
.config-tile:hover {
    box-shadow: 0 3px 12px rgba(0,0,0,.12);
    transform: translateY(-1px);
}

.config-tile-icon { font-size: 1.1rem; flex-shrink: 0; }

/* Sin color propio — hereda el color de texto de Bootstrap/.card en ambos modos */
.config-tile-label {
    font-size: 0.78rem;
    line-height: 1.2;
    word-break: break-word;
}

.config-tile-module {
    position: absolute;
    bottom: 2px;
    right: 4px;
    font-size: 0.62rem;
    color: #adb5bd;
    text-transform: capitalize;
}
</style>
