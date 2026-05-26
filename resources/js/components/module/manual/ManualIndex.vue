<template>
    <div class="manual-wiki" :class="{ 'sidebar-open': sidebarOpen }">

        <!-- Header superior -->
        <header class="wiki-header">
            <button class="btn btn-light btn-sm d-lg-none me-2" @click="sidebarOpen = !sidebarOpen" aria-label="Abrir menú">
                <i data-feather="menu"></i>
            </button>
            <h1 class="wiki-title mb-0">
                <span class="me-2">📖</span>
                Manual de Usuario - MegaISP
            </h1>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span
                    v-if="lastUpdate"
                    :class="['badge', 'd-none', 'd-md-inline', lastUpdateBadgeClass]"
                    :title="lastUpdateTooltip">
                    {{ lastUpdateStale ? '⚠️ Manual desactualizado' : '🔄 Última regeneración: ' + formatDate(lastUpdate) }}
                </span>
                <button
                    v-if="isDeveloper"
                    class="btn btn-primary btn-sm"
                    :disabled="generating"
                    @click="regenerateAll">
                    <span v-if="generating" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else data-feather="refresh-cw" class="wiki-btn-icon"></i>
                    {{ generating ? 'Generando...' : 'Regenerar Manual Completo' }}
                </button>
            </div>
        </header>

        <div class="wiki-body">

            <!-- Sidebar izquierdo -->
            <aside class="wiki-sidebar">
                <div class="wiki-search">
                    <i data-feather="search" class="wiki-search-icon"></i>
                    <input
                        v-model="search"
                        type="search"
                        class="wiki-search-input"
                        placeholder="Buscar módulo..."
                        aria-label="Buscar"
                    />
                </div>
                <nav class="wiki-nav" aria-label="Navegación del manual">
                    <template v-for="(node, i) in filteredMenu" :key="i">
                        <!-- Item simple -->
                        <a
                            v-if="!node.children"
                            href="#"
                            class="wiki-nav-item wiki-nav-leaf"
                            :class="{ 'active': isActive(node) }"
                            @click.prevent="selectItem(node)">
                            <i :data-feather="node.icon || 'circle'" class="wiki-nav-icon"></i>
                            <span>{{ node.label }}</span>
                        </a>
                        <!-- Grupo colapsable -->
                        <div v-else class="wiki-nav-group">
                            <button
                                type="button"
                                class="wiki-nav-item wiki-nav-group-toggle"
                                @click="toggleGroup(node.label)">
                                <i :data-feather="expanded[node.label] ? 'chevron-down' : 'chevron-right'" class="wiki-nav-chevron"></i>
                                <i :data-feather="node.icon || 'folder'" class="wiki-nav-icon"></i>
                                <span>{{ node.label }}</span>
                            </button>
                            <div v-show="expanded[node.label]" class="wiki-nav-children">
                                <a
                                    v-for="child in node.children"
                                    :key="child.label"
                                    href="#"
                                    class="wiki-nav-item wiki-nav-child"
                                    :class="{ 'active': isActive(child) }"
                                    @click.prevent="selectItem(child)">
                                    {{ child.label }}
                                </a>
                            </div>
                        </div>
                    </template>
                    <div v-if="filteredMenu.length === 0" class="text-muted small p-3">
                        Sin resultados para "{{ search }}".
                    </div>
                </nav>
            </aside>

            <!-- Overlay para mobile -->
            <div v-if="sidebarOpen" class="wiki-overlay d-lg-none" @click="sidebarOpen = false"></div>

            <!-- Panel derecho de contenido -->
            <main class="wiki-content">
                <div v-if="loading" class="wiki-loading">
                    <div class="spinner-border text-primary"></div>
                    <p class="text-muted mt-2 mb-0">Cargando manual...</p>
                </div>

                <div v-else-if="generating" class="wiki-loading">
                    <div class="spinner-border text-primary"></div>
                    <p class="text-muted mt-2 mb-0">
                        Generando manual con Claude API. Esto puede tardar varios minutos...
                    </p>
                </div>

                <div v-else-if="!activeItem" class="wiki-empty">
                    <i data-feather="book-open" class="wiki-empty-icon"></i>
                    <h3>Bienvenido al Manual de MegaISP</h3>
                    <p class="text-muted">Selecciona un módulo del menú izquierdo para ver su documentación.</p>
                </div>

                <article v-else class="wiki-article">
                    <header class="wiki-article-header">
                        <h2 class="wiki-article-title">{{ activeItem.label }}</h2>
                        <div class="wiki-article-meta">
                            <template v-if="activeSection">
                                <span class="badge bg-info-subtle text-info">
                                    <i data-feather="clock" class="badge-icon"></i>
                                    Última actualización: {{ formatDate(activeSection.generated_at) }}
                                </span>
                                <span class="badge bg-light text-muted">v{{ activeSection.version }}</span>
                                <span class="badge bg-light text-muted">
                                    <code>{{ activeSection.module_slug }}</code>
                                </span>
                            </template>
                            <button
                                v-if="isDeveloper"
                                class="btn btn-outline-primary btn-sm"
                                :disabled="generating"
                                title="Regenera el manual completo (incluyendo esta sección)"
                                @click="regenerateAll">
                                <i data-feather="refresh-cw" class="wiki-btn-icon"></i>
                                Regenerar
                            </button>
                        </div>
                    </header>

                    <div v-if="activeSection" class="wiki-markdown" v-html="renderedMarkdown"></div>

                    <div v-else class="wiki-no-content">
                        <i data-feather="file-text" class="wiki-no-content-icon"></i>
                        <h4>Esta sección aún no ha sido generada</h4>
                        <p class="text-muted">
                            No existe documentación para "<strong>{{ activeItem.label }}</strong>" todavía.
                        </p>
                        <p class="text-muted small">
                            Búsqueda intentada: <code>{{ activeItem.search }}</code>
                        </p>
                        <button
                            v-if="isDeveloper"
                            class="btn btn-primary"
                            :disabled="generating"
                            @click="regenerateAll">
                            <i data-feather="zap" class="wiki-btn-icon"></i>
                            Generar ahora
                        </button>
                        <p v-else class="small text-muted mt-3">
                            Pídeselo a un usuario con rol DESARROLLADOR.
                        </p>
                    </div>
                </article>
            </main>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

const MENU = [
    { label: 'Dashboard',        icon: 'home',       search: 'dashboard' },
    { label: 'Actualizaciones',  icon: 'package',    search: 'release' },
    { label: 'Inteligencia Artificial', icon: 'cpu', children: [
        { label: 'Asistente IA',     search: 'iachat' },
        { label: 'Historial',        search: 'iahistorial' },
        { label: 'Mis Prompts',      search: 'iaprompts' },
        { label: 'Configuración IA', search: 'iaconfig' },
    ]},
    { label: 'Planes', icon: 'grid', children: [
        { label: 'Internet',       search: 'internet' },
        { label: 'Voz',            search: 'voise' },
        { label: 'Personalizado',  search: 'custom' },
        { label: 'Paquetes',       search: 'package' },
    ]},
    { label: 'Clientes Potenciales', icon: 'user-x', children: [
        { label: 'Crear',  search: 'crm' },
        { label: 'Listar', search: 'crmlist' },
    ]},
    { label: 'Cliente', icon: 'user-check', children: [
        { label: 'Dashboard', search: 'clientdashboard' },
        { label: 'Crear',     search: 'clientcreate' },
        { label: 'Listar',    search: 'client' },
    ]},
    { label: 'Vendedores', icon: 'users', children: [
        { label: 'Dashboard',           search: 'sellerdashboard' },
        { label: 'Lista de vendedores', search: 'seller' },
        { label: 'Mi panel',            search: 'sellerpanel' },
    ]},
    { label: 'Ticket', icon: 'grid', children: [
        { label: 'Dashboard',     search: 'ticketdashboard' },
        { label: 'Nuevo/Abierto', search: 'ticket' },
        { label: 'Cerrados',      search: 'ticketclose' },
        { label: 'Reciclados',    search: 'ticketrecycling' },
    ]},
    { label: 'Finanzas', icon: 'dollar-sign', children: [
        { label: 'Transacciones',        search: 'transaction' },
        { label: 'Facturas',             search: 'billing' },
        { label: 'Pagos',                search: 'payment' },
        { label: 'Facturas Proforma',    search: 'invoice' },
        { label: 'Contabilidad General', search: 'generalaccounting' },
    ]},
    { label: 'Mensajes', icon: 'mail', children: [
        { label: 'Inbox', search: 'inbox' },
    ]},
    { label: 'Tareas Programadas', icon: 'calendar', children: [
        { label: 'Proyectos',  search: 'project' },
        { label: 'Tareas',     search: 'task' },
        { label: 'Calendario', search: 'calendar' },
        { label: 'Archivados', search: 'archived' },
    ]},
    { label: 'Mapas', icon: 'map',    search: 'maps' },
    { label: 'OLTs',  icon: 'server', search: 'olt' },
    { label: 'Gestión de red', icon: 'box', children: [
        { label: 'Enrutadores', search: 'router' },
        { label: 'Redes IPv4',  search: 'networkip' },
    ]},
    { label: 'Inventario', icon: 'archive', children: [
        { label: 'Almacenes',         search: 'inventorystore' },
        { label: 'Tipo de Artículos', search: 'inventoryitemtype' },
        { label: 'Artículos',         search: 'inventoryitem' },
        { label: 'Movimientos',       search: 'inventorymovement' },
    ]},
    { label: 'Administración', icon: 'command', search: 'admin' },
    { label: 'Configuración',  icon: 'tool',    search: 'config' },
    { label: 'MegaFamilia',    icon: 'shield',  search: 'megafamilia' },
    { label: 'Desarrollador',  icon: 'code',    search: 'devtools' },
];

export default {
    name: 'ManualIndex',
    props: {
        isDeveloper: { type: Boolean, default: false },
    },
    data() {
        return {
            loading: false,
            generating: false,
            sections: [],
            lastUpdate: null,
            activeItem: null,
            search: '',
            sidebarOpen: false,
            expanded: this.initialExpanded(),
            menu: MENU,
        };
    },
    computed: {
        activeSection() {
            return this.findSection(this.activeItem);
        },
        renderedMarkdown() {
            return this.activeSection ? this.renderMarkdown(this.activeSection.content) : '';
        },
        filteredMenu() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.menu;
            const matches = (s) => (s || '').toLowerCase().includes(q);
            const out = [];
            for (const node of this.menu) {
                if (!node.children) {
                    if (matches(node.label)) out.push(node);
                } else {
                    const children = node.children.filter(c => matches(c.label));
                    if (matches(node.label) || children.length > 0) {
                        out.push({ ...node, children: children.length ? children : node.children });
                    }
                }
            }
            return out;
        },
        lastUpdateAgeDays() {
            if (!this.lastUpdate) return null;
            const d = new Date(this.lastUpdate);
            if (Number.isNaN(d.getTime())) return null;
            return (Date.now() - d.getTime()) / (1000 * 60 * 60 * 24);
        },
        lastUpdateStale() {
            return this.lastUpdateAgeDays !== null && this.lastUpdateAgeDays > 7;
        },
        lastUpdateBadgeClass() {
            return this.lastUpdateStale
                ? 'bg-warning-subtle text-warning border border-warning-subtle'
                : 'bg-light text-muted';
        },
        lastUpdateTooltip() {
            if (!this.lastUpdate) return '';
            const days = this.lastUpdateAgeDays;
            const txt = 'Última regeneración: ' + this.formatDate(this.lastUpdate);
            return days !== null ? `${txt} (hace ${Math.floor(days)} días)` : txt;
        },
    },
    watch: {
        search(v) {
            if (v.trim()) {
                for (const node of this.menu) {
                    if (node.children) this.expanded[node.label] = true;
                }
            }
            this.refreshFeather();
        },
        expanded: { handler() { this.refreshFeather(); }, deep: true },
        activeItem() { this.refreshFeather(); },
    },
    mounted() {
        this.loadSections();
        this.refreshFeather();
    },
    updated() {
        this.refreshFeather();
    },
    methods: {
        initialExpanded() {
            const map = {};
            for (const node of MENU) {
                if (node.children) map[node.label] = false;
            }
            return map;
        },
        toggleGroup(label) {
            this.expanded[label] = !this.expanded[label];
        },
        isActive(item) {
            return this.activeItem && this.activeItem.label === item.label
                && this.activeItem.search === item.search;
        },
        selectItem(item) {
            this.activeItem = item;
            if (window.innerWidth < 992) this.sidebarOpen = false;
        },
        findSection(item) {
            if (!item || !this.sections.length) return null;
            const needle = (item.search || '').toLowerCase();
            if (!needle) return null;

            // 1. Match exacto por module_slug
            const exact = this.sections.find(s => (s.module_slug || '').toLowerCase() === needle);
            if (exact) return exact;

            // 2. Match por inclusión bidireccional sobre module_slug
            const incl = this.sections.find(s => {
                const slug = (s.module_slug || '').toLowerCase();
                return slug.includes(needle) || needle.includes(slug);
            });
            if (incl) return incl;

            // 3. Fallback: match por title con item.label
            const label = (item.label || '').toLowerCase();
            const byTitle = this.sections.find(s =>
                (s.title || '').toLowerCase().includes(label)
            );
            return byTitle || null;
        },
        async loadSections() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/manual/sections');
                const flat = [];
                for (const g of (data.groups || [])) {
                    for (const s of (g.sections || [])) flat.push(s);
                }
                this.sections = flat;
                this.lastUpdate = data.last_update || null;
            } catch (e) {
                console.error('[Manual] Error al cargar secciones', e);
            } finally {
                this.loading = false;
                this.refreshFeather();
            }
        },
        async regenerateAll() {
            if (!this.isDeveloper || this.generating) return;
            if (!confirm('¿Regenerar el manual completo? Esto consumirá tokens de la API de Claude y puede tardar varios minutos.')) {
                return;
            }
            this.generating = true;
            try {
                const { data } = await axios.post('/api/manual/generate');
                if (data.errors && data.errors.length) {
                    alert('Se generaron ' + data.generated + ' secciones con ' + data.errors.length + ' errores.');
                } else {
                    alert('Manual regenerado: ' + data.generated + ' secciones.');
                }
                await this.loadSections();
            } catch (e) {
                console.error('[Manual] Error al regenerar', e);
                alert('Error al regenerar el manual: ' + (e.response?.data?.message || e.message));
            } finally {
                this.generating = false;
            }
        },
        refreshFeather() {
            this.$nextTick(() => {
                if (window.feather && typeof window.feather.replace === 'function') {
                    try { window.feather.replace(); } catch (e) { /* no-op */ }
                }
            });
        },
        formatDate(value) {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('es-MX', {
                year: 'numeric', month: 'short', day: '2-digit',
                hour: '2-digit', minute: '2-digit',
            });
        },
        renderMarkdown(md) {
            if (!md) return '';
            const esc = s => s
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const lines = md.split(/\r?\n/);
            let html = '';
            let inList = null;
            let i = 0;
            const closeList = () => { if (inList) { html += `</${inList}>`; inList = null; } };

            while (i < lines.length) {
                const line = lines[i].replace(/\s+$/, '');
                const next = i + 1 < lines.length ? lines[i + 1].trim() : '';

                // Tabla pipe (| ... | + línea separadora |---|---|)
                if (line.startsWith('|') && line.endsWith('|') && /^\|[\s:|-]+\|$/.test(next)) {
                    closeList();
                    const headers = line.split('|').slice(1, -1).map(s => s.trim());
                    i += 2;
                    const rows = [];
                    while (i < lines.length && lines[i].trim().startsWith('|') && lines[i].trim().endsWith('|')) {
                        const cells = lines[i].split('|').slice(1, -1).map(s => s.trim());
                        rows.push(cells);
                        i++;
                    }
                    html += '<table class="wiki-table"><thead><tr>';
                    for (const h of headers) html += `<th>${this.inline(esc(h))}</th>`;
                    html += '</tr></thead><tbody>';
                    for (const row of rows) {
                        html += '<tr>';
                        for (const c of row) html += `<td>${this.inline(esc(c))}</td>`;
                        html += '</tr>';
                    }
                    html += '</tbody></table>';
                    continue;
                }

                // Regla horizontal
                if (/^---+$/.test(line.trim())) {
                    closeList();
                    html += '<hr>';
                    i++;
                    continue;
                }

                let m;
                if ((m = line.match(/^####\s+(.*)$/))) {
                    closeList();
                    html += `<h6 id="${this.slugify(m[1])}">${this.inline(esc(m[1]))}</h6>`;
                    i++; continue;
                }
                if ((m = line.match(/^###\s+(.*)$/))) {
                    closeList();
                    html += `<h5 id="${this.slugify(m[1])}">${this.inline(esc(m[1]))}</h5>`;
                    i++; continue;
                }
                if ((m = line.match(/^##\s+(.*)$/))) {
                    closeList();
                    html += `<h4 id="${this.slugify(m[1])}" class="wiki-h2">${this.inline(esc(m[1]))}</h4>`;
                    i++; continue;
                }
                if ((m = line.match(/^#\s+(.*)$/))) {
                    closeList();
                    html += `<h3 id="${this.slugify(m[1])}">${this.inline(esc(m[1]))}</h3>`;
                    i++; continue;
                }
                if ((m = line.match(/^\s*[-*]\s+(.*)$/))) {
                    if (inList !== 'ul') { closeList(); html += '<ul>'; inList = 'ul'; }
                    html += `<li>${this.inline(esc(m[1]))}</li>`;
                    i++; continue;
                }
                if ((m = line.match(/^\s*\d+\.\s+(.*)$/))) {
                    if (inList !== 'ol') { closeList(); html += '<ol>'; inList = 'ol'; }
                    html += `<li>${this.inline(esc(m[1]))}</li>`;
                    i++; continue;
                }
                if (line.trim() === '') { closeList(); i++; continue; }
                closeList();
                html += `<p>${this.inline(esc(line))}</p>`;
                i++;
            }
            closeList();
            return html;
        },
        inline(s) {
            return s
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>');
        },
        slugify(text) {
            return (text || '')
                .toString()
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[¿?¡!.,;:'"()]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },
    },
};
</script>

<style scoped>
.manual-wiki {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 70px);
    background: #fff;
}

.wiki-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    flex-shrink: 0;
}
.wiki-title {
    font-size: 1.15rem;
    font-weight: 600;
    color: #111827;
}
.wiki-btn-icon {
    width: 14px;
    height: 14px;
    margin-right: 4px;
    vertical-align: -2px;
}
.badge-icon {
    width: 12px;
    height: 12px;
    margin-right: 4px;
    vertical-align: -2px;
}

.wiki-body {
    display: flex;
    flex: 1;
    min-height: 0;
    position: relative;
}

.wiki-sidebar {
    width: 280px;
    flex-shrink: 0;
    background: #f8f9fa;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.wiki-search {
    position: relative;
    padding: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
}
.wiki-search-icon {
    position: absolute;
    left: 1.4rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    width: 14px;
    height: 14px;
}
.wiki-search-input {
    width: 100%;
    padding: 0.4rem 0.6rem 0.4rem 1.9rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    background: #fff;
}
.wiki-search-input:focus {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
}
.wiki-nav {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
}
.wiki-nav-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    width: 100%;
    padding: 0.45rem 0.9rem;
    border: 0;
    background: transparent;
    color: #374151;
    font-size: 0.875rem;
    text-align: left;
    text-decoration: none;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background-color 0.12s, color 0.12s;
}
.wiki-nav-item:hover {
    background: rgba(13, 110, 253, 0.06);
    color: #0d6efd;
}
.wiki-nav-icon {
    width: 16px;
    height: 16px;
    color: #6b7280;
    flex-shrink: 0;
}
.wiki-nav-item:hover .wiki-nav-icon,
.wiki-nav-item:hover .wiki-nav-chevron { color: #0d6efd; }
.wiki-nav-chevron {
    width: 13px;
    height: 13px;
    color: #9ca3af;
    flex-shrink: 0;
}
.wiki-nav-group-toggle {
    font-weight: 500;
}
.wiki-nav-children {
    padding-left: 0;
    background: rgba(0,0,0,0.015);
}
.wiki-nav-child {
    padding-left: 2.4rem;
    font-size: 0.84rem;
    color: #4b5563;
}
.wiki-nav-item.active {
    background: rgba(13, 110, 253, 0.10);
    color: #0d6efd;
    font-weight: 600;
    border-left-color: #0d6efd;
}
.wiki-nav-item.active .wiki-nav-icon { color: #0d6efd; }

.wiki-content {
    flex: 1;
    min-width: 0;
    overflow-y: auto;
    background: #fff;
    padding: 1.75rem 2rem;
}
.wiki-loading, .wiki-empty {
    text-align: center;
    padding: 4rem 1rem;
    color: #6b7280;
}
.wiki-empty-icon {
    width: 52px;
    height: 52px;
    color: #d1d5db;
    margin-bottom: 1rem;
}
.wiki-empty h3 {
    color: #374151;
    margin-bottom: 0.5rem;
}
.wiki-article {
    max-width: 860px;
    margin: 0 auto;
}
.wiki-article-header {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}
.wiki-article-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.5rem;
}
.wiki-article-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.wiki-no-content {
    text-align: center;
    padding: 3rem 1rem;
    background: #f9fafb;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
}
.wiki-no-content-icon {
    width: 40px;
    height: 40px;
    color: #9ca3af;
    margin-bottom: 1rem;
}
.wiki-no-content h4 {
    color: #374151;
    margin-bottom: 0.5rem;
}

.wiki-markdown {
    font-size: 15px;
    line-height: 1.65;
    color: #374151;
}
.wiki-markdown :deep(h3) {
    font-size: 1.45rem;
    margin-top: 1.75rem;
    margin-bottom: 0.75rem;
    color: #111827;
}
.wiki-markdown :deep(h4) {
    font-size: 1.25rem;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #1f2937;
}
/* Separador entre secciones H2 (renderizadas como h4.wiki-h2) */
.wiki-markdown :deep(h4.wiki-h2:not(:first-child)) {
    border-top: 1px solid #e5e7eb;
    padding-top: 1.5rem;
    margin-top: 2.25rem;
}
.wiki-markdown :deep(h5) {
    font-size: 1.08rem;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    color: #374151;
}
.wiki-markdown :deep(p) {
    font-size: 15px;
    line-height: 1.65;
    color: #374151;
    margin-bottom: 0.85rem;
}
.wiki-markdown :deep(ul),
.wiki-markdown :deep(ol) {
    padding-left: 1.5rem;
    margin-bottom: 0.85rem;
    line-height: 1.65;
    font-size: 15px;
}
.wiki-markdown :deep(code) {
    background: #f3f4f6;
    color: #be185d;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 0.9em;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.wiki-markdown :deep(strong) { color: #111827; }
.wiki-markdown :deep(a) {
    color: #0d6efd;
    text-decoration: none;
}
.wiki-markdown :deep(a:hover) { text-decoration: underline; }
.wiki-markdown :deep(hr) {
    border: 0;
    border-top: 1px solid #e5e7eb;
    margin: 2rem 0;
}
.wiki-markdown :deep(table.wiki-table) {
    border-collapse: collapse;
    width: 100%;
    margin: 1rem 0 1.25rem;
    font-size: 14px;
}
.wiki-markdown :deep(table.wiki-table th),
.wiki-markdown :deep(table.wiki-table td) {
    border: 1px solid #e5e7eb;
    padding: 0.5rem 0.75rem;
    text-align: left;
    vertical-align: top;
}
.wiki-markdown :deep(table.wiki-table th) {
    background: #f9fafb;
    font-weight: 600;
    color: #111827;
}
.wiki-markdown :deep(table.wiki-table tr:nth-child(even) td) {
    background: #fafbfc;
}

.wiki-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.35);
    z-index: 5;
}
@media (max-width: 991.98px) {
    .wiki-sidebar {
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 10;
        transform: translateX(-100%);
        transition: transform 0.2s ease;
        box-shadow: 2px 0 8px rgba(0,0,0,0.08);
    }
    .manual-wiki.sidebar-open .wiki-sidebar {
        transform: translateX(0);
    }
    .wiki-content {
        padding: 1.25rem 1rem;
    }
    .wiki-article-title {
        font-size: 1.4rem;
    }
}
</style>
