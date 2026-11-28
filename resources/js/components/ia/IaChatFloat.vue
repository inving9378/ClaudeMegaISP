<template>
    <div class="ia-float-btn" :class="{ 'has-pulse': !isOpen && !archivoEnCamino, 'ia-catch': archivoEnCamino && !isOpen }"
        role="button" tabindex="0" :aria-label="isOpen ? 'Cerrar JARVIS' : 'Abrir JARVIS'" :title="archivoEnCamino && !isOpen ? 'Suelta aquí para mandárselo a JARVIS' : 'JARVIS — tu asistente'"
        @click="toggleChat" @keydown.enter.prevent="toggleChat" @keydown.space.prevent="toggleChat"
        @dragenter.prevent @dragover.prevent="onDragOverBtn" @drop.prevent="onDropBtn">
        <div class="ia-btn-inner">
            <i v-if="archivoEnCamino && !isOpen" class="fas fa-cloud-upload-alt"></i>
            <i v-else-if="!isOpen" class="fas fa-robot"></i>
            <i v-else class="fas fa-times"></i>
        </div>
        <span v-if="!isOpen && !archivoEnCamino" class="ia-badge">IA</span>
        <span v-if="archivoEnCamino && !isOpen" class="ia-catch-label">Suéltalo aquí</span>
    </div>
    <transition name="ia-slide">
        <div v-if="isOpen" class="ia-chat-panel" :class="{ 'ia-expanded': isExpanded }" role="dialog" aria-label="JARVIS, el asistente del sistema"
            @dragenter.prevent="onDragEnter" @dragover.prevent @dragleave="onDragLeave" @drop.prevent="onDrop">
            <div class="ia-header">
                <div class="ia-header-left">
                    <div class="ia-avatar"><i class="fas fa-robot"></i></div>
                    <div>
                        <div class="ia-title">JARVIS</div>
                        <div class="ia-subtitle"><span class="ia-dot"></span>{{ estadoCabecera }}</div>
                    </div>
                </div>
                <div class="ia-header-actions">
                    <button class="ia-btn-icon" @click="isExpanded = !isExpanded" :aria-label="isExpanded ? 'Reducir' : 'Agrandar'" :title="isExpanded ? 'Reducir' : 'Agrandar'"><i :class="isExpanded ? 'fas fa-compress-alt' : 'fas fa-expand-alt'"></i></button>
                    <button class="ia-btn-icon" @click="newConversation" :disabled="isTyping" aria-label="Empezar tema nuevo" title="Empezar tema nuevo (borra los archivos de esta plática)"><i class="fas fa-plus"></i></button>
                    <button class="ia-btn-icon" @click="isOpen = false" aria-label="Cerrar" title="Cerrar"><i class="fas fa-times"></i></button>
                </div>
            </div>

            <div v-if="loaded && messages.length === 0" class="ia-suggestions">
                <div class="ia-welcome">
                    <i class="fas fa-robot ia-welcome-icon"></i>
                    <p>¡Hola! Soy JARVIS. Pregúntame lo que sea del sistema o mándame un archivo: un Excel para revisarlo o cruzarlo con los clientes, un PDF, una foto, un video o un audio.</p>
                </div>
                <div class="ia-chips" v-if="suggestions.length">
                    <button class="ia-chip" v-for="s in suggestions" :key="s.text" @click="sendSuggestion(s.text)">
                        <i class="fas fa-comment-dots"></i> {{ s.label }}
                    </button>
                </div>
            </div>

            <div class="ia-messages" ref="messagesContainer" aria-live="polite">
                <div v-for="(msg, i) in messages" :key="i" :class="['ia-msg', msg.role === 'user' ? 'ia-msg-user' : 'ia-msg-ai']">
                    <div v-if="msg.role === 'assistant'" class="ia-msg-avatar"><i class="fas fa-robot"></i></div>
                    <div class="ia-msg-body">
                        <div v-if="msg.adjuntos && msg.adjuntos.length" class="ia-msg-files">
                            <template v-for="a in msg.adjuntos" :key="a.id">
                                <a v-if="a.miniatura && a.estado === 'listo'" class="ia-thumb" :href="a.enlace" target="_blank" rel="noopener" data-spa-skip :title="a.nombre">
                                    <img :src="a.miniatura" :alt="a.nombre" loading="lazy">
                                    <span v-if="a.tipo === 'video'" class="ia-thumb-play"><i class="fas fa-play"></i></span>
                                </a>
                                <a v-else-if="a.estado === 'listo'" class="ia-file" :href="a.enlace" target="_blank" rel="noopener" data-spa-skip :title="'Abrir ' + a.nombre">
                                    <span :class="['ia-file-icon', 'ia-t-' + (a.tipo || 'otro')]"><i :class="['fas', icono(a.tipo, a.nombre)]"></i></span>
                                    <span class="ia-file-text"><span class="ia-file-name">{{ a.nombre }}</span><span class="ia-file-meta">{{ a.resumen || peso(a.bytes) }}</span></span>
                                </a>
                                <span v-else class="ia-file ia-file-gone" :title="'Los archivos se borran solos'">
                                    <span class="ia-file-icon"><i :class="['fas', icono(a.tipo, a.nombre)]"></i></span>
                                    <span class="ia-file-text"><span class="ia-file-name">{{ a.nombre }}</span><span class="ia-file-meta">Ya se borró</span></span>
                                </span>
                            </template>
                        </div>
                        <div v-if="msg.content" class="ia-msg-text" v-html="fmt(msg.content)"></div>
                        <div class="ia-msg-time">{{ ft(msg.timestamp) }}</div>
                    </div>
                </div>

                <!-- Cambio que JARVIS preparó: solo se aplica con este botón (o respondiendo "sí"). -->
                <div v-if="pendingAction" class="ia-confirm" role="group" aria-label="Cambio por confirmar">
                    <div class="ia-confirm-title"><i class="fas fa-hand-paper"></i> ¿Lo hago?</div>
                    <div class="ia-confirm-text" v-html="fmt(pendingAction.resumen)"></div>
                    <details v-if="pendingAction.detalle && pendingAction.detalle.length" class="ia-confirm-detail">
                        <summary>{{ (pendingAction.total || pendingAction.detalle.length) === 1 ? 'Ver qué se va a hacer' : 'Ver los ' + (pendingAction.total || pendingAction.detalle.length) + ' cambios' }}</summary>
                        <ol><li v-for="(d, k) in pendingAction.detalle" :key="k">{{ d }}</li></ol>
                        <p v-if="pendingAction.total > pendingAction.detalle.length" class="ia-confirm-more">…y {{ pendingAction.total - pendingAction.detalle.length }} más.</p>
                    </details>
                    <div class="ia-confirm-actions">
                        <button class="ia-confirm-yes" @click="resolveAction('confirmar')" :disabled="isTyping"><i class="fas fa-check"></i> Sí, hazlo</button>
                        <button class="ia-confirm-no" @click="resolveAction('cancelar')" :disabled="isTyping">Cancelar</button>
                    </div>
                </div>

                <div v-if="isTyping" class="ia-msg ia-msg-ai">
                    <div class="ia-msg-avatar"><i class="fas fa-robot"></i></div>
                    <div class="ia-msg-body"><div class="ia-typing" aria-label="JARVIS está pensando"><span></span><span></span><span></span></div></div>
                </div>
            </div>

            <div class="ia-input-area">
                <ul v-if="pendientes.length" class="ia-tray" aria-label="Archivos por mandar">
                    <li v-for="p in pendientes" :key="p.key" :class="['ia-tray-item', 'is-' + p.estado]">
                        <span v-if="p.preview" class="ia-tray-thumb"><img :src="p.preview" :alt="p.nombre"></span>
                        <span v-else :class="['ia-file-icon', 'ia-t-' + (p.tipo || 'otro')]"><i :class="['fas', p.estado === 'error' ? 'fa-exclamation-circle' : icono(p.tipo, p.nombre)]"></i></span>
                        <span class="ia-tray-text">
                            <span class="ia-file-name" :title="p.nombre">{{ p.nombre }}</span>
                            <span class="ia-file-meta">{{ metaPendiente(p) }}</span>
                        </span>
                        <button class="ia-tray-remove" @click="quitar(p)" :aria-label="'Quitar ' + p.nombre" title="Quitar"><i class="fas fa-times"></i></button>
                        <span v-if="p.estado === 'subiendo' || p.estado === 'leyendo'" class="ia-tray-bar" :class="{ 'is-indeterminate': p.estado === 'leyendo' }">
                            <span :style="{ width: (p.estado === 'leyendo' ? 100 : p.progreso) + '%' }"></span>
                        </span>
                    </li>
                </ul>
                <div class="ia-input-row">
                    <button class="ia-attach-btn" @click="elegirArchivos" :disabled="pendientes.length >= limites.max_por_mensaje" aria-label="Adjuntar archivos" title="Adjuntar fotos, PDF, Excel, Word, video o audio">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <input ref="fileInput" type="file" multiple class="ia-file-input" :accept="accept" @change="onFileInput" tabindex="-1" aria-hidden="true">
                    <textarea ref="inputRef" v-model="inputText" @keydown.enter.exact.prevent="send" @input="autoGrow" @paste="onPaste"
                        :placeholder="pendientes.length ? 'Dime qué hago con esto…' : 'Escribe o arrastra un archivo…'" :disabled="isTyping" rows="1" class="ia-input" aria-label="Mensaje para JARVIS"></textarea>
                    <button class="ia-send-btn" @click="send" :disabled="!puedeEnviar" :aria-label="esperandoSubida ? 'Se enviará al terminar de subir' : 'Enviar'" :title="esperandoSubida ? 'Se enviará al terminar de subir' : 'Enviar'">
                        <i :class="esperandoSubida ? 'fas fa-cloud-upload-alt' : 'fas fa-paper-plane'"></i>
                    </button>
                </div>
                <div class="ia-input-hint">
                    <template v-if="esperandoSubida">Se manda en cuanto terminen de subir los archivos.</template>
                    <template v-else>Enter envía · Shift+Enter, otro renglón · Los archivos duran {{ limites.horas_de_vida }} h</template>
                </div>
            </div>

            <transition name="ia-drop">
                <div v-if="arrastrando" class="ia-drop" aria-hidden="true">
                    <div class="ia-drop-inner">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <strong>Suelta los archivos aquí</strong>
                        <span>Fotos, PDF, Excel, Word, videos y audios · hasta {{ limites.max_mb }} MB cada uno</span>
                    </div>
                </div>
            </transition>
        </div>
    </transition>
</template>
<script>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import axios from 'axios';

/** Partes de 1 MB: pasan aunque el servidor limite el tamaño de cada subida. */
const PARTE = 1024 * 1024;
const SUBIDAS_A_LA_VEZ = 2;

const TIPOS = {
    imagen: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif'],
    pdf: ['pdf'],
    hoja: ['xlsx', 'xlsm', 'xls', 'ods', 'csv', 'tsv'],
    documento: ['docx', 'odt', 'rtf', 'txt', 'md', 'json', 'xml', 'html', 'htm', 'log', 'sql', 'yaml', 'yml', 'ini', 'vcf', 'eml'],
    video: ['mp4', 'mov', 'm4v', 'webm', 'mkv', 'avi', '3gp', 'mpeg', 'mpg', 'wmv'],
    audio: ['mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'aac', 'flac', 'amr', 'weba'],
};
const extension = (nombre) => (String(nombre).split('.').pop() || '').toLowerCase();
const tipoDe = (nombre) => Object.keys(TIPOS).find((t) => TIPOS[t].includes(extension(nombre))) || null;

/** crypto.randomUUID solo existe con https; getRandomValues sí está en http (la red interna). */
const uuid = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    const b = window.crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
};

const escapeHtml = (t) => String(t)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

/** Enlaces internos [texto](/ruta). Los archivos del chat abren en otra pestaña y la navegación SPA no los toma. */
const enlace = (texto, ruta) => {
    if (/^\/ia\/asistente\/adjuntos\/\d+\/descargar/.test(ruta)) {
        return `<a class="ia-download" href="${ruta}" download data-spa-skip><i class="fas fa-download"></i> ${texto}</a>`;
    }
    if (/^\/ia\/asistente\/adjuntos\//.test(ruta)) {
        return `<a class="ia-link" href="${ruta}" target="_blank" rel="noopener" data-spa-skip>${texto}</a>`;
    }
    return `<a class="ia-link" href="${ruta}">${texto}</a>`;
};

const inline = (t) => t
    .replace(/\[([^\]]+)\]\((\/[^)\s]*)\)/g, (m, texto, ruta) => enlace(texto, ruta))
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/(^|[\s(])_([^_\n]+?)_(?=$|[\s).,;:])/g, '$1<em>$2</em>');

const ES_TABLA = /^\s*\|.*\|\s*$/;
const SEPARADOR = /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/;
const VINETA = /^\s*[-*•]\s+/;
const NUMERADA = /^\s*\d+[.)]\s+/;
const TITULO = /^\s*#{1,4}\s+/;
const celdas = (l) => l.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((c) => c.trim());
const esNumero = (c) => /^[-+]?\$?\s?[\d,]+(\.\d+)?\s?%?$/.test(c.replace(/<[^>]+>/g, ''));

/**
 * Texto de JARVIS → HTML seguro: primero se escapa TODO (ningún HTML de la IA o del usuario se
 * ejecuta) y luego se arman párrafos, listas, tablas, negritas, `código` y enlaces internos.
 * Un enlace que no empiece con "/" se queda como texto.
 */
const fmt = (t) => {
    if (!t) return '';
    const lineas = escapeHtml(t).split('\n');
    const n = lineas.length;
    const bloque = (i) => ES_TABLA.test(lineas[i]) || VINETA.test(lineas[i]) || NUMERADA.test(lineas[i]) || TITULO.test(lineas[i]);
    const out = [];
    let i = 0;
    while (i < n) {
        const l = lineas[i];
        if (ES_TABLA.test(l) && i + 1 < n && SEPARADOR.test(lineas[i + 1])) {
            const cab = celdas(l);
            const filas = [];
            i += 2;
            while (i < n && ES_TABLA.test(lineas[i])) filas.push(celdas(lineas[i++]));
            // Una columna es numérica si todas sus celdas con dato lo son: título y celdas a la derecha.
            const numerica = cab.map((_, k) => {
                const llenas = filas.map((f) => (f[k] || '').trim()).filter((c) => c !== '' && !/^[—–-]$/.test(c));
                return llenas.length > 0 && llenas.every(esNumero);
            });
            const cls = (k) => (numerica[k] ? ' class="is-num"' : '');
            const th = cab.map((c, k) => `<th${cls(k)}>${inline(c)}</th>`).join('');
            const tb = filas.map((f) => '<tr>' + f.map((c, k) => `<td${cls(k)}>${inline(c)}</td>`).join('') + '</tr>').join('');
            out.push(`<div class="ia-table-wrap"><table class="ia-table"><thead><tr>${th}</tr></thead><tbody>${tb}</tbody></table></div>`);
            continue;
        }
        if (VINETA.test(l) || NUMERADA.test(l)) {
            const re = VINETA.test(l) ? VINETA : NUMERADA;
            const tag = re === VINETA ? 'ul' : 'ol';
            const items = [];
            while (i < n && re.test(lineas[i])) items.push(`<li>${inline(lineas[i++].replace(re, ''))}</li>`);
            out.push(`<${tag}>${items.join('')}</${tag}>`);
            continue;
        }
        if (TITULO.test(l)) {
            out.push(`<p class="ia-h">${inline(l.replace(TITULO, ''))}</p>`);
            i++;
            continue;
        }
        if (l.trim() === '') {
            i++;
            continue;
        }
        const parrafo = [];
        while (i < n && lineas[i].trim() !== '' && !bloque(i)) parrafo.push(inline(lineas[i++]));
        out.push(`<p>${parrafo.join('<br>')}</p>`);
    }
    return out.join('');
};

const peso = (b) => {
    if (!b && b !== 0) return '';
    if (b < 1024) return `${b} B`;
    if (b < 1048576) return `${Math.round(b / 1024)} KB`;
    return `${(b / 1048576).toFixed(b < 10485760 ? 1 : 0)} MB`;
};

const icono = (tipo, nombre) => {
    if (tipo === 'hoja') return extension(nombre) === 'csv' ? 'fa-file-csv' : 'fa-file-excel';
    if (tipo === 'documento') return ['docx', 'odt', 'rtf'].includes(extension(nombre)) ? 'fa-file-word' : 'fa-file-alt';
    return { imagen: 'fa-file-image', pdf: 'fa-file-pdf', video: 'fa-file-video', audio: 'fa-file-audio' }[tipo] || 'fa-file-alt';
};

export default {
    name: 'IaChatFloat',
    props: { url: String },
    setup(props) {
        const isOpen = ref(false), isExpanded = ref(false), isTyping = ref(false), inputText = ref(''), messages = ref([]);
        const messagesContainer = ref(null), inputRef = ref(null), fileInput = ref(null);
        const suggestions = ref([]);
        const pendingAction = ref(null);
        const loaded = ref(false);
        const limites = reactive({ max_mb: 100, max_mb_hoja: 40, horas_de_vida: 24, max_por_mensaje: 10, extensiones: Object.values(TIPOS).flat() });

        const pendientes = ref([]);
        const arrastrando = ref(false);
        const archivoEnCamino = ref(false);
        const enviarAlTerminar = ref(false);
        let profundidadArrastre = 0;
        let profundidadVentana = 0;
        const cola = [];
        let activas = 0;

        const scroll = () => nextTick(() => { const e = messagesContainer.value; if (e) e.scrollTop = e.scrollHeight; });
        const say = (content) => { messages.value.push({ role: 'assistant', content, timestamp: new Date() }); scroll(); };

        const accept = computed(() => limites.extensiones.map((e) => '.' + e).join(','));
        const subiendo = computed(() => pendientes.value.some((p) => ['en_cola', 'subiendo', 'leyendo'].includes(p.estado)));
        const listos = computed(() => pendientes.value.filter((p) => p.estado === 'listo'));
        const esperandoSubida = computed(() => enviarAlTerminar.value && subiendo.value);
        const puedeEnviar = computed(() => !isTyping.value && !esperandoSubida.value && (inputText.value.trim() !== '' || listos.value.length > 0 || subiendo.value));
        const estadoCabecera = computed(() => {
            if (isTyping.value) return 'Pensando…';
            if (subiendo.value) return 'Recibiendo archivos…';
            return 'En línea';
        });

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
                    if (res.data.archivos) Object.assign(limites, res.data.archivos);
                }
            } catch (e) {
                // Sin historial no se bloquea el chat: se puede seguir escribiendo.
            } finally {
                loaded.value = true;
                scroll();
            }
        };

        const abrir = () => {
            if (isOpen.value) return;
            isOpen.value = true;
            if (!loaded.value) loadConversation();
            nextTick(() => inputRef.value?.focus());
            scroll();
        };
        const toggleChat = () => { if (isOpen.value) { isOpen.value = false; } else { abrir(); } };
        const sendSuggestion = (t) => { inputText.value = t; send(); };
        const ft = (d) => d ? new Date(d).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '';
        const autoGrow = () => {
            const el = inputRef.value;
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 160) + 'px';
        };

        // ── Archivos ────────────────────────────────────────────────────────────────────────

        const agregarArchivos = (lista) => {
            const archivos = Array.from(lista || []);
            if (!archivos.length) return;
            abrir();
            for (const f of archivos) {
                if (pendientes.value.length >= limites.max_por_mensaje) {
                    say(`Puedo recibir hasta ${limites.max_por_mensaje} archivos por mensaje. Manda los demás en el siguiente.`);
                    break;
                }
                const tipo = tipoDe(f.name);
                const maximo = (tipo === 'hoja' ? limites.max_mb_hoja : limites.max_mb) * 1048576;
                const p = reactive({
                    key: uuid(), file: f, nombre: f.name || 'archivo', bytes: f.size, tipo, progreso: 0, estado: 'en_cola', error: null,
                    adjunto: null, control: null, preview: tipo === 'imagen' && !['heic', 'heif'].includes(extension(f.name)) ? URL.createObjectURL(f) : null,
                });
                if (!tipo) Object.assign(p, { estado: 'error', error: 'Este tipo de archivo no lo puedo leer' });
                else if (f.size === 0) Object.assign(p, { estado: 'error', error: 'El archivo está vacío' });
                else if (f.size > maximo) Object.assign(p, { estado: 'error', error: `Pesa más de ${maximo / 1048576} MB` });
                pendientes.value.push(p);
                if (p.estado === 'en_cola') cola.push(p);
            }
            atenderCola();
            nextTick(() => inputRef.value?.focus());
        };

        const atenderCola = () => {
            while (activas < SUBIDAS_A_LA_VEZ && cola.length) {
                const p = cola.shift();
                if (p.estado !== 'en_cola') continue;
                activas++;
                subir(p).finally(() => { activas--; atenderCola(); });
            }
        };

        const subir = async (p) => {
            p.estado = 'subiendo';
            p.control = new AbortController();
            const total = Math.max(1, Math.ceil(p.file.size / PARTE));
            const subida = uuid();
            try {
                for (let i = 0; i < total; i++) {
                    const trozo = p.file.slice(i * PARTE, (i + 1) * PARTE);
                    const fd = new FormData();
                    fd.append('subida', subida);
                    fd.append('indice', i);
                    fd.append('total', total);
                    fd.append('nombre', p.nombre);
                    fd.append('tamano', p.file.size);
                    fd.append('parte', trozo, 'parte');
                    const ultima = i === total - 1;
                    let res;
                    for (let intento = 0; ; intento++) {
                        try {
                            res = await axios.post(`${props.url}/ia/asistente/adjuntos`, fd, {
                                signal: p.control.signal,
                                onUploadProgress: (e) => {
                                    const avance = e.total ? e.loaded / e.total : 0;
                                    p.progreso = Math.min(99, Math.round(((i + avance) / total) * 100));
                                    if (ultima && avance >= 1) p.estado = 'leyendo';
                                },
                            });
                            break;
                        } catch (err) {
                            // Un corte de red se reintenta una vez; un rechazo del servidor no.
                            if (intento >= 1 || err.response || axios.isCancel(err) || err.name === 'CanceledError') throw err;
                        }
                    }
                    if (ultima) {
                        p.adjunto = res.data.adjunto;
                        p.progreso = 100;
                        p.estado = 'listo';
                    }
                }
            } catch (err) {
                if (p.estado === 'quitado') return;
                p.estado = 'error';
                p.error = err.response?.data?.error || err.response?.data?.message || 'No se pudo subir. Revisa tu conexión.';
                if (enviarAlTerminar.value) enviarAlTerminar.value = false;
            }
        };

        const quitar = (p) => {
            const antes = p.estado;
            p.estado = 'quitado';
            if (antes === 'subiendo' || antes === 'leyendo') p.control?.abort();
            if (p.adjunto?.id) axios.delete(`${props.url}/ia/asistente/adjuntos/${p.adjunto.id}`).catch(() => {});
            if (p.preview) URL.revokeObjectURL(p.preview);
            pendientes.value = pendientes.value.filter((x) => x !== p);
        };

        const metaPendiente = (p) => ({
            en_cola: 'En espera…',
            subiendo: `Subiendo ${p.progreso}% · ${peso(p.bytes)}`,
            leyendo: 'Leyendo el archivo…',
            listo: p.adjunto?.resumen || peso(p.bytes),
            error: p.error,
        }[p.estado] || '');

        const elegirArchivos = () => fileInput.value?.click();
        const onFileInput = (e) => { agregarArchivos(e.target.files); e.target.value = ''; };
        const onPaste = (e) => {
            const archivos = Array.from(e.clipboardData?.files || []);
            if (archivos.length) {
                e.preventDefault();
                agregarArchivos(archivos);
            }
        };

        const traeArchivos = (e) => Array.from(e.dataTransfer?.types || []).includes('Files');
        const onDragEnter = (e) => { if (!traeArchivos(e)) return; profundidadArrastre++; arrastrando.value = true; };
        const onDragLeave = (e) => { if (!traeArchivos(e)) return; profundidadArrastre = Math.max(0, profundidadArrastre - 1); if (!profundidadArrastre) arrastrando.value = false; };
        const onDrop = (e) => {
            profundidadArrastre = 0;
            arrastrando.value = false;
            archivoEnCamino.value = false;
            profundidadVentana = 0;
            agregarArchivos(e.dataTransfer?.files);
        };
        const onDragOverBtn = (e) => { if (traeArchivos(e)) e.dataTransfer.dropEffect = 'copy'; };
        const onDropBtn = (e) => {
            archivoEnCamino.value = false;
            profundidadVentana = 0;
            agregarArchivos(e.dataTransfer?.files);
        };

        // Con el chat cerrado, al arrastrar archivos sobre la página el robotsito avisa que los recibe.
        const ventanaEnter = (e) => { if (traeArchivos(e)) { profundidadVentana++; archivoEnCamino.value = true; } };
        const ventanaLeave = (e) => { if (traeArchivos(e)) { profundidadVentana = Math.max(0, profundidadVentana - 1); if (!profundidadVentana) archivoEnCamino.value = false; } };
        const ventanaFin = () => { profundidadVentana = 0; archivoEnCamino.value = false; arrastrando.value = false; profundidadArrastre = 0; };

        // ── Mensajes ────────────────────────────────────────────────────────────────────────

        const send = async () => {
            if (isTyping.value) return;
            if (subiendo.value) {
                if (inputText.value.trim() !== '' || listos.value.length || subiendo.value) enviarAlTerminar.value = true;
                return;
            }
            const text = inputText.value.trim();
            const archivos = listos.value.map((p) => p.adjunto);
            if (!text && !archivos.length) return;

            enviarAlTerminar.value = false;
            messages.value.push({ role: 'user', content: text, adjuntos: archivos, timestamp: new Date() });
            pendientes.value.forEach((p) => { if (p.preview) URL.revokeObjectURL(p.preview); });
            pendientes.value = [];
            inputText.value = '';
            nextTick(autoGrow);
            isTyping.value = true; pendingAction.value = null; scroll();
            try {
                // El término buscado en un listado no se comparte con el asistente (#9990815).
                const query = new URLSearchParams(window.location.search);
                query.delete('search');
                const context = window.location.pathname + (query.toString() ? `?${query}` : '');
                const res = await axios.post(`${props.url}/ia/chat`, { message: text, context, adjuntos: archivos.map((a) => a.id) });
                if (res.data.success) {
                    say(res.data.message);
                    pendingAction.value = res.data.accion_pendiente || null;
                } else {
                    say('No pude responder: ' + (res.data.error || 'inténtalo de nuevo.'));
                }
            } catch (e) {
                say('No pude conectarme. Revisa tu conexión e inténtalo de nuevo.');
            } finally { isTyping.value = false; scroll(); nextTick(() => inputRef.value?.focus()); }
        };

        watch(subiendo, (ahora) => {
            if (!ahora && enviarAlTerminar.value) {
                enviarAlTerminar.value = false;
                if (!pendientes.value.some((p) => p.estado === 'error')) send();
            }
        });

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
                say('No pude completar el cambio. Inténtalo de nuevo.');
            } finally { isTyping.value = false; scroll(); }
        };

        const newConversation = async () => {
            if (isTyping.value) return;
            try {
                await axios.post(`${props.url}/ia/asistente/nueva`);
                messages.value = [];
                pendingAction.value = null;
                [...pendientes.value].forEach(quitar);
            } catch (e) {
                say('No pude empezar un tema nuevo.');
            }
        };

        onMounted(() => {
            loadSuggestions();
            window.addEventListener('dragenter', ventanaEnter);
            window.addEventListener('dragleave', ventanaLeave);
            window.addEventListener('drop', ventanaFin);
            window.addEventListener('dragend', ventanaFin);
        });
        onBeforeUnmount(() => {
            window.removeEventListener('dragenter', ventanaEnter);
            window.removeEventListener('dragleave', ventanaLeave);
            window.removeEventListener('drop', ventanaFin);
            window.removeEventListener('dragend', ventanaFin);
            pendientes.value.forEach((p) => { if (p.preview) URL.revokeObjectURL(p.preview); });
        });

        return {
            isOpen, isExpanded, isTyping, inputText, messages, messagesContainer, inputRef, fileInput, suggestions, pendingAction, loaded,
            limites, pendientes, arrastrando, archivoEnCamino, accept, puedeEnviar, esperandoSubida, estadoCabecera,
            toggleChat, sendSuggestion, send, resolveAction, newConversation, fmt, ft, peso, icono, autoGrow,
            elegirArchivos, onFileInput, onPaste, quitar, metaPendiente, onDragEnter, onDragLeave, onDrop, onDragOverBtn, onDropBtn,
        };
    },
};
</script>
<style scoped>
/* Los var(--mn-…) llevan respaldo índigo: en una instalación sin la paleta (#2066) el chat conserva su color.
   right:24px — la esquina de siempre del robotsito. NO moverlo: en ese mismo lugar (debajo, z-index 1040)
   está montada la burbuja de la Torre, y el robot la tapa; si se mueve, la burbuja aparece flotando. */
.ia-float-btn{position:fixed;bottom:24px;right:24px;z-index:9999;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--mn-principal, #6366f1),var(--mn-principal-activo, #8b5cf6));box-shadow:0 4px 20px rgba(var(--mn-principal-rgb, 99,102,241), .5);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .25s cubic-bezier(.16,1,.3,1),box-shadow .25s cubic-bezier(.16,1,.3,1)}
.ia-float-btn:hover{transform:scale(1.1)}
.ia-float-btn:focus-visible{outline:3px solid rgba(var(--mn-principal-rgb, 99,102,241), .45);outline-offset:3px}
.ia-float-btn.has-pulse::after{content:'';position:absolute;width:100%;height:100%;border-radius:50%;background:rgba(var(--mn-principal-rgb, 99,102,241), .3);animation:ia-pulse 2s infinite}
@keyframes ia-pulse{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.6);opacity:0}}
/* Archivos en camino con el chat cerrado: el robotsito se ofrece a recibirlos. */
.ia-float-btn.ia-catch{transform:scale(1.18);box-shadow:0 0 0 6px rgba(var(--mn-principal-rgb, 99,102,241), .18),0 10px 30px rgba(var(--mn-principal-rgb, 99,102,241), .55)}
.ia-catch-label{position:absolute;right:68px;white-space:nowrap;background:#1e293b;color:#fff;font-size:12px;font-weight:600;padding:6px 10px;border-radius:8px;box-shadow:0 6px 18px rgba(15,23,42,.25);pointer-events:none}
.ia-btn-inner{color:white;font-size:22px;z-index:1}.ia-badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;font-size:9px;font-weight:700;border-radius:10px;padding:2px 5px;z-index:2}

.ia-chat-panel{position:fixed;bottom:90px;right:24px;z-index:9998;width:420px;max-width:calc(100vw - 32px);height:600px;max-height:calc(100vh - 110px);background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(15,23,42,.18);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(var(--mn-principal-rgb, 99,102,241), .15);color:#1e293b}
.ia-chat-panel.ia-expanded{width:760px;height:84vh}
.ia-chat-panel,.ia-chat-panel *,.ia-chat-panel *::before,.ia-chat-panel *::after{box-sizing:border-box}
.ia-chat-panel button,.ia-chat-panel textarea{font-family:inherit}
.ia-chat-panel ::selection{background:rgba(var(--mn-principal-rgb, 99,102,241), .22)}
.ia-header{background:linear-gradient(135deg,var(--mn-principal, #6366f1),var(--mn-principal-activo, #8b5cf6));padding:14px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.ia-header-left{display:flex;align-items:center;gap:10px}
.ia-avatar{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:white;font-size:18px}
.ia-title{color:white;font-weight:600;font-size:14px}.ia-subtitle{color:rgba(255,255,255,.85);font-size:11px;display:flex;align-items:center;gap:4px}
.ia-dot{width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;animation:blink 1.5s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.ia-header-actions{display:flex;gap:4px}
.ia-btn-icon{background:rgba(255,255,255,.15);border:none;color:white;width:28px;height:28px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px}
.ia-btn-icon:hover{background:rgba(255,255,255,.3)}
.ia-btn-icon:focus-visible{outline:2px solid #fff;outline-offset:1px}
.ia-btn-icon:disabled{opacity:.5;cursor:not-allowed}

.ia-suggestions{padding:16px;flex-shrink:0}
.ia-welcome{text-align:center;padding:8px 6px 12px}
.ia-welcome-icon{font-size:32px;color:var(--mn-principal-texto, #4f46e5);margin-bottom:8px;display:block}
.ia-welcome p{color:#475569;font-size:13px;line-height:1.5;margin:0;text-wrap:balance}
.ia-chips{display:flex;flex-wrap:wrap;gap:6px}
.ia-chip{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:20px;padding:5px 12px;font-size:12px;cursor:pointer;color:#475569;transition:all .2s;display:flex;align-items:center;gap:5px}
.ia-chip:hover{background:var(--mn-100, #e0e7ff);border-color:var(--mn-principal, #6366f1);color:var(--mn-principal-texto, #4f46e5)}

.ia-messages{flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:12px;scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
.ia-messages::-webkit-scrollbar{width:6px}.ia-messages::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:6px}
.ia-msg{display:flex;gap:8px;align-items:flex-start}.ia-msg-user{flex-direction:row-reverse}
.ia-msg-avatar{width:30px;height:30px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--mn-principal, #6366f1),var(--mn-principal-activo, #8b5cf6));display:flex;align-items:center;justify-content:center;color:white;font-size:13px}
.ia-msg-body{max-width:84%;min-width:0;display:flex;flex-direction:column;gap:4px}.ia-msg-user .ia-msg-body{align-items:flex-end}
.ia-expanded .ia-msg-ai .ia-msg-body{max-width:90%}
.ia-msg-text{padding:10px 13px;border-radius:14px;font-size:13px;line-height:1.55;word-break:break-word;max-width:100%}
.ia-msg-ai .ia-msg-text{background:#f8fafc;border:1px solid #e2e8f0;color:#1e293b;border-radius:4px 14px 14px 14px}
.ia-msg-user .ia-msg-text{background:linear-gradient(135deg,var(--mn-principal, #6366f1),var(--mn-principal-activo, #8b5cf6));color:white;border-radius:14px 4px 14px 14px}
.ia-msg-text :deep(p){margin:0 0 .55em}.ia-msg-text :deep(p:last-child){margin-bottom:0}
.ia-msg-text :deep(.ia-h){font-weight:700;margin-top:.75em}
.ia-msg-text :deep(ul),.ia-msg-text :deep(ol){margin:.2em 0 .6em;padding-left:1.25em}
.ia-msg-text :deep(li){margin:.15em 0}
.ia-msg-text :deep(li:last-child){margin-bottom:0}
.ia-msg-text :deep(.ia-link){color:var(--mn-principal-texto, #4f46e5);font-weight:600;text-decoration:underline;text-decoration-color:var(--mn-200, #c7d2fe);text-underline-offset:3px}
.ia-msg-text :deep(.ia-link):hover{color:var(--mn-700, #4338ca);text-decoration-color:currentColor}
.ia-msg-user .ia-msg-text :deep(.ia-link){color:white;text-decoration-color:rgba(255,255,255,.6)}
.ia-msg-text :deep(.ia-download){display:inline-flex;align-items:center;gap:7px;margin:4px 0;padding:7px 12px;border-radius:9px;background:var(--mn-principal, #6366f1);color:#fff;font-weight:600;font-size:12.5px;text-decoration:none;box-shadow:0 2px 8px rgba(var(--mn-principal-rgb, 99,102,241), .3)}
.ia-msg-text :deep(.ia-download):hover{background:var(--mn-principal-activo, #8b5cf6);color:#fff}
.ia-msg-text :deep(.ia-table-wrap){overflow-x:auto;margin:.4em -2px .6em;border:1px solid #e2e8f0;border-radius:8px;background:#fff}
.ia-msg-text :deep(.ia-table){border-collapse:collapse;width:100%;font-size:12px;font-variant-numeric:tabular-nums}
.ia-msg-text :deep(.ia-table th){background:#f1f5f9;color:#334155;font-weight:600;text-align:left;padding:6px 9px;white-space:nowrap;border-bottom:1px solid #e2e8f0}
.ia-msg-text :deep(.ia-table td){padding:5px 9px;border-bottom:1px solid #f1f5f9;vertical-align:top}
.ia-msg-text :deep(.ia-table tr:last-child td){border-bottom:0}
.ia-msg-text :deep(.ia-table .is-num){text-align:right;white-space:nowrap}
.ia-msg-time{font-size:10px;color:#64748b;padding:0 4px}

/* Archivos dentro de los mensajes */
.ia-msg-files{display:flex;flex-wrap:wrap;align-items:flex-start;gap:6px;justify-content:flex-end;max-width:100%}
.ia-msg-ai .ia-msg-files{justify-content:flex-start}
.ia-thumb{position:relative;display:block;width:88px;height:88px;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;background:#f1f5f9}
.ia-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s cubic-bezier(.16,1,.3,1)}
.ia-thumb:hover img{transform:scale(1.05)}
.ia-thumb-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;background:linear-gradient(to top,rgba(15,23,42,.45),rgba(15,23,42,.05))}
.ia-file{display:flex;align-items:center;gap:9px;max-width:260px;padding:7px 10px 7px 7px;border-radius:10px;background:#fff;border:1px solid #e2e8f0;text-decoration:none;color:#1e293b;transition:border-color .2s,box-shadow .2s}
a.ia-file:hover{border-color:var(--mn-principal, #6366f1);box-shadow:0 2px 10px rgba(var(--mn-principal-rgb, 99,102,241), .12)}
.ia-file-gone{opacity:.65}
.ia-file-icon{width:32px;height:32px;flex-shrink:0;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;background:#f1f5f9;color:#64748b}
.ia-t-pdf{background:#fee2e2;color:#b91c1c}
.ia-t-hoja{background:#dcfce7;color:#15803d}
.ia-t-documento{background:#dbeafe;color:#1d4ed8}
.ia-t-imagen{background:#fef3c7;color:#b45309}
.ia-t-video{background:#ede9fe;color:#6d28d9}
.ia-t-audio{background:#fce7f3;color:#be185d}
.ia-file-text{display:flex;flex-direction:column;min-width:0}
.ia-file-name{font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ia-file-meta{font-size:11px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* Cambio por confirmar */
.ia-confirm{margin-left:38px;border:1px solid #fcd34d;background:#fffbeb;border-radius:12px;padding:10px 12px;font-size:13px;color:#78350f}
.ia-confirm-title{font-weight:700;margin-bottom:4px}
.ia-confirm-text{line-height:1.5}
.ia-confirm-text :deep(p){margin:0 0 .4em}.ia-confirm-text :deep(p:last-child){margin:0}
.ia-confirm-detail{margin-top:8px;font-size:12px}
.ia-confirm-detail summary{cursor:pointer;font-weight:600;color:#92400e;list-style:none;display:inline-flex;align-items:center;gap:6px}
.ia-confirm-detail summary::-webkit-details-marker{display:none}
.ia-confirm-detail summary::before{content:'';width:6px;height:6px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(-45deg);transition:transform .2s}
.ia-confirm-detail[open] summary::before{transform:rotate(45deg)}
.ia-confirm-detail ol{margin:6px 0 0;padding:6px 8px 6px 28px;max-height:160px;overflow:auto;background:rgba(255,255,255,.7);border:1px solid #fde68a;border-radius:8px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11px;color:#57534e}
.ia-confirm-more{margin:4px 0 0;color:#92400e}
.ia-confirm-actions{display:flex;gap:8px;margin-top:10px}
.ia-confirm-yes{background:#16a34a;color:white;border:none;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer}
.ia-confirm-yes:hover{background:#15803d}
.ia-confirm-no{background:white;color:#78350f;border:1px solid #fcd34d;border-radius:8px;padding:6px 12px;font-size:12px;cursor:pointer}
.ia-confirm-yes:disabled,.ia-confirm-no:disabled{opacity:.5;cursor:not-allowed}
.ia-confirm-yes:focus-visible,.ia-confirm-no:focus-visible{outline:2px solid #b45309;outline-offset:2px}

.ia-typing{display:flex;gap:4px;padding:8px 12px}
.ia-typing span{width:7px;height:7px;border-radius:50%;background:#94a3b8;animation:ia-bounce .8s infinite}
.ia-typing span:nth-child(2){animation-delay:.15s}.ia-typing span:nth-child(3){animation-delay:.3s}
@keyframes ia-bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}

/* Redacción + bandeja de archivos */
.ia-input-area{border-top:1px solid #e2e8f0;padding:10px 12px;flex-shrink:0;background:#fafafa}
.ia-tray{list-style:none;margin:0 0 8px;padding:0;display:flex;flex-direction:column;gap:6px;max-height:172px;overflow-y:auto}
.ia-tray-item{position:relative;display:flex;align-items:center;gap:9px;padding:6px 6px 6px 6px;border-radius:10px;background:#fff;border:1px solid #e2e8f0;overflow:hidden}
.ia-tray-item.is-error{border-color:#fca5a5;background:#fef2f2}
.ia-tray-item.is-error .ia-file-icon{background:#fee2e2;color:#dc2626}
.ia-tray-item.is-error .ia-file-meta{color:#b91c1c;white-space:normal}
.ia-tray-item.is-en_cola{opacity:.75}
.ia-tray-thumb{width:32px;height:32px;flex-shrink:0;border-radius:8px;overflow:hidden;background:#f1f5f9}
.ia-tray-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.ia-tray-text{flex:1;min-width:0;display:flex;flex-direction:column}
.ia-tray-remove{width:26px;height:26px;flex-shrink:0;border:none;background:transparent;color:#64748b;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px}
.ia-tray-remove:hover{background:#f1f5f9;color:#0f172a}
.ia-tray-remove:focus-visible{outline:2px solid var(--mn-principal, #6366f1);outline-offset:1px}
.ia-tray-bar{position:absolute;left:0;right:0;bottom:0;height:3px;background:rgba(var(--mn-principal-rgb, 99,102,241), .12)}
.ia-tray-bar span{display:block;height:100%;background:var(--mn-principal, #6366f1);transition:width .2s ease-out}
.ia-tray-bar.is-indeterminate span{width:35% !important;animation:ia-leyendo 1.1s cubic-bezier(.45,0,.55,1) infinite}
@keyframes ia-leyendo{0%{transform:translateX(-100%)}100%{transform:translateX(290%)}}
.ia-input-row{display:flex;gap:8px;align-items:flex-end}
.ia-attach-btn{width:36px;height:36px;flex-shrink:0;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#475569;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:color .2s,border-color .2s,background .2s}
.ia-attach-btn:hover{color:var(--mn-principal-texto, #4f46e5);border-color:var(--mn-principal, #6366f1);background:var(--mn-100, #e0e7ff)}
.ia-attach-btn:disabled{opacity:.45;cursor:not-allowed}
.ia-attach-btn:focus-visible,.ia-send-btn:focus-visible{outline:2px solid var(--mn-principal, #6366f1);outline-offset:2px}
.ia-file-input{display:none}
.ia-input{flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;resize:none;min-height:36px;max-height:160px;background:white;color:#1e293b;line-height:1.4;caret-color:var(--mn-principal, #6366f1)}
.ia-input::placeholder{color:#64748b}
.ia-input:focus{outline:none;border-color:var(--mn-principal, #6366f1);box-shadow:0 0 0 3px rgba(var(--mn-principal-rgb, 99,102,241), .12)}
.ia-send-btn{width:36px;height:36px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,var(--mn-principal, #6366f1),var(--mn-principal-activo, #8b5cf6));border:none;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center}
.ia-send-btn:disabled{opacity:.4;cursor:not-allowed}
.ia-input-hint{font-size:10.5px;color:#64748b;margin-top:5px}

/* Arrastrar y soltar */
.ia-drop{position:absolute;inset:0;z-index:5;display:flex;align-items:center;justify-content:center;padding:14px;background:rgba(var(--mn-principal-rgb, 99,102,241), .10);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)}
.ia-drop-inner{width:100%;height:100%;border:2px dashed var(--mn-principal, #6366f1);border-radius:14px;background:rgba(255,255,255,.92);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;text-align:center;padding:20px;color:#334155;pointer-events:none}
.ia-drop-inner i{font-size:34px;color:var(--mn-principal-texto, #4f46e5);margin-bottom:4px}
.ia-drop-inner strong{font-size:15px;color:#0f172a}
.ia-drop-inner span{font-size:12px;color:#475569;max-width:260px}
.ia-drop-enter-active{transition:opacity .22s cubic-bezier(.16,1,.3,1)}
.ia-drop-enter-active .ia-drop-inner{transition:transform .3s cubic-bezier(.16,1,.3,1)}
.ia-drop-leave-active{transition:opacity .15s ease-out}
.ia-drop-enter-from,.ia-drop-leave-to{opacity:0}
.ia-drop-enter-from .ia-drop-inner{transform:scale(.96)}

.ia-slide-enter-active,.ia-slide-leave-active{transition:all .25s cubic-bezier(.4,0,.2,1)}
.ia-slide-enter-from,.ia-slide-leave-to{opacity:0;transform:translateY(20px) scale(.95)}
.ia-msg-text :deep(code){background:#e2e8f0;color:#0f172a;padding:1px 5px;border-radius:4px;font-size:11.5px}
.ia-msg-user .ia-msg-text :deep(code){background:rgba(255,255,255,.2);color:#fff}

@media (prefers-reduced-motion: reduce){
    .ia-float-btn.has-pulse::after,.ia-dot,.ia-typing span,.ia-tray-bar.is-indeterminate span{animation:none}
    .ia-float-btn,.ia-thumb img,.ia-slide-enter-active,.ia-slide-leave-active,.ia-drop-enter-active .ia-drop-inner{transition:none}
}

/* Modo oscuro (data-layout-mode del sistema, nunca prefers-color-scheme) */
[data-layout-mode=dark] .ia-chat-panel{background:#1c2130;border-color:#323849;color:#e2e8f0;box-shadow:0 20px 60px rgba(0,0,0,.5)}
[data-layout-mode=dark] .ia-welcome p{color:#a7b0c2}
[data-layout-mode=dark] .ia-chip{background:#252a3a;border-color:#363c4f;color:#c8cfdc}
[data-layout-mode=dark] .ia-chip:hover{background:rgba(var(--mn-principal-rgb, 99,102,241), .2);color:#fff}
[data-layout-mode=dark] .ia-messages{scrollbar-color:#3a4155 transparent}
[data-layout-mode=dark] .ia-messages::-webkit-scrollbar-thumb{background:#3a4155}
[data-layout-mode=dark] .ia-msg-ai .ia-msg-text{background:#252a3a;border-color:#323849;color:#e2e8f0}
[data-layout-mode=dark] .ia-msg-text :deep(.ia-link){color:#c4b5fd;text-decoration-color:rgba(196,181,253,.45)}
[data-layout-mode=dark] .ia-msg-user .ia-msg-text :deep(.ia-link){color:#fff}
[data-layout-mode=dark] .ia-msg-text :deep(.ia-table-wrap){background:#1f2433;border-color:#363c4f}
[data-layout-mode=dark] .ia-msg-text :deep(.ia-table th){background:#2a3042;color:#d5dbe6;border-color:#363c4f}
[data-layout-mode=dark] .ia-msg-text :deep(.ia-table td){border-color:#2a3042}
[data-layout-mode=dark] .ia-msg-text :deep(code){background:#323849;color:#e2e8f0}
[data-layout-mode=dark] .ia-msg-time{color:#8b93a7}
[data-layout-mode=dark] .ia-thumb{border-color:#363c4f;background:#252a3a}
[data-layout-mode=dark] .ia-file,[data-layout-mode=dark] .ia-tray-item{background:#252a3a;border-color:#363c4f;color:#e2e8f0}
[data-layout-mode=dark] .ia-file-meta{color:#9aa3b5}
[data-layout-mode=dark] .ia-file-icon,[data-layout-mode=dark] .ia-tray-thumb{background:#323849;color:#a7b0c2}
[data-layout-mode=dark] .ia-t-pdf{background:rgba(239,68,68,.16);color:#fca5a5}
[data-layout-mode=dark] .ia-t-hoja{background:rgba(34,197,94,.16);color:#86efac}
[data-layout-mode=dark] .ia-t-documento{background:rgba(59,130,246,.18);color:#93c5fd}
[data-layout-mode=dark] .ia-t-imagen{background:rgba(245,158,11,.16);color:#fcd34d}
[data-layout-mode=dark] .ia-t-video{background:rgba(139,92,246,.2);color:#c4b5fd}
[data-layout-mode=dark] .ia-t-audio{background:rgba(236,72,153,.16);color:#f9a8d4}
[data-layout-mode=dark] .ia-tray-item.is-error{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.45)}
[data-layout-mode=dark] .ia-tray-item.is-error .ia-file-meta{color:#fca5a5}
[data-layout-mode=dark] .ia-tray-remove{color:#9aa3b5}
[data-layout-mode=dark] .ia-tray-remove:hover{background:#323849;color:#fff}
[data-layout-mode=dark] .ia-confirm{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.45);color:#fde68a}
[data-layout-mode=dark] .ia-confirm-detail summary,[data-layout-mode=dark] .ia-confirm-more{color:#fcd34d}
[data-layout-mode=dark] .ia-confirm-detail ol{background:rgba(0,0,0,.2);border-color:rgba(245,158,11,.3);color:#e7e5e4}
[data-layout-mode=dark] .ia-confirm-no{background:transparent;color:#fde68a;border-color:rgba(245,158,11,.5)}
[data-layout-mode=dark] .ia-input-area{background:#191d2a;border-color:#323849}
[data-layout-mode=dark] .ia-input{background:#232838;border-color:#363c4f;color:#e2e8f0}
[data-layout-mode=dark] .ia-input::placeholder{color:#8b93a7}
[data-layout-mode=dark] .ia-attach-btn{background:#232838;border-color:#363c4f;color:#c8cfdc}
[data-layout-mode=dark] .ia-attach-btn:hover{background:rgba(var(--mn-principal-rgb, 99,102,241), .2);color:#fff}
[data-layout-mode=dark] .ia-input-hint{color:#8b93a7}
[data-layout-mode=dark] .ia-drop-inner{background:rgba(28,33,48,.94);color:#c8cfdc}
[data-layout-mode=dark] .ia-drop-inner strong{color:#fff}
[data-layout-mode=dark] .ia-drop-inner span{color:#a7b0c2}
[data-layout-mode=dark] .ia-catch-label{background:#f8fafc;color:#0f172a}
</style>
