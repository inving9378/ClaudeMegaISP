<template>
    <div class="wa-shell">
        <!-- Banner FAKE MODE -->
        <div v-if="fakeMode" class="wa-fake-banner">
            <i class="bi bi-cone-striped"></i>
            Modo desarrollo — Evolution API simulada (WHATSAPP_FAKE=true). Los mensajes no se envían realmente.
        </div>

        <div class="wa-grid">
            <!-- ───────────── Columna 1: Lista de conversaciones (28%) ───────────── -->
            <aside class="wa-sidebar">
                <div class="wa-sidebar-header">
                    <div class="wa-title">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </div>
                    <select v-model="filterInstance" @change="loadConversations" class="wa-select">
                        <option value="">Todas las instancias</option>
                        <option v-for="ins in instances" :key="ins.id" :value="ins.id">
                            {{ ins.name }}
                        </option>
                    </select>
                </div>

                <div class="wa-search">
                    <i class="bi bi-search"></i>
                    <input
                        v-model="searchQuery"
                        @input="onSearchDebounced"
                        type="search"
                        placeholder="Buscar contacto o número"
                    />
                </div>

                <div class="wa-status-tabs">
                    <button
                        :class="{ active: statusFilter === 'open' }"
                        @click="setStatus('open')"
                    >Abiertas</button>
                    <button
                        :class="{ active: statusFilter === 'closed' }"
                        @click="setStatus('closed')"
                    >Cerradas</button>
                    <button
                        :class="{ active: statusFilter === '' }"
                        @click="setStatus('')"
                    >Todas</button>
                </div>

                <div class="wa-conv-list">
                    <div v-if="loadingConversations" class="wa-empty">Cargando…</div>
                    <div v-else-if="!conversations.length" class="wa-empty">Sin conversaciones</div>
                    <div
                        v-for="conv in conversations"
                        :key="conv.id"
                        class="wa-conv-item"
                        :class="{ active: activeConversation && activeConversation.id === conv.id }"
                        @click="selectConversation(conv)"
                    >
                        <div class="wa-avatar">
                            {{ initials(conv.contact_name || conv.contact_number) }}
                        </div>
                        <div class="wa-conv-body">
                            <div class="wa-conv-row1">
                                <span class="wa-conv-name">
                                    {{ conv.contact_name || conv.contact_number }}
                                </span>
                                <span class="wa-conv-time">{{ formatTime(conv.last_message_at) }}</span>
                            </div>
                            <div class="wa-conv-row2">
                                <span class="wa-conv-preview">
                                    {{ conv.last_message?.body || '—' }}
                                </span>
                                <span v-if="conv.unread_count > 0" class="wa-badge">
                                    {{ conv.unread_count }}
                                </span>
                            </div>
                            <div v-if="conv.client" class="wa-conv-client">
                                <i class="bi bi-person-check"></i>
                                <a :href="'/clientes/' + conv.client.id">
                                    Cliente #{{ conv.client.id }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ───────────── Columna 2: Chat activo (44%) ───────────── -->
            <main class="wa-chat">
                <template v-if="activeConversation">
                    <div class="wa-chat-header">
                        <div>
                            <div class="wa-chat-name">
                                {{ activeConversation.contact_name || activeConversation.contact_number }}
                            </div>
                            <div class="wa-chat-meta">
                                <span>{{ activeConversation.contact_number }}</span>
                                <span v-if="activeConversation.instance"> · {{ activeConversation.instance.name }}</span>
                                <span v-if="activeConversation.client" class="wa-client-tag">
                                    Cliente: {{ activeConversation.client.client_main_information?.name || ('#' + activeConversation.client.id) }}
                                </span>
                            </div>
                        </div>
                        <div class="wa-chat-actions">
                            <button @click="closeConversation" title="Cerrar conversación">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            <a v-if="activeConversation.client" :href="'/clientes/' + activeConversation.client.id" title="Ir al cliente">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <div ref="messagesContainer" class="wa-messages">
                        <div v-if="loadingMessages" class="wa-empty">Cargando mensajes…</div>
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            class="wa-bubble"
                            :class="msg.direction === 'out' ? 'out' : 'in'"
                        >
                            <div class="wa-bubble-body">{{ msg.body }}</div>
                            <div class="wa-bubble-meta">
                                <span>{{ formatTime(msg.created_at) }}</span>
                                <span v-if="msg.direction === 'out'" class="wa-status-icon">
                                    <i v-if="msg.status === 'pending'" class="bi bi-clock"></i>
                                    <i v-else-if="msg.status === 'sent'" class="bi bi-check"></i>
                                    <i v-else-if="msg.status === 'delivered'" class="bi bi-check-all"></i>
                                    <i v-else-if="msg.status === 'read'" class="bi bi-check-all" style="color:#53bdeb"></i>
                                    <i v-else-if="msg.status === 'failed'" class="bi bi-exclamation-triangle" style="color:#f15c6d"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de respuestas rápidas IA -->
                    <div v-if="iaResult && iaResult.quick_replies && iaResult.quick_replies.length" class="wa-quick-replies">
                        <div class="wa-quick-label">
                            <i class="bi bi-stars"></i> Respuestas sugeridas por IA
                        </div>
                        <div class="wa-quick-chips">
                            <span
                                v-for="reply in iaResult.quick_replies"
                                :key="reply"
                                @click="useQuickReply(reply)"
                                class="wa-quick-chip"
                            >{{ reply }}</span>
                        </div>
                    </div>

                    <!-- Indicador borrador IA -->
                    <div v-if="iaResult && newMessage && newMessage === iaResult.draft" class="wa-draft-indicator">
                        <i class="bi bi-robot"></i> Borrador generado por IA — edítalo antes de enviar
                    </div>

                    <div class="wa-composer">
                        <textarea
                            ref="composer"
                            v-model="newMessage"
                            @keydown.enter.exact.prevent="sendMessage"
                            placeholder="Escribe un mensaje"
                            rows="2"
                        ></textarea>
                        <button @click="sendMessage" :disabled="sending || !newMessage.trim()">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </template>
                <div v-else class="wa-empty-chat">
                    <i class="bi bi-chat-square-dots"></i>
                    <p>Selecciona una conversación para empezar</p>
                </div>
            </main>

            <!-- ───────────── Columna 3: Panel IA (28%) ───────────── -->
            <aside class="wa-ia-panel">
                <div class="wa-ia-header">
                    <span><i class="bi bi-stars"></i> Asistente IA</span>
                    <label class="wa-toggle">
                        <input type="checkbox" v-model="iaEnabled" />
                        <span>Auto-IA</span>
                    </label>
                </div>

                <div v-if="!activeConversation" class="wa-ia-empty">
                    Selecciona una conversación para ver la asistencia IA
                </div>

                <template v-else>
                    <div v-if="iaLoading" class="wa-ia-empty">Analizando con IA…</div>

                    <template v-else-if="iaResult">
                        <!-- Intención detectada -->
                        <div class="wa-ia-section">
                            <div class="wa-ia-title">Intención detectada</div>
                            <div class="wa-intent-chip primary">
                                <i :class="intentIcon(iaResult.intent?.primary)"></i>
                                <span>{{ iaResult.intent?.primary_label || '—' }}</span>
                                <em>{{ Math.round((iaResult.intent?.confidence || 0) * 100) }}%</em>
                            </div>
                            <div v-if="iaResult.intent?.secondary_label" class="wa-intent-chip secondary">
                                <i :class="intentIcon(iaResult.intent?.secondary)"></i>
                                <span>{{ iaResult.intent.secondary_label }}</span>
                                <em>{{ Math.round((iaResult.intent.secondary_confidence || 0) * 100) }}%</em>
                            </div>
                        </div>

                        <!-- Contexto cliente -->
                        <div v-if="activeConversation.client" class="wa-ia-section wa-client-card">
                            <div class="wa-ia-title">Contexto del cliente</div>
                            <div class="wa-client-info">
                                <div><strong>{{ activeConversation.client.client_main_information?.name || 'N/A' }}</strong></div>
                                <div><i class="bi bi-wifi"></i> {{ clientPlan }}</div>
                                <div><i class="bi bi-calendar-check"></i> Último pago: {{ activeConversation.client.client_main_information?.fecha_pago || '—' }}</div>
                                <div><i class="bi bi-clock-history"></i> {{ clientSeniority }}</div>
                                <div>
                                    <span class="wa-client-status" :class="clientStatusClass">
                                        {{ activeConversation.client.client_main_information?.estado || 'N/A' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tono -->
                        <div class="wa-ia-section">
                            <div class="wa-ia-title">Tono</div>
                            <div class="wa-tone-buttons">
                                <button
                                    :class="{ active: selectedTone === 'friendly' }"
                                    @click="changeTone('friendly')"
                                >Amigable</button>
                                <button
                                    :class="{ active: selectedTone === 'formal' }"
                                    @click="changeTone('formal')"
                                >Formal</button>
                                <button
                                    :class="{ active: selectedTone === 'brief' }"
                                    @click="changeTone('brief')"
                                >Breve</button>
                            </div>
                        </div>

                        <!-- Acciones IA -->
                        <div class="wa-ia-section">
                            <button class="wa-ia-btn full" @click="regenerateAssist">
                                <i class="bi bi-arrow-clockwise"></i> Regenerar respuesta
                            </button>
                            <button class="wa-ia-btn full primary" @click="sendDraftDirect" :disabled="!newMessage.trim()">
                                <i class="bi bi-send"></i> Enviar borrador directo
                            </button>
                        </div>

                        <!-- Acciones sugeridas -->
                        <div v-if="iaResult.suggested_actions && iaResult.suggested_actions.length" class="wa-ia-section">
                            <div class="wa-ia-title">Acciones sugeridas</div>
                            <div
                                v-for="(action, idx) in iaResult.suggested_actions"
                                :key="idx"
                                class="wa-action-card"
                                :class="'type-' + action.type"
                            >
                                <div class="wa-action-label">{{ action.label }}</div>
                                <div class="wa-action-detail">{{ action.detail }}</div>
                            </div>
                        </div>

                        <!-- Historial IA -->
                        <div v-if="iaHistory.length" class="wa-ia-section">
                            <div class="wa-ia-title">Historial IA</div>
                            <div v-for="(h, idx) in iaHistory.slice(0, 5)" :key="idx" class="wa-ia-history">
                                <span>{{ h.time }}</span> · <em>{{ h.intent }}</em>
                            </div>
                        </div>
                    </template>

                    <div v-else class="wa-ia-empty">
                        Sin asistencia disponible — abre una conversación con mensajes no leídos o presiona Regenerar.
                        <button class="wa-ia-btn full" style="margin-top: 12px" @click="regenerateAssist">
                            <i class="bi bi-stars"></i> Generar asistencia
                        </button>
                    </div>
                </template>
            </aside>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'WhatsAppPanel',

    props: {
        fakeMode: { type: Boolean, default: false },
    },

    data() {
        return {
            instances: [],
            conversations: [],
            activeConversation: null,
            messages: [],
            newMessage: '',
            sending: false,
            loadingConversations: false,
            loadingMessages: false,
            searchQuery: '',
            statusFilter: 'open',
            filterInstance: '',
            pollingInterval: null,
            searchTimer: null,

            iaEnabled: true,
            iaLoading: false,
            iaResult: null,
            selectedTone: 'friendly',
            iaHistory: [],
        };
    },

    computed: {
        clientPlan() {
            const c = this.activeConversation?.client;
            return c?.internet_plan?.name || c?.plan?.name || 'Sin plan';
        },
        clientSeniority() {
            const c = this.activeConversation?.client;
            if (!c?.created_at) return 'Antigüedad: N/A';
            try {
                const d = new Date(c.created_at);
                const months = Math.round((Date.now() - d.getTime()) / (1000 * 60 * 60 * 24 * 30));
                return `Cliente hace ${months} meses`;
            } catch {
                return 'Antigüedad: N/A';
            }
        },
        clientStatusClass() {
            const s = (this.activeConversation?.client?.client_main_information?.estado || '').toLowerCase();
            if (s === 'activo') return 'ok';
            if (s === 'bloqueado' || s === 'inactivo') return 'bad';
            return 'warn';
        },
    },

    methods: {
        async loadInstances() {
            try {
                const { data } = await axios.get('/whatsapp/api/instances');
                this.instances = Array.isArray(data) ? data : [];
            } catch (e) {
                this.instances = [];
            }
        },

        // Carga inicial: con spinner, reemplaza la lista entera.
        async loadConversations() {
            this.loadingConversations = true;
            try {
                this.conversations = await this.fetchConversations();
            } catch {
                this.conversations = [];
            } finally {
                this.loadingConversations = false;
            }
        },

        // Polling silencioso: solo actualiza si hay cambios reales.
        // Evita el parpadeo de reasignar this.conversations cada 5s sin necesidad.
        async refreshConversationsQuiet() {
            try {
                const next = await this.fetchConversations();
                if (!this.conversationsChanged(this.conversations, next)) return;
                this.conversations = next;
            } catch { /* silencio en polling */ }
        },

        async fetchConversations() {
            const { data } = await axios.get('/whatsapp/api/conversations', {
                params: {
                    instance: this.filterInstance || undefined,
                    status: this.statusFilter || undefined,
                    search: this.searchQuery || undefined,
                },
            });
            return data.data || [];
        },

        // Comparación liviana: cambio en cantidad, IDs en orden, unread_count o
        // last_message_at del primer item (ordenado por last_message_at desc).
        conversationsChanged(curr, next) {
            if (curr.length !== next.length) return true;
            for (let i = 0; i < next.length; i++) {
                const a = curr[i] || {};
                const b = next[i] || {};
                if (a.id !== b.id) return true;
                if (a.unread_count !== b.unread_count) return true;
                if (a.last_message_at !== b.last_message_at) return true;
                if (a.status !== b.status) return true;
            }
            return false;
        },

        // Carga inicial de mensajes — con spinner, scroll al fondo siempre.
        async loadMessages(conversationId) {
            this.loadingMessages = true;
            try {
                this.messages = await this.fetchMessages(conversationId);
                this.$nextTick(() => this.scrollToBottom());
            } catch {
                this.messages = [];
            } finally {
                this.loadingMessages = false;
            }
        },

        // Polling silencioso de mensajes: solo asigna si llegaron nuevos.
        // Mantiene scroll-position del usuario; auto-scroll solo si ya estaba al fondo.
        async refreshMessagesQuiet(conversationId) {
            try {
                const next = await this.fetchMessages(conversationId);
                const lastCurrId = this.messages.length ? this.messages[this.messages.length - 1].id : null;
                const lastNextId = next.length ? next[next.length - 1].id : null;
                if (lastCurrId === lastNextId && this.messages.length === next.length) return;

                const wasAtBottom = this.isScrolledToBottom();
                this.messages = next;
                if (wasAtBottom) {
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch { /* silencio en polling */ }
        },

        async fetchMessages(conversationId) {
            const { data } = await axios.get(`/whatsapp/api/conversations/${conversationId}/messages`);
            // El backend devuelve ASC (antiguo → reciente) gracias al orderBy de la
            // relación messages() en el modelo WhatsAppConversation. No invertir.
            return data.data || [];
        },

        isScrolledToBottom() {
            const el = this.$refs.messagesContainer;
            if (!el) return true;
            // tolerancia de 32px para que cambios mínimos de scroll del usuario
            // no impidan el auto-scroll al recibir un mensaje nuevo.
            return el.scrollHeight - el.scrollTop - el.clientHeight < 32;
        },

        async selectConversation(conv) {
            this.activeConversation = conv;
            this.iaResult = null;
            await this.loadMessages(conv.id);
            await this.markRead(conv.id);
            if (conv.unread_count > 0 && this.iaEnabled) {
                await this.loadIaAssist(conv.id);
            }
        },

        async sendMessage() {
            if (!this.activeConversation || !this.newMessage.trim() || this.sending) return;
            this.sending = true;
            const body = this.newMessage.trim();

            // Optimistic UI
            this.messages.push({
                id: 'tmp-' + Date.now(),
                direction: 'out',
                body,
                status: 'pending',
                created_at: new Date().toISOString(),
            });
            this.newMessage = '';
            this.$nextTick(() => this.scrollToBottom());

            try {
                await axios.post(
                    `/whatsapp/api/conversations/${this.activeConversation.id}/send`,
                    { body }
                );
                // Refrescar sin parpadeo — sustituye el mensaje optimista con el real.
                await this.refreshMessagesQuiet(this.activeConversation.id);
            } catch (e) {
                console.error('Error enviando mensaje', e);
            } finally {
                this.sending = false;
            }
        },

        async markRead(id) {
            try { await axios.post(`/whatsapp/api/conversations/${id}/mark-read`); } catch {}
        },

        async closeConversation() {
            if (!this.activeConversation) return;
            try {
                await axios.post(`/whatsapp/api/conversations/${this.activeConversation.id}/close`);
                this.activeConversation = null;
                this.messages = [];
                this.iaResult = null;
                await this.loadConversations();
            } catch {}
        },

        setStatus(s) {
            this.statusFilter = s;
            this.loadConversations();
        },

        onSearchDebounced() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.loadConversations(), 350);
        },

        useQuickReply(text) {
            this.newMessage = text;
            this.$nextTick(() => this.$refs.composer?.focus());
        },

        async loadIaAssist(conversationId) {
            this.iaLoading = true;
            try {
                const { data } = await axios.post(
                    `/whatsapp/api/conversations/${conversationId}/ia-assist`,
                    { tone: this.selectedTone }
                );
                this.iaResult = data;
                this.newMessage = data.draft || '';
                this.iaHistory.unshift({
                    time: new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }),
                    intent: data.intent?.primary_label || 'Sin intención',
                });
            } catch (e) {
                console.error('IA assist error', e);
                this.iaResult = null;
            } finally {
                this.iaLoading = false;
            }
        },

        async changeTone(tone) {
            this.selectedTone = tone;
            if (this.activeConversation) await this.loadIaAssist(this.activeConversation.id);
        },

        async regenerateAssist() {
            if (this.activeConversation) await this.loadIaAssist(this.activeConversation.id);
        },

        async sendDraftDirect() {
            if (this.newMessage.trim()) await this.sendMessage();
        },

        startPolling() {
            this.pollingInterval = setInterval(async () => {
                // Polling silencioso: NO toca loadingConversations/loadingMessages,
                // NO reasigna arrays a menos que haya cambios reales. Resuelve el
                // parpadeo visible del refresh cada 5s.
                await this.refreshConversationsQuiet();
                if (this.activeConversation) {
                    await this.refreshMessagesQuiet(this.activeConversation.id);
                }
            }, 5000);
        },

        stopPolling() {
            clearInterval(this.pollingInterval);
        },

        scrollToBottom() {
            const el = this.$refs.messagesContainer;
            if (el) el.scrollTop = el.scrollHeight;
        },

        formatTime(ts) {
            if (!ts) return '';
            const d = new Date(ts);
            const today = new Date();
            const sameDay = d.toDateString() === today.toDateString();
            if (sameDay) {
                return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            }
            return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit' });
        },

        initials(text) {
            if (!text) return '?';
            return text.replace(/[^A-Za-zÀ-ÿ0-9 ]/g, '')
                .split(' ').filter(Boolean).slice(0, 2)
                .map(w => w[0]).join('').toUpperCase() || text.slice(-2);
        },

        intentIcon(intent) {
            const map = {
                upgrade_plan: 'bi bi-arrow-up-circle',
                price_inquiry: 'bi bi-currency-dollar',
                service_issue: 'bi bi-wifi-off',
                payment_inquiry: 'bi bi-receipt',
                disconnection_request: 'bi bi-x-octagon',
                reconnection_request: 'bi bi-arrow-clockwise',
                schedule_visit: 'bi bi-calendar-event',
                invoice_dispute: 'bi bi-file-earmark-x',
                complaint: 'bi bi-emoji-frown',
                compliment: 'bi bi-emoji-smile',
                general_inquiry: 'bi bi-chat-dots',
            };
            return map[intent] || 'bi bi-chat-dots';
        },
    },

    mounted() {
        this.loadInstances();
        this.loadConversations();
        this.startPolling();
    },

    beforeUnmount() {
        this.stopPolling();
    },
};
</script>

<style scoped>
.wa-shell {
    height: calc(100vh - 60px);
    display: flex;
    flex-direction: column;
    background: #0b141a;
    color: #e9edef;
    font-family: 'Helvetica Neue', Arial, sans-serif;
}
.wa-fake-banner {
    background: #f7c948;
    color: #2b2300;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
}
.wa-grid {
    flex: 1;
    display: grid;
    grid-template-columns: 28% 44% 28%;
    overflow: hidden;
}

/* ───────────── Sidebar ───────────── */
.wa-sidebar {
    background: #111b21;
    border-right: 1px solid #1f2c33;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.wa-sidebar-header {
    padding: 12px;
    border-bottom: 1px solid #1f2c33;
}
.wa-title {
    font-size: 17px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #25d366;
}
.wa-select, .wa-search input {
    width: 100%;
    background: #202c33;
    color: #e9edef;
    border: 1px solid #2a3942;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 13px;
}
.wa-search {
    position: relative;
    padding: 8px 12px;
}
.wa-search i {
    position: absolute;
    left: 22px;
    top: 50%;
    transform: translateY(-50%);
    color: #8696a0;
}
.wa-search input { padding-left: 30px; }
.wa-status-tabs {
    display: flex;
    padding: 0 12px 8px;
    gap: 4px;
}
.wa-status-tabs button {
    flex: 1;
    background: transparent;
    color: #8696a0;
    border: 1px solid #2a3942;
    border-radius: 4px;
    padding: 4px 6px;
    font-size: 11px;
    cursor: pointer;
}
.wa-status-tabs button.active {
    background: #2a3942;
    color: #e9edef;
}
.wa-conv-list { flex: 1; overflow-y: auto; }
.wa-empty {
    padding: 24px 12px;
    color: #8696a0;
    text-align: center;
    font-size: 13px;
}
.wa-conv-item {
    display: flex;
    gap: 10px;
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #1f2c33;
    transition: background .15s;
}
.wa-conv-item:hover { background: #1f2c33; }
.wa-conv-item.active { background: #2a3942; }
.wa-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #25d366, #128c7e);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}
.wa-conv-body { flex: 1; min-width: 0; }
.wa-conv-row1, .wa-conv-row2 {
    display: flex; justify-content: space-between; align-items: center;
}
.wa-conv-name {
    font-weight: 500;
    font-size: 14px;
    color: #e9edef;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.wa-conv-time { font-size: 11px; color: #8696a0; flex-shrink: 0; }
.wa-conv-preview {
    color: #8696a0;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}
.wa-badge {
    background: #25d366;
    color: #111b21;
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
}
.wa-conv-client {
    font-size: 11px;
    color: #53bdeb;
    margin-top: 2px;
}
.wa-conv-client a { color: #53bdeb; text-decoration: none; }

/* ───────────── Chat ───────────── */
.wa-chat {
    background: #0b141a;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect fill="%230b141a" width="40" height="40"/><circle cx="2" cy="2" r="1" fill="%23142028"/></svg>');
}
.wa-chat-header {
    background: #202c33;
    padding: 10px 16px;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid #1f2c33;
}
.wa-chat-name { font-weight: 600; }
.wa-chat-meta { font-size: 12px; color: #8696a0; }
.wa-client-tag {
    margin-left: 8px;
    background: #1f3a4a;
    color: #53bdeb;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}
.wa-chat-actions {
    display: flex; gap: 6px;
}
.wa-chat-actions button, .wa-chat-actions a {
    background: transparent;
    border: none;
    color: #aebac1;
    font-size: 18px;
    cursor: pointer;
    padding: 4px 8px;
}
.wa-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.wa-bubble {
    max-width: 70%;
    padding: 6px 10px;
    border-radius: 8px;
    word-wrap: break-word;
    font-size: 14px;
    position: relative;
}
.wa-bubble.in {
    align-self: flex-start;
    background: #202c33;
    color: #e9edef;
}
.wa-bubble.out {
    align-self: flex-end;
    background: #005c4b;
    color: #e9edef;
}
.wa-bubble-body { white-space: pre-wrap; }
.wa-bubble-meta {
    font-size: 10px;
    color: rgba(233, 237, 239, 0.6);
    margin-top: 2px;
    text-align: right;
}
.wa-status-icon { margin-left: 4px; }

.wa-quick-replies {
    padding: 8px 16px;
    background: #182229;
    border-top: 1px solid #2ecc7115;
}
.wa-quick-label {
    font-size: 10px;
    color: #2ecc7188;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.wa-quick-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.wa-quick-chip {
    background: #1a2a1a;
    border: 1px solid #2ecc7130;
    border-radius: 16px;
    padding: 5px 12px;
    color: #9de0b3;
    font-size: 11px;
    cursor: pointer;
    transition: background .15s;
}
.wa-quick-chip:hover { background: #2a3a2a; }

.wa-draft-indicator {
    padding: 6px 16px;
    background: #1a2a1a;
    color: #9de0b3;
    font-size: 11px;
    border-top: 1px solid #2ecc7130;
}

.wa-composer {
    background: #202c33;
    padding: 10px 14px;
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.wa-composer textarea {
    flex: 1;
    resize: none;
    background: #2a3942;
    color: #e9edef;
    border: none;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    font-family: inherit;
}
.wa-composer textarea:focus { outline: none; }
.wa-composer button {
    background: #25d366;
    color: #fff;
    border: none;
    width: 42px; height: 42px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
}
.wa-composer button:disabled {
    background: #2a3942;
    color: #8696a0;
    cursor: not-allowed;
}

.wa-empty-chat {
    flex: 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: #8696a0;
}
.wa-empty-chat i { font-size: 64px; margin-bottom: 12px; }

/* ───────────── Panel IA ───────────── */
.wa-ia-panel {
    background: #12122a;
    border-left: 1px solid #1f1f3a;
    padding: 12px;
    overflow-y: auto;
}
.wa-ia-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
    color: #2ecc71;
    font-weight: 600;
}
.wa-toggle {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px;
    color: #9de0b3;
    cursor: pointer;
}
.wa-toggle input { accent-color: #2ecc71; }
.wa-ia-empty {
    color: #6a6a8e;
    font-size: 12px;
    padding: 12px;
    text-align: center;
}
.wa-ia-section {
    background: #1a1a3a;
    border: 1px solid #2ecc7115;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 10px;
}
.wa-ia-title {
    font-size: 10px;
    color: #2ecc7188;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 6px;
}
.wa-intent-chip {
    display: flex; align-items: center; gap: 6px;
    background: #1a2a3a;
    padding: 6px 10px;
    border-radius: 6px;
    margin-bottom: 4px;
    font-size: 12px;
    color: #c0d8f0;
}
.wa-intent-chip.primary { border-left: 3px solid #2ecc71; }
.wa-intent-chip.secondary { border-left: 3px solid #53bdeb; opacity: .8; }
.wa-intent-chip em {
    margin-left: auto;
    font-style: normal;
    font-weight: 600;
    color: #9de0b3;
}

.wa-client-card .wa-client-info {
    font-size: 12px; color: #c0d8f0;
    display: flex; flex-direction: column; gap: 4px;
}
.wa-client-info div { display: flex; align-items: center; gap: 6px; }
.wa-client-status {
    display: inline-block; padding: 2px 10px;
    border-radius: 10px; font-size: 11px;
    background: #f7c948; color: #2b2300;
}
.wa-client-status.ok { background: #2ecc71; color: #0a2b15; }
.wa-client-status.bad { background: #f15c6d; color: #fff; }
.wa-client-status.warn { background: #f7c948; color: #2b2300; }

.wa-tone-buttons { display: flex; gap: 4px; }
.wa-tone-buttons button {
    flex: 1; background: #1a2a3a; color: #9de0b3;
    border: 1px solid #2ecc7130; border-radius: 4px;
    padding: 5px 6px; font-size: 11px; cursor: pointer;
}
.wa-tone-buttons button.active {
    background: #2ecc71; color: #0a2b15; font-weight: 600;
}

.wa-ia-btn {
    background: #2a3942; color: #e9edef;
    border: 1px solid #2ecc7130; border-radius: 6px;
    padding: 7px 10px; font-size: 12px; cursor: pointer;
    width: 100%;
    margin-bottom: 6px;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.wa-ia-btn.full { width: 100%; }
.wa-ia-btn.primary { background: #2ecc71; color: #0a2b15; font-weight: 600; }
.wa-ia-btn:disabled { opacity: .5; cursor: not-allowed; }

.wa-action-card {
    background: #1a2a3a; border-left: 3px solid #2ecc71;
    padding: 6px 10px; margin-bottom: 6px;
    border-radius: 4px;
}
.wa-action-card.type-offer { border-left-color: #2ecc71; }
.wa-action-card.type-ticket { border-left-color: #53bdeb; }
.wa-action-card.type-discount { border-left-color: #f7c948; }
.wa-action-card.type-escalate { border-left-color: #f15c6d; }
.wa-action-label { font-size: 12px; font-weight: 600; color: #e9edef; }
.wa-action-detail { font-size: 11px; color: #aebac1; margin-top: 2px; }

.wa-ia-history {
    font-size: 11px; color: #8696a0;
    padding: 4px 0; border-top: 1px solid #2ecc7115;
}
.wa-ia-history em { color: #9de0b3; font-style: normal; }
</style>
