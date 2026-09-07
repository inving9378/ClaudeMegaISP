<template>
  <div class="jcd-wrap">
    <!-- Disparador: vive DENTRO de la Torre (no es un widget global, esa es la burbuja #651). -->
    <button type="button" class="btn btn-outline-dark btn-sm jcd-toggle" @click="toggle" :title="tituloToggle">
      <i class="bi bi-robot me-1"></i> Jarvis
      <span v-if="pendientes > 0" class="badge bg-warning text-dark rounded-pill ms-1 jcd-badge hdr-badge">{{ pendientes }}</span>
    </button>

    <transition name="jcd-fade">
      <div v-if="abierto" class="jcd-backdrop" @click="cerrar"></div>
    </transition>

    <transition name="jcd-slide">
      <aside v-if="abierto" class="jcd-drawer">
        <div class="jcd-head">
          <div class="jcd-head-title">
            <button v-if="vista === 'chat'" type="button" class="btn btn-sm btn-link jcd-back" title="Volver a la lista" @click="volver">
              <i class="bi bi-arrow-left"></i>
            </button>
            <i v-else class="bi bi-robot me-1"></i>
            <span v-if="vista === 'chat' && conversacionSel">{{ conversacionSel.categoria || 'Sugerencia' }}</span>
            <span v-else>Sugerencias de Jarvis</span>
          </div>
          <button type="button" class="btn-close" aria-label="Cerrar" @click="cerrar"></button>
        </div>

        <div class="jcd-body">
          <div v-if="error" class="alert alert-danger py-2 px-3 small m-2 mb-0">{{ error }}</div>

          <!-- ── Vista lista: candidatos vivos del detector #805 ── -->
          <template v-if="vista === 'lista'">
            <div v-if="cargando" class="jcd-loading">
              <span class="spinner-border spinner-border-sm me-1"></span> Buscando sugerencias…
            </div>
            <div v-else-if="!candidatos.length && !error" class="jcd-empty">
              <i class="bi bi-check2-circle jcd-empty-ico"></i>
              <p>Sin sugerencias vivas ahora mismo.</p>
              <p class="text-muted small">Jarvis avisa aquí en cuanto el detector encuentre algo citable.</p>
            </div>
            <ul v-else class="jcd-list">
              <li v-for="c in candidatos" :key="c.clave" class="jcd-item" @click="abrirConversacion(c)">
                <div class="jcd-item-top">
                  <span class="badge jcd-cat">{{ c.categoria || 'general' }}</span>
                  <span v-if="c.estado === 'convertida'" class="badge bg-success">convertida</span>
                  <span v-else-if="c.mensajes > 0" class="badge bg-info text-dark">{{ c.mensajes }} msj</span>
                </div>
                <p class="jcd-item-txt">{{ c.texto }}</p>
                <p v-if="c.citas && c.citas.length" class="jcd-item-citas">
                  <i class="bi bi-link-45deg"></i> {{ c.citas.map(citaTexto).join(' · ') }}
                </p>
              </li>
            </ul>
            <div v-if="generadoAt" class="jcd-foot text-muted small">Detectado {{ formatoFecha(generadoAt) }}</div>
          </template>

          <!-- ── Vista chat: hilo de una sugerencia ── -->
          <template v-else-if="vista === 'chat' && conversacionSel">
            <div class="jcd-brief">
              <p class="jcd-brief-txt">{{ conversacionSel.texto_sugerencia }}</p>
              <p v-if="conversacionSel.citas && conversacionSel.citas.length" class="jcd-brief-citas">
                <i class="bi bi-link-45deg"></i> {{ conversacionSel.citas.map(citaTexto).join(' · ') }}
              </p>
            </div>

            <div ref="msgsEl" class="jcd-msgs">
              <div v-if="!conversacionSel.mensajes.length" class="jcd-msgs-empty text-muted small">
                Todavía no hay mensajes. Escríbele a Jarvis para conversar el brief.
              </div>
              <div v-for="(m, i) in conversacionSel.mensajes" :key="i" class="jcd-msg" :class="m.rol === 'irving' ? 'jcd-msg-me' : 'jcd-msg-jarvis'">
                <div class="jcd-msg-bubble">{{ m.contenido }}</div>
              </div>
              <div v-if="enviando" class="jcd-msg jcd-msg-jarvis">
                <div class="jcd-msg-bubble jcd-msg-typing"><span class="spinner-border spinner-border-sm"></span></div>
              </div>
            </div>

            <form class="jcd-input" @submit.prevent="enviarMensaje">
              <input v-model="mensaje" type="text" class="form-control form-control-sm" placeholder="Escribe a Jarvis…" :disabled="enviando" maxlength="4000">
              <button type="submit" class="btn btn-primary btn-sm" :disabled="enviando || !mensaje.trim()">
                <i class="bi bi-send"></i>
              </button>
            </form>

            <!-- Item #828 (Fase 4) — botón explícito al cierre del chat: Irving revisa el brief
                 (título + prompt, prellenados del hilo) y CONFIRMA antes de encolar. Nunca se
                 auto-crea el item ni hay slash-command (decisión q4, ya tomada). -->
            <div v-if="conversacionSel.estado === 'convertida'" class="jcd-generado">
              <i class="bi bi-check-circle-fill me-1"></i> Item #{{ conversacionSel.item_id }} generado en la Hoja de Ruta.
            </div>
            <div v-else-if="!mostrarBorrador" class="jcd-generar">
              <button
                type="button"
                class="btn btn-outline-success btn-sm w-100"
                :disabled="!conversacionSel.mensajes.length"
                @click="abrirBorrador"
              >
                <i class="bi bi-clipboard-check me-1"></i> Generar item del roadmap
              </button>
            </div>
            <div v-else class="jcd-borrador">
              <label class="form-label small fw-semibold mb-1">Título del item</label>
              <input v-model="borrador.title" type="text" class="form-control form-control-sm mb-2" maxlength="255" placeholder="Título">
              <label class="form-label small fw-semibold mb-1">Prompt para quien lo ejecute</label>
              <textarea v-model="borrador.prompt" class="form-control form-control-sm mb-2" rows="5" placeholder="Instrucciones…"></textarea>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary btn-sm" :disabled="generando" @click="cancelarBorrador">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm flex-fill" :disabled="generando || !borrador.title.trim()" @click="confirmarGenerarItem">
                  {{ generando ? 'Generando…' : 'Confirmar y encolar' }}
                </button>
              </div>
            </div>
          </template>
        </div>
      </aside>
    </transition>
  </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from "vue";
import axios from "axios";

/**
 * Item #827 (Jarvis Parte 3b, Fase 3) — drawer lateral del chat de sugerencias, dentro de la
 * Torre de Control. Consume los endpoints de la Fase 2 (#826, `JarvisChatController`, prefijo
 * `/api/roadmap/jarvis-chat/*`). Decisión de Irving q1 (ya tomada): un solo hilo por sugerencia,
 * sin módulo `/jarvis` aparte y sin tocar la burbuja global (#651, sigue siendo solo presencia).
 */
export default {
  name: "JarvisChatDrawer",
  setup() {
    const abierto = ref(false);
    const vista = ref("lista"); // 'lista' | 'chat'
    const cargando = ref(false);
    const error = ref("");
    const candidatos = ref([]);
    const generadoAt = ref(null);
    const conversacion = ref(null);
    const mensaje = ref("");
    const enviando = ref(false);
    const msgsEl = ref(null);

    // Item #828 (Fase 4) — borrador del brief antes de encolarlo como item del roadmap.
    const mostrarBorrador = ref(false);
    const borrador = ref({ title: "", prompt: "" });
    const generando = ref(false);

    const conversacionSel = computed(() => conversacion.value);

    const pendientes = computed(
      () => candidatos.value.filter((c) => c.estado !== "convertida").length
    );

    const tituloToggle = computed(() =>
      pendientes.value > 0
        ? `Jarvis tiene ${pendientes.value} sugerencia(s) por conversar`
        : "Sugerencias de Jarvis"
    );

    async function cargarSugerencias() {
      cargando.value = true;
      error.value = "";
      try {
        const { data } = await axios.get("/api/roadmap/jarvis-chat/sugerencias");
        candidatos.value = data.candidatos || [];
        generadoAt.value = data.generado_at || null;
      } catch (e) {
        error.value = "No se pudieron cargar las sugerencias: " + (e?.response?.data?.message || e.message);
      } finally {
        cargando.value = false;
      }
    }

    function toggle() {
      abierto.value = !abierto.value;
      if (abierto.value && vista.value === "lista") cargarSugerencias();
    }

    function cerrar() {
      abierto.value = false;
    }

    async function scrollAbajo() {
      await nextTick();
      if (msgsEl.value) msgsEl.value.scrollTop = msgsEl.value.scrollHeight;
    }

    async function abrirConversacion(candidato) {
      error.value = "";
      mostrarBorrador.value = false;
      try {
        const { data } = await axios.post("/api/roadmap/jarvis-chat/conversaciones", {
          clave: candidato.clave,
          categoria: candidato.categoria,
          texto: candidato.texto,
          citas: candidato.citas || [],
        });
        conversacion.value = data.conversacion;
        vista.value = "chat";
        scrollAbajo();
      } catch (e) {
        error.value = "No se pudo abrir el hilo: " + (e?.response?.data?.message || e.message);
      }
    }

    function volver() {
      vista.value = "lista";
      conversacion.value = null;
      mostrarBorrador.value = false;
      cargarSugerencias();
    }

    async function enviarMensaje() {
      const texto = mensaje.value.trim();
      if (!texto || !conversacion.value) return;
      mensaje.value = "";
      enviando.value = true;
      error.value = "";
      // Optimista: el mensaje de Irving aparece de inmediato; la respuesta de Jarvis se
      // agrega cuando llegue. Si falla, se retira para no dejar un mensaje fantasma.
      conversacion.value.mensajes.push({ rol: "irving", contenido: texto, created_at: null });
      scrollAbajo();
      try {
        const { data } = await axios.post(
          `/api/roadmap/jarvis-chat/conversaciones/${conversacion.value.id}/mensajes`,
          { mensaje: texto }
        );
        conversacion.value.mensajes.push({ rol: "jarvis", contenido: data.assistant.contenido, created_at: data.assistant.created_at });
      } catch (e) {
        conversacion.value.mensajes.pop();
        error.value = "Jarvis no pudo responder: " + (e?.response?.data?.mensaje || e?.response?.data?.message || e.message);
      } finally {
        enviando.value = false;
        scrollAbajo();
      }
    }

    // Item #828 (Fase 4) — cierra el ciclo #806: NO auto-crea el item ni hay slash-command
    // (decisión q4 ya tomada). El botón prellena un borrador editable a partir del hilo (el
    // último mensaje de Jarvis suele traer el título/prompt que redactó); Irving lo revisa,
    // lo ajusta si quiere y confirma. Reusa exactamente el mismo camino que "Agregar item" en
    // la Hoja de Ruta (`POST /api/roadmap/items` → `RoadmapController::store`, el mecanismo
    // interno único) — nada nuevo que escriba items aquí. El vínculo con
    // `jarvis_conversaciones.item_id` es solo trazabilidad (Fase 2/#826, ya existente).
    function idemKey() {
      if (window.crypto?.randomUUID) return window.crypto.randomUUID();
      return "idem-" + Date.now() + "-" + Math.random().toString(36).slice(2);
    }

    function abrirBorrador() {
      if (!conversacion.value) return;
      const ultimoJarvis = [...conversacion.value.mensajes].reverse().find((m) => m.rol === "jarvis");
      const categoria = conversacion.value.categoria
        ? conversacion.value.categoria.charAt(0).toUpperCase() + conversacion.value.categoria.slice(1) + " — "
        : "";
      borrador.value = {
        title: (categoria + (conversacion.value.texto_sugerencia || "")).slice(0, 255),
        prompt: ultimoJarvis ? ultimoJarvis.contenido : "",
      };
      mostrarBorrador.value = true;
    }

    function cancelarBorrador() {
      mostrarBorrador.value = false;
    }

    async function confirmarGenerarItem() {
      const titulo = borrador.value.title.trim();
      if (!titulo || !conversacion.value || generando.value) return;
      generando.value = true;
      error.value = "";
      try {
        const { data } = await axios.post("/api/roadmap/items", {
          title: titulo,
          description: conversacion.value.texto_sugerencia || null,
          prompt: borrador.value.prompt.trim() || null,
          idempotency_key: idemKey(),
        });
        const itemId = data.item.id;
        try {
          await axios.post(`/api/roadmap/jarvis-chat/conversaciones/${conversacion.value.id}/vincular-item`, {
            item_id: itemId,
          });
        } catch (e) {
          // El item YA se creó; el vínculo es solo trazabilidad (#826) y no debe bloquear el
          // flujo si falla por su cuenta.
          console.error("No se pudo vincular el hilo al item recién creado:", e);
        }
        conversacion.value.item_id = itemId;
        conversacion.value.estado = "convertida";
        mostrarBorrador.value = false;
      } catch (e) {
        error.value = "No se pudo generar el item: " + (e?.response?.data?.message || e.message);
      } finally {
        generando.value = false;
      }
    }

    function citaTexto(c) {
      if (!c) return "";
      if (c.tipo === "archivo_linea") return `${c.archivo || "?"}:${c.linea || "?"}`;
      if (c.tipo === "item_roadmap") return `item #${c.id || "?"}`;
      return c.texto || "cita";
    }

    function formatoFecha(iso) {
      try {
        return new Date(iso).toLocaleString("es-MX", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" });
      } catch (e) {
        return iso;
      }
    }

    let timer = null;
    onMounted(() => {
      // Refresca el badge en segundo plano (igual cadencia que la burbuja, #651) aunque el
      // drawer esté cerrado — así el contador se ve vivo sin tener que abrirlo primero.
      cargarSugerencias();
      timer = setInterval(() => {
        if (!abierto.value || vista.value === "lista") cargarSugerencias();
      }, 60000);
    });
    onBeforeUnmount(() => {
      if (timer) clearInterval(timer);
    });

    return {
      abierto, vista, cargando, error, candidatos, generadoAt, conversacionSel, mensaje, enviando, msgsEl,
      pendientes, tituloToggle, toggle, cerrar, abrirConversacion, volver, enviarMensaje, citaTexto, formatoFecha,
      mostrarBorrador, borrador, generando, abrirBorrador, cancelarBorrador, confirmarGenerarItem,
    };
  },
};
</script>

<style scoped>
.jcd-wrap { display: inline-block; }
.jcd-toggle { position: relative; display: inline-flex; align-items: center; }
.jcd-badge { display: inline-flex; align-items: center; font-size: 10px; vertical-align: middle; }

.jcd-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .35); z-index: 2040; }
.jcd-drawer {
    position: fixed; top: 0; right: 0; bottom: 0; z-index: 2050;
    width: min(400px, 92vw); background: #fff; color: #0f172a;
    box-shadow: -12px 0 34px rgba(0, 0, 0, .25);
    display: flex; flex-direction: column;
}
.jcd-head {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 12px 14px; background: #0f172a; color: #e2e8f0; flex: 0 0 auto;
}
.jcd-head-title { display: flex; align-items: center; gap: 4px; font-weight: 600; }
.jcd-back { color: #e2e8f0; padding: 0 4px 0 0; text-decoration: none; }
.jcd-body { flex: 1 1 auto; overflow-y: auto; display: flex; flex-direction: column; }

.jcd-loading, .jcd-empty { padding: 24px 16px; text-align: center; color: #64748b; }
.jcd-empty-ico { font-size: 28px; color: #16a34a; margin-bottom: 8px; display: block; }

.jcd-list { list-style: none; margin: 0; padding: 0; }
.jcd-item { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; cursor: pointer; }
.jcd-item:hover { background: #f8fafc; }
.jcd-item-top { display: flex; gap: 6px; margin-bottom: 4px; }
.jcd-cat { background: #e0e7ff; color: #3730a3; text-transform: capitalize; }
.jcd-item-txt { margin: 0 0 4px; font-size: 13px; line-height: 1.4; }
.jcd-item-citas { margin: 0; font-size: 11px; color: #64748b; }
.jcd-foot { padding: 8px 14px; }

.jcd-brief { padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex: 0 0 auto; }
.jcd-brief-txt { margin: 0 0 4px; font-size: 13px; line-height: 1.4; }
.jcd-brief-citas { margin: 0; font-size: 11px; color: #64748b; }

.jcd-msgs { flex: 1 1 auto; overflow-y: auto; padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; }
.jcd-msgs-empty { text-align: center; padding: 12px 0; }
.jcd-msg { display: flex; }
.jcd-msg-me { justify-content: flex-end; }
.jcd-msg-jarvis { justify-content: flex-start; }
.jcd-msg-bubble {
    max-width: 82%; padding: 8px 12px; border-radius: 12px; font-size: 13px; line-height: 1.4;
    white-space: pre-wrap; word-break: break-word;
}
.jcd-msg-me .jcd-msg-bubble { background: #0f172a; color: #fff; border-bottom-right-radius: 3px; }
.jcd-msg-jarvis .jcd-msg-bubble { background: #f1f5f9; color: #0f172a; border-bottom-left-radius: 3px; }
.jcd-msg-typing { padding: 6px 12px; }

.jcd-input { display: flex; gap: 6px; padding: 10px 14px; border-top: 1px solid #e2e8f0; flex: 0 0 auto; }
.jcd-input input { flex: 1 1 auto; }

.jcd-generar { padding: 10px 14px; border-top: 1px solid #e2e8f0; flex: 0 0 auto; }
.jcd-generado {
    padding: 10px 14px; border-top: 1px solid #e2e8f0; flex: 0 0 auto;
    color: #15803d; font-size: 13px; font-weight: 600;
}
.jcd-borrador { padding: 10px 14px; border-top: 1px solid #e2e8f0; flex: 0 0 auto; background: #f8fafc; }

.jcd-fade-enter-active, .jcd-fade-leave-active { transition: opacity .15s ease; }
.jcd-fade-enter-from, .jcd-fade-leave-to { opacity: 0; }
.jcd-slide-enter-active, .jcd-slide-leave-active { transition: transform .18s ease; }
.jcd-slide-enter-from, .jcd-slide-leave-to { transform: translateX(100%); }
</style>
