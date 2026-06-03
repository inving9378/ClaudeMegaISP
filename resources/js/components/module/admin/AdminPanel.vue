<template>
    <div class="admin-panel">

        <!-- Barra de filtro por categoría -->
        <div class="admin-filter-bar mb-3 d-flex flex-wrap gap-2 align-items-center">
            <q-btn
                :class="['btn-filter', activeCategory === null ? 'active' : '']"
                :dark="darkMode"
                @click="activeCategory = null"
                size="sm"
            >Todas <span class="filter-count">{{ cards.length }}</span></q-btn>
            <q-btn
                v-for="cat in categories"
                :key="cat"
                :class="['btn-filter', activeCategory === cat ? 'active' : '']"
                :dark="darkMode"
                @click="activeCategory = cat"
                size="sm"
            >{{ cat }} <span class="filter-count">{{ countByCategory(cat) }}</span></q-btn>
        </div>

        <!-- Estado de carga -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted small">Cargando secciones…</p>
        </div>

        <div v-else-if="visibleGroups.length === 0" class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fa fa-th-large fa-3x mb-3 d-block opacity-25"></i>
                No hay módulos con tarjetas de administración instalados.
            </div>
        </div>

        <!-- Grupos por categoría -->
        <template v-else>
            <div
                v-for="group in visibleGroups"
                :key="group.category"
                class="admin-group mb-4"
            >
                <!-- Encabezado del grupo -->
                <div class="admin-group-header d-flex align-items-center mb-3">
                    <span class="admin-group-badge me-2" :style="{ background: catColor(group.category) }">
                        <i :class="'fa fa-fw fa-' + catIcon(group.category)"></i>
                    </span>
                    <h6 class="mb-0 fw-semibold">
                        {{ group.category }}
                        <span class="text-muted fw-normal ms-1 small">({{ group.cards.length }})</span>
                    </h6>
                </div>

                <!-- Tarjetas de la categoría -->
                <div class="row g-3">
                    <div
                        v-for="card in group.cards"
                        :key="card._module + '_' + card.title"
                        class="col-xl-3 col-lg-4 col-md-6"
                    >
                        <a :href="card.url || '#'" class="text-decoration-none">
                            <div class="card h-100 admin-card">
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

// Metadatos de cada categoría: etiqueta (la propia key), icono FA5 y color del badge.
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

// Orden determinista de las categorías en la vista.
const CATEGORY_ORDER = [
    'Sistema', 'Usuarios', 'Finanzas', 'Red', 'Localización',
    'Operación', 'IA y Marketing', 'Auditoría y Docs', 'Otros',
];

// Categoría por defecto según el slug del módulo. Un módulo nuevo que declare
// "category" en su admin_card del module.json tiene prioridad sobre este mapa.
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

// Overrides por URL para módulos que agrupan varias tarjetas en categorías distintas
// (p.ej. core-configuracion declara Socios/IFT/Métodos de Pago además de su propia config).
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

    // darkMode (ref de appConfig) expuesto al template; el resto usa Options API.
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
        // Categorías presentes, en el orden determinista definido arriba.
        categories() {
            const present = new Set(this.cards.map(c => this.cardCategory(c)));
            return CATEGORY_ORDER.filter(c => present.has(c));
        },

        filteredCards() {
            if (!this.activeCategory) return this.cards;
            return this.cards.filter(c => this.cardCategory(c) === this.activeCategory);
        },

        // Tarjetas agrupadas por categoría, en orden determinista.
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

        // Resolución de categoría: card.category (manifest) > URL override > slug map > 'Otros'.
        cardCategory(card) {
            if (card.category && CATEGORY_META[card.category]) return card.category;
            if (card.url && URL_CATEGORY[card.url]) return URL_CATEGORY[card.url];
            return SLUG_CATEGORY[card._module] || 'Otros';
        },

        countByCategory(cat) {
            return this.cards.filter(c => this.cardCategory(c) === cat).length;
        },

        catIcon(cat) {
            return CATEGORY_META[cat]?.icon || 'th-large';
        },

        catColor(cat) {
            return CATEGORY_META[cat]?.color || '#adb5bd';
        },

        moduleBadge(slug) {
            return (slug || '').replace(/^(core|addon)-/, '').replace(/-/g, ' ');
        },
    },
};
</script>

<style scoped>
/* ── Barra de filtro ─────────────────────────────────────────────────────── */
.admin-filter-bar {
    border-bottom: 1px solid rgba(0,0,0,.08);
    padding-bottom: 12px;
}
[data-layout-mode=dark] .admin-filter-bar {
    border-bottom-color: rgba(255,255,255,.1);
}

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
.btn-filter.active .filter-count,
.btn-filter:hover .filter-count {
    background: rgba(255,255,255,.25);
    color: #fff;
}
[data-layout-mode=dark] .btn-filter {
    border-color: rgba(255,255,255,.18);
}
.filter-count {
    display: inline-block;
    min-width: 18px;
    padding: 0 5px;
    margin-left: 4px;
    border-radius: 10px;
    background: rgba(0,0,0,.08);
    font-size: 0.68rem;
    line-height: 16px;
    text-align: center;
}
[data-layout-mode=dark] .filter-count {
    background: rgba(255,255,255,.12);
}

/* ── Encabezado del grupo ───────────────────────────────────────────────── */
.admin-group-header {
    border-bottom: 1px solid rgba(0,0,0,.06);
    padding-bottom: 6px;
}
[data-layout-mode=dark] .admin-group-header {
    border-bottom-color: rgba(255,255,255,.08);
}
.admin-group-badge {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.85rem;
    flex-shrink: 0;
}

/* ── Tarjeta ─────────────────────────────────────────────────────────────── */
/* .card del sistema gestiona el fondo en modo claro/oscuro — no sobreescribir. */
.admin-card {
    position: relative;
    transition: box-shadow 0.15s, transform 0.15s;
    border: 1px solid rgba(0,0,0,.08);
    overflow: hidden;
}
.admin-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
    transform: translateY(-2px);
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
    line-clamp: 2;
    -webkit-line-clamp: 2;
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
}
.admin-card-module {
    padding: 4px 12px 8px;
    font-size: 0.68rem;
    color: #adb5bd;
    text-transform: capitalize;
}
</style>
