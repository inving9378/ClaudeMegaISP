<template>
  <!-- Visor "Trabajando ahora" (#349): pantalla de trabajo del Circuito CC.
       Prop-driven (sin polling propio): TorreControl le pasa las sesiones ya parseadas.
       Estructura ARRAY lista para N sesiones (worktrees paralelos #334); hoy renderiza 1. -->
  <div v-if="mostrar" class="ta-wrap" :class="{ 'ta-dark': dark }">

    <!-- Una TARJETA por sesión viva (hoy 1; se multiplican solas cuando #334 aterrice) -->
    <div v-for="s in vivas" :key="s.sid" class="ta-card" :class="{ 'ta-stale': s.stale }">
      <div class="ta-head">
        <span class="ta-livedot"></span>
        <span class="ta-title">
          Trabajando ahora<span v-if="vivas.length > 1" class="ta-sid"> · {{ s.sid }}</span>
          <template v-if="s.item">
            <span class="ta-sep">·</span>
            <span class="ta-idnum">#{{ s.item.id }}</span>
            <span class="ta-itemtitle">{{ s.item.title || '(sin título)' }}</span>
          </template>
        </span>
        <span class="ta-clock" :title="'Corriendo hace ' + fmtClock(secsSince(s.started_at))">
          ⏱ {{ fmtClock(secsSince(s.started_at)) }}
          <span class="ta-beat" :class="{ 'ta-beat-cold': s.stale }">· ♥ hace {{ secsSince(s.heartbeat_at) }}s</span>
        </span>
      </div>

      <!-- Stepper de fases: triage → decisión → rama → editando → verificando → integrando -->
      <div class="ta-stepper">
        <template v-for="(f, i) in FASES" :key="f.key">
          <div class="ta-step" :class="stepClass(s, f.key)">
            <span class="ta-step-dot">{{ stepReached(s, f.key) ? '●' : '○' }}</span>
            <span class="ta-step-label">{{ f.label }}</span>
            <span v-if="stepAt(s, f.key)" class="ta-step-at">{{ hhmm(stepAt(s, f.key)) }}</span>
          </div>
          <span v-if="i < FASES.length - 1" class="ta-step-line" :class="{ 'ta-step-line-on': stepIndex(s) > i }"></span>
        </template>
      </div>

      <div v-if="s.stale" class="ta-alert">
        ⚠ El latido se enfrió hace {{ secsSince(s.heartbeat_at) }}s — posible circuito atorado.
      </div>

      <!-- Artefactos best-effort (rama / commits / archivos), si el log los expuso -->
      <div v-if="tieneArtefactos(s)" class="ta-artefactos">
        <span v-if="s.artefactos.rama" class="ta-chip ta-chip-rama" :title="s.artefactos.rama">🌿 {{ s.artefactos.rama }}</span>
        <span v-for="c in (s.artefactos.commits || [])" :key="c" class="ta-chip ta-chip-commit">⎇ {{ c.slice(0, 7) }}</span>
        <span v-for="a in (s.artefactos.archivos || [])" :key="a" class="ta-chip ta-chip-file" :title="a">📄 {{ baseName(a) }}</span>
      </div>

      <!-- Actividad en streaming, PARSEADA en pasos legibles (no el log crudo) -->
      <div v-if="s.pasos && s.pasos.length" class="ta-timeline">
        <div v-for="(p, i) in [...s.pasos].reverse()" :key="i" class="ta-tl-row" :class="{ 'ta-tl-now': i === 0 }">
          <span class="ta-tl-fase">{{ faseLabel(p.fase) }}</span>
          <span v-if="p.item_id" class="ta-tl-item">#{{ p.item_id }}<span v-if="p.title" class="ta-tl-ititle"> {{ p.title }}</span></span>
          <span class="ta-tl-when">{{ p.at ? relShort(p.at) : '' }}</span>
        </div>
      </div>
    </div>

    <!-- Resumen de la última vuelta (CIRCUITO_META) — visible hasta la siguiente (punto 4) -->
    <div v-if="resumen" class="ta-resumen">
      <div class="ta-resumen-head">
        <span class="ta-resumen-tag">✔ Última vuelta</span>
        <span class="ta-resumen-when">{{ resumen.at ? relShort(resumen.at) : '' }}</span>
      </div>
      <div class="ta-resumen-body">
        <span class="ta-badge" :class="resumen.ejecuto ? 'ta-badge-exec' : 'ta-badge-noexec'">
          {{ resumen.ejecuto ? '⚙ ejecutó código' : '👁 solo propuso' }}
        </span>
        <span v-if="resumen.n_propuestas" class="ta-badge ta-badge-soft">{{ resumen.n_propuestas }} propuesta(s)</span>
        <span v-if="resumen.n_decisiones" class="ta-badge ta-badge-soft">{{ resumen.n_decisiones }} decisión(es)</span>
        <span v-for="it in resumen.items_tocados" :key="it.id" class="ta-chip ta-chip-item" :title="it.title || ''">
          #{{ it.id }}<span v-if="it.title" class="ta-chip-ititle"> {{ it.title }}</span>
        </span>
      </div>
      <div v-if="resumen.resumen" class="ta-resumen-text">{{ resumen.resumen }}</div>
    </div>
  </div>
</template>

<script>
import { computed } from "vue";

// Enum canónico de fases (mismo orden que RoadmapCircuitoService::FASES).
const FASES = [
    { key: "triage",      label: "Triage" },
    { key: "decision",    label: "Decisión" },
    { key: "rama",        label: "Rama" },
    { key: "editando",    label: "Editando" },
    { key: "verificando", label: "Verificando" },
    { key: "integrando",  label: "Integrando" },
];

export default {
    name: "TorreTrabajandoAhora",
    props: {
        sesiones: { type: Array, default: () => [] },
        resumen:  { type: Object, default: null },
        nowMs:    { type: Number, default: () => Date.now() },
        pausado:  { type: Boolean, default: false },
        dark:     { type: Boolean, default: false },
    },
    setup(props) {
        // Solo tarjeta viva para sesiones corriendo (o con latido frío). Las terminadas
        // se resumen abajo en "Última vuelta", no como tarjeta redundante.
        const vivas = computed(() => (props.sesiones || []).filter((s) => s && s.running));
        const mostrar = computed(() => vivas.value.length > 0 || !!props.resumen);

        // ── helpers de tiempo (animan entre polls con nowMs) ──
        const secsSince = (iso) => {
            if (!iso) return 0;
            return Math.max(0, Math.round((props.nowMs - new Date(iso).getTime()) / 1000));
        };
        const fmtClock = (secs) => {
            if (secs == null) return "—";
            if (secs < 60) return `${secs}s`;
            const m = Math.floor(secs / 60), s = secs % 60;
            if (m < 60) return `${m}m ${String(s).padStart(2, "0")}s`;
            const h = Math.floor(m / 60);
            return `${h}h ${String(m % 60).padStart(2, "0")}m`;
        };
        const relShort = (iso) => {
            const s = secsSince(iso);
            if (s < 60) return `hace ${s}s`;
            const m = Math.round(s / 60);
            return m < 60 ? `hace ${m} min` : `hace ${Math.round(m / 60)} h`;
        };
        const hhmm = (iso) => {
            if (!iso) return "";
            const d = new Date(iso);
            return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
        };

        // ── stepper ──
        const reachedSet = (s) => new Set((s.pasos || []).map((p) => p.fase));
        const stepIndex = (s) => FASES.findIndex((f) => f.key === s.fase_actual);
        const stepReached = (s, key) => reachedSet(s).has(key);
        const stepAt = (s, key) => {
            const hit = (s.pasos || []).filter((p) => p.fase === key).pop();
            return hit ? hit.at : null;
        };
        const stepClass = (s, key) => {
            if (s.fase_actual === key) return "ta-step-current";
            return stepReached(s, key) ? "ta-step-done" : "ta-step-pending";
        };

        const faseLabel = (key) => (FASES.find((f) => f.key === key) || {}).label || key;
        const baseName = (p) => (p ? String(p).split("/").pop() : p);
        const tieneArtefactos = (s) => {
            const a = s.artefactos || {};
            return !!(a.rama || (a.commits && a.commits.length) || (a.archivos && a.archivos.length));
        };

        return {
            FASES, vivas, mostrar,
            secsSince, fmtClock, relShort, hhmm,
            stepIndex, stepReached, stepAt, stepClass, faseLabel, baseName, tieneArtefactos,
        };
    },
};
</script>

<style scoped>
.ta-wrap{
  --ta-surface:#fff; --ta-ink:#0f172a; --ta-muted:#64748b; --ta-line:#e5e7eb;
  --ta-accent:#0d9488; --ta-live:#10b981; --ta-warn:#d97706; --ta-soft:#f1f5f9;
  margin:12px 0; display:flex; flex-direction:column; gap:12px; color:var(--ta-ink);
}
.ta-wrap.ta-dark{
  --ta-surface:#0f172a; --ta-ink:#e2e8f0; --ta-muted:#94a3b8; --ta-line:#1e293b;
  --ta-accent:#2dd4bf; --ta-live:#34d399; --ta-warn:#fbbf24; --ta-soft:#1e293b;
}

.ta-card{
  background:var(--ta-surface); border:1px solid var(--ta-line);
  border-left:3px solid var(--ta-live); border-radius:12px; padding:13px 15px;
}
.ta-card.ta-stale{ border-left-color:var(--ta-warn); }

.ta-head{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
.ta-livedot{ width:9px;height:9px;border-radius:50%;background:var(--ta-live);flex:0 0 auto;animation:ta-pulse 1.3s infinite; }
@keyframes ta-pulse{ 0%{box-shadow:0 0 0 0 rgba(16,185,129,.5)} 70%{box-shadow:0 0 0 7px rgba(16,185,129,0)} 100%{box-shadow:0 0 0 0 rgba(16,185,129,0)} }
.ta-title{ font-weight:700; font-size:14px; display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap; }
.ta-sid{ color:var(--ta-muted); font-weight:600; }
.ta-sep{ color:var(--ta-muted); }
.ta-idnum{ color:var(--ta-accent); font-weight:800; }
.ta-itemtitle{ font-weight:600; color:var(--ta-ink); }
.ta-clock{ margin-left:auto; font-size:12px; color:var(--ta-muted); font-variant-numeric:tabular-nums; }
.ta-beat{ color:var(--ta-live); }
.ta-beat-cold{ color:var(--ta-warn); }

/* Stepper */
.ta-stepper{ display:flex; align-items:center; margin:13px 0 4px; flex-wrap:wrap; row-gap:8px; }
.ta-step{ display:inline-flex; flex-direction:column; align-items:center; gap:1px; min-width:64px; }
.ta-step-dot{ font-size:13px; line-height:1; }
.ta-step-label{ font-size:11px; font-weight:600; white-space:nowrap; }
.ta-step-at{ font-size:9.5px; color:var(--ta-muted); font-variant-numeric:tabular-nums; }
.ta-step-pending{ color:var(--ta-muted); opacity:.6; }
.ta-step-done{ color:var(--ta-accent); }
.ta-step-current .ta-step-dot,
.ta-step-current .ta-step-label{ color:var(--ta-live); font-weight:800; }
.ta-step-current .ta-step-dot{ animation:ta-pulse 1.3s infinite; border-radius:50%; }
.ta-step-line{ flex:1 1 14px; height:2px; min-width:14px; background:var(--ta-line); margin:0 2px; align-self:flex-start; margin-top:7px; }
.ta-step-line-on{ background:var(--ta-accent); }

.ta-alert{ margin-top:8px; font-size:12px; color:var(--ta-warn); }

/* Artefactos + chips */
.ta-artefactos{ display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
.ta-chip{ font-size:11px; padding:2px 8px; border-radius:999px; border:1px solid var(--ta-line);
  background:var(--ta-soft); color:var(--ta-ink); max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ta-chip-rama{ color:var(--ta-accent); }
.ta-chip-commit{ font-family:ui-monospace,Menlo,Consolas,monospace; }
.ta-chip-ititle,.ta-tl-ititle{ color:var(--ta-muted); font-weight:400; }

/* Timeline legible (pasos parseados, no log crudo) */
.ta-timeline{ margin-top:11px; border-top:1px dashed var(--ta-line); padding-top:9px; display:flex; flex-direction:column; gap:4px; }
.ta-tl-row{ display:flex; align-items:baseline; gap:8px; font-size:12px; }
.ta-tl-now .ta-tl-fase{ color:var(--ta-live); font-weight:800; }
.ta-tl-fase{ font-weight:700; min-width:88px; }
.ta-tl-item{ color:var(--ta-accent); font-weight:700; }
.ta-tl-when{ margin-left:auto; color:var(--ta-muted); font-variant-numeric:tabular-nums; }

/* Resumen última vuelta */
.ta-resumen{ background:var(--ta-soft); border:1px solid var(--ta-line); border-radius:12px; padding:11px 14px; }
.ta-resumen-head{ display:flex; align-items:center; justify-content:space-between; }
.ta-resumen-tag{ font-weight:700; font-size:13px; color:var(--ta-accent); }
.ta-resumen-when{ font-size:11px; color:var(--ta-muted); }
.ta-resumen-body{ display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; align-items:center; }
.ta-badge{ font-size:11px; font-weight:700; padding:2px 9px; border-radius:999px; }
.ta-badge-exec{ background:rgba(16,185,129,.15); color:var(--ta-live); }
.ta-badge-noexec{ background:rgba(100,116,139,.15); color:var(--ta-muted); }
.ta-badge-soft{ background:transparent; border:1px solid var(--ta-line); color:var(--ta-muted); }
.ta-chip-item{ color:var(--ta-accent); }
.ta-resumen-text{ margin-top:8px; font-size:12.5px; color:var(--ta-ink); line-height:1.5; }
</style>
