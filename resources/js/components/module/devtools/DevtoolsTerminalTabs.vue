<template>
    <div
        class="dt-tabs"
        :class="{ 'dt-tabs-off': !usable }"
        :title="usable ? '' : 'Terminal no disponible'"
    >
        <div class="dt-tabs-list" role="tablist">
            <div
                v-for="tab in tabs"
                :key="tab.id"
                class="dt-tab"
                :class="{ active: tab.active }"
                role="tab"
                :aria-selected="tab.active"
                :title="usable ? `${tab.index}: ${tab.name} — ${tab.command}` : 'Terminal no disponible'"
            >
                <button type="button" class="dt-tab-btn" :disabled="!usable" @click="select(tab)">
                    <span class="dt-tab-idx">{{ tab.index }}</span>{{ tab.name }}
                </button>
                <button
                    v-if="tabs.length > 1"
                    type="button"
                    class="dt-tab-close"
                    :disabled="!usable"
                    :aria-label="`Cerrar ventana ${tab.index}`"
                    @click.stop="askClose(tab)"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <button
            type="button"
            class="dt-tab-add"
            :disabled="!usable"
            :title="usable ? 'Nueva ventana (Ctrl+b c)' : 'Terminal no disponible'"
            @click="add"
        >
            <i class="fas fa-plus"></i>
        </button>

        <q-dialog v-model="confirmOpen" no-refocus @hide="focusTerm">
            <q-card :dark="dark" class="dt-tabs-dialog">
                <q-card-section class="text-h6">
                    ¿Cerrar la ventana {{ pending?.index }}?
                </q-card-section>
                <q-card-section class="q-pt-none">
                    <div>Nombre: <b>{{ pending?.name }}</b></div>
                    <div>Proceso: <code>{{ pending?.command }}</code></div>
                    <div v-if="pendingIsClaude" class="text-negative q-mt-sm">
                        Ahí corre Claude Code: se termina esa sesión y lo que no esté guardado se pierde.
                    </div>
                    <div v-else class="q-mt-sm">Se termina lo que esté corriendo en esa ventana.</div>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Cancelar" v-close-popup />
                    <q-btn unelevated no-caps color="negative" label="Cerrar ventana" :loading="closing" @click="confirmClose" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
import { ref, computed, onBeforeUnmount } from "vue";
import { createTmuxTabs } from "./helper/tmux-tabs.js";

// Barra de pestañas del panel Terminal: cada pestaña es una ventana tmux de la
// sesión del usuario. La lógica de tmux vive en helper/tmux-tabs.js; aquí solo
// se pinta y se confirma. El panel llama a attach(iframe.contentWindow) en cada
// carga del iframe de ttyd.
export default {
    name: "DevtoolsTerminalTabs",
    props: {
        dark: { type: Boolean, default: false },
    },
    setup(props, { expose }) {
        const tabs = ref([]);
        const ready = ref(false);
        const tmuxVisible = ref(false);
        const usable = computed(() => ready.value && tmuxVisible.value);

        let term = null;
        let ctl = null;
        let retryTimer = null;

        const detach = () => {
            clearTimeout(retryTimer);
            retryTimer = null;
            if (ctl) ctl.dispose();
            ctl = null;
            term = null;
            tabs.value = [];
            ready.value = false;
            tmuxVisible.value = false;
        };

        // ttyd monta su xterm en window.term un poco después del load del iframe.
        const attach = (contentWindow) => {
            detach();
            let retries = 0;
            const hook = () => {
                let t = null;
                let initialTitle = "";
                try {
                    t = contentWindow?.term || null;
                    initialTitle = contentWindow?.document?.title || "";
                } catch (_) {
                    return; // iframe de otro origen (TTYD_URL externo): sin pestañas
                }
                if (!t) {
                    if (retries++ < 20) retryTimer = setTimeout(hook, 500);
                    return;
                }
                term = t;
                ctl = createTmuxTabs(t, {
                    initialTitle,
                    onChange: (s) => {
                        tabs.value = s.tabs;
                        ready.value = s.ready;
                        tmuxVisible.value = s.tmux;
                    },
                });
            };
            hook();
        };

        const focusTerm = () => {
            setTimeout(() => {
                try { term?.focus(); } catch (_) {}
            }, 0);
        };

        const add = () => {
            ctl?.newWindow();
            focusTerm();
        };

        const select = async (tab) => {
            if (!tab.active) await ctl?.selectWindow(tab);
            focusTerm();
        };

        const confirmOpen = ref(false);
        const closing = ref(false);
        const pending = ref(null);
        const pendingIsClaude = computed(() =>
            /claude/i.test(`${pending.value?.command || ""} ${pending.value?.name || ""}`)
        );

        const askClose = (tab) => {
            if (tabs.value.length <= 1) return; // nunca la última: tmux cerraría la sesión
            pending.value = tab;
            confirmOpen.value = true;
        };

        const confirmClose = async () => {
            const tab = pending.value;
            const sigue = tab && tabs.value.some((t) => t.id === tab.id);
            if (sigue && tabs.value.length > 1) {
                closing.value = true;
                try {
                    await ctl?.killWindow(tab);
                } finally {
                    closing.value = false;
                }
            }
            confirmOpen.value = false;
        };

        onBeforeUnmount(detach);
        expose({ attach, detach });

        return {
            tabs,
            usable,
            add,
            select,
            askClose,
            confirmOpen,
            closing,
            pending,
            pendingIsClaude,
            confirmClose,
            focusTerm,
        };
    },
};
</script>

<style scoped>
.dt-tabs {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0 0.5rem;
}
.dt-tabs-list {
    display: flex;
    align-items: center;
    gap: 2px;
    min-width: 0;
    overflow-x: auto;
    scrollbar-width: thin;
}
.dt-tab {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    height: 28px;
    border-radius: 4px;
    border-bottom: 2px solid transparent;
    color: var(--dt-text-muted);
    transition: color 0.15s, background 0.15s;
}
.dt-tab:hover {
    background: var(--dt-hover);
    color: var(--dt-text);
}
.dt-tab.active {
    color: var(--dt-text);
    background: var(--dt-hover);
    border-bottom-color: var(--dt-accent);
}
.dt-tab-btn,
.dt-tab-close,
.dt-tab-add {
    background: transparent;
    border: none;
    color: inherit;
    cursor: pointer;
    font-size: 12px;
}
.dt-tab-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    max-width: 140px;
    padding: 0 6px 0 8px;
    height: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dt-tab-idx {
    font-family: ui-monospace, "SF Mono", Menlo, monospace;
    font-size: 10px;
    color: var(--dt-text-muted);
}
.dt-tab.active .dt-tab-idx {
    color: var(--dt-accent);
}
.dt-tab-close {
    padding: 0 6px;
    height: 100%;
    font-size: 10px;
    opacity: 0.6;
}
.dt-tab-close:hover {
    opacity: 1;
    color: var(--dt-danger);
}
.dt-tab-add {
    flex-shrink: 0;
    padding: 0.35rem 0.55rem;
    border-radius: 4px;
    color: var(--dt-text);
}
.dt-tab-add:hover {
    color: var(--dt-accent);
    background: var(--dt-hover);
}
.dt-tabs button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.dt-tabs-off .dt-tab:hover {
    background: transparent;
}
.dt-tabs-dialog {
    min-width: 320px;
}
</style>
