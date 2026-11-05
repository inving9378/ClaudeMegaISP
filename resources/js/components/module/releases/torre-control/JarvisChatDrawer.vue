<template>
  <div class="jcd-wrap">
    <!-- Disparador: vive DENTRO de la Torre (no es un widget global, esa es la burbuja #651). -->
    <button type="button" class="btn btn-outline-dark btn-sm jcd-toggle" @click="toggle" :title="tituloToggle">
      <i class="bi bi-robot me-1"></i> Asesor
      <span v-if="pendientes > 0" class="badge bg-warning text-dark rounded-pill ms-1 jcd-badge hdr-badge">{{ pendientes }}</span>
      <span v-if="respuestaLista && !abierto" class="jcd-dot" title="El Asesor ya respondió"></span>
    </button>

    <transition name="jcd-slide">
      <aside v-if="abierto" class="jcd-drawer" :class="{ plegado, 'jcd-dark': oscuro, 'jcd-acoplado': acoplado, 'jcd-hoja': hoja, 'jcd-pestana-delgada': acoplado && plegado }" :style="acoplado && !plegado ? { width: ancho + 'px' } : null">
        <div v-if="acoplado && !plegado" class="jcd-resize" title="Arrastra para cambiar el ancho" @mousedown.prevent="iniciarResize" @touchstart.prevent="iniciarResize"></div>
        <button v-if="acoplado && plegado" type="button" class="jcd-pestana" title="Desplegar Asesor" @click="plegado = false">
          <i class="bi bi-robot"></i><span>Asesor</span><i v-if="respuestaLista" class="jcd-dot"></i>
        </button>
        <div v-show="!(acoplado && plegado)" class="jcd-head">
          <div class="jcd-head-title">
            <button v-if="hoja && vista !== 'chat'" type="button" class="btn btn-sm btn-link jcd-back" title="Volver a la Torre" @click="cerrar">
              <i class="bi bi-arrow-left"></i>
            </button>
            <button v-else-if="vista === 'chat'" type="button" class="btn btn-sm btn-link jcd-back" title="Volver a la lista" @click="volver">
              <i class="bi bi-arrow-left"></i>
            </button>
            <i v-else class="bi bi-robot me-1"></i>
            <span v-if="vista === 'chat' && conversacionSel">{{ conversacionSel.categoria || 'Sugerencia' }}</span>
            <span v-else>Asesor de la Torre</span>
          </div>
          <div class="jcd-head-btns">
            <button v-if="!hoja && anchoVentana >= 1200" type="button" class="btn btn-sm btn-link jcd-fold" :title="modo === 'acoplado' ? 'Pasar a flotante' : 'Acoplar a la derecha'" @click="cambiarModo">
              <i :class="modo === 'acoplado' ? 'bi bi-window-stack' : 'bi bi-layout-sidebar-inset-reverse'"></i>
            </button>
            <button type="button" class="btn btn-sm btn-link jcd-fold" :title="plegado ? 'Desplegar' : 'Minimizar'" @click="plegado = !plegado">
              <i :class="plegado ? 'bi bi-chevron-down' : 'bi bi-dash-lg'"></i>
            </button>
            <button type="button" class="btn-close jcd-close" aria-label="Cerrar" @click="cerrar"></button>
          </div>
        </div>
        <div v-if="vista !== 'chat'" v-show="!plegado && !(acoplado && plegado)" class="jcd-tabs">
          <button type="button" class="jcd-tab" :class="{ activo: vista === 'conversar' }" @click="irAConversar">Conversar</button>
          <button type="button" class="jcd-tab" :class="{ activo: vista === 'lista' }" @click="irASugerencias">
            Sugerencias<span v-if="pendientes > 0"> ({{ pendientes }})</span>
          </button>
        </div>

        <div v-show="!plegado" class="jcd-body">
          <div v-if="error" class="alert alert-danger py-2 px-3 small m-2 mb-0">{{ error }}</div>

          <!-- ── #1068: conversar con Jarvis. Abre con el resumen desde la última visita (datos de
               la base) y responde preguntas sobre el circuito con el estado real, sin inventar. ── -->
          <template v-if="vista === 'conversar'">
            <div v-if="cargandoConversar" class="jcd-loading">
              <span class="spinner-border spinner-border-sm me-1"></span> Preparando el resumen…
            </div>
            <template v-else-if="conversacionLibre">
              <div class="jcd-nueva">
                <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="cargandoConversar" @click="nuevaConversacion"><i class="bi bi-plus-lg"></i> Nueva conversación</button>
              </div>
              <div ref="msgsEl" class="jcd-msgs">
                <div v-for="(m, i) in conversacionLibre.mensajes" :key="i" class="jcd-msg" :class="m.rol === 'irving' ? 'jcd-msg-me' : 'jcd-msg-jarvis'">
                  <div class="jcd-msg-bubble" :class="{ 'jcd-bubble-wide': m.propuesta }">
                    {{ m.contenido }}
                    <div v-if="m.rol === 'jarvis'" class="jcd-escuchar-wrap">
                      <button type="button" class="btn btn-link btn-sm jcd-escuchar" @click="escuchar(m.contenido, i)">
                        <i :class="hablando === i ? 'bi bi-stop-circle' : 'bi bi-volume-up'"></i>
                        {{ hablando === i ? 'Detener' : 'Escuchar' }}
                      </button>
                    </div>
                    <div v-if="m.propuesta && m.propuesta.items && m.propuesta.items.length && !(m.tarjeta && m.tarjeta.cerrada)" class="jcd-propuesta">
                      <TarjetaAprobacionItems
                        :items="m.propuesta.items"
                        :avisos="m.propuesta.avisos || []"
                        :puede-crear="puedeCrearItems"
                        :oscuro="oscuro"
                        :ocupado="!!(m.tarjeta && m.tarjeta.ocupado)"
                        :mensaje="m.tarjeta ? m.tarjeta.mensaje : ''"
                        :error="m.tarjeta ? m.tarjeta.error : ''"
                        :creados="m.tarjeta ? m.tarjeta.creados : []"
                        :rechazados="m.tarjeta ? m.tarjeta.rechazados : []"
                        :requieren-confirmacion="m.tarjeta ? m.tarjeta.requieren : []"
                        @crear="crearPropuesta(m, $event)"
                        @editar="editarPropuesta(m, $event)"
                        @descartar="descartarPropuesta(m)"
                        @deshacer="deshacerItem(m, $event)"
                      />
                    </div>
                  </div>
                </div>
                <div v-if="enviando || pendiente" class="jcd-msg jcd-msg-jarvis">
                  <div class="jcd-msg-bubble jcd-msg-typing"><span class="spinner-border spinner-border-sm"></span><span v-if="pendiente && !enviando" class="ms-2 small">El Asesor está respondiendo…</span></div>
                </div>
              </div>
              <div v-if="chips.length" class="jcd-chips">
                <button v-for="c in chips" :key="c.clave" type="button" class="jcd-chip" :title="c.texto" @click="abrirConversacion(c)">
                  {{ c.categoria || 'general' }}: {{ c.texto }}
                </button>
              </div>
              <form class="jcd-input" @submit.prevent="enviarMensaje">
                <textarea ref="inputEl" v-model="mensaje" rows="2" class="form-control form-control-sm" placeholder="Pregúntale al Asesor por el circuito… (Enter envía, Shift+Enter salto de línea)" :disabled="enviando" maxlength="2000" @keydown.enter.exact.prevent="enviarMensaje"></textarea>
                <button type="submit" class="btn btn-primary btn-sm" :disabled="enviando || !mensaje.trim()">
                  <i class="bi bi-send"></i>
                </button>
              </form>
              <details v-if="anteriores.length" class="jcd-anteriores">
                <summary>Últimas conversaciones ({{ anteriores.length }})</summary>
                <ul>
                  <li v-for="a in anteriores" :key="a.id">
                    <a href="#" @click.prevent="verAnterior(a.id)">{{ a.fecha }} · {{ a.pregunta || 'solo el resumen' }}</a>
                  </li>
                </ul>
              </details>
            </template>
          </template>

          <!-- ── Vista lista: candidatos vivos del detector #805 ── -->
          <template v-if="vista === 'lista'">
            <div v-if="cargando" class="jcd-loading">
              <span class="spinner-border spinner-border-sm me-1"></span> Buscando sugerencias…
            </div>
            <div v-else-if="!candidatos.length && !error" class="jcd-empty">
              <i class="bi bi-check2-circle jcd-empty-ico"></i>
              <p>Sin sugerencias vivas ahora mismo.</p>
              <p class="text-muted small">El Asesor avisa aquí en cuanto el detector encuentre algo citable.</p>
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
                Todavía no hay mensajes. Escríbele al Asesor para conversar el brief.
              </div>
              <div v-for="(m, i) in conversacionSel.mensajes" :key="i" class="jcd-msg" :class="m.rol === 'irving' ? 'jcd-msg-me' : 'jcd-msg-jarvis'">
                <div class="jcd-msg-bubble">{{ m.contenido }}</div>
              </div>
              <div v-if="enviando" class="jcd-msg jcd-msg-jarvis">
                <div class="jcd-msg-bubble jcd-msg-typing"><span class="spinner-border spinner-border-sm"></span></div>
              </div>
            </div>

            <form class="jcd-input" @submit.prevent="enviarMensaje">
              <textarea ref="inputEl" v-model="mensaje" rows="2" class="form-control form-control-sm" placeholder="Escribe al Asesor… (Enter envía, Shift+Enter salto de línea)" :disabled="enviando" maxlength="4000" @keydown.enter.exact.prevent="enviarMensaje"></textarea>
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
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from "vue";
import axios from "axios";
import { useEscuchar } from "./torreEscuchar";
import TarjetaAprobacionItems from "./TarjetaAprobacionItems.vue";
import { darkMode } from "../../../../hook/appConfig.js";

/**
 * Item #827 (Jarvis Parte 3b, Fase 3) — drawer lateral del chat de sugerencias, dentro de la
 * Torre de Control. Consume los endpoints de la Fase 2 (#826, `JarvisChatController`, prefijo
 * `/api/roadmap/jarvis-chat/*`). Decisión de Irving q1 (ya tomada): un solo hilo por sugerencia,
 * sin módulo `/jarvis` aparte y sin tocar la burbuja global (#651, sigue siendo solo presencia).
 */
export default {
  name: "JarvisChatDrawer",
  components: { TarjetaAprobacionItems },
  props: {
    userId: { type: [String, Number], default: "" },
    pestana: { type: String, default: "" },
    itemId: { type: [Number, String], default: null },
  },
  emits: ["dock"],
  setup(props, { emit }) {
    // ── #1112: recordar abierto/modo/ancho por usuario (todo en try/catch: si el
    // almacenamiento falla, la pantalla funciona igual). ──
    const K = (n) => `jarvis_panel_${props.userId || "anon"}_${n}`;
    const lsGet = (n) => { try { return window.localStorage.getItem(K(n)); } catch (e) { return null; } };
    const lsSet = (n, v) => { try { window.localStorage.setItem(K(n), String(v)); } catch (e) { /* sin almacenamiento */ } };
    const anchoVentana = ref(window.innerWidth);
    const modo = ref(lsGet("modo") === "flotante" ? "flotante" : "acoplado");
    const ancho = ref(Math.min(520, Math.max(320, Number(lsGet("ancho")) || 380)));
    const hoja = computed(() => anchoVentana.value < 768);
    const acoplado = computed(() => anchoVentana.value >= 1200 && modo.value === "acoplado");
    const respuestaLista = ref(false);
    const pendiente = ref(false);
    const abierto = ref(false);
    const oscuro = computed(() => darkMode.value); // #1108: sigue la luna de la Torre
    const vista = ref("conversar"); // 'conversar' (#1068) | 'lista' | 'chat'
    const cargando = ref(false);
    const error = ref("");
    const candidatos = ref([]);
    const generadoAt = ref(null);
    const conversacion = ref(null);
    const mensaje = ref("");
    const enviando = ref(false);
    const msgsEl = ref(null);
    const inputEl = ref(null);
    const plegado = ref(false);
    const dockPx = computed(() => (abierto.value && acoplado.value ? (plegado.value ? 38 : ancho.value) : 0));
    watch(dockPx, (v) => emit("dock", v), { immediate: true });
    const chips = computed(() => candidatos.value.filter((c) => c.estado !== "convertida").slice(0, 4));

    async function enfocar() {
      await nextTick();
      if (inputEl.value) inputEl.value.focus();
    }

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
        ? `El Asesor tiene ${pendientes.value} sugerencia(s) por conversar`
        : "Conversar con el Asesor"
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

    // ── #1068: conversar con Jarvis («¿en qué nos quedamos?») ──
    const conversacionLibre = ref(null);
    const anteriores = ref([]);
    const cargandoConversar = ref(false);
    // «Escuchar» reusa la lógica única de la Torre (misma voz y velocidad que Integración).
    const { hablando, vozTts, rateTts, leer, initVoces } = useEscuchar();

    function aplicarConversacion(data) {
      conversacionLibre.value = data.conversacion;
      if (data.puede_crear_items !== undefined) puedeCrearItems.value = data.puede_crear_items !== false;
      pendiente.value = !!data.pendiente;
      if (data.conversacion && data.conversacion.id) lsSet("conv", data.conversacion.id);
      if (pendiente.value) vigilarPendiente(); else detenerVigilancia();
    }

    // Conversación nueva (resumen desde la última visita). Es el botón «Nueva conversación».
    async function abrirConversar() {
      cargandoConversar.value = true;
      error.value = "";
      try {
        const { data } = await axios.post("/api/roadmap/jarvis-chat/conversar");
        aplicarConversacion(data);
        anteriores.value = data.anteriores || [];
        vozTts.value = data.voz_tts || null;
        if (data.rate_tts) rateTts.value = Number(data.rate_tts);
        scrollAbajo(true);
        enfocar();
      } catch (e) {
        error.value = "El Asesor no pudo preparar el resumen: " + (e?.response?.data?.message || e.message);
      } finally {
        cargandoConversar.value = false;
      }
    }
    function nuevaConversacion() {
      detenerVigilancia();
      conversacionLibre.value = null;
      abrirConversar();
    }

    // Al abrir NO se crea conversación: se retoma la última (servidor; mientras no exista,
    // el id guardado en localStorage); solo si no hay ninguna se llama a «conversar».
    async function cargarInicial() {
      cargandoConversar.value = true;
      error.value = "";
      try {
        let data = null;
        try {
          const r = await axios.get("/api/roadmap/jarvis-chat/ultima");
          if (r.data && r.data.conversacion) data = r.data;
          else if (r.data && r.data.ok) data = { conversacion: null };
        } catch (e) { /* 404 mientras el backend no llegue */ }
        if (!data) {
          const id = lsGet("conv");
          if (id) {
            try { data = (await axios.get(`/api/roadmap/jarvis-chat/conversaciones/${id}`)).data; } catch (e) { data = null; }
          }
        }
        cargarRecientes();
        if (data && data.conversacion) {
          aplicarConversacion(data);
          respuestaLista.value = false;
          scrollAbajo(true);
          enfocar();
          cargandoConversar.value = false;
          return;
        }
      } finally {
        cargandoConversar.value = false;
      }
      await abrirConversar();
    }
    async function cargarRecientes() {
      try {
        const { data } = await axios.get("/api/roadmap/jarvis-chat/recientes");
        if (data && Array.isArray(data.conversaciones)) {
          anteriores.value = data.conversaciones.map((c) => ({ id: c.id, fecha: c.fecha, pregunta: c.titulo }));
        }
      } catch (e) { /* sin endpoint todavía: queda la lista de «conversar» */ }
    }

    // Respuesta a medias: el servidor la guarda igual; se vuelve a consultar hasta que llegue.
    let vigilia = null;
    function detenerVigilancia() { if (vigilia) { clearInterval(vigilia); vigilia = null; } }
    function vigilarPendiente() {
      if (vigilia) return;
      vigilia = setInterval(async () => {
        const c = conversacionLibre.value;
        if (!c || enviando.value) return;
        try {
          const { data } = await axios.get(`/api/roadmap/jarvis-chat/conversaciones/${c.id}`);
          if (!data.pendiente) {
            const cerca = estaCercaDelFondo();
            conversacionLibre.value = data.conversacion;
            pendiente.value = false;
            detenerVigilancia();
            if (!abierto.value) respuestaLista.value = true;
            scrollAbajo(cerca);
          }
        } catch (e) { /* reintenta en el próximo ciclo */ }
      }, 4000);
    }
    async function verAnterior(id) {
      try {
        const { data } = await axios.get(`/api/roadmap/jarvis-chat/conversaciones/${id}`);
        aplicarConversacion(data);
        scrollAbajo(true);
      } catch (e) {
        error.value = "No se pudo abrir esa conversación: " + (e?.response?.data?.message || e.message);
      }
    }
    function irAConversar() {
      vista.value = "conversar";
      enfocar();
      if (!conversacionLibre.value) cargarInicial();
    }
    function irASugerencias() {
      vista.value = "lista";
      cargarSugerencias();
    }
    function escuchar(texto, idx) {
      if (!window.speechSynthesis) {
        error.value = "Este navegador no puede leer en voz alta.";
        return;
      }
      leer({ id: idx, title: "", resumen: texto });
    }

    function toggle() {
      if (abierto.value) { cerrar(); return; }
      abrir();
    }

    function abrir() {
      abierto.value = true;
      lsSet("abierto", 1);
      respuestaLista.value = false;
      plegado.value = false;
      enfocar();
      if (vista.value === "lista") cargarSugerencias();
      // Retoma la última conversación; la ya cargada en memoria se conserva (y se refresca si quedó pendiente).
      if (vista.value === "conversar") {
        if (!conversacionLibre.value) cargarInicial();
        else if (pendiente.value) vigilarPendiente();
      }
    }

    function cerrar() {
      abierto.value = false;
      lsSet("abierto", 0);
      if (window.speechSynthesis) window.speechSynthesis.cancel();
      hablando.value = null;
    }

    function cambiarModo() {
      modo.value = modo.value === "acoplado" ? "flotante" : "acoplado";
      lsSet("modo", modo.value);
      plegado.value = false;
    }

    function estaCercaDelFondo() {
      const el = msgsEl.value;
      return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80;
    }
    // Baja al último mensaje; si Irving subió a leer, conserva su posición (salvo force).
    async function scrollAbajo(force = false) {
      const seguir = force || estaCercaDelFondo();
      await nextTick();
      if (seguir && msgsEl.value) msgsEl.value.scrollTop = msgsEl.value.scrollHeight;
    }

    // Arrastre del borde izquierdo (320–520 px).
    function iniciarResize(ev) {
      const mover = (e) => {
        const x = e.touches ? e.touches[0].clientX : e.clientX;
        ancho.value = Math.min(520, Math.max(320, Math.round(window.innerWidth - x)));
      };
      const soltar = () => {
        window.removeEventListener("mousemove", mover);
        window.removeEventListener("mouseup", soltar);
        window.removeEventListener("touchmove", mover);
        window.removeEventListener("touchend", soltar);
        lsSet("ancho", ancho.value);
      };
      window.addEventListener("mousemove", mover);
      window.addEventListener("mouseup", soltar);
      window.addEventListener("touchmove", mover);
      window.addEventListener("touchend", soltar);
    }
    const alRedimensionar = () => { anchoVentana.value = window.innerWidth; };

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
        scrollAbajo(true);
        enfocar();
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
      // #1068 — el mismo envío sirve a la conversación libre y al hilo de una sugerencia.
      const c = vista.value === "conversar" ? conversacionLibre.value : conversacion.value;
      if (!texto || !c) return;
      mensaje.value = "";
      enviando.value = true;
      error.value = "";
      // Optimista: el mensaje de Irving aparece de inmediato; la respuesta de Jarvis se
      // agrega cuando llegue. Si falla, se retira para no dejar un mensaje fantasma.
      c.mensajes.push({ rol: "irving", contenido: texto, created_at: null });
      scrollAbajo(true);
      try {
        const { data } = await axios.post(
          `/api/roadmap/jarvis-chat/conversaciones/${c.id}/mensajes`,
          { mensaje: texto, contexto: { pestana: props.pestana || "", item_id: props.itemId ? Number(props.itemId) : null } }
        );
        if (data.puede_crear_items !== undefined) puedeCrearItems.value = data.puede_crear_items !== false;
        const msgJarvis = { rol: "jarvis", contenido: data.assistant.contenido, created_at: data.assistant.created_at };
        // #1111: si el backend manda `propuesta`, se muestra la tarjeta; si no, nada cambia.
        if (data.propuesta && data.propuesta.items && data.propuesta.items.length) {
          msgJarvis.propuesta = data.propuesta;
          msgJarvis.tarjeta = { ocupado: false, mensaje: "", error: "", creados: [], rechazados: [], requieren: [], cerrada: false };
        }
        const cerca = estaCercaDelFondo();
        c.mensajes.push(msgJarvis);
        if (!abierto.value) respuestaLista.value = true;
        scrollAbajo(cerca);
      } catch (e) {
        c.mensajes.pop();
        error.value = "El Asesor no pudo responder: " + (e?.response?.data?.mensaje || e?.response?.data?.message || e.message);
      } finally {
        enviando.value = false;
        enfocar();
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

    // ── #1111: tarjeta de aprobación de items propuestos por Jarvis ──
    const puedeCrearItems = ref(true);
    const BASE_PROP = "/api/roadmap/jarvis-chat";
    const msgErr = (e) => e?.response?.data?.mensaje || e?.response?.data?.message || e.message;

    async function llamarTarjeta(m, fn) {
      const t = m.tarjeta;
      if (!t || t.ocupado) return; // sin doble clic
      t.ocupado = true;
      t.error = "";
      try {
        await fn(t);
      } catch (e) {
        t.error = msgErr(e);
      } finally {
        t.ocupado = false;
      }
    }

    function crearPropuesta(m, { alInicio, confirmadosC }) {
      return llamarTarjeta(m, async (t) => {
        const { data } = await axios.post(`${BASE_PROP}/propuestas/${m.propuesta.id}/crear`, {
          huella: m.propuesta.huella,
          al_inicio: alInicio,
          confirmados_c: confirmadosC,
        });
        t.creados = [...t.creados, ...(data.creados || [])];
        t.rechazados = data.rechazados || [];
        t.requieren = data.requieren_confirmacion || [];
        t.mensaje = data.mensaje || "";
      });
    }

    function editarPropuesta(m, items) {
      return llamarTarjeta(m, async (t) => {
        const { data } = await axios.post(`${BASE_PROP}/propuestas/${m.propuesta.id}/editar`, {
          huella: m.propuesta.huella,
          items,
        });
        if (data.propuesta) m.propuesta = data.propuesta; // huella NUEVA
        t.requieren = [];
      });
    }

    function descartarPropuesta(m) {
      return llamarTarjeta(m, async (t) => {
        await axios.post(`${BASE_PROP}/propuestas/${m.propuesta.id}/descartar`, { huella: m.propuesta.huella });
        t.cerrada = true;
      });
    }

    function deshacerItem(m, creado) {
      return llamarTarjeta(m, async (t) => {
        const { data } = await axios.post(`${BASE_PROP}/items/${creado.id}/deshacer`);
        t.creados = t.creados.filter((c) => c.id !== creado.id);
        t.mensaje = data.mensaje || `Item #${creado.id} cancelado.`;
      });
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
      initVoces();
      // Refresca el badge en segundo plano (igual cadencia que la burbuja, #651) aunque el
      // drawer esté cerrado — así el contador se ve vivo sin tener que abrirlo primero.
      cargarSugerencias();
      window.addEventListener("resize", alRedimensionar);
      if (lsGet("abierto") === "1") abrir();
      timer = setInterval(() => {
        if (!abierto.value || vista.value === "lista") cargarSugerencias();
      }, 60000);
    });
    onBeforeUnmount(() => {
      if (timer) clearInterval(timer);
      detenerVigilancia();
      window.removeEventListener("resize", alRedimensionar);
      if (window.speechSynthesis) window.speechSynthesis.cancel();
    });

    return {
      oscuro, abierto, vista, cargando, error, candidatos, generadoAt, conversacionSel, mensaje, enviando, msgsEl,
      inputEl, plegado, chips, pendientes, tituloToggle, toggle, cerrar, abrirConversacion, volver, enviarMensaje, citaTexto, formatoFecha,
      mostrarBorrador, borrador, generando, abrirBorrador, cancelarBorrador, confirmarGenerarItem,
      puedeCrearItems, crearPropuesta, editarPropuesta, descartarPropuesta, deshacerItem,
      modo, ancho, anchoVentana, hoja, acoplado, respuestaLista, pendiente, cambiarModo, iniciarResize, nuevaConversacion,
      conversacionLibre, anteriores, cargandoConversar, hablando, verAnterior, irAConversar, irASugerencias, escuchar,
    };
  },
};
</script>

<style scoped>
.jcd-wrap { display: inline-block; }
.jcd-drawer {
    --jcd-bg: #fff; --jcd-fg: #0f172a; --jcd-muted: #475569; --jcd-border: #e2e8f0;
    --jcd-head-bg: #0f172a; --jcd-head-fg: #e2e8f0; --jcd-tab-fg: #cbd5e1;
    --jcd-tab-active-bg: #fff; --jcd-tab-active-fg: #0f172a;
    --jcd-soft: #f8fafc; --jcd-hover: #f1f5f9;
    --jcd-chip-bg: #eef2ff; --jcd-chip-bd: #c7d2fe; --jcd-chip-fg: #3730a3;
    --jcd-me-bg: #0f172a; --jcd-me-fg: #fff; --jcd-bot-bg: #f1f5f9; --jcd-bot-fg: #0f172a;
    --jcd-field-bg: #fff; --jcd-field-fg: #0f172a; --jcd-field-bd: #cbd5e1; --jcd-ph: #64748b;
    --jcd-ok: #15803d; --jcd-link: #1d4ed8; --jcd-cat-bg: #e0e7ff; --jcd-cat-fg: #3730a3;
    color-scheme: light;
}
.jcd-drawer.jcd-dark {
    --jcd-bg: #1e293b; --jcd-fg: #e2e8f0; --jcd-muted: #cbd5e1; --jcd-border: #334155;
    --jcd-head-bg: #0b1220; --jcd-head-fg: #e2e8f0; --jcd-tab-fg: #cbd5e1;
    --jcd-tab-active-bg: #1e293b; --jcd-tab-active-fg: #f8fafc;
    --jcd-soft: #172033; --jcd-hover: #273449;
    --jcd-chip-bg: #312e81; --jcd-chip-bd: #4f46e5; --jcd-chip-fg: #e0e7ff;
    --jcd-me-bg: #4f46e5; --jcd-me-fg: #fff; --jcd-bot-bg: #334155; --jcd-bot-fg: #f1f5f9;
    --jcd-field-bg: #0f172a; --jcd-field-fg: #f1f5f9; --jcd-field-bd: #475569; --jcd-ph: #94a3b8;
    --jcd-ok: #4ade80; --jcd-link: #93c5fd; --jcd-cat-bg: #3730a3; --jcd-cat-fg: #e0e7ff;
    color-scheme: dark;
}
.jcd-close { filter: invert(1) grayscale(100%) brightness(200%); }
.jcd-dark .jcd-empty-ico { color: var(--jcd-ok); }
.jcd-drawer .text-muted { color: var(--jcd-muted) !important; }
.jcd-drawer .form-control { background: var(--jcd-field-bg); color: var(--jcd-field-fg); border-color: var(--jcd-field-bd); }
.jcd-drawer .form-control::placeholder { color: var(--jcd-ph); opacity: 1; }
.jcd-drawer .form-control:focus { background: var(--jcd-field-bg); color: var(--jcd-field-fg); border-color: #818cf8; box-shadow: 0 0 0 .2rem rgba(129, 140, 248, .35); }
.jcd-drawer .form-label { color: var(--jcd-fg); }
.jcd-drawer .btn-outline-success { color: var(--jcd-ok); border-color: var(--jcd-ok); }
.jcd-drawer .btn-outline-success:hover { background: var(--jcd-ok); color: var(--jcd-bg); }
.jcd-dark .btn-link { color: var(--jcd-link); }
.jcd-toggle { position: relative; display: inline-flex; align-items: center; }
.jcd-badge { display: inline-flex; align-items: center; font-size: 10px; vertical-align: middle; }

/* Panel compacto anclado bajo la cabecera; deja libre la esquina inferior derecha (robotsito, ayuda, telefonía). */
.jcd-drawer {
    position: fixed; top: 64px; right: 16px; z-index: 1040;
    width: min(400px, calc(100vw - 32px)); max-height: calc(100vh - 64px - 150px);
    background: var(--jcd-bg); color: var(--jcd-fg); border-radius: 12px; overflow: hidden;
    box-shadow: 0 10px 34px rgba(0, 0, 0, .28);
    display: flex; flex-direction: column;
}
.jcd-head-btns { display: flex; align-items: center; gap: 6px; }
.jcd-fold { color: var(--jcd-head-fg); padding: 0 4px; text-decoration: none; }
.jcd-chips { display: flex; gap: 6px; padding: 6px 14px 0; overflow-x: auto; flex: 0 0 auto; }
.jcd-chip {
    border: 1px solid var(--jcd-chip-bd); background: var(--jcd-chip-bg); color: var(--jcd-chip-fg); border-radius: 999px;
    font-size: 11px; padding: 3px 10px; white-space: nowrap; max-width: 220px;
    overflow: hidden; text-overflow: ellipsis; flex: 0 0 auto;
}
.jcd-input textarea { resize: none; }
.jcd-tabs { display: flex; gap: 4px; padding: 6px 10px 0; background: var(--jcd-head-bg); flex: 0 0 auto; }
.jcd-tab {
    border: 0; background: transparent; color: var(--jcd-tab-fg); font-size: 13px; font-weight: 600;
    padding: 6px 10px; border-radius: 8px 8px 0 0;
}
.jcd-tab.activo { background: var(--jcd-tab-active-bg); color: var(--jcd-tab-active-fg); }
.jcd-escuchar-wrap { margin-top: 4px; }
.jcd-escuchar { padding: 0; font-size: 12px; text-decoration: none; }
.jcd-anteriores { padding: 6px 12px 10px; font-size: 12px; color: var(--jcd-muted); }
.jcd-anteriores ul { list-style: none; padding-left: 0; margin: 6px 0 0; }
.jcd-anteriores li { padding: 2px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.jcd-head {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 12px 14px; background: var(--jcd-head-bg); color: var(--jcd-head-fg); flex: 0 0 auto;
}
.jcd-head-title { display: flex; align-items: center; gap: 4px; font-weight: 600; }
.jcd-back { color: var(--jcd-head-fg); padding: 0 4px 0 0; text-decoration: none; }
.jcd-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; }

.jcd-loading, .jcd-empty { padding: 24px 16px; text-align: center; color: var(--jcd-muted); }
.jcd-empty-ico { font-size: 28px; color: #16a34a; margin-bottom: 8px; display: block; }

.jcd-list { list-style: none; margin: 0; padding: 0; }
.jcd-item { padding: 12px 14px; border-bottom: 1px solid var(--jcd-border); cursor: pointer; }
.jcd-item:hover { background: var(--jcd-hover); }
.jcd-item-top { display: flex; gap: 6px; margin-bottom: 4px; }
.jcd-cat { background: var(--jcd-cat-bg); color: var(--jcd-cat-fg); text-transform: capitalize; }
.jcd-item-txt { margin: 0 0 4px; font-size: 13px; line-height: 1.4; }
.jcd-item-citas { margin: 0; font-size: 11px; color: var(--jcd-muted); }
.jcd-foot { padding: 8px 14px; }

.jcd-brief { padding: 12px 14px; background: var(--jcd-soft); border-bottom: 1px solid var(--jcd-border); flex: 0 0 auto; }
.jcd-brief-txt { margin: 0 0 4px; font-size: 13px; line-height: 1.4; }
.jcd-brief-citas { margin: 0; font-size: 11px; color: var(--jcd-muted); }

.jcd-msgs { flex: 1 1 auto; min-height: 120px; overflow-y: auto; padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; }
.jcd-msgs-empty { text-align: center; padding: 12px 0; }
.jcd-msg { display: flex; }
.jcd-msg-me { justify-content: flex-end; }
.jcd-msg-jarvis { justify-content: flex-start; }
.jcd-msg-bubble {
    max-width: 82%; min-width: 0; padding: 8px 12px; border-radius: 12px; font-size: 13px; line-height: 1.4;
    white-space: pre-wrap; word-break: break-word;
}
.jcd-msg-me .jcd-msg-bubble { background: var(--jcd-me-bg); color: var(--jcd-me-fg); border-bottom-right-radius: 3px; }
.jcd-msg-jarvis .jcd-msg-bubble { background: var(--jcd-bot-bg); color: var(--jcd-bot-fg); border-bottom-left-radius: 3px; }
.jcd-msg-jarvis .jcd-bubble-wide { max-width: 100%; width: 100%; }
.jcd-propuesta { margin-top: 8px; white-space: normal; }
.jcd-msg-typing { padding: 6px 12px; }

.jcd-input { display: flex; gap: 6px; padding: 10px 14px; border-top: 1px solid var(--jcd-border); flex: 0 0 auto; }
.jcd-input input { flex: 1 1 auto; }

.jcd-generar { padding: 10px 14px; border-top: 1px solid var(--jcd-border); flex: 0 0 auto; }
.jcd-generado {
    padding: 10px 14px; border-top: 1px solid var(--jcd-border); flex: 0 0 auto;
    color: var(--jcd-ok); font-size: 13px; font-weight: 600;
}
.jcd-borrador { padding: 10px 14px; border-top: 1px solid var(--jcd-border); flex: 0 0 auto; background: var(--jcd-soft); }

.jcd-slide-enter-active, .jcd-slide-leave-active { transition: transform .18s ease; }
.jcd-slide-enter-from, .jcd-slide-leave-to { transform: translateY(-12px); opacity: 0; }

/* ── #1112: modos acoplado / hoja completa ── */
.jcd-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: #22c55e; margin-left: 4px; box-shadow: 0 0 0 2px rgba(34, 197, 94, .3); }
.jcd-toggle .jcd-dot { position: absolute; top: -3px; right: -3px; margin: 0; }
.jcd-nueva { padding: 6px 14px 0; flex: 0 0 auto; }
.jcd-drawer.jcd-acoplado {
    top: 64px; right: 0; bottom: 0; max-height: none; border-radius: 0; z-index: 1030;
    box-shadow: -4px 0 18px rgba(0, 0, 0, .18);
}
/* espacio libre abajo: el robotsito, la ayuda y la telefonía no tapan el campo de texto */
.jcd-drawer.jcd-acoplado .jcd-body, .jcd-drawer.jcd-hoja .jcd-body { padding-bottom: 150px; }
.jcd-resize { position: absolute; left: 0; top: 0; bottom: 0; width: 6px; cursor: ew-resize; z-index: 2; }
.jcd-resize:hover { background: rgba(129, 140, 248, .5); }
.jcd-drawer.jcd-pestana-delgada { width: 38px; }
.jcd-pestana {
    border: 0; background: var(--jcd-head-bg); color: var(--jcd-head-fg); width: 100%; height: 100%;
    display: flex; flex-direction: column; align-items: center; gap: 10px; padding-top: 14px; cursor: pointer;
}
.jcd-pestana span { writing-mode: vertical-rl; font-weight: 600; font-size: 13px; }
.jcd-drawer.jcd-hoja {
    top: 0; left: 0; right: 0; bottom: 0; width: 100%; max-height: none; border-radius: 0; z-index: 1060;
}
</style>
