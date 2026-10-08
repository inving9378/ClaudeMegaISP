<template>
    <div class="ia-float-btn" @click="toggleChat" :class="{ 'has-pulse': !isOpen }" title="JARVIS — tu asistente">
        <div class="ia-btn-inner"><i v-if="!isOpen" class="fas fa-robot"></i><i v-else class="fas fa-times"></i></div>
        <span v-if="!isOpen" class="ia-badge">IA</span>
    </div>
    <transition name="ia-slide">
        <div v-if="isOpen" class="ia-chat-panel" :class="{ 'ia-expanded': isExpanded }">
            <div class="ia-header">
                <div class="ia-header-left">
                    <div class="ia-avatar"><i class="fas fa-robot"></i></div>
                    <div>
                        <div class="ia-title">JARVIS</div>
                        <div class="ia-subtitle"><span class="ia-dot"></span>{{ isTyping ? 'Pensando...' : 'En línea' }}</div>
                    </div>
                </div>
                <div class="ia-header-actions">
                    <button class="ia-btn-icon" @click="isExpanded = !isExpanded" :title="isExpanded ? 'Reducir' : 'Agrandar'"><i :class="isExpanded ? 'fas fa-compress-alt' : 'fas fa-expand-alt'"></i></button>
                    <button class="ia-btn-icon" @click="newConversation" :disabled="isTyping" title="Empezar tema nuevo"><i class="fas fa-plus"></i></button>
                    <button class="ia-btn-icon" @click="isOpen = false" title="Cerrar"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div v-if="loaded && messages.length === 0" class="ia-suggestions">
                <div class="ia-welcome"><i class="fas fa-robot ia-welcome-icon"></i><p>¡Hola! Soy JARVIS. Pregúntame por un cliente, sus pagos, tickets o cómo hacer algo en el sistema.</p></div>
                <div class="ia-chips" v-if="suggestions.length">
                    <button class="ia-chip" v-for="s in suggestions" :key="s.text" @click="sendSuggestion(s.text)">
                        <i class="fas fa-comment-dots"></i> {{ s.label }}
                    </button>
                </div>
            </div>
            <div class="ia-messages" ref="messagesContainer">
                <div v-for="(msg, i) in messages" :key="i" :class="['ia-msg', msg.role === 'user' ? 'ia-msg-user' : 'ia-msg-ai']">
                    <div v-if="msg.role === 'assistant'" class="ia-msg-avatar"><i class="fas fa-robot"></i></div>
                    <div class="ia-msg-body">
                        <div class="ia-msg-text" v-html="fmt(msg.content)"></div>
                        <div class="ia-msg-time">{{ ft(msg.timestamp) }}</div>
                    </div>
                </div>
                <!-- Cambio que JARVIS preparó: solo se aplica con este botón (o respondiendo "sí"). -->
                <div v-if="pendingAction" class="ia-confirm">
                    <div class="ia-confirm-title"><i class="fas fa-hand-paper"></i> ¿Lo hago?</div>
                    <div class="ia-confirm-text" v-html="fmt(pendingAction.resumen)"></div>
                    <div class="ia-confirm-actions">
                        <button class="ia-confirm-yes" @click="resolveAction('confirmar')" :disabled="isTyping"><i class="fas fa-check"></i> Sí, hazlo</button>
                        <button class="ia-confirm-no" @click="resolveAction('cancelar')" :disabled="isTyping">Cancelar</button>
                    </div>
                </div>
                <div v-if="isTyping" class="ia-msg ia-msg-ai">
                    <div class="ia-msg-avatar"><i class="fas fa-robot"></i></div>
                    <div class="ia-msg-body"><div class="ia-typing"><span></span><span></span><span></span></div></div>
                </div>
            </div>
            <div class="ia-input-area">
                <div class="ia-input-row">
                    <textarea ref="inputRef" v-model="inputText" @keydown.enter.exact.prevent="send" placeholder="Escribe... (Enter para enviar)" :disabled="isTyping" rows="1" class="ia-input"></textarea>
                    <button class="ia-send-btn" @click="send" :disabled="isTyping || !inputText.trim()"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div class="ia-input-hint">Shift+Enter = nueva línea · JARVIS recuerda la plática; <i class="fas fa-plus"></i> empieza un tema nuevo</div>
            </div>
        </div>
    </transition>
</template>
<script>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';

const escapeHtml = (t) => String(t)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

/**
 * Texto de JARVIS → HTML seguro: primero se escapa TODO (ningún HTML de la IA o del usuario se
 * ejecuta) y luego se aplican solo negritas, `código` y enlaces internos [texto](/ruta).
 * Un enlace que no empiece con "/" se queda como texto.
 */
const fmt = (t) => {
    if (!t) return '';
    return escapeHtml(t)
        .replace(/\[([^\]]+)\]\((\/[^)\s]*)\)/g, (m, texto, ruta) => `<a class="ia-link" href="${ruta}">${texto}</a>`)
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/`(.*?)`/g, '<code>$1</code>')
        .replace(/\n/g, '<br>');
};

export default {
    name: 'IaChatFloat',
    props: { url: String },
    setup(props) {
        const isOpen = ref(false), isExpanded = ref(false), isTyping = ref(false), inputText = ref(''), messages = ref([]);
        const messagesContainer = ref(null), inputRef = ref(null);
        const suggestions = ref([]);
        const pendingAction = ref(null);
        const loaded = ref(false);

        const scroll = () => nextTick(() => { const e = messagesContainer.value; if (e) e.scrollTop = e.scrollHeight; });
        const say = (content) => { messages.value.push({ role: 'assistant', content, timestamp: new Date() }); scroll(); };

        const loadSuggestions = async () => {
            try {
                const res = await axios.get(`${props.url}/ia/suggestions`);
                const list = (res.data && res.data.success) ? (res.data.suggestions || []) : [];
                suggestions.value = list.map((s) => {
                    const label = s.text.charAt(0).toUpperCase() + s.text.slice(1);
                    return { text: s.text, label: label.length > 30 ? label.slice(0, 28) + '…' : label };
                });
            } catch (e) {
                suggestions.value = [];
            }
        };

        // La memoria vive en el servidor: al abrir se trae la plática de este usuario.
        const loadConversation = async () => {
            try {
                const res = await axios.get(`${props.url}/ia/asistente/conversacion`);
                if (res.data && res.data.success) {
                    messages.value = res.data.messages || [];
                    pendingAction.value = res.data.accion_pendiente || null;
                }
            } catch (e) {
                // Sin historial no se bloquea el chat: se puede seguir escribiendo.
            } finally {
                loaded.value = true;
                scroll();
            }
        };

        const toggleChat = () => {
            isOpen.value = !isOpen.value;
            if (isOpen.value) {
                if (!loaded.value) loadConversation();
                nextTick(() => inputRef.value?.focus());
                scroll();
            }
        };
        const sendSuggestion = (t) => { inputText.value = t; send(); };
        const ft = (d) => d ? new Date(d).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '';

        const send = async () => {
            const text = inputText.value.trim();
            if (!text || isTyping.value) return;
            messages.value.push({ role: 'user', content: text, timestamp: new Date() });
            inputText.value = ''; isTyping.value = true; pendingAction.value = null; scroll();
            try {
                const res = await axios.post(`${props.url}/ia/chat`, { message: text, context: window.location.pathname + window.location.search });
                if (res.data.success) {
                    say(res.data.message);
                    pendingAction.value = res.data.accion_pendiente || null;
                } else {
                    say('❌ ' + (res.data.error || 'No pude responder.'));
                }
            } catch (e) {
                say('❌ No pude conectarme. Revisa tu conexión e inténtalo de nuevo.');
            } finally { isTyping.value = false; scroll(); nextTick(() => inputRef.value?.focus()); }
        };

        const resolveAction = async (que) => {
            const accion = pendingAction.value;
            if (!accion || isTyping.value) return;
            isTyping.value = true;
            messages.value.push({ role: 'user', content: que === 'confirmar' ? 'Sí, hazlo' : 'Cancelar', timestamp: new Date() });
            pendingAction.value = null;
            try {
                const res = await axios.post(`${props.url}/ia/asistente/acciones/${accion.id}/${que}`);
                say(res.data.message || 'Listo.');
            } catch (e) {
                say('❌ No pude completar el cambio. Inténtalo de nuevo.');
            } finally { isTyping.value = false; scroll(); }
        };

        const newConversation = async () => {
            if (isTyping.value) return;
            try {
                await axios.post(`${props.url}/ia/asistente/nueva`);
                messages.value = [];
                pendingAction.value = null;
            } catch (e) {
                say('❌ No pude empezar un tema nuevo.');
            }
        };

        onMounted(loadSuggestions);

        return { isOpen, isExpanded, isTyping, inputText, messages, messagesContainer, inputRef, suggestions, pendingAction, loaded, toggleChat, sendSuggestion, send, resolveAction, newConversation, fmt, ft };
    },
};
</script>
<style scoped>
/* right:24px — la esquina de siempre del robotsito. NO moverlo: en ese mismo lugar (debajo, z-index 1040)
   está montada la burbuja de la Torre, y el robot la tapa; si se mueve, la burbuja aparece flotando. */
.ia-float-btn{position:fixed;bottom:24px;right:24px;z-index:9999;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 20px rgba(99,102,241,.5);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .2s}
.ia-float-btn:hover{transform:scale(1.1)}
.ia-float-btn.has-pulse::after{content:'';position:absolute;width:100%;height:100%;border-radius:50%;background:rgba(99,102,241,.3);animation:ia-pulse 2s infinite}
@keyframes ia-pulse{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.6);opacity:0}}
.ia-btn-inner{color:white;font-size:22px;z-index:1}.ia-badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;font-size:9px;font-weight:700;border-radius:10px;padding:2px 5px;z-index:2}
.ia-chat-panel{position:fixed;bottom:90px;right:24px;z-index:9998;width:400px;max-width:calc(100vw - 32px);height:580px;max-height:calc(100vh - 110px);background:white;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.15);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(99,102,241,.15)}
.ia-chat-panel.ia-expanded{width:720px;height:82vh}
.ia-header{background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.ia-header-left{display:flex;align-items:center;gap:10px}
.ia-avatar{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:white;font-size:18px}
.ia-title{color:white;font-weight:600;font-size:14px}.ia-subtitle{color:rgba(255,255,255,.8);font-size:11px;display:flex;align-items:center;gap:4px}
.ia-dot{width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;animation:blink 1.5s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.ia-header-actions{display:flex;gap:4px}
.ia-btn-icon{background:rgba(255,255,255,.15);border:none;color:white;width:28px;height:28px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px}
.ia-btn-icon:hover{background:rgba(255,255,255,.3)}
.ia-btn-icon:disabled{opacity:.5;cursor:not-allowed}
.ia-suggestions{padding:16px;flex-shrink:0}
.ia-welcome{text-align:center;padding:8px 0 12px}
.ia-welcome-icon{font-size:32px;color:#6366f1;margin-bottom:8px;display:block}
.ia-welcome p{color:#64748b;font-size:13px;margin:0}
.ia-chips{display:flex;flex-wrap:wrap;gap:6px}
.ia-chip{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:20px;padding:5px 12px;font-size:12px;cursor:pointer;color:#475569;transition:all .2s;display:flex;align-items:center;gap:5px}
.ia-chip:hover{background:#e0e7ff;border-color:#6366f1;color:#6366f1}
.ia-messages{flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:12px}
.ia-messages::-webkit-scrollbar{width:4px}.ia-messages::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px}
.ia-msg{display:flex;gap:8px;align-items:flex-start}.ia-msg-user{flex-direction:row-reverse}
.ia-msg-avatar{width:30px;height:30px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-size:13px}
.ia-msg-body{max-width:82%;display:flex;flex-direction:column;gap:4px}.ia-msg-user .ia-msg-body{align-items:flex-end}
.ia-msg-text{padding:10px 13px;border-radius:14px;font-size:13px;line-height:1.5;word-break:break-word}
.ia-msg-ai .ia-msg-text{background:#f8fafc;border:1px solid #e2e8f0;color:#1e293b;border-radius:4px 14px 14px 14px}
.ia-msg-user .ia-msg-text{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:14px 4px 14px 14px}
.ia-msg-text :deep(.ia-link){display:inline-block;margin-top:4px;color:#4f46e5;font-weight:600;text-decoration:none;border-bottom:1px dashed #a5b4fc}
.ia-msg-text :deep(.ia-link):hover{color:#3730a3;border-bottom-style:solid}
.ia-msg-user .ia-msg-text :deep(.ia-link){color:white;border-bottom-color:rgba(255,255,255,.6)}
.ia-msg-time{font-size:10px;color:#94a3b8;padding:0 4px}
.ia-confirm{margin-left:38px;border:1px solid #fcd34d;background:#fffbeb;border-radius:12px;padding:10px 12px;font-size:13px;color:#78350f}
.ia-confirm-title{font-weight:700;margin-bottom:4px}
.ia-confirm-text{line-height:1.5}
.ia-confirm-actions{display:flex;gap:8px;margin-top:8px}
.ia-confirm-yes{background:#16a34a;color:white;border:none;border-radius:8px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer}
.ia-confirm-no{background:white;color:#78350f;border:1px solid #fcd34d;border-radius:8px;padding:5px 12px;font-size:12px;cursor:pointer}
.ia-confirm-yes:disabled,.ia-confirm-no:disabled{opacity:.5;cursor:not-allowed}
.ia-typing{display:flex;gap:4px;padding:8px 12px}
.ia-typing span{width:7px;height:7px;border-radius:50%;background:#94a3b8;animation:ia-bounce .8s infinite}
.ia-typing span:nth-child(2){animation-delay:.15s}.ia-typing span:nth-child(3){animation-delay:.3s}
@keyframes ia-bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}
.ia-input-area{border-top:1px solid #e2e8f0;padding:10px 12px;flex-shrink:0;background:#fafafa}
.ia-input-row{display:flex;gap:8px;align-items:flex-end}
.ia-input{flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;resize:none;max-height:80px;background:white;color:#1e293b;line-height:1.4}
.ia-input:focus{outline:none;border-color:#6366f1}
.ia-send-btn{width:36px;height:36px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center}
.ia-send-btn:disabled{opacity:.4;cursor:not-allowed}
.ia-input-hint{font-size:10px;color:#94a3b8;margin-top:4px}
.ia-slide-enter-active,.ia-slide-leave-active{transition:all .25s cubic-bezier(.4,0,.2,1)}
.ia-slide-enter-from,.ia-slide-leave-to{opacity:0;transform:translateY(20px) scale(.95)}
code{background:#1e293b;color:#7dd3fc;padding:1px 5px;border-radius:4px;font-size:11px}
</style>
