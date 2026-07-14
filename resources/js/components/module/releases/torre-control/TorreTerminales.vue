<template>
  <!-- Rejilla de terminales en vivo (#350): una tarjeta-terminal por sesión de CC.
       Read-only, polling ligero propio. Array-ready-for-N (hoy 1; con #334 se llena sola). -->
  <div class="tt-wrap" :class="{ 'tt-dark': dark }">

    <div class="tt-bar">
      <span class="tt-bar-title"><i class="bi bi-terminal me-1"></i> Terminales en vivo</span>
      <span class="tt-bar-meta">
        {{ sesiones.length }} sesión(es) · actualiza cada {{ Math.round(POLL_MS / 1000) }}s
        <span v-if="anyRunning" class="tt-bar-live"><span class="tt-dot"></span>en vivo</span>
      </span>
    </div>

    <!-- Empty state honesto (dependencia dura de #334) -->
    <div v-if="!sesiones.length" class="tt-empty">
      <i class="bi bi-terminal-x tt-empty-ico"></i>
      <p class="tt-empty-h">Ninguna sesión activa ahora mismo</p>
      <p class="tt-empty-p">
        El circuito corre por vueltas; cuando una arranque verás su terminal aquí.
        La vista de <b>varias terminales a la vez</b> se enciende con la ejecución en
        paralelo (<b>#334</b>): un cuadro por cada CC en su worktree.
      </p>
    </div>

    <!-- Supervisor arriba, conectado por líneas a cada terminal (#430): el flujo se anima
         SOLO hacia las que trabajan. El mapeo es 1:1 y en el mismo orden de la rejilla. -->
    <div v-if="sesiones.length" class="tt-sup" :class="{ 'tt-sup-active': anyActive }">
      <div class="tt-sup-node">
        <span class="tt-avatar tt-av-sup">
          <img class="tt-avatar-img" :src="supervisorUrl" :alt="(supervisor && supervisor.nombre) || 'Supervisor'" loading="lazy" @error="onAvatarError" />
        </span>
        <div class="tt-idblock">
          <span class="tt-name">{{ (supervisor && supervisor.nombre) || 'Supervisor' }}</span>
          <span class="tt-worker-sm">reparte el trabajo</span>
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
      <div v-for="s in sesiones" :key="s.sid" class="tt-term" :class="{ 'tt-stale': s.stale, 'tt-off': !s.running, 'tt-idle': s.idle }">
        <div class="tt-term-head">
          <!-- Avatar de la persona (por slot wt-K) + animación enganchada al estado live -->
          <span class="tt-avatar" :class="avatarClass(s)">
            <img class="tt-avatar-img" :src="avatarUrl(s.sid)" :alt="s.nombre || s.sid" loading="lazy" @error="onAvatarError" />
            <span class="tt-avatar-ring" aria-hidden="true"></span>
            <span class="tt-avatar-dot" aria-hidden="true"></span>
          </span>
          <span class="tt-idblock">
            <span class="tt-name">{{ s.nombre || s.sid }}</span>
            <span class="tt-worker-sm" title="Worker del equipo (firma auditable)">{{ s.sid }}</span>
          </span>
          <span class="tt-state" :class="s.idle ? 'tt-s-idle' : (s.running ? (s.stale ? 'tt-s-stale' : 'tt-s-run') : 'tt-s-off')">
            <span v-if="s.running && !s.stale" class="tt-dot"></span>{{ s.idle ? 'esperando trabajo' : (s.running ? (s.stale ? 'latido frío' : 'corriendo') : 'terminada') }}
          </span>
          <span class="tt-term-item">
            <template v-if="s.item"><b class="tt-idnum">#{{ s.item.id }}</b> {{ s.item.title || '(sin título)' }}</template>
            <template v-else-if="s.idle"><i class="tt-muted">slot libre</i></template>
            <template v-else><i class="tt-muted">triaje / sin item fijo</i></template>
          </span>
          <span v-if="!s.idle" class="tt-term-clock">⏱ {{ fmtClock(secsSince(s.started_at)) }}<span v-if="s.running" class="tt-beat" :class="{ 'tt-beat-cold': s.stale }"> · ♥ {{ secsSince(s.heartbeat_at) }}s</span></span>
          <button v-if="!s.idle" class="tt-fs-btn" title="Pantalla completa" @click="openFs(s.sid)">⤢</button>
        </div>

        <!-- Stepper compacto de fases (de #349) -->
        <div class="tt-steps">
          <span v-for="f in FASES" :key="f.key" class="tt-step" :class="stepClass(s, f.key)" :title="f.label">
            {{ stepReached(s, f.key) ? '●' : '○' }}<span class="tt-step-lbl">{{ f.label }}</span>
          </span>
        </div>

        <!-- Terminal cruda -->
        <pre :ref="el => setPre(s.sid, el)" class="tt-pre">{{ s.log_tail || (s.idle ? 'esperando trabajo… el supervisor le asignará el próximo item de la cola.' : 'Sin salida todavía…') }}</pre>
      </div>
    </div>

    <!-- Overlay pantalla completa -->
    <div v-if="fsSesion" class="tt-fs" @click.self="closeFs">
      <div class="tt-fs-card">
        <div class="tt-fs-head">
          <span class="tt-state" :class="fsSesion.running ? (fsSesion.stale ? 'tt-s-stale' : 'tt-s-run') : 'tt-s-off'">
            <span v-if="fsSesion.running && !fsSesion.stale" class="tt-dot"></span>{{ fsSesion.running ? (fsSesion.stale ? 'latido frío' : 'corriendo') : 'terminada' }}
          </span>
          <span class="tt-term-item">
            <template v-if="fsSesion.item"><b class="tt-idnum">#{{ fsSesion.item.id }}</b> {{ fsSesion.item.title || '(sin título)' }}</template>
            <template v-else><i class="tt-muted">triaje / sin item fijo</i></template>
          </span>
          <span class="tt-term-clock">⏱ {{ fmtClock(secsSince(fsSesion.started_at)) }}<span v-if="fsSesion.running" class="tt-beat"> · ♥ {{ secsSince(fsSesion.heartbeat_at) }}s</span></span>
          <button class="tt-fs-btn" title="Cerrar (Esc)" @click="closeFs">✕</button>
        </div>
        <div class="tt-steps">
          <span v-for="f in FASES" :key="f.key" class="tt-step" :class="stepClass(fsSesion, f.key)">
            {{ stepReached(fsSesion, f.key) ? '●' : '○' }}<span class="tt-step-lbl">{{ f.label }}</span>
          </span>
        </div>
        <pre ref="fsPre" class="tt-pre tt-pre-fs">{{ fsSesion.log_tail || 'Sin salida todavía…' }}</pre>
      </div>
    </div>
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

export default {
    name: "TorreTerminales",
    setup() {
        const sesiones = ref([]);
        const supervisor = ref(null);   // #430: nodo supervisor (data.supervisor del feed estado)
        const nowMs = ref(Date.now());
        const fsSid = ref(null);
        const pres = {};        // sid -> <pre> (rejilla)
        const fsPre = ref(null);
        let pollTimer = null, tickTimer = null;

        const anyRunning = computed(() => sesiones.value.some((s) => s.running));
        const fsSesion = computed(() => sesiones.value.find((s) => s.sid === fsSid.value) || null);

        const secsSince = (iso) => (iso ? Math.max(0, Math.round((nowMs.value - new Date(iso).getTime()) / 1000)) : 0);
        const fmtClock = (secs) => {
            if (secs == null) return "—";
            if (secs < 60) return `${secs}s`;
            const m = Math.floor(secs / 60), s = secs % 60;
            if (m < 60) return `${m}m ${String(s).padStart(2, "0")}s`;
            return `${Math.floor(m / 60)}h ${String(m % 60).padStart(2, "0")}m`;
        };

        const reachedSet = (s) => new Set((s.pasos || []).map((p) => p.fase));
        const stepReached = (s, key) => reachedSet(s).has(key);
        const stepClass = (s, key) => {
            if (s.fase_actual === key) return "tt-step-cur";
            return stepReached(s, key) ? "tt-step-done" : "tt-step-pend";
        };

        // Avatar por SLOT (wt-K); si falta el png real cae al placeholder neutro (no truena).
        const AVATAR_BASE = "/images/circuito/";
        const AVATAR_PLACEHOLDER = AVATAR_BASE + "avatar-placeholder.svg";
        const avatarUrl = (sid) => `${AVATAR_BASE}${sid}.png`;
        const onAvatarError = (e) => {
            if (e.target && e.target.src.indexOf("avatar-placeholder") === -1) {
                e.target.src = AVATAR_PLACEHOLDER;   // swap único → sin bucle
            }
        };
        // Estado visual del avatar (engancha la animación a los flags live existentes, sin tocar lógica).
        const avatarClass = (s) => {
            if (s.idle) return "tt-av-idle";
            if (!s.running) return "tt-av-off";
            return s.stale ? "tt-av-stale" : "tt-av-run";
        };
        const supervisorUrl = AVATAR_BASE + "supervisor.png";
        // Línea supervisor→terminal: flujo animado SOLO hacia las que trabajan (running y no frías).
        const linkClass = (s) => (s.running && !s.stale ? "tt-link-active" : (s.stale ? "tt-link-stale" : "tt-link-idle"));
        const anyActive = computed(() => sesiones.value.some((s) => s.running && !s.stale));

        const setPre = (sid, el) => { if (el) pres[sid] = el; };
        const scrollAll = () => {
            Object.values(pres).forEach((el) => { if (el) el.scrollTop = el.scrollHeight; });
            if (fsPre.value) fsPre.value.scrollTop = fsPre.value.scrollHeight;
        };

        async function poll() {
            try {
                const { data } = await axios.get("/api/roadmap/circuito/estado");
                sesiones.value = (data.trabajando && data.trabajando.sesiones) || [];
                supervisor.value = data.supervisor || null;
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
            sesiones, supervisor, anyRunning, anyActive, fsSesion, fsPre,
            secsSince, fmtClock, stepReached, stepClass, setPre,
            avatarUrl, onAvatarError, avatarClass, supervisorUrl, linkClass,
            openFs, closeFs,
        };
    },
};
</script>

<style scoped>
.tt-wrap{
  --tt-bg:#f8fafc; --tt-surface:#fff; --tt-ink:#0f172a; --tt-muted:#64748b; --tt-line:#e5e7eb;
  --tt-accent:#0d9488; --tt-live:#10b981; --tt-warn:#d97706;
  --tt-termbg:#0a1120; --tt-termink:#b8c4d8; --tt-termgreen:#4ade80;
  color:var(--tt-ink);
}
.tt-wrap.tt-dark{
  --tt-bg:#0b1220; --tt-surface:#0f172a; --tt-ink:#e2e8f0; --tt-muted:#94a3b8; --tt-line:#1e293b;
  --tt-accent:#2dd4bf; --tt-live:#34d399; --tt-warn:#fbbf24;
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
.tt-sup-node{ display:inline-flex; align-items:center; gap:11px; padding:8px 14px 8px 8px; border:1px solid var(--tt-line); border-radius:14px; background:var(--tt-surface); }
.tt-av-sup .tt-avatar-img{ width:48px; height:48px; border-radius:12px; }
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
/* Respeto a quien pide menos movimiento: apaga TODA animación de las terminales */
@media (prefers-reduced-motion: reduce){
  .tt-av-run .tt-avatar-img,.tt-av-run .tt-avatar-ring,.tt-av-run .tt-avatar-dot,.tt-dot{ animation:none !important; }
  .tt-av-run .tt-avatar-ring{ box-shadow:0 0 0 2px rgba(16,185,129,.5); }   /* halo fijo, sin pulso */
}

/* Bloque nombre + wt-K de referencia chica (el nombre reemplaza al wt-K como título) */
.tt-idblock{ display:flex; flex-direction:column; line-height:1.15; min-width:0; }
.tt-name{ font-weight:800; font-size:14px; color:var(--tt-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tt-worker-sm{ font-size:10px; font-weight:700; letter-spacing:.02em; color:var(--tt-accent); font-variant-numeric:tabular-nums; }
/* Firma del worker (wt-K) — chip auditable, prueba de "son 6 reales" (#334 A) */
.tt-worker{ font-size:11px; font-weight:800; letter-spacing:.02em; padding:2px 8px; border-radius:7px; background:rgba(13,148,136,.14); color:var(--tt-accent); font-variant-numeric:tabular-nums; white-space:nowrap; }
.tt-term.tt-idle{ opacity:.62; border-style:dashed; }
.tt-term-item{ font-size:13px; font-weight:600; flex:1 1 160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tt-idnum{ color:var(--tt-accent); }
.tt-muted{ color:var(--tt-muted); font-weight:400; }
.tt-term-clock{ font-size:11.5px; color:var(--tt-muted); font-variant-numeric:tabular-nums; white-space:nowrap; }
.tt-beat{ color:var(--tt-live); } .tt-beat-cold{ color:var(--tt-warn); }
.tt-fs-btn{ border:1px solid var(--tt-line); background:transparent; color:var(--tt-muted); border-radius:7px; width:26px; height:26px; cursor:pointer; line-height:1; font-size:14px; }
.tt-fs-btn:hover{ color:var(--tt-ink); border-color:var(--tt-accent); }

.tt-steps{ display:flex; flex-wrap:wrap; gap:4px 12px; padding:7px 12px; border-bottom:1px solid var(--tt-line); }
.tt-step{ font-size:10.5px; color:var(--tt-muted); display:inline-flex; align-items:center; gap:3px; }
.tt-step-lbl{ font-weight:600; }
.tt-step-done{ color:var(--tt-accent); }
.tt-step-cur{ color:var(--tt-live); font-weight:800; }
.tt-step-pend{ opacity:.55; }

.tt-pre{
  margin:0; padding:11px 13px; background:var(--tt-termbg); color:var(--tt-termink);
  font-family:ui-monospace,"SF Mono",Menlo,Consolas,monospace; font-size:11.5px; line-height:1.5;
  height:260px; overflow:auto; white-space:pre-wrap; word-break:break-word; flex:1 1 auto;
}

/* Overlay pantalla completa */
.tt-fs{ position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:10500; display:flex; align-items:center; justify-content:center; padding:24px; }
.tt-fs-card{ background:var(--tt-surface); border:1px solid var(--tt-line); border-radius:14px; width:min(1200px,96vw); height:min(88vh,900px); display:flex; flex-direction:column; overflow:hidden; }
.tt-fs-head{ display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid var(--tt-line); flex-wrap:wrap; }
.tt-pre-fs{ height:auto; flex:1 1 auto; font-size:12.5px; }
</style>
