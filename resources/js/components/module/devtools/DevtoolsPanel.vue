<template>
    <div class="devtools-root" :data-theme="currentTheme" ref="rootRef">
        <!-- ============================================================ -->
        <!-- COLUMNA 1: SIDEBAR NAV (colapsable, persistido en localStorage) -->
        <!-- ============================================================ -->
        <div class="dt-sidebar" :class="{ collapsed: sidebarCollapsed }">
            <button
                class="dt-sidebar-toggle"
                @click="toggleSidebar"
                :title="sidebarCollapsed ? 'Expandir menú' : 'Colapsar menú'"
            >
                <i class="fas fa-bars"></i>
            </button>
            <nav class="dt-nav">
                <a
                    v-for="item in placeholderNav"
                    :key="item.route"
                    :href="item.route"
                    class="dt-nav-item"
                    :class="{ active: item.active }"
                    :title="sidebarCollapsed ? item.label : ''"
                >
                    <i :class="item.icon" class="dt-nav-icon"></i>
                    <span class="dt-nav-label">{{ item.label }}</span>
                </a>
                <div class="dt-nav-scaffold-note" v-if="!sidebarCollapsed">
                    Items completos se cargan en 3B
                </div>
            </nav>
        </div>

        <!-- ============================================================ -->
        <!-- COLUMNA 2: CLAUDE CHAT (scaffold — lógica llega en 3B) -->
        <!-- ============================================================ -->
        <div class="dt-chat">
            <div class="dt-chat-header">
                <span class="dt-chat-title">
                    <i class="fas fa-robot"></i> Claude AI
                    <small class="dt-chat-subtitle">{{ userName }}</small>
                </span>
                <button class="dt-icon-btn" @click="close" title="Cerrar DevTools">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="dt-messages">
                <p class="dt-empty-state">
                    <i class="fas fa-tools"></i><br />
                    Scaffold 3A — chat + adjuntos + voz se conectan en 3B-3D.
                </p>
            </div>
            <div class="dt-input-bar">
                <textarea
                    class="dt-textarea"
                    placeholder="Pregunta a Claude (disabled en scaffold)…"
                    disabled
                    rows="2"
                ></textarea>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- DIVIDER arrastrable entre chat y terminal -->
        <!-- ============================================================ -->
        <div
            class="dt-divider"
            @mousedown="startDrag"
            :class="{ dragging }"
        ></div>

        <!-- ============================================================ -->
        <!-- COLUMNA 3: TERMINAL ttyd (lógica preservada del original) -->
        <!-- ============================================================ -->
        <div class="dt-terminal" :style="{ width: terminalPct + '%' }">
            <div class="dt-terminal-header">
                <span class="dt-terminal-title">
                    <i class="fas fa-terminal"></i> Terminal
                    <small class="dt-terminal-url">{{ effectiveTtydUrl }}</small>
                </span>
                <button class="dt-icon-btn" @click="probeTtyd" title="Reintentar conexión">
                    <i class="fas fa-sync"></i>
                </button>
            </div>
            <div class="dt-terminal-body">
                <iframe
                    v-if="ttydReachable !== false"
                    :src="effectiveTtydUrl"
                    @load="onIframeLoad"
                    @error="onIframeError"
                ></iframe>
                <div v-else class="dt-ttyd-setup">
                    <h5>Terminal no disponible</h5>
                    <p>
                        No se pudo conectar a
                        <code>{{ effectiveTtydUrl }}</code>.
                    </p>
                    <p>Ejecuta en el servidor:</p>
                    <pre>ttyd -p 7681 -W --interface 0.0.0.0 bash</pre>
                    <p>O activa el servicio systemd:</p>
                    <pre>sudo systemctl enable --now ttyd</pre>
                    <button class="btn btn-outline-primary" @click="probeTtyd">
                        <i class="fas fa-sync"></i> Reintentar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";

export default {
    name: "DevtoolsPanel",
    props: {
        ttydUrl: { type: String, default: "http://127.0.0.1:7681" },
        csrfToken: { type: String, required: true },
        userName: { type: String, default: "Dev" },
    },
    setup(props) {
        // -------------------------------------------------------------
        // Theme reactivo desde <body data-layout-mode="...">
        // -------------------------------------------------------------
        // Lee el atributo al montar y se reactualiza si el usuario cambia
        // el tema desde otra vista (vía MutationObserver). Esto requiere
        // que el body tenga el atributo — fix aplicado en commit 474c343
        // a master-without-nav.blade.php.
        const themeRaw = ref(
            document.body.getAttribute("data-layout-mode") || "light"
        );
        let themeObserver = null;
        const currentTheme = computed(() =>
            themeRaw.value === "dark" ? "dark" : "light"
        );

        // -------------------------------------------------------------
        // Sidebar colapsable (estado persistido en localStorage)
        // -------------------------------------------------------------
        const sidebarCollapsed = ref(
            localStorage.getItem("devtools_sidebar_collapsed") === "true"
        );
        const toggleSidebar = () => {
            sidebarCollapsed.value = !sidebarCollapsed.value;
            localStorage.setItem(
                "devtools_sidebar_collapsed",
                sidebarCollapsed.value ? "true" : "false"
            );
        };

        // -------------------------------------------------------------
        // Placeholder nav items (3 de prueba — se reemplaza con fetch a
        // /devtools/nav-items en 3B)
        // -------------------------------------------------------------
        const placeholderNav = [
            { label: "Dashboard", icon: "fas fa-tachometer-alt", route: "/dashboard" },
            { label: "IA", icon: "fas fa-robot", route: "/ia" },
            { label: "DevTools", icon: "fas fa-tools", route: "/devtools", active: true },
        ];

        // -------------------------------------------------------------
        // Terminal: width drag entre chat y terminal
        // -------------------------------------------------------------
        const rootRef = ref(null);
        const terminalPct = ref(38);
        const dragging = ref(false);
        const startDrag = (e) => {
            dragging.value = true;
            e.preventDefault();
        };
        const onDrag = (e) => {
            if (!dragging.value || !rootRef.value) return;
            const rect = rootRef.value.getBoundingClientRect();
            const pct = ((rect.right - e.clientX) / rect.width) * 100;
            terminalPct.value = Math.max(20, Math.min(70, pct));
        };
        const endDrag = () => {
            dragging.value = false;
        };

        // -------------------------------------------------------------
        // ttyd (preservado del archivo original)
        // -------------------------------------------------------------
        // El TTYD_URL del .env suele apuntar a 127.0.0.1, lo cual sólo
        // funciona si el navegador corre en el mismo host que el servidor.
        // Si el prop es ese default placeholder, derivamos la URL del
        // hostname con el que la página fue servida — así el iframe llega
        // al ttyd remoto desde cualquier máquina cliente.
        const ttydReachable = ref(null);
        const effectiveTtydUrl = computed(() => {
            const placeholder = "http://127.0.0.1:7681";
            if (props.ttydUrl && props.ttydUrl !== placeholder) {
                return props.ttydUrl;
            }
            if (typeof window !== "undefined" && window.location?.hostname) {
                return `http://${window.location.hostname}:7681`;
            }
            return props.ttydUrl;
        });
        const probeTtyd = async () => {
            ttydReachable.value = null;
            try {
                await fetch(effectiveTtydUrl.value, {
                    method: "GET",
                    mode: "no-cors",
                });
                ttydReachable.value = true;
            } catch (e) {
                ttydReachable.value = false;
            }
        };
        const onIframeLoad = () => {
            ttydReachable.value = true;
        };
        const onIframeError = () => {
            ttydReachable.value = false;
        };

        // -------------------------------------------------------------
        // Navegación de salida
        // -------------------------------------------------------------
        const close = () => {
            window.location.href = "/home";
        };

        // -------------------------------------------------------------
        // Lifecycle
        // -------------------------------------------------------------
        onMounted(() => {
            probeTtyd();
            document.addEventListener("mousemove", onDrag);
            document.addEventListener("mouseup", endDrag);
            // Observe cambios en data-layout-mode para reaccionar al
            // toggle de tema global del usuario sin recargar la página.
            themeObserver = new MutationObserver(() => {
                themeRaw.value =
                    document.body.getAttribute("data-layout-mode") || "light";
            });
            themeObserver.observe(document.body, {
                attributes: true,
                attributeFilter: ["data-layout-mode"],
            });
        });
        onBeforeUnmount(() => {
            document.removeEventListener("mousemove", onDrag);
            document.removeEventListener("mouseup", endDrag);
            if (themeObserver) themeObserver.disconnect();
        });

        return {
            // theme
            currentTheme,
            // sidebar
            sidebarCollapsed,
            toggleSidebar,
            placeholderNav,
            // root + drag
            rootRef,
            terminalPct,
            dragging,
            startDrag,
            // ttyd
            effectiveTtydUrl,
            ttydReachable,
            probeTtyd,
            onIframeLoad,
            onIframeError,
            // misc
            close,
        };
    },
};
</script>

<style scoped>
/* ====================================================================
   CSS variables — dark/light themes
   ==================================================================== */
.devtools-root[data-theme="dark"] {
    --dt-bg: #1a1b26;
    --dt-sidebar-bg: #16171f;
    --dt-chat-bg: #1e1f2e;
    --dt-border: #2a2b3d;
    --dt-text: #cdd6f4;
    --dt-text-muted: #6c7086;
    --dt-accent: #ff8c00;
    --dt-user-bubble: #2a2b3d;
    --dt-ai-bubble: #1e2030;
    --dt-hover: #2a2b3d;
}
.devtools-root[data-theme="light"] {
    --dt-bg: #f8f9fa;
    --dt-sidebar-bg: #ffffff;
    --dt-chat-bg: #ffffff;
    --dt-border: #e0e0e0;
    --dt-text: #212529;
    --dt-text-muted: #6c757d;
    --dt-accent: #ff8c00;
    --dt-user-bubble: #e9ecef;
    --dt-ai-bubble: #f1f3f5;
    --dt-hover: #f0f0f0;
}

/* ====================================================================
   Root layout — fixed full-screen, 3-column flex
   ==================================================================== */
.devtools-root {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    height: 100vh;
    overflow: hidden;
    background: var(--dt-bg);
    color: var(--dt-text);
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}

/* ====================================================================
   Columna 1 — Sidebar
   ==================================================================== */
.dt-sidebar {
    width: 220px;
    min-width: 220px;
    transition: width 0.25s ease, min-width 0.25s ease;
    background: var(--dt-sidebar-bg);
    border-right: 1px solid var(--dt-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.dt-sidebar.collapsed {
    width: 52px;
    min-width: 52px;
}
.dt-sidebar-toggle {
    height: 44px;
    background: transparent;
    border: none;
    color: var(--dt-text);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid var(--dt-border);
}
.dt-sidebar-toggle:hover {
    background: var(--dt-hover);
    color: var(--dt-accent);
}
.dt-nav {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
}
.dt-nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 1rem;
    color: var(--dt-text);
    text-decoration: none;
    white-space: nowrap;
    font-size: 13px;
    transition: background 0.15s, color 0.15s;
    cursor: pointer;
}
.dt-nav-item:hover {
    background: var(--dt-hover);
    color: var(--dt-accent);
}
.dt-nav-item.active {
    background: var(--dt-hover);
    color: var(--dt-accent);
    border-left: 3px solid var(--dt-accent);
    padding-left: calc(1rem - 3px);
}
.dt-nav-icon {
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}
.dt-sidebar.collapsed .dt-nav-label,
.dt-sidebar.collapsed .dt-nav-scaffold-note {
    display: none;
}
.dt-nav-scaffold-note {
    padding: 0.5rem 1rem;
    font-size: 11px;
    color: var(--dt-text-muted);
    font-style: italic;
}

/* ====================================================================
   Columna 2 — Chat
   ==================================================================== */
.dt-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--dt-chat-bg);
    min-width: 0;
}
.dt-chat-header {
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
    border-bottom: 1px solid var(--dt-border);
    background: var(--dt-sidebar-bg);
}
.dt-chat-title {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.dt-chat-subtitle {
    color: var(--dt-text-muted);
    font-weight: 400;
    font-size: 12px;
}
.dt-messages {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
}
.dt-empty-state {
    color: var(--dt-text-muted);
    text-align: center;
    margin-top: 3rem;
    font-style: italic;
    line-height: 1.8;
}
.dt-empty-state i {
    font-size: 2rem;
    color: var(--dt-accent);
    opacity: 0.6;
}
.dt-input-bar {
    padding: 0.5rem;
    border-top: 1px solid var(--dt-border);
    background: var(--dt-sidebar-bg);
}
.dt-textarea {
    width: 100%;
    background: var(--dt-bg);
    color: var(--dt-text);
    border: 1px solid var(--dt-border);
    border-radius: 6px;
    padding: 0.5rem;
    font-size: 13px;
    resize: vertical;
    font-family: inherit;
    min-height: 50px;
}
.dt-textarea:focus {
    outline: none;
    border-color: var(--dt-accent);
}
.dt-textarea:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ====================================================================
   Divider (entre chat y terminal)
   ==================================================================== */
.dt-divider {
    width: 4px;
    background: var(--dt-border);
    cursor: col-resize;
    transition: background 0.15s;
    flex-shrink: 0;
}
.dt-divider:hover,
.dt-divider.dragging {
    background: var(--dt-accent);
}

/* ====================================================================
   Columna 3 — Terminal
   ==================================================================== */
.dt-terminal {
    min-width: 200px;
    display: flex;
    flex-direction: column;
    background: #000;
    flex-shrink: 0;
}
.dt-terminal-header {
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
    background: var(--dt-sidebar-bg);
    color: var(--dt-text);
    border-bottom: 1px solid var(--dt-border);
}
.dt-terminal-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    font-size: 13px;
}
.dt-terminal-url {
    color: var(--dt-text-muted);
    font-weight: 400;
    font-size: 11px;
}
.dt-icon-btn {
    background: transparent;
    border: none;
    color: var(--dt-text);
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: color 0.15s, background 0.15s;
}
.dt-icon-btn:hover {
    color: var(--dt-accent);
    background: var(--dt-hover);
}
.dt-terminal-body {
    flex: 1;
    position: relative;
    background: #000;
}
.dt-terminal-body iframe {
    width: 100%;
    height: 100%;
    border: 0;
}
.dt-ttyd-setup {
    padding: 2rem;
    color: var(--dt-text);
    background: var(--dt-bg);
    height: 100%;
}
.dt-ttyd-setup pre {
    background: var(--dt-sidebar-bg);
    padding: 0.75rem 1rem;
    border-radius: 6px;
    color: var(--dt-accent);
    border-left: 3px solid var(--dt-accent);
    font-size: 13px;
    margin: 0.5rem 0;
}
</style>
