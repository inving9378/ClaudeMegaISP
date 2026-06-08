<template>
    <div class="module-config-panel">

        <!-- Barra de filtro por categoría -->
        <div class="config-filter-bar mb-4">
            <button
                :class="['cfg-pill', activeCategory === null ? 'active' : '']"
                @click="activeCategory = null"
            >Todas</button>
            <button
                v-for="cat in categories"
                :key="cat"
                :class="['cfg-pill', activeCategory === cat ? 'active' : '']"
                @click="activeCategory = cat"
            >{{ cat }}</button>
        </div>

        <!-- Estado de carga -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted small">Cargando secciones…</p>
        </div>

        <div v-else-if="visibleGroups.length === 0" class="config-empty">
            <i class="fa fa-cog fa-2x mb-2 opacity-25"></i>
            <p class="mb-0 text-muted small">No hay secciones de configuración disponibles.</p>
        </div>

        <!-- Grupos por type -->
        <template v-else>
            <div
                v-for="group in visibleGroups"
                :key="group.type"
                class="config-group mb-4"
            >
                <!-- Encabezado del grupo -->
                <div class="config-group-header mb-3">
                    <span class="cfg-group-badge" :style="{ background: typeColor(group.type) }">
                        <i :class="'fa fa-fw fa-' + typeIcon(group.type)"></i>
                    </span>
                    <span class="cfg-group-title">{{ typeLabel(group.type) }}</span>
                    <span class="cfg-group-count">{{ group.sections.length }}</span>
                    <span class="cfg-group-line"></span>
                </div>

                <!-- Tiles -->
                <div class="row g-3">
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
                            <div class="card-body text-center px-2 py-3">
                                <div class="cfg-icon-wrap mb-2" :style="{ background: iconBg(section.type), color: typeColor(section.type) }">
                                    <i :class="'fa fa-fw fa-' + (section.icon || 'cog')"></i>
                                </div>
                                <p class="cfg-tile-label mb-0">{{ section.label }}</p>
                            </div>
                            <span v-if="section._module && section._module !== 'core-configuracion'" class="cfg-module-badge">
                                {{ moduleBadge(section._module) }}
                            </span>
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
    general:        { label: 'General',        icon: 'cog',          color: '#556ee6', bg: 'rgba(85,110,230,.13)' },
    pagos:          { label: 'Pagos',           icon: 'credit-card',  color: '#f1b44c', bg: 'rgba(241,180,76,.13)' },
    notificaciones: { label: 'Notificaciones',  icon: 'bell',         color: '#34c38f', bg: 'rgba(52,195,143,.13)' },
    scheduling:     { label: 'Programación',    icon: 'clock',        color: '#ffa726', bg: 'rgba(255,167,38,.13)' },
    api_key:        { label: 'API / Claves',    icon: 'key',          color: '#50a5f1', bg: 'rgba(80,165,241,.13)' },
    herramientas:   { label: 'Herramientas',    icon: 'tools',        color: '#74788d', bg: 'rgba(116,120,141,.13)' },
    webhook:        { label: 'Webhooks',        icon: 'plug',         color: '#e83e8c', bg: 'rgba(232,62,140,.13)' },
};

export default {
    name: 'ModuleConfigPanel',

    props: {
        baseUrl: { type: String, default: '' },
    },

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
        categories() {
            const cats = [...new Set(this.sectionsFlat.map(s => s.category).filter(Boolean))];
            return cats.sort();
        },

        filteredSections() {
            if (!this.activeCategory) return this.sectionsFlat;
            return this.sectionsFlat.filter(s => s.category === this.activeCategory);
        },

        visibleGroups() {
            const groups = {};
            for (const section of this.filteredSections) {
                const t = section.type || 'general';
                if (!groups[t]) groups[t] = { type: t, sections: [] };
                groups[t].sections.push(section);
            }
            const order = ['general', 'pagos', 'notificaciones', 'api_key', 'scheduling', 'herramientas', 'webhook'];
            return Object.values(groups)
                .sort((a, b) => {
                    const ia = order.indexOf(a.type);
                    const ib = order.indexOf(b.type);
                    return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
                })
                .filter(g => g.sections.length > 0);
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

        typeLabel(type)  { return TYPE_META[type]?.label  || type; },
        typeIcon(type)   { return TYPE_META[type]?.icon   || 'cog'; },
        typeColor(type)  { return TYPE_META[type]?.color  || '#74788d'; },
        iconBg(type)     { return TYPE_META[type]?.bg     || 'rgba(116,120,141,.13)'; },

        moduleBadge(slug) {
            return slug.replace(/^(core|addon)-/, '').replace(/-/g, ' ');
        },
    },
};
</script>

<style scoped>
/* ── Barra de filtro ─────────────────────────────────────────────────────── */
.config-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(0,0,0,.08);
}
[data-layout-mode=dark] .config-filter-bar {
    border-bottom-color: rgba(255,255,255,.1);
}

.cfg-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 500;
    border: 1.5px solid rgba(0,0,0,.12);
    background: transparent;
    color: inherit;
    cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
    line-height: 1.4;
}
.cfg-pill:hover {
    border-color: var(--bs-primary, #556ee6);
    color: var(--bs-primary, #556ee6);
}
.cfg-pill.active {
    background: var(--bs-primary, #556ee6);
    border-color: var(--bs-primary, #556ee6);
    color: #fff;
}
[data-layout-mode=dark] .cfg-pill {
    border-color: rgba(255,255,255,.18);
}
[data-layout-mode=dark] .cfg-pill:hover {
    border-color: var(--bs-primary, #556ee6);
    color: var(--bs-primary, #556ee6);
}

/* ── Encabezado del grupo ───────────────────────────────────────────────── */
.config-group-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cfg-group-badge {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.72rem;
    flex-shrink: 0;
}

.cfg-group-title {
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

.cfg-group-count {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 1px 7px;
    border-radius: 20px;
    background: rgba(0,0,0,.07);
    color: #74788d;
    flex-shrink: 0;
}
[data-layout-mode=dark] .cfg-group-count {
    background: rgba(255,255,255,.1);
    color: #adb5bd;
}

.cfg-group-line {
    flex: 1;
    height: 1px;
    background: rgba(0,0,0,.07);
}
[data-layout-mode=dark] .cfg-group-line {
    background: rgba(255,255,255,.08);
}

/* ── Tile ───────────────────────────────────────────────────────────────── */
.config-tile {
    display: block;
    position: relative;
    border-radius: 10px;
    transition: box-shadow .18s, transform .18s;
    overflow: hidden;
    min-height: 90px;
}
.config-tile:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.13);
    transform: translateY(-2px);
}
/* acento superior de color al hacer hover */
.config-tile::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: currentColor;
    opacity: 0;
    transition: opacity .18s;
    border-radius: 10px 10px 0 0;
}
.config-tile:hover::before {
    opacity: .35;
}

.cfg-icon-wrap {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.cfg-tile-label {
    font-size: 0.74rem;
    font-weight: 500;
    line-height: 1.3;
    word-break: break-word;
    /* hereda el color de texto del tema */
}

/* badge del módulo en esquina inferior derecha */
.cfg-module-badge {
    position: absolute;
    bottom: 4px;
    right: 6px;
    font-size: 0.6rem;
    color: #adb5bd;
    text-transform: capitalize;
    line-height: 1;
}

/* ── Empty state ────────────────────────────────────────────────────────── */
.config-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 0;
}
</style>
