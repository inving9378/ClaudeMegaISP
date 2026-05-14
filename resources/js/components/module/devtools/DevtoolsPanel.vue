<template>
    <div class="devtools-root">
        <!-- Header strip -->
        <div class="devtools-header">
            <div class="devtools-title">
                <i class="fa fa-code text-warning"></i>
                <span>DevTools</span>
                <span class="text-muted small ms-2">{{ userName }}</span>
            </div>
            <button class="btn btn-sm btn-outline-light" @click="close">
                <i class="fa fa-times"></i>
                Cerrar
            </button>
        </div>

        <!-- Split body -->
        <div class="devtools-body" ref="body">
            <!-- LEFT: Claude chat -->
            <div class="panel panel-left" :style="{ width: leftPct + '%' }">
                <div class="panel-header">
                    <i class="fa fa-robot text-info"></i>
                    Claude
                    <span class="text-muted small ms-2">{{ chatModel }}</span>
                </div>
                <div class="chat-messages" ref="messagesEl">
                    <div v-if="messages.length === 0" class="chat-empty">
                        Saluda a Claude para empezar.
                    </div>
                    <div
                        v-for="(m, i) in messages"
                        :key="i"
                        class="chat-msg"
                        :class="m.role"
                    >
                        <div class="chat-msg-role">{{ m.role === 'user' ? userName : 'Claude' }}</div>
                        <div class="chat-msg-content" v-text="m.content"></div>
                        <div v-if="m.tokens" class="chat-msg-tokens">
                            in: {{ m.tokens.input }} · out: {{ m.tokens.output }}
                        </div>
                    </div>
                    <div v-if="sending" class="chat-msg assistant">
                        <div class="chat-msg-role">Claude</div>
                        <div class="chat-msg-content text-muted">
                            <i class="fa fa-spinner fa-spin"></i>
                            Pensando…
                        </div>
                    </div>
                </div>
                <div class="chat-input">
                    <textarea
                        v-model="input"
                        @keydown.enter.exact.prevent="send"
                        @keydown.enter.shift.exact="newline"
                        placeholder="Pregúntale a Claude…  (Enter para enviar · Shift+Enter para salto)"
                        rows="2"
                        :disabled="sending"
                    ></textarea>
                    <button class="btn btn-primary" :disabled="sending || !input.trim()" @click="send">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>
                <div class="chat-footer text-muted small">
                    Total: in {{ totals.input }} · out {{ totals.output }} tokens
                </div>
            </div>

            <!-- Divider -->
            <div
                class="divider"
                @mousedown="startDrag"
                :class="{ dragging }"
            ></div>

            <!-- RIGHT: ttyd iframe -->
            <div class="panel panel-right" :style="{ width: 100 - leftPct + '%' }">
                <div class="panel-header">
                    <i class="fa fa-terminal text-success"></i>
                    Terminal
                    <span class="text-muted small ms-2">{{ effectiveTtydUrl }}</span>
                    <button class="btn btn-sm btn-link ms-auto" @click="probeTtyd">
                        <i class="fa fa-sync"></i>
                    </button>
                </div>
                <div class="terminal-body">
                    <iframe
                        v-if="ttydReachable !== false"
                        :src="effectiveTtydUrl"
                        @load="onIframeLoad"
                        @error="onIframeError"
                    ></iframe>
                    <div v-else class="ttyd-setup">
                        <h5>Terminal no disponible</h5>
                        <p class="text-muted">No se pudo conectar a <code>{{ effectiveTtydUrl }}</code>.</p>
                        <p>Ejecuta en el servidor:</p>
                        <pre>ttyd -p 7681 -W --interface 0.0.0.0 bash</pre>
                        <p>O activa el servicio systemd:</p>
                        <pre>sudo systemctl enable --now ttyd</pre>
                        <button class="btn btn-outline-primary" @click="probeTtyd">
                            <i class="fa fa-sync"></i>
                            Reintentar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'DevtoolsPanel',
    props: {
        ttydUrl: { type: String, default: 'http://127.0.0.1:7681' },
        csrfToken: { type: String, required: true },
        userName: { type: String, default: 'Dev' },
    },
    computed: {
        // El TTYD_URL del .env suele apuntar a 127.0.0.1, lo cual sólo
        // funciona si el navegador corre en el mismo host que el servidor.
        // Si el prop es ese default placeholder, derivamos la URL del
        // hostname con el que la página fue servida — así el iframe llega
        // al ttyd remoto desde cualquier máquina cliente.
        // Nota: si la app corre sobre HTTPS, también hay que servir ttyd
        // con TLS o el iframe será bloqueado por mixed-content.
        effectiveTtydUrl() {
            const placeholder = 'http://127.0.0.1:7681';
            if (this.ttydUrl && this.ttydUrl !== placeholder) {
                return this.ttydUrl;
            }
            if (typeof window !== 'undefined' && window.location?.hostname) {
                return `http://${window.location.hostname}:7681`;
            }
            return this.ttydUrl;
        },
    },
    data() {
        return {
            chatModel: 'claude-sonnet-4',
            input: '',
            messages: [],
            sending: false,
            totals: { input: 0, output: 0 },

            // split state
            leftPct: 50,
            dragging: false,
            ttydReachable: null, // null = unknown, true = ok, false = failed
        };
    },
    mounted() {
        this.probeTtyd();
        document.addEventListener('mousemove', this.onDrag);
        document.addEventListener('mouseup', this.endDrag);
    },
    beforeUnmount() {
        document.removeEventListener('mousemove', this.onDrag);
        document.removeEventListener('mouseup', this.endDrag);
    },
    methods: {
        close() {
            window.location.href = '/home';
        },
        newline() {
            this.input += '\n';
        },
        async send() {
            const msg = this.input.trim();
            if (!msg || this.sending) return;

            this.messages.push({ role: 'user', content: msg });
            this.input = '';
            this.sending = true;
            this.scrollToBottom();

            const history = this.messages
                .filter((m) => m.role === 'user' || m.role === 'assistant')
                .slice(0, -1)
                .map((m) => ({ role: m.role, content: m.content }));

            try {
                const { data } = await axios.post('/devtools/chat', {
                    message: msg,
                    history,
                }, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });

                if (data.success) {
                    this.messages.push({
                        role: 'assistant',
                        content: data.reply,
                        tokens: {
                            input: data.input_tokens || 0,
                            output: data.output_tokens || 0,
                        },
                    });
                    this.totals.input += data.input_tokens || 0;
                    this.totals.output += data.output_tokens || 0;
                } else {
                    this.messages.push({
                        role: 'assistant',
                        content: '[Error] ' + (data.error || 'fallo desconocido'),
                    });
                }
            } catch (e) {
                this.messages.push({
                    role: 'assistant',
                    content: '[Error de red] ' + (e.response?.data?.error || e.message),
                });
            } finally {
                this.sending = false;
                this.scrollToBottom();
            }
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.messagesEl;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        // ttyd probe: try to fetch the URL; CORS may block it, but a network
        // error vs an opaque response tells us if the host is up.
        async probeTtyd() {
            this.ttydReachable = null;
            try {
                await fetch(this.effectiveTtydUrl, { method: 'GET', mode: 'no-cors' });
                this.ttydReachable = true;
            } catch (e) {
                this.ttydReachable = false;
            }
        },
        onIframeLoad() {
            this.ttydReachable = true;
        },
        onIframeError() {
            this.ttydReachable = false;
        },

        // Drag divider
        startDrag(e) {
            this.dragging = true;
            e.preventDefault();
        },
        onDrag(e) {
            if (!this.dragging) return;
            const body = this.$refs.body;
            if (!body) return;
            const rect = body.getBoundingClientRect();
            const pct = ((e.clientX - rect.left) / rect.width) * 100;
            this.leftPct = Math.max(20, Math.min(80, pct));
        },
        endDrag() {
            this.dragging = false;
        },
    },
};
</script>

<style scoped>
.devtools-root {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: #1a1b26;
    color: #cdd6f4;
    display: flex;
    flex-direction: column;
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}
.devtools-header {
    height: 44px;
    background: #11111b;
    color: #cdd6f4;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
    border-bottom: 1px solid #2a2b3d;
}
.devtools-title { font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.devtools-body {
    flex: 1;
    display: flex;
    overflow: hidden;
    min-height: 0;
}
.panel {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #1e1e2e;
}
.panel-header {
    height: 36px;
    background: #181825;
    color: #cdd6f4;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0 0.75rem;
    border-bottom: 1px solid #2a2b3d;
    font-size: 13px;
}
.divider {
    width: 6px;
    cursor: col-resize;
    background: #2a2b3d;
    transition: background 0.15s;
    flex-shrink: 0;
}
.divider:hover, .divider.dragging { background: #ff8c00; }

/* Chat */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.chat-empty {
    color: #585b70;
    text-align: center;
    margin-top: 2rem;
    font-style: italic;
}
.chat-msg {
    padding: 0.5rem 0.7rem;
    border-radius: 6px;
    background: #313244;
    max-width: 92%;
}
.chat-msg.user {
    background: #45475a;
    align-self: flex-end;
}
.chat-msg.assistant { background: #313244; align-self: flex-start; }
.chat-msg-role {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #a6adc8;
    margin-bottom: 0.2rem;
}
.chat-msg-content {
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 13px;
    line-height: 1.45;
    color: #cdd6f4;
}
.chat-msg-tokens {
    font-size: 10px;
    color: #585b70;
    margin-top: 0.3rem;
    text-align: right;
}
.chat-input {
    display: flex;
    gap: 0.5rem;
    padding: 0.5rem;
    background: #181825;
    border-top: 1px solid #2a2b3d;
}
.chat-input textarea {
    flex: 1;
    background: #1e1e2e;
    color: #cdd6f4;
    border: 1px solid #2a2b3d;
    border-radius: 6px;
    padding: 0.5rem;
    font-size: 13px;
    resize: vertical;
    font-family: inherit;
}
.chat-input textarea:focus {
    outline: none;
    border-color: #ff8c00;
}
.chat-footer { padding: 0.25rem 0.75rem; background: #181825; }

/* Terminal */
.terminal-body { flex: 1; position: relative; background: #000; }
.terminal-body iframe { width: 100%; height: 100%; border: 0; }
.ttyd-setup {
    padding: 2rem;
    color: #cdd6f4;
}
.ttyd-setup pre {
    background: #11111b;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    color: #ff8c00;
    border-left: 3px solid #ff8c00;
    font-size: 13px;
}
</style>
