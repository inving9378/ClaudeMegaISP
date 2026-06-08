<template>
    <div class="admin-panel">

        <!-- Barra de filtro por categoría -->
        <div class="config-filter-bar mb-4">
            <button
                :class="['cfg-pill', activeCategory === null ? 'active' : '']"
                @click="activeCategory = null"
            >
                Todas
                <span class="cfg-pill-count">{{ cards.length }}</span>
            </button>
            <button
                v-for="cat in categories"
                :key="cat"
                :class="['cfg-pill', activeCategory === cat ? 'active' : '']"
                @click="activeCategory = cat"
            >
                {{ cat }}
                <span class="cfg-pill-count">{{ countByCategory(cat) }}</span>
            </button>
        </div>

        <!-- Estado de carga -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted small">Cargando secciones…</p>
        </div>

        <div v-else-if="visibleGroups.length === 0" class="config-empty">
            <i class="fa fa-th-large fa-2x mb-2 opacity-25"></i>
            <p class="mb-0 text-muted small">No hay módulos con tarjetas de administración instalados.</p>
        </div>

        <!-- Grupos por categoría -->
        <template v-else>
            <div
                v-for="group in visibleGroups"
                :key="group.category"
                class="admin-group mb-4"
            >
                <!-- Encabezado del grupo -->
                <div class="config-group-header mb-3">
                    <span class="cfg-group-badge" :style="{ background: catColor(group.category) }">
                        <i :class="'fa fa-fw fa-' + catIcon(group.category)"></i>
                    </span>
                    <span class="cfg-group-title">{{ group.category }}</span>
                    <span class="cfg-group-count">{{ group.cards.length }}</span>
                    <span class="cfg-group-line"></span>
                </div>

                <!-- Tarjetas -->
                <div class="row g-3">
                    <div
                        v-for="card in group.cards"
                        :key="card._module + '_' + card.title"
                        class="col-xl-3 col-lg-4 col-md-6"
                    >
                        <a :href="card.url || '#'" class="text-decoration-none">
                            <div class="card h-100 admin-card" :style="{ '--accent': catColor(group.category) }">
                                <div class="card-body d-flex align-items-start gap-3 p-3">
                                    <div class="admin-card-icon" :style="{ background: catColor(group.category) }">
                                        <i :class="'fa fa-fw fa-' + (card.icon || 'cube')"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <h6 class="card-title mb-1 text-truncate">{{ card.title }}</h6>
                                        <p class="card-text text-muted small mb-0 admin-card-desc">
                                            {{ card.description }}
                                        </p>
                                    </div>
                                </div>
                                <div class="admin-card-module">
                                    <i class="fa fa-puzzle-piece me-1"></i>{{ moduleBadge(card._module) }}
                                </div>
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

const CATEGORY_META = {
    'Sistema':            { icon: 'cog',             color: '#556ee6' },
    'Usuarios':           { icon: 'users',           color: '#50a5f1' },
    'Finanzas':           { icon: 'dollar-sign',     color: '#f1b44c' },
    'Red':                { icon: 'network-wired',   color: '#e83e8c' },
    'Localización':       { icon: 'map-marker-alt',  color: '#34c38f' },
    'Operación':          { icon: 'headset',         color: '#6f42c1' },
    'IA y Marketing':     { icon: 'robot',           color: '#fd7e14' },
    'Auditoría y Docs':   { icon: 'clipboard-list',  color: '#74788d' },
    'Otros':              { icon: 'th-large',        color: '#adb5bd' },
};

const CATEGORY_ORDER = [
    'Sistema', 'Usuarios', 'Finanzas', 'Red', 'Localización',
    'Operación', 'IA y Marketing', 'Auditoría y Docs', 'Otros',
];

const SLUG_CATEGORY = {
    'addon-cobranza-blaster':      'Finanzas',
    'addon-demo':                  'Sistema',
    'addon-devtools':              'Sistema',
    'addon-embajadores':           'Finanzas',
    'addon-evaluador-empresarial': 'Operación',
    'addon-finanzas':              'Finanzas',
    'addon-flotas':                'Red',
    'addon-gestion-red':           'Red',
    'addon-hub':                   'IA y Marketing',
    'addon-ia':                    'IA y Marketing',
    'addon-inventario':            'Red',
    'addon-manual':                'Auditoría y Docs',
    'addon-mapas':                 'Red',
    'addon-marketing':             'IA y Marketing',
    'addon-megafamilia':           'Operación',
    'addon-mensajes':              'Operación',
    'addon-payments':              'Finanzas',
    'addon-planes':                'Red',
    'addon-reportes':              'IA y Marketing',
    'addon-roadmap':               'Sistema',
    'addon-scheduling':            'Sistema',
    'addon-smart-import-export':   'Auditoría y Docs',
    'addon-tickets':               'Operación',
    'addon-vendedores':            'Finanzas',
    'addon-warroom':               'IA y Marketing',
    'addon-whatsapp-agent':        'IA y Marketing',
    'core-auditoria':              'Auditoría y Docs',
    'core-configuracion':          'Sistema',
    'core-documentacion':          'Auditoría y Docs',
    'core-localizacion':           'Localización',
    'core-module-manager':         'Sistema',
    'core-release':                'Sistema',
    'core-usuarios':               'Usuarios',
};

const URL_CATEGORY = {
    '/administracion/socios':            'Finanzas',
    '/administracion/ift':               'Localización',
    '/administracion/metotdo-de-pago':   'Finanzas',
};

export default {
    name: 'AdminPanel',

    props: {
        csrfToken: { type: String, default: '' },
    },

    setup() {
        return { darkMode };
    },

    data() {
        return {
            cards: [],
            loading: true,
            activeCategory: null,
        };
    },

    computed: {
        categories() {
            const present = new Set(this.cards.map(c => this.cardCategory(c)));
            return CATEGORY_ORDER.filter(c => present.has(c));
        },

        filteredCards() {
            if (!this.activeCategory) return this.cards;
            return this.cards.filter(c => this.cardCategory(c) === this.activeCategory);
        },

        visibleGroups() {
            const groups = {};
            for (const card of this.filteredCards) {
                const cat = this.cardCategory(card);
                if (!groups[cat]) groups[cat] = { category: cat, cards: [] };
                groups[cat].cards.push(card);
            }
            return CATEGORY_ORDER
                .filter(cat => groups[cat])
                .map(cat => groups[cat]);
        },
    },

    mounted() {
        this.loadCards();
    },

    methods: {
        async loadCards() {
            try {
                const { data } = await axios.get('/admin/administracion/cards');
                this.cards = data.cards || [];
            } catch (e) {
                console.error('AdminPanel: error cargando tarjetas', e);
            } finally {
                this.loading = false;
            }
        },

        cardCategory(card) {
            if (card.category && CATEGORY_META[card.category]) return card.category;
            if (card.url && URL_CATEGORY[card.url]) return URL_CATEGORY[card.url];
            return SLUG_CATEGORY[card._module] || 'Otros';
        },

        countByCategory(cat) {
            return this.cards.filter(c => this.cardCategory(c) === cat).length;
        },

        catIcon(cat)  { return CATEGORY_META[cat]?.icon  || 'th-large'; },
        catColor(cat) { return CATEGORY_META[cat]?.color || '#adb5bd'; },

        moduleBadge(slug) {
            return (slug || '').replace(/^(core|addon)-/, '').replace(/-/g, ' ');
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
    gap: 5px;
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

.cfg-pill-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    padding: 0 5px;
    border-radius: 10px;
    background: rgba(0,0,0,.08);
    font-size: 0.68rem;
    line-height: 16px;
    transition: background .15s, color .15s;
}
.cfg-pill.active .cfg-pill-count {
    background: rgba(255,255,255,.25);
    color: #fff;
}
[data-layout-mode=dark] .cfg-pill-count {
    background: rgba(255,255,255,.12);
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

/* ── Tarjeta ─────────────────────────────────────────────────────────────── */
.admin-card {
    position: relative;
    border-radius: 10px;
    transition: box-shadow .18s, transform .18s;
    overflow: hidden;
}
.admin-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--accent, #556ee6);
    opacity: 0;
    transition: opacity .18s;
    border-radius: 10px 10px 0 0;
}
.admin-card:hover {
    box-shadow: 0 4px 18px rgba(0,0,0,.13);
    transform: translateY(-2px);
}
.admin-card:hover::before {
    opacity: 1;
}

.admin-card-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.admin-card-desc {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-clamp: 2;
}

.admin-card-module {
    padding: 4px 12px 8px;
    font-size: 0.68rem;
    color: #adb5bd;
    text-transform: capitalize;
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
