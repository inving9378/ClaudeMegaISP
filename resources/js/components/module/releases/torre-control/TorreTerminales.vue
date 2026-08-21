<template>
  <!-- Rejilla de terminales en vivo (#350): una tarjeta-terminal por sesión de CC.
       Read-only, polling ligero propio. Renderiza N terminales en vivo (wt-1..wt-6, #334 ya activo). -->
  <div class="tt-wrap" :class="{ 'tt-dark': dark }">

    <div class="tt-bar">
      <span class="tt-bar-title"><i class="bi bi-terminal me-1"></i> Terminales en vivo</span>
      <span class="tt-bar-meta">
        {{ sesiones.length }} sesión(es) · actualiza cada {{ Math.round(POLL_MS / 1000) }}s
        <span v-if="anyRunning" class="tt-bar-live"><span class="tt-dot"></span>en vivo</span>
      </span>
    </div>

    <!-- Empty state honesto: el paralelo (#334) YA existe; 0 sesiones = ninguna vuelta corriendo. -->
    <div v-if="!sesiones.length" class="tt-empty">
      <i class="bi bi-terminal-x tt-empty-ico"></i>
      <p class="tt-empty-h">Sin vueltas corriendo ahora mismo</p>
      <p class="tt-empty-p">
        El circuito corre por vueltas en paralelo; aquí verás <b>wt-1…wt-6</b> cuando
        corran — un cuadro por cada CC en su worktree, en vivo.
      </p>
    </div>

    <!-- Supervisor arriba, conectado por líneas a cada terminal (#430): el flujo se anima
         SOLO hacia las que trabajan. El mapeo es 1:1 y en el mismo orden de la rejilla.
         Escritorio del supervisor (#475): revisa la cola y dos listas — lo recién resuelto
         y lo que ya está listo para que un terminal lo tome. -->
    <div v-if="sesiones.length" class="tt-sup" :class="{ 'tt-sup-active': anyActive }">
      <div class="tt-sup-desk">
        <div class="tt-sup-node">
          <span class="tt-avatar tt-av-sup">
            <img v-if="supervisor && supervisor.avatar_url" class="tt-avatar-img" :src="supervisor.avatar_url" :alt="(supervisor && supervisor.nombre) || 'Supervisor'" loading="lazy" />
            <span v-else class="tt-avatar-img tt-avatar-initials" :style="initialsStyle((supervisor && supervisor.nombre) || 'Supervisor')">{{ initials((supervisor && supervisor.nombre) || 'Supervisor') }}</span>
            <span class="tt-desk-ico" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
            <label v-if="puedeEditarAvatar" class="tt-avatar-cam" :class="{ 'tt-avatar-cam-busy': uploadingAvatar === 'supervisor' }" title="Cambiar foto" @click.stop>
              <i class="bi" :class="uploadingAvatar === 'supervisor' ? 'bi-arrow-repeat tt-spin' : 'bi-camera-fill'"></i>
              <input type="file" accept="image/jpeg,image/png,image/webp" class="tt-avatar-input" @click.stop @change="onAvatarFile($event, 'supervisor')" />
            </label>
          </span>
          <div class="tt-idblock">
            <span class="tt-name">{{ (supervisor && supervisor.nombre) || 'Supervisor' }}</span>
            <span class="tt-worker-sm">
              <a v-if="itemEnCurso" :href="'/roadmap/item/' + itemEnCurso.id" class="tt-sup-cur-link" target="_blank" rel="noopener">revisando item #{{ itemEnCurso.id }}</a>
              <template v-else-if="itemEnCursoEstado === 'cola_vacia'">cola vacía</template>
              <template v-else>sin item en curso</template>
            </span>
            <span v-if="itemEnCurso" class="tt-sup-cur-title" :title="itemEnCurso.title">{{ itemEnCurso.title }}</span>
          </div>
        </div>

        <div class="tt-sup-lists">
          <div class="tt-sup-list">
            <span class="tt-sup-list-h"><i class="bi bi-check2-circle"></i> Recién resueltos</span>
            <ul v-if="recienResueltos.length" class="tt-sup-list-ul">
              <li v-for="r in recienResueltos" :key="'rr' + r.id" :title="r.title">
                <b class="tt-sup-list-num">#{{ r.id }}</b>
                <span class="tt-sup-list-txt">{{ r.title }}</span>
              </li>
            </ul>
            <p v-else class="tt-sup-list-empty">Nada resuelto todavía</p>
          </div>
          <div class="tt-sup-list">
            <span class="tt-sup-list-h"><i class="bi bi-inbox"></i> Listos para terminal</span>
            <ul v-if="listosParaTerminal.length" class="tt-sup-list-ul">
              <li v-for="r in listosParaTerminal" :key="'lp' + r.id" :title="r.title">
                <b class="tt-sup-list-num">#{{ r.id }}</b>
                <span class="tt-sup-list-txt">{{ r.title }}</span>
              </li>
            </ul>
            <p v-else class="tt-sup-list-empty">Cola vacía</p>
          </div>
        </div>
      </div>

      <div class="tt-sup-links">
        <span v-for="s in sesiones" :key="s.sid" class="tt-link" :class="linkClass(s)" :title="(s.nombre || s.sid) + (s.running && !s.stale ? ' · recibiendo trabajo' : ' · en reposo')">
          <span class="tt-link-line"></span>
          <span class="tt-link-tip">{{ s.nombre || s.sid }}</span>
        </span>
      </div>
    </div>

    <!-- Rejilla responsiva: 1 = ancho completo · 2-4 = grid · N = scroll -->
    <div v-if="sesiones.length" class="tt-grid" :class="{ 'tt-grid-solo': sesiones.length === 1 }">
      <div v-for="s in sesiones" :key="s.sid" class="tt-term" :class="{ 'tt-stale': s.stale, 'tt-off': !s.running, 'tt-idle': s.idle, 'tt-orphan': s.reclamo_huerfano }">
        <div class="tt-term-head">
          <!-- Avatar de la persona (por slot wt-K) + animación enganchada al estado live -->
          <span class="tt-avatar" :class="avatarClass(s)">
            <img v-if="s.avatar_url" class="tt-avatar-img" :src="s.avatar_url" :alt="s.nombre || s.sid" loading="lazy" />
            <span v-else class="tt-avatar-img tt-avatar-initials" :style="initialsStyle(s.nombre || s.sid)">{{ initials(s.nombre || s.sid) }}</span>
            <span class="tt-avatar-ring" aria-hidden="true"></span>
            <span class="tt-avatar-dot" aria-hidden="true"></span>
            <span v-if="gestureIcon(s)" class="tt-avatar-gesture" :class="gestureClass(s)" aria-hidden="true">
              <i :class="gestureIcon(s)"></i>
            </span>
            <label v-if="puedeEditarAvatar" class="tt-avatar-cam" :class="{ 'tt-avatar-cam-busy': uploadingAvatar === s.sid }" title="Cambiar foto" @click.stop>
              <i class="bi" :class="uploadingAvatar === s.sid ? 'bi-arrow-repeat tt-spin' : 'bi-camera-fill'"></i>
              <input type="file" accept="image/jpeg,image/png,image/webp" class="tt-avatar-input" @click.stop @change="onAvatarFile($event, s.sid)" />
            </label>
          </span>
          <span class="tt-idblock">
            <span class="tt-name">{{ s.nombre || s.sid }}</span>
            <span class="tt-worker-sm" title="Worker del equipo (firma auditable)">{{ s.sid }}</span>
          </span>
          <span class="tt-state" :class="s.idle ? 'tt-s-idle' : (s.running ? (s.stale ? 'tt-s-stale' : 'tt-s-run') : 'tt-s-off')">
            <span v-if="s.running && !s.stale" class="tt-dot"></span>{{ workerStateText(s) }}
          </span>
          <span class="tt-term-item">
            <template v-if="s.item"><b class="tt-idnum">#{{ s.item.id }}</b> {{ s.item.title || '(sin título)' }}</template>
            <template v-else-if="s.idle"><i class="tt-muted">slot libre</i></template>
            <template v-else><i class="tt-muted">triaje / sin item fijo</i></template>
          </span>
          <span v-if="!s.idle" class="tt-term-clock" :class="roundClockClass(s)" :title="roundClockTooltip(s)">⏱ {{ fmtClock(secsSince(s.started_at)) }}<span class="tt-clock-limit" v-if="s.running"> / {{ fmtClock(vueltaLimiteSeg) }}</span><span v-if="s.running" class="tt-beat" :class="{ 'tt-beat-cold': s.stale }"> · ♥ {{ secsSince(s.heartbeat_at) }}s</span></span>
          <button v-if="!s.idle" class="tt-fs-btn" title="Pantalla completa" @click="openFs(s.sid)">⤢</button>
        </div>

        <!-- #889: reclamo huérfano — item ya `status=done` que sigue reteniendo esta terminal
             sin más trabajo pendiente (esperando la resolución de Irving). Solo libera el
             `worker_sid`; el estado_aprobacion del item queda intacto. -->
        <div v-if="s.reclamo_huerfano" class="tt-orphan-row">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span class="tt-orphan-label">terminó el #{{ s.reclamo_huerfano.item_id }} · esperando tu resolución</span>
          <select
            v-if="terminalesLibres.length"
            v-model="reasignarDestino[s.reclamo_huerfano.item_id]"
            class="tt-orphan-select"
            :disabled="reasignando === s.reclamo_huerfano.item_id"
          >
            <option value="">Reasignar a…</option>
            <option v-for="t in terminalesLibres" :key="t.sid" :value="t.sid">{{ t.nombre }}</option>
          </select>
          <button
            v-if="terminalesLibres.length"
            class="tt-orphan-btn"
            :disabled="!reasignarDestino[s.reclamo_huerfano.item_id] || reasignando === s.reclamo_huerfano.item_id"
            @click="reasignarReclamo(s.reclamo_huerfano.item_id)"
          >{{ reasignando === s.reclamo_huerfano.item_id ? "Moviendo…" : "Reasignar" }}</button>
          <button
            class="tt-orphan-btn"
            :disabled="liberando === s.reclamo_huerfano.item_id"
            @click="liberarReclamo(s.reclamo_huerfano.item_id)"
          >{{ liberando === s.reclamo_huerfano.item_id ? "Liberando…" : "Liberar reclamo" }}</button>
        </div>

        <!-- Stepper compacto de fases (de #349). #938 criterio 6: sin dato de fase real, NO se
             pinta nada de progreso — ni siquiera los puntos huecos (eso ya sería "inventar"). -->
        <div v-if="hasFaseData(s)" class="tt-steps">
          <span v-for="f in FASES" :key="f.key" class="tt-step" :class="stepClass(s, f.key)" :title="f.label">
            {{ stepReached(s, f.key) ? '●' : '○' }}<span class="tt-step-lbl">{{ f.label }}</span>
          </span>
        </div>
        <div v-else-if="!s.idle" class="tt-steps tt-steps-empty">sin dato de fase todavía — solo el reloj</div>

        <!-- #546: reloj en regresión del ETA del item en curso (no desaparece al llegar a 0). -->
        <div v-if="etaVisible(s)" class="tt-eta-row" :class="etaClass(s)" :title="etaTooltip(s)">
          <i class="bi" :class="etaIcon(s)"></i>
          <span class="tt-eta-label">{{ etaLabel(s) }}</span>
          <span class="tt-eta-bar"><span class="tt-eta-fill" :style="{ width: etaPct(s) + '%' }"></span></span>
        </div>

        <!-- Terminal cruda. #938 criterio 3: el poll ya NO fuerza el scroll al final si el usuario
             se desplazó hacia arriba a leer historial; #938 criterio 4: el indicador de flujo dice
             la verdad (en vivo mientras llegan renglones reales, sin señal en cuanto se cortan). -->
        <div class="tt-console-wrap">
          <span v-if="consoleLive(s)" class="tt-live-pill" :class="consoleLive(s).ok ? 'tt-live-ok' : 'tt-live-bad'">
            <span v-if="consoleLive(s).ok" class="tt-live-dot"></span>{{ consoleLive(s).ok ? '●' : '⚠' }}
            {{ consoleLive(s).ok ? `en vivo · hace ${consoleLive(s).secs} s` : `sin señal desde hace ${fmtClock(consoleLive(s).secs)}` }}
          </span>
          <pre :ref="el => setPre(s.sid, el)" class="tt-pre" @scroll="onConsoleScroll(s.sid)">{{ s.log_tail || (s.idle ? 'esperando trabajo… el supervisor le asignará el próximo item de la cola.' : 'Sin salida todavía…') }}</pre>
          <button v-if="scrolledUp[s.sid]" class="tt-tofinal" @click="goToBottom(s.sid)">▼ ir al final</button>
        </div>
      </div>
    </div>

    <!-- Overlay pantalla completa -->
    <div v-if="fsSesion" class="tt-fs" @click.self="closeFs">
      <div class="tt-fs-card">
        <div class="tt-fs-head">
          <span class="tt-state" :class="fsSesion.running ? (fsSesion.stale ? 'tt-s-stale' : 'tt-s-run') : 'tt-s-off'">
            <span v-if="fsSesion.running && !fsSesion.stale" class="tt-dot"></span>{{ workerStateText(fsSesion) }}
          </span>
          <span class="tt-term-item">
            <template v-if="fsSesion.item"><b class="tt-idnum">#{{ fsSesion.item.id }}</b> {{ fsSesion.item.title || '(sin título)' }}</template>
            <template v-else><i class="tt-muted">triaje / sin item fijo</i></template>
          </span>
          <span class="tt-term-clock" :class="roundClockClass(fsSesion)" :title="roundClockTooltip(fsSesion)">⏱ {{ fmtClock(secsSince(fsSesion.started_at)) }}<span class="tt-clock-limit" v-if="fsSesion.running"> / {{ fmtClock(vueltaLimiteSeg) }}</span><span v-if="fsSesion.running" class="tt-beat"> · ♥ {{ secsSince(fsSesion.heartbeat_at) }}s</span></span>
          <button class="tt-fs-btn" title="Cerrar (Esc)" @click="closeFs">✕</button>
        </div>
        <div v-if="hasFaseData(fsSesion)" class="tt-steps">
          <span v-for="f in FASES" :key="f.key" class="tt-step" :class="stepClass(fsSesion, f.key)">
            {{ stepReached(fsSesion, f.key) ? '●' : '○' }}<span class="tt-step-lbl">{{ f.label }}</span>
          </span>
        </div>
        <div v-else-if="!fsSesion.idle" class="tt-steps tt-steps-empty">sin dato de fase todavía — solo el reloj</div>
        <div v-if="etaVisible(fsSesion)" class="tt-eta-row" :class="etaClass(fsSesion)" :title="etaTooltip(fsSesion)">
          <i class="bi" :class="etaIcon(fsSesion)"></i>
          <span class="tt-eta-label">{{ etaLabel(fsSesion) }}</span>
          <span class="tt-eta-bar"><span class="tt-eta-fill" :style="{ width: etaPct(fsSesion) + '%' }"></span></span>
        </div>
        <pre ref="fsPre" class="tt-pre tt-pre-fs">{{ fsSesion.log_tail || 'Sin salida todavía…' }}</pre>
      </div>
    </div>

    <!-- #854: error de subida de avatar (validación server-side: tipo/tamaño real) -->
    <transition name="tt-toast-fade">
      <div v-if="avatarError.visible" class="tt-avatar-toast"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ avatarError.message }}</div>
    </transition>

    <!-- #889: aviso del resultado de "Liberar reclamo" (éxito o error) -->
    <transition name="tt-toast-fade">
      <div v-if="accionAviso.visible" class="tt-op-toast" :class="{ 'tt-op-toast-ok': accionAviso.ok }">
        <i class="bi" :class="accionAviso.ok ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'"></i>
        {{ accionAviso.message }}
      </div>
    </transition>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, nextTick } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";

const FASES = [
    { key: "triage",      label: "Triage" },
    { key: "decision",    label: "Decisión" },
    { key: "rama",        label: "Rama" },
    { key: "editando",    label: "Editando" },
    { key: "verificando", label: "Verificando" },
    { key: "integrando",  label: "Integrando" },
];
const POLL_MS = 3000;
const STRETCH_MS = 2600;   // #475: cuánto dura el gesto de "estirarse" al terminar

export default {
    name: "TorreTerminales",
    setup() {
        const sesiones = ref([]);
        const supervisor = ref(null);   // #430: nodo supervisor (data.supervisor del feed estado)
        const recienResueltos = ref([]);      // #475: escritorio del supervisor — lista 1
        const listosParaTerminal = ref([]);    // #475: escritorio del supervisor — lista 2
        const nowMs = ref(Date.now());
        const fsSid = ref(null);
        const pres = {};        // sid -> <pre> (rejilla)
        const fsPre = ref(null);
        const prevRunning = {};   // #475: sid -> running anterior, para detectar "terminó ahora"
        const stretchUntil = {};  // #475: sid -> ms hasta el que se muestra el gesto de estirarse
        let pollTimer = null, tickTimer = null;

        // #938: límite real de una vuelta (config circuito.vuelta_timeout_seg, mismo payload del
        // poll) — el reloj de cada terminal se pinta CONTRA este número real, nunca inventado.
        const vueltaLimiteSeg = ref(600);

        // #938 criterio 3: el poll ya no debe saltar al final si el usuario se desplazó a leer
        // historial. sid -> true mientras el usuario está lejos del final de esa consola.
        const scrolledUp = ref({});
        const SCROLL_BOTTOM_PX = 24;

        // #938 criterio 4 / regla 2: el indicador de flujo se basa en si LLEGARON renglones nuevos
        // de verdad (no solo el heartbeat de la sesión). sid -> último log_tail visto / cuándo cambió.
        const lastLogTail = {};
        const lastLogChangeAt = {};
        const LIVE_SIN_SENAL_SEG = 20;   // sin renglones nuevos por más de esto → "sin señal"

        const anyRunning = computed(() => sesiones.value.some((s) => s.running));
        const fsSesion = computed(() => sesiones.value.find((s) => s.sid === fsSid.value) || null);

        // #854: item que el supervisor analiza AHORA (mismo payload del poll de 3s, sin llamada nueva).
        const itemEnCursoEstado = computed(() => (supervisor.value && supervisor.value.item_en_curso && supervisor.value.item_en_curso.estado) || 'cola_vacia');
        const itemEnCurso = computed(() => {
            const d = supervisor.value && supervisor.value.item_en_curso;
            return d && d.estado === 'revisando' && d.id ? d : null;
        });

        const secsSince = (iso) => (iso ? Math.max(0, Math.round((nowMs.value - new Date(iso).getTime()) / 1000)) : 0);
        const fmtClock = (secs) => {
            if (secs == null) return "—";
            if (secs < 60) return `${secs}s`;
            const m = Math.floor(secs / 60), s = secs % 60;
            if (m < 60) return `${m}m ${String(s).padStart(2, "0")}s`;
            return `${Math.floor(m / 60)}h ${String(m % 60).padStart(2, "0")}m`;
        };

        // #938 criterio 5: reloj de la RONDA (transcurrido desde started_at) contra el límite real
        // de la vuelta — ámbar al 80%, rojo al 95%. Distinto del ETA del item (arriba): ese es el
        // presupuesto estimado de la tarea; este es el timeout duro de `vuelta.sh`.
        const roundClockRatio = (s) => (s && s.running && vueltaLimiteSeg.value > 0) ? (secsSince(s.started_at) / vueltaLimiteSeg.value) : 0;
        const roundClockClass = (s) => {
            const r = roundClockRatio(s);
            if (r >= 0.95) return "tt-clock-red";
            if (r >= 0.80) return "tt-clock-amber";
            return "";
        };
        const roundClockTooltip = (s) => (s && s.running)
            ? `${fmtClock(secsSince(s.started_at))} de ${fmtClock(vueltaLimiteSeg.value)} · límite real de la vuelta`
            : "";

        // #546: reloj en regresión del ETA — reusa el mismo tickTimer/nowMs global (sin timers nuevos).
        // restante_segundos llega del server en cada poll (re-sincroniza sin deriva); entre polls se
        // baja localmente restando lo transcurrido desde trabajo_iniciado_at.
        const etaVisible = (s) => !!(s && !s.idle && s.item && s.eta_segundos != null && s.trabajo_iniciado_at);
        const etaRestante = (s) => {
            if (!etaVisible(s)) return null;
            return s.eta_segundos - secsSince(s.trabajo_iniciado_at);
        };
        const etaClass = (s) => {
            const r = etaRestante(s);
            if (r == null) return "";
            if (r < 0) return "tt-eta-over";
            return r / s.eta_segundos < 0.2 ? "tt-eta-warn" : "tt-eta-ok";
        };
        const etaIcon = (s) => {
            const r = etaRestante(s);
            if (r == null) return "bi-hourglass";
            return r < 0 ? "bi-exclamation-triangle-fill" : "bi-hourglass-split";
        };
        const etaLabel = (s) => {
            const r = etaRestante(s);
            if (r == null) return "";
            return r < 0 ? `excedido +${fmtClock(-r)}` : `quedan ${fmtClock(r)}`;
        };
        const etaPct = (s) => {
            if (!etaVisible(s) || s.eta_segundos <= 0) return 100;
            const transcurrido = secsSince(s.trabajo_iniciado_at);
            return Math.max(0, Math.min(100, (transcurrido / s.eta_segundos) * 100));
        };
        const etaTooltip = (s) => {
            const metodo = s.eta_metodo === "historico"
                ? "estimado por histórico de tareas similares"
                : "estimado por tabla de tiempos por nivel de riesgo (sin histórico suficiente)";
            return `ETA: ${fmtClock(s.eta_segundos)} · ${metodo}`;
        };

        const reachedSet = (s) => new Set((s.pasos || []).map((p) => p.fase));
        const stepReached = (s, key) => reachedSet(s).has(key);
        const stepClass = (s, key) => {
            if (s.fase_actual === key) return "tt-step-cur";
            return stepReached(s, key) ? "tt-step-done" : "tt-step-pend";
        };
        // #938 criterio 6: la barra de fases NO se pinta sin dato real — ni siquiera vacía/hueca.
        const hasFaseData = (s) => !!(s && s.pasos && s.pasos.length);

        // #854: texto de estado de una terminal trabajadora — misma lógica que el "revisando item
        // #N" del supervisor, para que las dos tarjetas no digan lo mismo de dos maneras distintas.
        const workerStateText = (s) => {
            if (s.idle) return "esperando trabajo";
            if (!s.running) return "terminada";
            if (s.stale) return "latido frío";
            return s.item ? `trabajando item #${s.item.id}` : "corriendo";
        };

        // Estado visual del avatar (engancha la animación a los flags live existentes, sin tocar lógica).
        const avatarClass = (s) => {
            if (isStretching(s.sid)) return "tt-av-stretch";   // #475: gesto de cansancio al terminar
            if (s.idle) return "tt-av-idle";
            if (!s.running) return "tt-av-off";
            return s.stale ? "tt-av-stale" : "tt-av-run";
        };

        // #854: sin foto (avatar_url null) → iniciales sobre color derivado del nombre. Nunca imagen
        // rota ni hueco vacío. Ejemplo del item: "Maya" → "M".
        const initials = (name) => (String(name || "?").trim().charAt(0) || "?").toUpperCase();
        const initialsStyle = (name) => {
            let h = 0;
            const str = String(name || "?");
            for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) >>> 0;
            return { background: `hsl(${h % 360}, 55%, 40%)`, color: "#fff" };
        };

        // #854: subida de avatar — el nombre de archivo lo genera el servidor (uuid), así que cada
        // reemplazo produce una URL nueva y el cambio se ve sin recargar duro (sin necesitar ?v=).
        const puedeEditarAvatar = ref(false);
        const uploadingAvatar = ref(null);
        const avatarError = ref({ visible: false, message: "" });
        let avatarErrorTimer = null;
        const showAvatarError = (message) => {
            avatarError.value = { visible: true, message };
            if (avatarErrorTimer) clearTimeout(avatarErrorTimer);
            avatarErrorTimer = setTimeout(() => { avatarError.value.visible = false; }, 4500);
        };
        async function onAvatarFile(e, sid) {
            const file = e.target.files && e.target.files[0];
            e.target.value = "";   // permite volver a elegir el mismo archivo si se rechaza
            if (!file) return;
            const fd = new FormData();
            fd.append("sid", sid);
            fd.append("avatar", file);
            uploadingAvatar.value = sid;
            try {
                const { data } = await axios.post("/api/roadmap/circuito/worker-avatar", fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });
                if (sid === "supervisor") {
                    if (supervisor.value) supervisor.value = { ...supervisor.value, avatar_url: data.avatar_url };
                } else {
                    const s = sesiones.value.find((x) => x.sid === sid);
                    if (s) s.avatar_url = data.avatar_url;
                }
            } catch (err) {
                const resp = err.response && err.response.data;
                showAvatarError((resp && (resp.error || resp.message)) || "No se pudo subir la imagen.");
            } finally {
                uploadingAvatar.value = null;
            }
        }

        // #889: liberar un reclamo huérfano — solo suelta el `worker_sid` de la terminal; el
        // estado_aprobacion del item queda intacto (lo confirma el mensaje que regresa el server).
        const liberando = ref(null);
        const accionAviso = ref({ visible: false, message: "", ok: true });
        let accionAvisoTimer = null;
        const showAccionAviso = (message, ok) => {
            accionAviso.value = { visible: true, message, ok };
            if (accionAvisoTimer) clearTimeout(accionAvisoTimer);
            accionAvisoTimer = setTimeout(() => { accionAviso.value.visible = false; }, 5000);
        };
        async function liberarReclamo(itemId) {
            liberando.value = itemId;
            try {
                const { data } = await axios.post(`/api/roadmap/items/${itemId}/liberar-reclamo`);
                showAccionAviso(data.mensaje || "Reclamo liberado.", true);
                await poll();   // refleja la terminal libre de inmediato, sin esperar el próximo tick
            } catch (err) {
                const resp = err.response && err.response.data;
                showAccionAviso((resp && (resp.message || resp.mensaje)) || "No se pudo liberar el reclamo.", false);
            } finally {
                liberando.value = null;
            }
        }

        // #972: reasignar un reclamo huérfano a OTRA terminal libre (idle=true) — hermano de
        // "Liberar reclamo". `terminalesLibres` sale del mismo poll de 3s, sin llamada aparte.
        const reasignando = ref(null);
        const reasignarDestino = ref({});
        const terminalesLibres = computed(() =>
            sesiones.value.filter((s) => s.idle).map((s) => ({ sid: s.sid, nombre: s.nombre || s.sid }))
        );
        async function reasignarReclamo(itemId) {
            const destino = reasignarDestino.value[itemId];
            if (!destino) return;
            if (!window.confirm(`¿Mover el reclamo del #${itemId} a la terminal ${destino}?`)) return;
            reasignando.value = itemId;
            try {
                const { data } = await axios.post(`/api/roadmap/items/${itemId}/reasignar-reclamo`, { sid_destino: destino });
                showAccionAviso(data.mensaje || "Reclamo reasignado.", true);
                reasignarDestino.value[itemId] = "";
                await poll();
            } catch (err) {
                const resp = err.response && err.response.data;
                showAccionAviso((resp && (resp.message || resp.mensaje)) || "No se pudo reasignar el reclamo.", false);
            } finally {
                reasignando.value = null;
            }
        }

        // Línea supervisor→terminal: flujo animado SOLO hacia las que trabajan (running y no frías).
        const linkClass = (s) => (s.running && !s.stale ? "tt-link-active" : (s.stale ? "tt-link-stale" : "tt-link-idle"));
        const anyActive = computed(() => sesiones.value.some((s) => s.running && !s.stale));

        // #475: gesto sobre el avatar — "concentrado viendo la computadora" mientras trabaja,
        // y un estiramiento breve de cansancio justo al terminar (transición running→terminado).
        const isStretching = (sid) => !!stretchUntil[sid] && nowMs.value < stretchUntil[sid];
        const gestureIcon = (s) => {
            if (isStretching(s.sid)) return "bi bi-arrows-angle-expand";
            if (!s.idle && s.running && !s.stale) return "bi bi-display";
            return null;
        };
        const gestureClass = (s) => (isStretching(s.sid) ? "tt-gesture-stretch" : "tt-gesture-work");

        const setPre = (sid, el) => { if (el) pres[sid] = el; };
        // #938 criterio 3: solo sigue el final la consola que YA estaba en el final — si el usuario
        // se desplazó hacia arriba (scrolledUp[sid]), el poll deja de moverle la vista.
        const scrollAll = () => {
            Object.entries(pres).forEach(([sid, el]) => {
                if (el && !scrolledUp.value[sid]) el.scrollTop = el.scrollHeight;
            });
            if (fsPre.value) fsPre.value.scrollTop = fsPre.value.scrollHeight;
        };
        const onConsoleScroll = (sid) => {
            const el = pres[sid];
            if (!el) return;
            scrolledUp.value = { ...scrolledUp.value, [sid]: (el.scrollHeight - el.scrollTop - el.clientHeight) >= SCROLL_BOTTOM_PX };
        };
        const goToBottom = (sid) => {
            const el = pres[sid];
            if (!el) return;
            el.scrollTop = el.scrollHeight;
            scrolledUp.value = { ...scrolledUp.value, [sid]: false };
        };

        // #938 criterio 4 / regla 2: "en vivo" mientras LLEGAN renglones reales; "sin señal" en
        // cuanto se cortan. null = no aplica (idle / sesión terminada, sin pill que mostrar).
        const consoleLive = (s) => {
            if (!s || s.idle || !s.running) return null;
            const changedAt = lastLogChangeAt[s.sid];
            if (!changedAt) return { ok: true, secs: 0 };
            const secs = Math.max(0, Math.round((nowMs.value - changedAt) / 1000));
            return { ok: secs <= LIVE_SIN_SENAL_SEG, secs };
        };

        async function poll() {
            try {
                const { data } = await axios.get("/api/roadmap/circuito/estado");
                const nuevas = (data.trabajando && data.trabajando.sesiones) || [];
                vueltaLimiteSeg.value = Number(data.vuelta_limite_segundos) || 600;   // #938 criterio 5
                // #475: detecta running→terminado por sesión para disparar el gesto de "estirarse".
                // #938 criterio 4: detecta si REALMENTE llegó contenido nuevo de consola (no solo late).
                nuevas.forEach((s) => {
                    if (prevRunning[s.sid] === true && !s.running) {
                        stretchUntil[s.sid] = Date.now() + STRETCH_MS;
                    }
                    prevRunning[s.sid] = !!s.running;
                    if (!s.running) {
                        delete lastLogChangeAt[s.sid];
                        delete lastLogTail[s.sid];
                        return;
                    }
                    if (lastLogTail[s.sid] === undefined || lastLogTail[s.sid] !== s.log_tail) {
                        lastLogChangeAt[s.sid] = Date.now();
                    }
                    lastLogTail[s.sid] = s.log_tail;
                });
                sesiones.value = nuevas;
                supervisor.value = data.supervisor || null;
                recienResueltos.value = (data.supervisor && data.supervisor.recien_resueltos) || [];
                listosParaTerminal.value = (data.supervisor && data.supervisor.listos_para_terminal) || [];
                puedeEditarAvatar.value = !!data.puede_editar_avatar;   // #854: mismo payload del poll
                nextTick(scrollAll);
            } catch (e) { /* silencioso: no romper la vista por un poll */ }
        }

        const openFs = (sid) => { fsSid.value = sid; nextTick(scrollAll); };
        const closeFs = () => { fsSid.value = null; };
        const onKey = (e) => { if (e.key === "Escape") closeFs(); };

        onMounted(() => {
            poll();
            pollTimer = setInterval(poll, POLL_MS);
            tickTimer = setInterval(() => { nowMs.value = Date.now(); }, 1000);
            window.addEventListener("keydown", onKey);
        });
        onUnmounted(() => {
            if (pollTimer) clearInterval(pollTimer);
            if (tickTimer) clearInterval(tickTimer);
            window.removeEventListener("keydown", onKey);
        });

        return {
            FASES, POLL_MS, dark: darkMode,
            sesiones, supervisor, recienResueltos, listosParaTerminal, anyRunning, anyActive, fsSesion, fsPre,
            itemEnCurso, itemEnCursoEstado,
            secsSince, fmtClock, stepReached, stepClass, hasFaseData, setPre, workerStateText,
            avatarClass, gestureIcon, gestureClass, linkClass,
            initials, initialsStyle, puedeEditarAvatar, uploadingAvatar, avatarError, onAvatarFile,
            openFs, closeFs,
            etaVisible, etaClass, etaIcon, etaLabel, etaPct, etaTooltip,
            liberando, accionAviso, liberarReclamo,
            reasignando, reasignarDestino, terminalesLibres, reasignarReclamo,
            vueltaLimiteSeg, roundClockClass, roundClockTooltip,
            scrolledUp, onConsoleScroll, goToBottom, consoleLive,
        };
    },
};
</script>

<style scoped>
.tt-wrap{
  --tt-bg:#f8fafc; --tt-surface:#fff; --tt-ink:#0f172a; --tt-muted:#64748b; --tt-line:#e5e7eb;
  --tt-accent:#0d9488; --tt-live:#10b981; --tt-warn:#d97706; --tt-danger:#dc2626;
  --tt-termbg:#0a1120; --tt-termink:#b8c4d8; --tt-termgreen:#4ade80;
  color:var(--tt-ink);
}
.tt-wrap.tt-dark{
  --tt-bg:#0b1220; --tt-surface:#0f172a; --tt-ink:#e2e8f0; --tt-muted:#94a3b8; --tt-line:#1e293b;
  --tt-accent:#2dd4bf; --tt-live:#34d399; --tt-warn:#fbbf24; --tt-danger:#f87171;
}

.tt-bar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:8px; }
.tt-bar-title{ font-weight:700; font-size:15px; }
.tt-bar-meta{ font-size:12px; color:var(--tt-muted); display:inline-flex; align-items:center; gap:8px; }
.tt-bar-live{ color:var(--tt-live); font-weight:700; display:inline-flex; align-items:center; gap:5px; }

.tt-dot{ width:8px;height:8px;border-radius:50%;background:var(--tt-live);display:inline-block;animation:tt-pulse 1.3s infinite; }
@keyframes tt-pulse{ 0%{box-shadow:0 0 0 0 rgba(16,185,129,.5)} 70%{box-shadow:0 0 0 6px rgba(16,185,129,0)} 100%{box-shadow:0 0 0 0 rgba(16,185,129,0)} }

.tt-empty{ text-align:center; padding:46px 20px; border:1px dashed var(--tt-line); border-radius:14px; background:var(--tt-surface); }
.tt-empty-ico{ font-size:34px; color:var(--tt-muted); }
.tt-empty-h{ font-weight:700; margin:10px 0 4px; }
.tt-empty-p{ color:var(--tt-muted); font-size:13px; max-width:560px; margin:0 auto; line-height:1.55; }

/* ── Supervisor + líneas de conexión (#430) ── */
.tt-sup{ margin-bottom:6px; }
.tt-sup-desk{ display:flex; align-items:stretch; gap:14px; flex-wrap:wrap; margin-bottom:8px; }
.tt-sup-node{ display:inline-flex; align-items:center; gap:11px; padding:8px 14px 8px 8px; border:1px solid var(--tt-line); border-radius:14px; background:var(--tt-surface); }
.tt-av-sup .tt-avatar-img{ width:48px; height:48px; border-radius:12px; }
/* "Escritorio" del supervisor (#475): icono de portapapeles fijo sobre su avatar */
.tt-desk-ico{
  position:absolute; left:-5px; bottom:-5px; width:19px; height:19px; border-radius:50%;
  background:var(--tt-surface); border:1px solid var(--tt-line); color:var(--tt-accent);
  display:flex; align-items:center; justify-content:center; font-size:10.5px;
}

/* ── Listas del escritorio: recién resueltos / listos para terminal (#475) ──
   #854 — diagnóstico: no había recorte de glifos (no hay line-height chico ni height fijo con
   overflow:hidden). La causa real era truncamiento agresivo a UNA sola línea (white-space:nowrap +
   ellipsis) sin separación vertical entre renglones (gap:3px) — con textos largos se leía "pisado".
   Fix: clamp de 2 líneas (en vez de 1), más aire por renglón y columna de número de ancho fijo. */
.tt-sup-lists{ display:flex; gap:10px; flex:1 1 420px; min-width:260px; }
.tt-sup-list{ flex:1 1 0; min-width:0; border:1px solid var(--tt-line); border-radius:12px; background:var(--tt-surface); padding:12px 10px; }
.tt-sup-list-h{ display:block; font-size:11px; font-weight:800; color:var(--tt-muted); margin-bottom:5px; }
.tt-sup-list-ul{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px; max-height:150px; overflow:auto; }
.tt-sup-list-ul li{
  display:flex; align-items:baseline; gap:6px;
  font-size:11.5px; line-height:1.5; color:var(--tt-ink);
  letter-spacing:.01em; word-break:normal; overflow-wrap:anywhere;
}
.tt-sup-list-num{ flex:0 0 auto; min-width:34px; color:var(--tt-accent); font-weight:800; }
.tt-sup-list-txt{
  flex:1 1 auto; min-width:0; display:-webkit-box; -webkit-box-orient:vertical;
  -webkit-line-clamp:2; overflow:hidden;
}
.tt-sup-list-empty{ margin:0; font-size:11.5px; color:var(--tt-muted); font-style:italic; }

.tt-sup-links{ display:flex; gap:10px; padding:0 6px; }
.tt-link{ flex:1 1 0; min-width:0; display:flex; flex-direction:column; align-items:center; gap:2px; }
.tt-link-line{
  width:2px; height:26px; border-radius:2px;
  background:linear-gradient(var(--tt-line), var(--tt-line));
}
.tt-link-tip{ font-size:10px; font-weight:700; color:var(--tt-muted); max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* ACTIVA: línea verde con flujo animado (dash cayendo del supervisor a la terminal) */
.tt-link-active .tt-link-line{
  background:repeating-linear-gradient(180deg, var(--tt-live) 0 6px, transparent 6px 12px);
  background-size:100% 24px;
  animation:tt-flow .7s linear infinite;
}
.tt-link-active .tt-link-tip{ color:var(--tt-live); }
/* STALE: ámbar fijo · IDLE/off: gris tenue */
.tt-link-stale .tt-link-line{ background:var(--tt-warn); opacity:.6; }
.tt-link-idle .tt-link-line{ opacity:.45; }
@keyframes tt-flow{ from{ background-position:0 0; } to{ background-position:0 24px; } }
@media (prefers-reduced-motion: reduce){
  .tt-link-active .tt-link-line{ animation:none !important; background:var(--tt-live); }
}

.tt-grid{ display:grid; gap:14px; grid-template-columns:repeat(auto-fill, minmax(400px, 1fr)); }
.tt-grid-solo{ grid-template-columns:1fr; }

.tt-term{ background:var(--tt-surface); border:1px solid var(--tt-line); border-radius:12px; overflow:hidden; display:flex; flex-direction:column; }
.tt-term.tt-stale{ border-color:var(--tt-warn); }
.tt-term.tt-off{ opacity:.85; }
.tt-term.tt-orphan{ border-color:var(--tt-warn); }

/* #889 — reclamo huérfano: item ya terminado que sigue reservando la terminal */
.tt-orphan-row{
  display:flex; align-items:center; gap:8px; padding:7px 12px;
  border-bottom:1px solid var(--tt-line); background:rgba(217,119,6,.12); color:var(--tt-warn);
  font-size:12px; font-weight:700; flex-wrap:wrap;
}
.tt-orphan-label{ flex:1 1 auto; min-width:0; }
.tt-orphan-select{
  border:1px solid var(--tt-warn); background:var(--tt-surface); color:var(--tt-warn);
  border-radius:7px; padding:3px 6px; font-size:11.5px; font-weight:700; max-width:150px;
}
.tt-orphan-btn{
  border:1px solid var(--tt-warn); background:transparent; color:var(--tt-warn);
  border-radius:7px; padding:3px 10px; font-size:11.5px; font-weight:700; cursor:pointer;
  white-space:nowrap;
}
.tt-orphan-btn:hover:not(:disabled){ background:var(--tt-warn); color:#fff; }
.tt-orphan-btn:disabled{ opacity:.6; cursor:default; }

.tt-term-head{ display:flex; align-items:center; gap:9px; padding:9px 12px; border-bottom:1px solid var(--tt-line); flex-wrap:wrap; }
.tt-state{ font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; display:inline-flex; align-items:center; gap:5px; white-space:nowrap; }
.tt-s-run{ background:rgba(16,185,129,.15); color:var(--tt-live); }
.tt-s-stale{ background:rgba(217,119,6,.15); color:var(--tt-warn); }
.tt-s-off{ background:rgba(100,116,139,.15); color:var(--tt-muted); }
.tt-s-idle{ background:rgba(100,116,139,.12); color:var(--tt-muted); font-style:italic; }
/* ── Avatar de la persona (#430) ── */
.tt-avatar{ position:relative; width:44px; height:44px; flex:0 0 auto; border-radius:11px; overflow:visible; }
.tt-avatar-img{ width:44px; height:44px; border-radius:11px; object-fit:cover; background:#e2e8f0; display:block; border:1px solid var(--tt-line); transition:filter .3s ease; }
.tt-avatar-ring{ position:absolute; inset:-3px; border-radius:14px; pointer-events:none; }
.tt-avatar-dot{ position:absolute; right:-2px; bottom:-2px; width:11px; height:11px; border-radius:50%; background:var(--tt-muted); border:2px solid var(--tt-surface); }
/* #854 — fallback sin foto: iniciales sobre color derivado del nombre (nunca imagen rota/hueco) */
.tt-avatar-initials{ display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px; user-select:none; }
.tt-av-sup .tt-avatar-initials{ font-size:18px; }
/* #854 — control discreto para cambiar la foto: cámara visible solo al pasar el cursor */
.tt-avatar-cam{
  position:absolute; inset:0; border-radius:11px; z-index:3; cursor:pointer;
  display:flex; align-items:center; justify-content:center; font-size:15px; color:#fff;
  background:rgba(15,23,42,.55); opacity:0; transition:opacity .15s ease;
}
.tt-av-sup .tt-avatar-cam{ border-radius:12px; }
.tt-avatar:hover .tt-avatar-cam,.tt-avatar:focus-within .tt-avatar-cam{ opacity:1; }
.tt-avatar-cam-busy{ pointer-events:none; }
.tt-avatar-input{ position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; }
.tt-spin{ animation:tt-spin-anim .8s linear infinite; display:inline-block; }
@keyframes tt-spin-anim{ to{ transform:rotate(360deg); } }
/* #854 — aviso de subida rechazada (tipo/tamaño no válido) */
.tt-avatar-toast{
  position:fixed; top:16px; right:16px; z-index:10600; max-width:360px;
  background:var(--tt-danger); color:#fff; padding:10px 14px; border-radius:10px;
  font-size:13px; font-weight:600; box-shadow:0 8px 20px rgba(0,0,0,.25);
}
.tt-toast-fade-enter-active,.tt-toast-fade-leave-active{ transition:all .2s ease; }
.tt-toast-fade-enter-from,.tt-toast-fade-leave-to{ opacity:0; transform:translateY(-8px); }
/* #889 — aviso de resultado de "Liberar reclamo" (abajo-derecha, no compite con el de avatar) */
.tt-op-toast{
  position:fixed; bottom:16px; right:16px; z-index:10600; max-width:360px;
  background:var(--tt-danger); color:#fff; padding:10px 14px; border-radius:10px;
  font-size:13px; font-weight:600; box-shadow:0 8px 20px rgba(0,0,0,.25);
  display:flex; align-items:center; gap:8px;
}
.tt-op-toast.tt-op-toast-ok{ background:var(--tt-live); }
@media (prefers-reduced-motion: reduce){
  .tt-spin{ animation:none !important; }
}
/* ACTIVO (corriendo): respira + halo verde + indicador parpadeante */
.tt-av-run .tt-avatar-img{ animation:tt-breathe 3.2s ease-in-out infinite; }
.tt-av-run .tt-avatar-ring{ box-shadow:0 0 0 0 rgba(16,185,129,.55); animation:tt-halo 2s ease-out infinite; }
.tt-av-run .tt-avatar-dot{ background:var(--tt-live); animation:tt-blink 1.1s steps(1,end) infinite; }
/* LATIDO FRÍO (stale): halo ámbar lento, sin respirar */
.tt-av-stale .tt-avatar-ring{ box-shadow:0 0 0 2px rgba(217,119,6,.4); }
.tt-av-stale .tt-avatar-dot{ background:var(--tt-warn); }
/* ESPERANDO (idle) / terminada: en reposo, atenuado, sin animación */
.tt-av-idle .tt-avatar-img,.tt-av-off .tt-avatar-img{ filter:grayscale(.7) opacity(.72); }
@keyframes tt-breathe{ 0%,100%{ transform:scale(1); } 50%{ transform:scale(1.05); } }
@keyframes tt-halo{ 0%{ box-shadow:0 0 0 0 rgba(16,185,129,.5); } 70%{ box-shadow:0 0 0 8px rgba(16,185,129,0); } 100%{ box-shadow:0 0 0 0 rgba(16,185,129,0); } }
@keyframes tt-blink{ 0%,60%{ opacity:1; } 61%,100%{ opacity:.25; } }
/* TERMINÓ AHORA (#475): estiramiento breve de cansancio, una sola vez, luego vuelve a "terminada" */
.tt-av-stretch .tt-avatar-img{ animation:tt-body-stretch 2.4s ease-in-out 1; filter:none; }
.tt-av-stretch .tt-avatar-dot{ background:var(--tt-warn); }
@keyframes tt-body-stretch{
  0%{ transform:scale(1) rotate(0deg) translateY(0); }
  25%{ transform:scale(1.07) rotate(-4deg) translateY(-2px); }
  50%{ transform:scale(1.11) rotate(4deg) translateY(-4px); }
  75%{ transform:scale(1.05) rotate(-2deg) translateY(-1px); }
  100%{ transform:scale(1) rotate(0deg) translateY(0); }
}
/* Gesto sobre el avatar (badge chico): "viendo la computadora" mientras trabaja / estiramiento al terminar */
.tt-avatar-gesture{
  position:absolute; left:-5px; top:-5px; width:17px; height:17px; border-radius:50%;
  background:var(--tt-surface); border:1px solid var(--tt-line); color:var(--tt-accent);
  display:flex; align-items:center; justify-content:center; font-size:9.5px; line-height:1;
}
.tt-gesture-work{ animation:tt-screen-glow 2.4s ease-in-out infinite; }
.tt-gesture-stretch{ color:var(--tt-warn); animation:tt-stretch-pop .9s ease-out 2; }
@keyframes tt-screen-glow{
  0%,100%{ opacity:.55; box-shadow:0 0 0 0 rgba(13,148,136,0); }
  50%{ opacity:1; box-shadow:0 0 6px 1px rgba(13,148,136,.45); }
}
@keyframes tt-stretch-pop{
  0%{ transform:scale(.6) rotate(-12deg); opacity:0; }
  40%{ transform:scale(1.25) rotate(8deg); opacity:1; }
  100%{ transform:scale(1) rotate(0deg); opacity:.9; }
}
/* Respeto a quien pide menos movimiento: apaga TODA animación de las terminales */
@media (prefers-reduced-motion: reduce){
  .tt-av-run .tt-avatar-img,.tt-av-run .tt-avatar-ring,.tt-av-run .tt-avatar-dot,.tt-dot{ animation:none !important; }
  .tt-av-run .tt-avatar-ring{ box-shadow:0 0 0 2px rgba(16,185,129,.5); }   /* halo fijo, sin pulso */
  .tt-av-stretch .tt-avatar-img{ animation:none !important; }
  .tt-gesture-work,.tt-gesture-stretch{ animation:none !important; }
}

/* Bloque nombre + wt-K de referencia chica (el nombre reemplaza al wt-K como título) */
.tt-idblock{ display:flex; flex-direction:column; line-height:1.15; min-width:0; }
.tt-name{ font-weight:800; font-size:14px; color:var(--tt-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tt-worker-sm{ font-size:10px; font-weight:700; letter-spacing:.02em; color:var(--tt-accent); font-variant-numeric:tabular-nums; }
/* #854 — item en curso del supervisor: número enlazado + título atenuado debajo, en una línea */
.tt-sup-cur-link{ color:var(--tt-accent); text-decoration:none; }
.tt-sup-cur-link:hover{ text-decoration:underline; }
.tt-sup-cur-title{
  display:block; margin-top:2px; font-size:10.5px; font-weight:500; color:var(--tt-muted);
  max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
/* Firma del worker (wt-K) — chip auditable, prueba de "son 6 reales" (#334 A) */
.tt-worker{ font-size:11px; font-weight:800; letter-spacing:.02em; padding:2px 8px; border-radius:7px; background:rgba(13,148,136,.14); color:var(--tt-accent); font-variant-numeric:tabular-nums; white-space:nowrap; }
.tt-term.tt-idle{ opacity:.62; border-style:dashed; }
.tt-term-item{ font-size:13px; font-weight:600; flex:1 1 160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tt-idnum{ color:var(--tt-accent); }
.tt-muted{ color:var(--tt-muted); font-weight:400; }
.tt-term-clock{ font-size:11.5px; color:var(--tt-muted); font-variant-numeric:tabular-nums; white-space:nowrap; }
/* #938 criterio 5 — reloj de la ronda contra el límite real: ámbar al 80%, rojo al 95% */
.tt-term-clock.tt-clock-amber{ color:var(--tt-warn); font-weight:800; }
.tt-term-clock.tt-clock-red{ color:var(--tt-danger); font-weight:800; }
.tt-clock-limit{ color:var(--tt-muted); font-weight:400; }
.tt-beat{ color:var(--tt-live); } .tt-beat-cold{ color:var(--tt-warn); }
.tt-fs-btn{ border:1px solid var(--tt-line); background:transparent; color:var(--tt-muted); border-radius:7px; width:26px; height:26px; cursor:pointer; line-height:1; font-size:14px; }
.tt-fs-btn:hover{ color:var(--tt-ink); border-color:var(--tt-accent); }

.tt-steps{ display:flex; flex-wrap:wrap; gap:4px 12px; padding:7px 12px; border-bottom:1px solid var(--tt-line); }
.tt-step{ font-size:10.5px; color:var(--tt-muted); display:inline-flex; align-items:center; gap:3px; }
.tt-step-lbl{ font-weight:600; }
.tt-step-done{ color:var(--tt-accent); }
.tt-step-cur{ color:var(--tt-live); font-weight:800; }
.tt-step-pend{ opacity:.55; }
/* #938 criterio 6 — sin dato de fase real: solo un texto discreto, nunca una barra inventada */
.tt-steps-empty{ font-size:10.5px; color:var(--tt-muted); font-style:italic; padding:7px 12px; border-bottom:1px solid var(--tt-line); }

/* #546 — reloj en regresión del ETA del item en curso */
.tt-eta-row{ display:flex; align-items:center; gap:7px; padding:6px 12px; border-bottom:1px solid var(--tt-line); font-size:11.5px; font-weight:700; font-variant-numeric:tabular-nums; }
.tt-eta-label{ white-space:nowrap; }
.tt-eta-bar{ flex:1 1 auto; height:5px; border-radius:999px; background:var(--tt-line); overflow:hidden; }
.tt-eta-fill{ display:block; height:100%; border-radius:999px; background:currentColor; transition:width 1s linear; }
.tt-eta-ok{ color:var(--tt-live); }
.tt-eta-warn{ color:var(--tt-warn); }
.tt-eta-over{ color:var(--tt-danger); }
.tt-eta-over .tt-eta-fill{ background:var(--tt-danger); }
@media (prefers-reduced-motion: reduce){
  .tt-eta-fill{ transition:none !important; }
}

.tt-pre{
  margin:0; padding:11px 13px; background:var(--tt-termbg); color:var(--tt-termink);
  font-family:ui-monospace,"SF Mono",Menlo,Consolas,monospace; font-size:11.5px; line-height:1.5;
  height:260px; overflow:auto; white-space:pre-wrap; word-break:break-word; flex:1 1 auto;
}

/* #938 criterios 3 y 4 — envoltura de la consola: pill de flujo (en vivo/sin señal) + botón
   flotante "ir al final" cuando el usuario se desplazó a leer historial. */
.tt-console-wrap{ position:relative; display:flex; flex:1 1 auto; min-height:0; }
.tt-live-pill{
  position:absolute; top:8px; right:10px; z-index:2; padding:2px 8px; border-radius:999px;
  font-size:10px; font-weight:800; background:rgba(0,0,0,.35); color:var(--tt-termgreen);
  display:inline-flex; align-items:center; gap:4px; pointer-events:none;
}
.tt-live-pill.tt-live-bad{ color:#fca5a5; }
.tt-live-dot{ width:6px; height:6px; border-radius:50%; background:var(--tt-termgreen); display:inline-block; animation:tt-pulse 1.3s infinite; }
.tt-tofinal{
  position:absolute; left:50%; bottom:10px; transform:translateX(-50%); z-index:2;
  background:var(--tt-accent); color:#fff; border:none; border-radius:999px;
  padding:4px 12px; font-size:11px; font-weight:800; cursor:pointer; box-shadow:0 2px 8px rgba(0,0,0,.25);
}
.tt-tofinal:hover{ filter:brightness(.95); }
@media (prefers-reduced-motion: reduce){
  .tt-live-dot{ animation:none !important; }
}

/* Overlay pantalla completa */
.tt-fs{ position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:10500; display:flex; align-items:center; justify-content:center; padding:24px; }
.tt-fs-card{ background:var(--tt-surface); border:1px solid var(--tt-line); border-radius:14px; width:min(1200px,96vw); height:min(88vh,900px); display:flex; flex-direction:column; overflow:hidden; }
.tt-fs-head{ display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid var(--tt-line); flex-wrap:wrap; }
.tt-pre-fs{ height:auto; flex:1 1 auto; font-size:12.5px; }
</style>
