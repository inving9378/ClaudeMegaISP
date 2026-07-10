<template>
  <div class="ig-wrap" :class="{ 'ig-dark': darkMode }">
    <div class="ig-head">
      <h2 class="ig-h2">Integración · Ramas del circuito</h2>
      <div class="ig-head-r">
        <span class="ig-modo">modo: <b>{{ modoIntegracion }}</b></span>
        <button class="ig-refresh" :disabled="busy === 'modo'" @click="toggleModo">
          ⇄ {{ modoIntegracion === 'auto-merge' ? 'revisar-y-mergear' : 'auto-merge' }}
        </button>
        <button class="ig-refresh" :disabled="loading" @click="load">↻ Actualizar</button>
      </div>
    </div>

    <div v-if="loading" class="ig-meta">Cargando ramas…</div>
    <div v-else-if="!ramas.length" class="ig-meta">No hay ramas del circuito todavía.</div>

    <div v-for="r in ramas" :key="r.id" class="ig-card">
      <!-- Cabecera -->
      <div class="ig-card-head">
        <span class="ig-sem" :class="'ig-sem-' + r.verificacion.estado" :title="r.verificacion.detalle">
          {{ r.verificacion.estado === 'ok' ? '✓' : (r.verificacion.estado === 'fail' ? '✗' : '○') }}
        </span>
        <span class="ig-tag" :class="lvClass(r.nivel_riesgo)">{{ r.nivel_riesgo || '—' }}</span>
        <div class="ig-titleblock">
          <div class="ig-title"><span class="ig-idnum">#{{ r.id }}</span> {{ r.title }}</div>
          <div class="ig-sub">
            <code>{{ r.branch }}</code>
            <span v-if="r.autor"> · {{ r.autor }}</span>
            <span v-if="r.merged" class="ig-merged">● mergeada a dev</span>
            <span v-else class="ig-pending">○ sin mergear</span>
          </div>
        </div>
      </div>

      <div class="ig-detalle">{{ r.verificacion.detalle }}</div>

      <!-- Reporte del ejecutor (texto + voz) -->
      <div v-if="r.reporte" class="ig-reporte">
        <div class="ig-reporte-head">
          <strong>Reporte del ejecutor — qué hace / cómo validar / verificación</strong>
          <button class="ig-voz" @click="leer(r)">{{ hablando === r.id ? '⏹ Detener' : '🔊 Escuchar' }}</button>
        </div>
        <div class="ig-reporte-txt">{{ r.reporte }}</div>
      </div>

      <!-- Qué cambió -->
      <div v-if="r.existe_rama" class="ig-changes">
        <div class="ig-files">
          <strong>{{ r.archivos.length }} archivo(s):</strong>
          <span v-for="(f, fi) in r.archivos" :key="fi" class="ig-file">{{ f }}</span>
          <span v-if="!r.archivos.length" class="ig-meta">sin cambios respecto a main todavía.</span>
        </div>
        <button v-if="r.diff" class="ig-difftoggle" @click="toggle(r.id)">
          {{ open[r.id] ? '▾ Ocultar diff' : '▸ Ver diff' }}
        </button>
        <pre v-if="open[r.id]" class="ig-diff">{{ r.diff }}</pre>
      </div>
      <div v-else class="ig-meta">⚠ la rama no existe localmente.</div>

      <!-- Acciones -->
      <div class="ig-actions">
        <button v-if="!r.merged" class="ig-btn ig-btn-ok" :disabled="busy === r.id" @click="merge(r)">✓ Mergear a dev</button>
        <button v-if="r.merged" class="ig-btn ig-btn-warn" :disabled="busy === r.id" @click="revert(r)">↩ Revertir</button>
        <button v-if="r.merged" class="ig-btn" :class="r.marcado_version ? 'ig-btn-ver-on' : 'ig-btn-ver'" :disabled="busy === r.id" @click="marcarVersion(r)">
          {{ r.marcado_version ? '🏷 Marcada para versión' : '🏷 Marcar para versión' }}
        </button>
        <button class="ig-btn ig-btn-no" :disabled="busy === r.id" @click="rechazar(r)">✕ Rechazar</button>
        <span v-if="busy === r.id" class="ig-meta">Procesando…</span>
        <span v-if="msg[r.id]" class="ig-msg">{{ msg[r.id] }}</span>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { darkMode } from '../../../hook/appConfig.js';

export default {
    name: 'IntegracionRamas',
    setup() {
        const loading = ref(true);
        const ramas = ref([]);
        const open = reactive({});
        const busy = ref(null);
        const msg = reactive({});
        const modoIntegracion = ref('auto-merge');
        const hablando = ref(null);

        const lvClass = (n) => (n === 'A' ? 'ig-lvA' : n === 'B' ? 'ig-lvB' : n === 'C' ? 'ig-lvC' : 'ig-lvNone');
        const toggle = (id) => { open[id] = !open[id]; };

        async function load() {
            loading.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/integracion');
                ramas.value = data.ramas || [];
                modoIntegracion.value = data.modo_integracion || 'auto-merge';
            } finally {
                loading.value = false;
            }
        }

        async function toggleModo() {
            if (busy.value) return;
            busy.value = 'modo';
            try {
                const nuevo = modoIntegracion.value === 'auto-merge' ? 'revisar-y-mergear' : 'auto-merge';
                const { data } = await axios.post('/api/roadmap/integracion/modo', { modo: nuevo });
                modoIntegracion.value = data.modo_integracion;
            } finally {
                busy.value = null;
            }
        }

        function leer(r) {
            const synth = window.speechSynthesis;
            if (!synth) return;
            if (hablando.value === r.id) { synth.cancel(); hablando.value = null; return; }
            synth.cancel();
            const txt = [r.title, r.reporte].filter(Boolean).join('. ');
            const u = new SpeechSynthesisUtterance(txt);
            u.lang = 'es-ES';
            u.onend = () => { hablando.value = null; };
            hablando.value = r.id;
            synth.speak(u);
        }

        async function marcarVersion(r) {
            if (busy.value) return;
            busy.value = r.id;
            msg[r.id] = '';
            try {
                const { data } = await axios.post('/api/roadmap/integracion/marcar-version', { id: r.id });
                r.marcado_version = data.marcado_version;
                msg[r.id] = data.marcado_version ? 'Marcada para versión ✓' : 'Desmarcada';
            } finally {
                busy.value = null;
            }
        }

        async function merge(r) {
            if (busy.value) return;
            if (!window.confirm(`¿Mergear la rama de #${r.id} a dev (main)?`)) return;
            busy.value = r.id;
            msg[r.id] = '';
            try {
                const { data } = await axios.post('/api/roadmap/integracion/merge', { id: r.id });
                msg[r.id] = data.ok ? 'Mergeada ✓' : ('Falló: ' + (data.salida || '').slice(0, 120));
                await load();
            } finally {
                busy.value = null;
            }
        }

        async function rechazar(r) {
            if (busy.value) return;
            const comentario = window.prompt('Comentario de rechazo (vuelve a la bandeja de decisiones):', '');
            if (comentario === null) return;
            busy.value = r.id;
            msg[r.id] = '';
            try {
                await axios.post('/api/roadmap/integracion/rechazar', { id: r.id, comentario });
                msg[r.id] = 'Rechazada — volvió a la bandeja.';
                await load();
            } finally {
                busy.value = null;
            }
        }

        async function revert(r) {
            if (busy.value) return;
            if (!window.confirm(`¿Revertir el merge de #${r.id} en dev?`)) return;
            busy.value = r.id;
            msg[r.id] = '';
            try {
                const { data } = await axios.post('/api/roadmap/integracion/revert', { id: r.id });
                msg[r.id] = data.ok ? 'Revertida ✓' : 'No se pudo revertir.';
                await load();
            } finally {
                busy.value = null;
            }
        }

        onMounted(load);

        return {
            darkMode, loading, ramas, open, busy, msg, lvClass, toggle, load, merge, rechazar, revert,
            modoIntegracion, hablando, toggleModo, leer, marcarVersion,
        };
    },
};
</script>

<style scoped>
.ig-wrap{
  --ig-surface:#fff; --ig-ink:#111827; --ig-muted:#6b7280; --ig-line:#e5e7eb;
  --ig-ok:#16a34a; --ig-warn:#d97706; --ig-bad:#dc2626; --ig-accent:#0d9488;
  max-width:1160px;margin:0 auto;color:var(--ig-ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
}
.ig-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.ig-h2{font-size:14px;font-weight:700;margin:0;}
.ig-refresh{font-size:12px;font-weight:600;padding:6px 11px;border-radius:8px;border:1px solid var(--ig-line);background:#fff;color:var(--ig-ink);cursor:pointer;}
.ig-meta{font-size:12.5px;color:var(--ig-muted);}
.ig-card{background:var(--ig-surface);border:1px solid var(--ig-line);border-radius:12px;padding:14px 16px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
.ig-card-head{display:flex;align-items:flex-start;gap:10px;}
.ig-sem{flex:0 0 auto;width:22px;height:22px;border-radius:6px;display:grid;place-items:center;font-weight:700;font-size:13px;}
.ig-sem-ok{background:#ecfdf5;color:#047857;} .ig-sem-fail{background:#fef2f2;color:#b91c1c;} .ig-sem-pending{background:#f1f5f9;color:#64748b;}
.ig-tag{flex:0 0 auto;font-size:11px;font-weight:700;padding:2px 7px;border-radius:6px;margin-top:1px;}
.ig-lvC{background:#fef2f2;color:#b91c1c;} .ig-lvB{background:#fffbeb;color:#b45309;} .ig-lvA{background:#ecfdf5;color:#047857;} .ig-lvNone{background:#f1f5f9;color:#475569;}
.ig-titleblock{min-width:0;}
.ig-title{font-size:13.5px;font-weight:600;line-height:1.3;}
.ig-idnum{color:var(--ig-accent);font-weight:700;}
.ig-sub{font-size:11.5px;color:var(--ig-muted);margin-top:2px;word-break:break-all;}
.ig-sub code{font-size:11px;color:#0369a1;font-weight:600;}
.ig-merged{color:#047857;font-weight:600;} .ig-pending{color:#b45309;font-weight:600;}
.ig-detalle{font-size:12px;color:var(--ig-muted);margin:8px 0 0 32px;}
.ig-changes{margin:10px 0 0 32px;}
.ig-files{font-size:12px;display:flex;flex-wrap:wrap;gap:6px;align-items:baseline;}
.ig-file{background:#f8fafc;border:1px solid var(--ig-line);border-radius:5px;padding:1px 6px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;}
.ig-difftoggle{margin-top:8px;font-size:12px;font-weight:600;background:none;border:none;color:var(--ig-accent);cursor:pointer;padding:0;}
.ig-diff{margin-top:8px;max-height:340px;overflow:auto;background:#0b1220;color:#cbd5e1;border-radius:8px;padding:10px 12px;font-size:11.5px;line-height:1.4;white-space:pre;}
.ig-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:12px 0 0 32px;}
.ig-btn{font-size:12px;font-weight:600;padding:6px 12px;border-radius:8px;border:1px solid transparent;cursor:pointer;}
.ig-btn:disabled{opacity:.6;cursor:default;}
.ig-btn-ok{background:#ecfdf5;color:#047857;border-color:#bbf7d0;}
.ig-btn-no{background:#fef2f2;color:#b91c1c;border-color:#fecaca;}
.ig-btn-warn{background:#fffbeb;color:#b45309;border-color:#fde68a;}
.ig-msg{font-size:12px;color:var(--ig-accent);font-weight:600;}
.ig-head-r{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.ig-modo{font-size:12px;color:var(--ig-muted);}
.ig-reporte{margin:8px 0 0 32px;padding:9px 11px;border:1px solid var(--ig-line);border-radius:8px;background:#f8fafc;}
.ig-reporte-head{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:11.5px;color:var(--ig-muted);}
.ig-voz{font-size:11.5px;font-weight:600;border:1px solid var(--ig-line);background:#fff;color:var(--ig-accent);border-radius:7px;padding:3px 8px;cursor:pointer;}
.ig-reporte-txt{font-size:12.5px;line-height:1.4;margin-top:5px;white-space:pre-wrap;}
.ig-btn-ver{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
.ig-btn-ver-on{background:#dbeafe;color:#1e40af;border-color:#93c5fd;}

/* ── Modo oscuro (mismo toggle del proyecto) ── */
.ig-dark{
  --ig-surface:#151d2e; --ig-ink:#e8edf6; --ig-muted:#8b97ab; --ig-line:#2a3550;
  --ig-ok:#4ade80; --ig-warn:#fbbf24; --ig-bad:#f87171; --ig-accent:#2dd4bf;
}
.ig-dark .ig-card{box-shadow:0 1px 2px rgba(0,0,0,.35);}
.ig-dark .ig-refresh{background:#0f172a;color:#e8edf6;border-color:#2a3550;}
.ig-dark .ig-sem-ok{background:rgba(74,222,128,.15);color:#4ade80;} .ig-dark .ig-sem-fail{background:rgba(248,113,113,.15);color:#f87171;} .ig-dark .ig-sem-pending{background:rgba(148,163,184,.15);color:#94a3b8;}
.ig-dark .ig-lvA{background:rgba(74,222,128,.15);color:#4ade80;} .ig-dark .ig-lvB{background:rgba(251,191,36,.15);color:#fbbf24;} .ig-dark .ig-lvC{background:rgba(248,113,113,.15);color:#f87171;} .ig-dark .ig-lvNone{background:rgba(148,163,184,.15);color:#94a3b8;}
.ig-dark .ig-merged{color:#4ade80;} .ig-dark .ig-pending{color:#fbbf24;}
.ig-dark .ig-sub code{color:#7dd3fc;}
.ig-dark .ig-file{background:#0f172a;border-color:#2a3550;}
.ig-dark .ig-btn-ok{background:rgba(74,222,128,.14);color:#4ade80;border-color:#2b5b3b;}
.ig-dark .ig-btn-no{background:rgba(248,113,113,.14);color:#f87171;border-color:#5b2b2b;}
.ig-dark .ig-btn-warn{background:rgba(251,191,36,.14);color:#fbbf24;border-color:#5b4a1f;}
.ig-dark .ig-reporte{background:#0f172a;border-color:#2a3550;}
.ig-dark .ig-voz{background:#0f172a;color:#2dd4bf;border-color:#2a3550;}
.ig-dark .ig-btn-ver{background:rgba(96,165,250,.14);color:#60a5fa;border-color:#2b3f5b;}
.ig-dark .ig-btn-ver-on{background:rgba(96,165,250,.24);color:#93c5fd;border-color:#3b5578;}
</style>
