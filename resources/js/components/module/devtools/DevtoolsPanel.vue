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
                <template v-for="item in navItems" :key="item.route">
                    <!-- Item sin hijos -->
                    <a
                        v-if="!item.children || item.children.length === 0"
                        :href="item.route"
                        class="dt-nav-item"
                        :class="{ active: item.active }"
                        :title="sidebarCollapsed ? item.label : ''"
                    >
                        <i :class="item.icon" class="dt-nav-icon"></i>
                        <span class="dt-nav-label">{{ item.label }}</span>
                    </a>
                    <!-- Item con hijos (expandible) -->
                    <div v-else class="dt-nav-group">
                        <button
                            class="dt-nav-item dt-nav-parent"
                            :class="{ active: item.active, open: expandedGroups[item.route] }"
                            @click="toggleGroup(item.route)"
                            :title="sidebarCollapsed ? item.label : ''"
                        >
                            <i :class="item.icon" class="dt-nav-icon"></i>
                            <span class="dt-nav-label">{{ item.label }}</span>
                            <i
                                v-if="!sidebarCollapsed"
                                class="fas fa-chevron-right dt-nav-chevron"
                                :class="{ rotated: expandedGroups[item.route] }"
                            ></i>
                        </button>
                        <div
                            class="dt-nav-children"
                            :class="{ open: expandedGroups[item.route] && !sidebarCollapsed }"
                        >
                            <a
                                v-for="child in item.children"
                                :key="child.route"
                                :href="child.route"
                                class="dt-nav-child"
                            >
                                {{ child.label }}
                            </a>
                        </div>
                    </div>
                </template>
            </nav>
        </div>

        <!-- ============================================================ -->
        <!-- COLUMNA 2: CLAUDE CHAT                                       -->
        <!-- ============================================================ -->
        <div class="dt-chat">
            <!-- Header chat -->
            <div class="dt-chat-header">
                <span class="dt-chat-title">
                    <i class="fas fa-robot"></i> Claude AI
                    <small class="dt-chat-subtitle">{{ userName }}</small>
                </span>
                <div class="dt-chat-controls">
                    <select
                        v-if="availableVoices.length > 0"
                        v-model="selectedVoice"
                        class="dt-voice-select"
                        title="Voz para leer respuestas"
                    >
                        <option v-for="v in availableVoices" :key="v.name" :value="v.name">
                            {{ v.name }}
                        </option>
                    </select>
                    <span v-if="totalTokens.input > 0" class="dt-tokens" :title="`In: ${totalTokens.input} / Out: ${totalTokens.output} / Cache w/r: ${totalTokens.cacheCreate}/${totalTokens.cacheRead}`">
                        ↑{{ totalTokens.input }} ↓{{ totalTokens.output }}
                    </span>
                    <button class="dt-icon-btn" @click="close" title="Cerrar DevTools">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Context bar -->
            <div v-if="context" class="dt-context-bar" :title="contextTitle">
                <i class="fas fa-code-branch"></i>
                <strong>{{ context.branch }}</strong>
                <span class="dt-ctx-sep">·</span>
                <span>{{ (context.recent_commits || []).length }} commits</span>
                <span class="dt-ctx-sep">·</span>
                <span>{{ (context.active_modules || []).length }} módulos</span>
                <span class="dt-ctx-sep">·</span>
                <span>CLAUDE.md {{ claudeMdSize }}</span>
            </div>

            <!-- Mensajes -->
            <div class="dt-messages" ref="messagesEl">
                <p v-if="messages.length === 0" class="dt-empty-state">
                    <i class="fas fa-robot"></i><br />
                    Saluda a Claude para empezar.<br />
                    <small>Enter envía · Shift+Enter salto · 🎤 voz · 📎 adjuntar</small>
                </p>

                <div
                    v-for="(msg, idx) in messages"
                    :key="idx"
                    class="dt-msg-wrapper"
                    :class="msg.role"
                >
                    <div class="dt-bubble">
                        <div class="dt-msg-content" v-html="renderMsg(msg.content)"></div>

                        <!-- Attachments preview (mensajes user) -->
                        <div
                            v-if="msg.attachments && msg.attachments.length"
                            class="dt-msg-attachments"
                        >
                            <div
                                v-for="(att, ai) in msg.attachments"
                                :key="ai"
                                class="dt-att-thumb"
                            >
                                <img
                                    v-if="att.type === 'image'"
                                    :src="att.preview"
                                    :alt="att.name"
                                />
                                <span v-else>
                                    <i class="fas fa-file-alt"></i> {{ att.name }}
                                </span>
                            </div>
                        </div>

                        <!-- Tokens por mensaje -->
                        <div v-if="msg.tokens" class="dt-msg-meta">
                            <span>↑{{ msg.tokens.input }} ↓{{ msg.tokens.output }}</span>
                            <span v-if="msg.tokens.cacheCreate || msg.tokens.cacheRead">
                                · 💾 {{ msg.tokens.cacheCreate }}/{{ msg.tokens.cacheRead }}
                            </span>
                        </div>
                    </div>

                    <!-- Acciones (solo assistant) -->
                    <div v-if="msg.role === 'assistant'" class="dt-msg-actions">
                        <button
                            class="dt-action-btn"
                            @click="regenerateMessage(idx)"
                            title="Regenerar respuesta"
                            :disabled="loading"
                        >
                            <i class="fas fa-redo"></i>
                        </button>
                        <button
                            class="dt-action-btn"
                            @click="copyMessage(msg.content, idx)"
                            title="Copiar"
                        >
                            <i :class="copiedIdx === idx ? 'fas fa-check' : 'fas fa-copy'"></i>
                        </button>
                        <button
                            class="dt-action-btn"
                            :class="{ active: speakingIdx === idx }"
                            @click="speakMessage(msg.content, idx)"
                            :title="speakingIdx === idx ? 'Detener' : 'Escuchar respuesta'"
                            :disabled="!speechSupported"
                        >
                            <i :class="speakingIdx === idx ? 'fas fa-stop' : 'fas fa-volume-up'"></i>
                        </button>
                        <button
                            class="dt-action-btn"
                            :class="{ active: feedback[idx] === 'up' }"
                            @click="feedbackMessage(idx, 'up')"
                            title="Útil"
                        >
                            <i class="fas fa-thumbs-up"></i>
                        </button>
                        <button
                            class="dt-action-btn"
                            :class="{ active: feedback[idx] === 'down' }"
                            @click="feedbackMessage(idx, 'down')"
                            title="No útil"
                        >
                            <i class="fas fa-thumbs-down"></i>
                        </button>
                    </div>
                </div>

                <!-- Typing indicator -->
                <div v-if="loading" class="dt-typing">
                    <span></span><span></span><span></span>
                </div>
            </div>

            <!-- Adjuntos pendientes -->
            <div v-if="pendingAttachments.length" class="dt-attachments-bar">
                <div
                    v-for="(att, i) in pendingAttachments"
                    :key="i"
                    class="dt-att-chip"
                >
                    <img v-if="att.type === 'image'" :src="att.preview" class="dt-att-img" :alt="att.name" />
                    <i v-else class="fas fa-file-alt"></i>
                    <span class="dt-att-name">{{ att.name }}</span>
                    <button
                        class="dt-att-remove"
                        @click="removeAttachment(i)"
                        title="Quitar"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Input bar -->
            <div class="dt-input-bar">
                <div class="dt-attach-btns">
                    <button
                        class="dt-icon-btn"
                        @click="triggerFileInput('image')"
                        title="Adjuntar imagen (vision)"
                    >
                        <i class="fas fa-image"></i>
                    </button>
                    <button
                        class="dt-icon-btn"
                        @click="triggerFileInput('file')"
                        title="Adjuntar archivo de texto (.txt .md .json .csv .php .js .vue .py)"
                    >
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <button
                        class="dt-icon-btn"
                        :class="{ recording: voiceListening }"
                        @click="toggleVoiceInput"
                        :disabled="!voiceSupported"
                        :title="voiceSupported ? (voiceListening ? 'Detener (Esc también)' : 'Hablar (es-MX)') : 'SpeechRecognition no soportado en este navegador'"
                    >
                        <i class="fas fa-microphone"></i>
                    </button>
                    <input
                        ref="fileInputImage"
                        type="file"
                        accept="image/*"
                        style="display: none"
                        @change="handleFileSelect($event, 'image')"
                    />
                    <input
                        ref="fileInputFile"
                        type="file"
                        accept=".pdf,.txt,.md,.json,.csv,.php,.js,.vue,.py"
                        style="display: none"
                        @change="handleFileSelect($event, 'file')"
                    />
                </div>
                <textarea
                    v-model="userInput"
                    ref="textareaEl"
                    @keydown.enter.exact.prevent="send"
                    @keydown.enter.shift.exact="newline"
                    class="dt-textarea"
                    :placeholder="voiceListening ? '🎤 Escuchando...' : 'Pregunta a Claude — Enter envía · Shift+Enter salto'"
                    :disabled="loading"
                    rows="2"
                ></textarea>
                <button
                    class="dt-send-btn"
                    :disabled="loading || (!userInput.trim() && pendingAttachments.length === 0)"
                    @click="send"
                    title="Enviar (Enter)"
                >
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- DIVIDER                                                       -->
        <!-- ============================================================ -->
        <div
            class="dt-divider"
            @mousedown="startDrag"
            :class="{ dragging }"
        ></div>

        <!-- ============================================================ -->
        <!-- COLUMNA 3: TERMINAL ttyd                                      -->
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
                    <p>No se pudo conectar a <code>{{ effectiveTtydUrl }}</code>.</p>
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
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick, watch } from "vue";
import { renderMarkdown, bindCodeCopyButtons } from "../../../composables/useMarkdown.js";

export default {
    name: "DevtoolsPanel",
    props: {
        ttydUrl: { type: String, default: "http://127.0.0.1:7681" },
        csrfToken: { type: String, required: true },
        userName: { type: String, default: "Dev" },
    },
    setup(props) {
        // =============================================================
        // 1. THEME REACTIVO (desde <body data-layout-mode>)
        // =============================================================
        const themeRaw = ref(
            document.body.getAttribute("data-layout-mode") || "light"
        );
        let themeObserver = null;
        const currentTheme = computed(() =>
            themeRaw.value === "dark" ? "dark" : "light"
        );

        // =============================================================
        // 2. SIDEBAR (colapsable + nav items dinámicos)
        // =============================================================
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

        // Empieza con un placeholder mientras llega el fetch — evita
        // un flash de sidebar vacío durante el primer paint.
        const navItems = ref([
            { label: "DevTools", icon: "fas fa-tools", route: "/devtools", active: true },
        ]);
        const expandedGroups = reactive({});
        const toggleGroup = (route) => {
            expandedGroups[route] = !expandedGroups[route];
        };
        const fetchNavItems = async () => {
            try {
                const { data } = await axios.get("/devtools/nav-items");
                if (Array.isArray(data) && data.length > 0) {
                    navItems.value = data;
                }
            } catch (e) {
                // Si falla, dejamos el placeholder — no es crítico para la app.
            }
        };

        // =============================================================
        // 3. CHAT — mensajes + envío + contexto + tokens
        // =============================================================
        const userInput = ref("");
        const messages = ref([]);
        const loading = ref(false);
        const messagesEl = ref(null);
        const textareaEl = ref(null);
        const context = ref(null);
        const totalTokens = reactive({
            input: 0,
            output: 0,
            cacheCreate: 0,
            cacheRead: 0,
        });

        const claudeMdSize = computed(() => {
            const n = context.value?.claude_md?.length || 0;
            if (n === 0) return "(falta)";
            if (n < 1024) return `${n} B`;
            return `${(n / 1024).toFixed(1)} KB`;
        });

        const contextTitle = computed(() => {
            if (!context.value) return "";
            const commits = (context.value.recent_commits || []).join("\n");
            const mods = (context.value.active_modules || []).join(", ");
            return `Rama: ${context.value.branch}\nÚltimos commits:\n${commits}\n\nMódulos activos: ${mods}`;
        });

        const renderMsg = (content) => renderMarkdown(content);

        const scrollToBottom = () => {
            nextTick(() => {
                if (messagesEl.value) {
                    messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
                }
                if (messagesEl.value) {
                    bindCodeCopyButtons(messagesEl.value);
                }
            });
        };

        watch(() => messages.value.length, scrollToBottom);
        watch(() => loading.value, scrollToBottom);

        const fetchContext = async () => {
            try {
                const { data } = await axios.get("/devtools/context");
                context.value = data;
            } catch (e) {
                context.value = null;
            }
        };

        const newline = () => {
            userInput.value += "\n";
        };

        const send = async () => {
            const msg = userInput.value.trim();
            const atts = pendingAttachments.value.slice();
            if ((!msg && atts.length === 0) || loading.value) return;

            messages.value.push({
                role: "user",
                content: msg,
                attachments: atts.length ? atts : undefined,
            });
            userInput.value = "";
            pendingAttachments.value = [];
            loading.value = true;

            const history = messages.value
                .filter((m) => m.role === "user" || m.role === "assistant")
                .slice(0, -1)
                .map((m) => ({ role: m.role, content: m.content }));

            try {
                const { data } = await axios.post(
                    "/devtools/chat",
                    {
                        message: msg,
                        history,
                        attachments: atts,
                    },
                    {
                        headers: { "X-CSRF-TOKEN": props.csrfToken },
                    }
                );

                if (data.success) {
                    messages.value.push({
                        role: "assistant",
                        content: data.reply,
                        tokens: {
                            input: data.input_tokens || 0,
                            output: data.output_tokens || 0,
                            cacheCreate: data.cache_creation_input_tokens || 0,
                            cacheRead: data.cache_read_input_tokens || 0,
                        },
                    });
                    totalTokens.input += data.input_tokens || 0;
                    totalTokens.output += data.output_tokens || 0;
                    totalTokens.cacheCreate += data.cache_creation_input_tokens || 0;
                    totalTokens.cacheRead += data.cache_read_input_tokens || 0;
                } else {
                    messages.value.push({
                        role: "assistant",
                        content: "**[Error]** " + (data.error || "fallo desconocido"),
                    });
                }
            } catch (e) {
                messages.value.push({
                    role: "assistant",
                    content:
                        "**[Error de red]** " +
                        (e.response?.data?.error || e.message),
                });
            } finally {
                loading.value = false;
            }
        };

        // Regenerar: borra el último assistant y reenvía el último user.
        const regenerateMessage = async (idx) => {
            if (loading.value) return;
            const userIdx = idx - 1;
            if (userIdx < 0 || messages.value[userIdx]?.role !== "user") return;
            const userMsg = messages.value[userIdx];
            // Conservar attachments si los tenía
            const atts = userMsg.attachments ? userMsg.attachments.slice() : [];
            // Cortar desde el user message (lo re-añade `send`)
            messages.value = messages.value.slice(0, userIdx);
            userInput.value = userMsg.content;
            pendingAttachments.value = atts;
            await send();
        };

        // =============================================================
        // 4. ADJUNTOS
        // =============================================================
        const pendingAttachments = ref([]);
        const fileInputImage = ref(null);
        const fileInputFile = ref(null);

        const triggerFileInput = (type) => {
            if (type === "image") fileInputImage.value?.click();
            else fileInputFile.value?.click();
        };

        const handleFileSelect = (event, type) => {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const dataUrl = e.target.result;
                const b64 = dataUrl.split(",")[1] || "";
                pendingAttachments.value.push({
                    type: type === "image" ? "image" : "file",
                    name: file.name,
                    preview: type === "image" ? dataUrl : null,
                    base64: b64,
                    mimeType: file.type || "",
                });
            };
            reader.readAsDataURL(file);
            event.target.value = "";
        };

        const removeAttachment = (i) => {
            pendingAttachments.value.splice(i, 1);
        };

        // =============================================================
        // 5. VOZ INPUT (Web Speech API — SpeechRecognition)
        // =============================================================
        const voiceSupported = ref(false);
        const voiceListening = ref(false);
        let recognizer = null;

        const startVoiceInput = () => {
            const Recognition =
                window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Recognition) {
                voiceSupported.value = false;
                return;
            }
            recognizer = new Recognition();
            recognizer.lang = "es-MX";
            recognizer.interimResults = true;
            recognizer.continuous = false;

            recognizer.onresult = (event) => {
                let transcript = "";
                for (let i = 0; i < event.results.length; i++) {
                    transcript += event.results[i][0].transcript;
                }
                userInput.value = transcript;
            };
            recognizer.onend = () => {
                voiceListening.value = false;
                recognizer = null;
            };
            recognizer.onerror = () => {
                voiceListening.value = false;
            };

            voiceListening.value = true;
            recognizer.start();
        };

        const stopVoiceInput = () => {
            if (recognizer) {
                try { recognizer.stop(); } catch (e) {}
                recognizer = null;
            }
            voiceListening.value = false;
        };

        const toggleVoiceInput = () => {
            if (voiceListening.value) stopVoiceInput();
            else startVoiceInput();
        };

        // =============================================================
        // 6. VOZ OUTPUT (speechSynthesis)
        // =============================================================
        const speechSupported = ref(typeof window !== "undefined" && "speechSynthesis" in window);
        const availableVoices = ref([]);
        const selectedVoice = ref(null);
        const speakingIdx = ref(null);

        const loadVoices = () => {
            if (!speechSupported.value) return;
            const all = window.speechSynthesis.getVoices();
            // Filtramos a voces que hablen español (cualquier variante)
            availableVoices.value = all.filter((v) => v.lang.startsWith("es"));
            if (availableVoices.value.length > 0 && !selectedVoice.value) {
                const mx = availableVoices.value.find((v) => v.lang === "es-MX");
                selectedVoice.value = mx ? mx.name : availableVoices.value[0].name;
            }
        };

        // Markdown → texto plano para no leer asteriscos, backticks, etc.
        const stripMarkdownForSpeech = (text) => {
            return String(text)
                .replace(/```[\s\S]*?```/g, " [bloque de código] ")
                .replace(/`([^`]+)`/g, "$1")
                .replace(/\*\*(.*?)\*\*/g, "$1")
                .replace(/\*(.*?)\*/g, "$1")
                .replace(/^#+\s/gm, "")
                .replace(/\[(.*?)\]\(.*?\)/g, "$1")
                .replace(/^\s*[-*+]\s/gm, "")
                .replace(/\|/g, " ");
        };

        const speakMessage = (text, idx) => {
            if (!speechSupported.value) return;
            // Toggle: si ya está hablando este, detener.
            if (speakingIdx.value === idx) {
                window.speechSynthesis.cancel();
                speakingIdx.value = null;
                return;
            }
            window.speechSynthesis.cancel();
            const utt = new SpeechSynthesisUtterance(stripMarkdownForSpeech(text));
            utt.lang = "es-MX";
            const voice = availableVoices.value.find((v) => v.name === selectedVoice.value);
            if (voice) utt.voice = voice;
            utt.onend = () => { speakingIdx.value = null; };
            utt.onerror = () => { speakingIdx.value = null; };
            speakingIdx.value = idx;
            window.speechSynthesis.speak(utt);
        };

        // =============================================================
        // 7. COPY + FEEDBACK
        // =============================================================
        const copiedIdx = ref(null);
        const copyMessage = async (text, idx) => {
            try {
                await navigator.clipboard.writeText(text);
                copiedIdx.value = idx;
                setTimeout(() => {
                    if (copiedIdx.value === idx) copiedIdx.value = null;
                }, 2000);
            } catch (e) {
                // Fallback silencioso
            }
        };

        // Feedback 👍👎 solo visual (sin persistencia)
        const feedback = reactive({});
        const feedbackMessage = (idx, type) => {
            if (feedback[idx] === type) {
                delete feedback[idx]; // toggle off
            } else {
                feedback[idx] = type;
            }
        };

        // =============================================================
        // 8. TERMINAL ttyd + DIVIDER drag
        // =============================================================
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
                await fetch(effectiveTtydUrl.value, { method: "GET", mode: "no-cors" });
                ttydReachable.value = true;
            } catch (e) {
                ttydReachable.value = false;
            }
        };
        const onIframeLoad = () => { ttydReachable.value = true; };
        const onIframeError = () => { ttydReachable.value = false; };

        const close = () => {
            window.location.href = "/home";
        };

        // =============================================================
        // LIFECYCLE
        // =============================================================
        const onKeyDown = (e) => {
            // Esc detiene grabación de voz si está activa.
            if (e.key === "Escape" && voiceListening.value) {
                stopVoiceInput();
            }
        };

        onMounted(() => {
            // Detectar soporte de SpeechRecognition
            voiceSupported.value = !!(window.SpeechRecognition || window.webkitSpeechRecognition);

            // Cargar voces para TTS (algunos navegadores las cargan asíncrono)
            loadVoices();
            if (speechSupported.value && window.speechSynthesis.onvoiceschanged !== undefined) {
                window.speechSynthesis.onvoiceschanged = loadVoices;
            }

            // Fetch initial data
            fetchContext();
            fetchNavItems();

            // ttyd probe
            probeTtyd();

            // Listeners globales
            document.addEventListener("mousemove", onDrag);
            document.addEventListener("mouseup", endDrag);
            document.addEventListener("keydown", onKeyDown);

            // Theme observer
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
            document.removeEventListener("keydown", onKeyDown);
            if (themeObserver) themeObserver.disconnect();
            if (speechSupported.value) window.speechSynthesis.cancel();
            stopVoiceInput();
        });

        return {
            // theme
            currentTheme,
            // sidebar
            sidebarCollapsed,
            toggleSidebar,
            navItems,
            expandedGroups,
            toggleGroup,
            // chat
            userInput,
            messages,
            loading,
            messagesEl,
            textareaEl,
            context,
            totalTokens,
            claudeMdSize,
            contextTitle,
            renderMsg,
            send,
            newline,
            regenerateMessage,
            // adjuntos
            pendingAttachments,
            fileInputImage,
            fileInputFile,
            triggerFileInput,
            handleFileSelect,
            removeAttachment,
            // voz input
            voiceSupported,
            voiceListening,
            toggleVoiceInput,
            // voz output
            speechSupported,
            availableVoices,
            selectedVoice,
            speakingIdx,
            speakMessage,
            // copy/feedback
            copiedIdx,
            copyMessage,
            feedback,
            feedbackMessage,
            // terminal + drag
            rootRef,
            terminalPct,
            dragging,
            startDrag,
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
    --dt-danger: #f38ba8;
    --dt-success: #a6e3a1;
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
    --dt-danger: #dc3545;
    --dt-success: #198754;
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
    flex-shrink: 0;
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
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 1rem;
    color: var(--dt-text);
    background: transparent;
    border: none;
    text-align: left;
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
.dt-sidebar.collapsed .dt-nav-label {
    display: none;
}
.dt-nav-parent {
    width: 100%;
}
.dt-nav-chevron {
    margin-left: auto;
    transition: transform 0.2s;
    font-size: 11px;
}
.dt-nav-chevron.rotated {
    transform: rotate(90deg);
}
.dt-nav-children {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.25s ease;
    background: var(--dt-bg);
}
.dt-nav-children.open {
    max-height: 400px;
}
.dt-nav-child {
    display: block;
    padding: 0.4rem 1rem 0.4rem 2.75rem;
    color: var(--dt-text-muted);
    text-decoration: none;
    font-size: 12px;
    transition: color 0.15s, background 0.15s;
}
.dt-nav-child:hover {
    color: var(--dt-accent);
    background: var(--dt-hover);
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
    flex-shrink: 0;
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
.dt-chat-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.dt-voice-select {
    background: var(--dt-bg);
    color: var(--dt-text);
    border: 1px solid var(--dt-border);
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 11px;
    max-width: 140px;
}
.dt-tokens {
    color: var(--dt-text-muted);
    font-size: 11px;
    font-family: ui-monospace, "SF Mono", Menlo, monospace;
}

/* Context bar */
.dt-context-bar {
    background: var(--dt-bg);
    border-bottom: 1px solid var(--dt-border);
    padding: 0.3rem 0.75rem;
    font-size: 11px;
    color: var(--dt-text-muted);
    cursor: help;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}
.dt-context-bar strong {
    color: var(--dt-accent);
    font-weight: 600;
}
.dt-ctx-sep {
    opacity: 0.4;
}

/* Mensajes */
.dt-messages {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
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
.dt-empty-state small {
    font-size: 11px;
    opacity: 0.7;
}

.dt-msg-wrapper {
    display: flex;
    flex-direction: column;
    max-width: 92%;
}
.dt-msg-wrapper.user {
    align-self: flex-end;
    align-items: flex-end;
}
.dt-msg-wrapper.assistant {
    align-self: flex-start;
    align-items: flex-start;
}
.dt-bubble {
    padding: 0.6rem 0.85rem;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
    word-break: break-word;
}
.dt-msg-wrapper.user .dt-bubble {
    background: var(--dt-user-bubble);
    color: var(--dt-text);
}
.dt-msg-wrapper.assistant .dt-bubble {
    background: var(--dt-ai-bubble);
    color: var(--dt-text);
}
.dt-msg-content {
    white-space: normal;
}
.dt-msg-content :deep(p) { margin: 0 0 0.5rem 0; }
.dt-msg-content :deep(p:last-child) { margin-bottom: 0; }
.dt-msg-content :deep(h1),
.dt-msg-content :deep(h2),
.dt-msg-content :deep(h3),
.dt-msg-content :deep(h4),
.dt-msg-content :deep(h5),
.dt-msg-content :deep(h6) {
    margin: 0.5rem 0 0.3rem 0;
    color: var(--dt-accent);
}
.dt-msg-content :deep(ul),
.dt-msg-content :deep(ol) {
    margin: 0.25rem 0 0.5rem 1.5rem;
    padding: 0;
}
.dt-msg-content :deep(.ia-code) {
    position: relative;
    background: var(--dt-bg);
    border: 1px solid var(--dt-border);
    border-radius: 6px;
    padding: 0.6rem 0.75rem;
    overflow-x: auto;
    font-size: 12px;
    margin: 0.5rem 0;
}
.dt-msg-content :deep(.ia-code code) {
    background: transparent;
    color: var(--dt-text);
    font-family: ui-monospace, "SF Mono", Menlo, monospace;
}
.dt-msg-content :deep(code) {
    background: var(--dt-bg);
    color: var(--dt-accent);
    padding: 2px 5px;
    border-radius: 3px;
    font-size: 12px;
}
.dt-msg-content :deep(.ia-code-copy) {
    position: absolute;
    top: 6px;
    right: 6px;
    background: var(--dt-sidebar-bg);
    border: 1px solid var(--dt-border);
    color: var(--dt-text-muted);
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 10px;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.15s, color 0.15s;
}
.dt-msg-content :deep(.ia-code:hover .ia-code-copy) { opacity: 1; }
.dt-msg-content :deep(.ia-code-copy:hover) { color: var(--dt-accent); }
.dt-msg-content :deep(a) {
    color: var(--dt-accent);
    text-decoration: none;
}
.dt-msg-content :deep(a:hover) { text-decoration: underline; }
.dt-msg-content :deep(table) {
    border-collapse: collapse;
    margin: 0.5rem 0;
    font-size: 12px;
}
.dt-msg-content :deep(th),
.dt-msg-content :deep(td) {
    border: 1px solid var(--dt-border);
    padding: 4px 8px;
}
.dt-msg-content :deep(th) {
    background: var(--dt-bg);
    color: var(--dt-accent);
}

.dt-msg-attachments {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 0.5rem;
}
.dt-att-thumb {
    border: 1px solid var(--dt-border);
    border-radius: 4px;
    padding: 4px;
    font-size: 11px;
    color: var(--dt-text-muted);
    background: var(--dt-bg);
}
.dt-att-thumb img {
    max-width: 120px;
    max-height: 80px;
    border-radius: 3px;
    display: block;
}

.dt-msg-meta {
    font-size: 10px;
    color: var(--dt-text-muted);
    margin-top: 0.35rem;
    font-family: ui-monospace, "SF Mono", Menlo, monospace;
    text-align: right;
}

.dt-msg-actions {
    display: flex;
    gap: 4px;
    margin-top: 4px;
    padding: 0 4px;
    opacity: 0;
    transition: opacity 0.15s;
}
.dt-msg-wrapper.assistant:hover .dt-msg-actions { opacity: 1; }
.dt-action-btn {
    background: transparent;
    border: 1px solid var(--dt-border);
    color: var(--dt-text-muted);
    border-radius: 4px;
    padding: 3px 8px;
    font-size: 11px;
    cursor: pointer;
    transition: color 0.15s, background 0.15s, border-color 0.15s;
}
.dt-action-btn:hover {
    color: var(--dt-accent);
    border-color: var(--dt-accent);
    background: var(--dt-hover);
}
.dt-action-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.dt-action-btn.active {
    color: var(--dt-accent);
    border-color: var(--dt-accent);
    background: var(--dt-hover);
}

/* Typing indicator */
.dt-typing {
    align-self: flex-start;
    display: flex;
    gap: 4px;
    padding: 0.5rem 0.85rem;
}
.dt-typing span {
    width: 6px;
    height: 6px;
    background: var(--dt-text-muted);
    border-radius: 50%;
    animation: dt-typing-bounce 1.2s infinite;
}
.dt-typing span:nth-child(2) { animation-delay: 0.15s; }
.dt-typing span:nth-child(3) { animation-delay: 0.3s; }
@keyframes dt-typing-bounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
    30% { transform: translateY(-4px); opacity: 1; }
}

/* Attachments bar (pendientes) */
.dt-attachments-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0.5rem 0.75rem;
    background: var(--dt-bg);
    border-top: 1px solid var(--dt-border);
    flex-shrink: 0;
}
.dt-att-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--dt-sidebar-bg);
    border: 1px solid var(--dt-border);
    border-radius: 12px;
    padding: 4px 10px 4px 4px;
    font-size: 11px;
    color: var(--dt-text);
    max-width: 200px;
}
.dt-att-img {
    width: 28px;
    height: 28px;
    object-fit: cover;
    border-radius: 50%;
}
.dt-att-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dt-att-remove {
    background: transparent;
    border: none;
    color: var(--dt-text-muted);
    cursor: pointer;
    padding: 0 4px;
    font-size: 11px;
}
.dt-att-remove:hover { color: var(--dt-danger); }

/* Input bar */
.dt-input-bar {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    padding: 0.5rem;
    border-top: 1px solid var(--dt-border);
    background: var(--dt-sidebar-bg);
    flex-shrink: 0;
}
.dt-attach-btns {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.dt-textarea {
    flex: 1;
    background: var(--dt-bg);
    color: var(--dt-text);
    border: 1px solid var(--dt-border);
    border-radius: 6px;
    padding: 0.5rem;
    font-size: 13px;
    resize: vertical;
    font-family: inherit;
    min-height: 50px;
    max-height: 200px;
}
.dt-textarea:focus { outline: none; border-color: var(--dt-accent); }
.dt-textarea:disabled { opacity: 0.5; cursor: not-allowed; }
.dt-send-btn {
    background: var(--dt-accent);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.5rem 0.9rem;
    cursor: pointer;
    transition: background 0.15s;
}
.dt-send-btn:hover:not(:disabled) { background: #e07a00; }
.dt-send-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.dt-icon-btn.recording {
    color: var(--dt-danger);
    animation: dt-pulse 1s infinite;
}
@keyframes dt-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* ====================================================================
   Divider
   ==================================================================== */
.dt-divider {
    width: 4px;
    background: var(--dt-border);
    cursor: col-resize;
    transition: background 0.15s;
    flex-shrink: 0;
}
.dt-divider:hover,
.dt-divider.dragging { background: var(--dt-accent); }

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
    flex-shrink: 0;
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
    padding: 0.35rem 0.55rem;
    border-radius: 4px;
    transition: color 0.15s, background 0.15s;
    font-size: 13px;
}
.dt-icon-btn:hover {
    color: var(--dt-accent);
    background: var(--dt-hover);
}
.dt-icon-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
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
