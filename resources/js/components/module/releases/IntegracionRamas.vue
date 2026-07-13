<template>
  <div class="ig-wrap" :class="{ 'ig-dark': darkMode }">
    <div class="ig-head">
      <h2 class="ig-h2">Integración · Ramas del circuito</h2>
      <div class="ig-head-r">
        <div class="ig-seg" role="tablist">
          <button class="ig-segbtn" :class="{ 'ig-segbtn-on': vista === 'radar' }" @click="verRadar">
            Para revisar
          </button>
          <button class="ig-segbtn" :class="{ 'ig-segbtn-on': vista === 'historial' }" @click="verHistorial">
            Historial <span v-if="archivadasCount" class="ig-segcount">{{ archivadasCount }}</span>
          </button>
        </div>
        <label v-if="vista === 'radar'" class="ig-toggle" :class="{ 'ig-toggle-on': autoMerge }"
               title="ON: cada rama verificada se mergea sola a dev. OFF: mergeas tú cada una con el botón.">
          <input type="checkbox" :checked="autoMerge" :disabled="busy === 'modo'" @change="toggleAutoMerge" />
          <span class="ig-tk"><span class="ig-th"></span></span>
          <span>Auto-merge a dev: <b>{{ autoMerge ? 'ON' : 'OFF' }}</b></span>
        </label>
        <button v-if="vista === 'radar' && algunoMergeado" class="ig-refresh ig-arch-mass" :disabled="busy === 'masivo'"
                title="Saca del radar TODAS las ramas ya mergeadas. Quedan en el Historial (reversible)."
                @click="archivarMergeados">🗂 Archivar lo mergeado</button>
        <button class="ig-refresh" :disabled="loading" @click="load">↻ Actualizar</button>
      </div>
    </div>

    <p v-if="vista === 'radar'" class="ig-hint-radar">
      Aquí solo lo <b>verificable por interfaz</b> y lo pendiente. Lo backend/interno se verifica solo
      (php&nbsp;-l + arranque + regresión + revisor) y se auto-archiva al Historial.
    </p>

    <div v-if="loading" class="ig-meta">Cargando ramas…</div>
    <div v-else-if="!ramas.length" class="ig-meta">
      {{ vista === 'historial' ? 'El historial está vacío.' : 'Nada pendiente por revisar. 🎉' }}
    </div>

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
            <span v-if="r.worker_sid" class="ig-worker" :title="'Trabajado por ' + (r.worker_nombre || r.worker_sid) + ' (' + r.worker_sid + ')'">🛠 trabajado por {{ r.worker_nombre || r.worker_sid }}</span>
            <span v-if="r.autor"> · {{ r.autor }}</span>
            <span v-if="r.merged" class="ig-merged">● mergeada a dev</span>
            <span v-else class="ig-pending">○ sin mergear</span>
            <span v-if="r.revision_ui === true" class="ig-clz ig-clz-ui" title="Toca la interfaz — verifícalo en el navegador.">👁 Revisar visual</span>
            <span v-else-if="r.revision_ui === false" class="ig-clz ig-clz-bk" title="Backend/interno — verificado por comprobaciones automáticas.">⚙ Backend/interno</span>
          </div>
        </div>
      </div>

      <!-- Nota de revisión visual (QUÉ mirar / QUÉ probar) — solo UI-verificable -->
      <div v-if="r.revision_ui === true && r.ui_hint" class="ig-uihint">
        <strong>👁 Cómo revisarlo:</strong> {{ r.ui_hint }}
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
        <!-- RADAR -->
        <template v-if="vista === 'radar'">
          <button v-if="!r.merged" class="ig-btn ig-btn-ok" :disabled="busy === r.id || r.merge_pending" @click="merge(r)">✓ Mergear a dev</button>
          <span v-if="r.merge_pending" class="ig-pill ig-pill-run">⏳ En cola / procesando…</span>
          <button v-if="r.merged" class="ig-btn ig-btn-warn" :disabled="busy === r.id" @click="revert(r)">↩ Revertir</button>
          <button v-if="r.merged" class="ig-btn" :class="r.marcado_version ? 'ig-btn-ver-on' : 'ig-btn-ver'" :disabled="busy === r.id" @click="marcarVersion(r)">
            {{ r.marcado_version ? '🏷 Marcada para versión' : '🏷 Marcar para versión' }}
          </button>
          <button v-if="r.merged" class="ig-btn ig-btn-arch" :disabled="busy === r.id" title="Sacar del radar → Historial (reversible)." @click="archivar(r)">✓ Archivar</button>
          <button class="ig-btn ig-btn-no" :disabled="busy === r.id" @click="rechazar(r)">✕ Rechazar</button>
        </template>
        <!-- HISTORIAL -->
        <template v-else>
          <span class="ig-meta" v-if="r.archivado_at">Archivada {{ fechaCorta(r.archivado_at) }}<span v-if="r.archivado_por"> · {{ r.archivado_por }}</span></span>
          <button class="ig-btn ig-btn-ver" :disabled="busy === r.id" title="Traer de vuelta al radar (quiero verlo)." @click="desarchivar(r)">↩ Traer al radar</button>
        </template>
        <span v-if="busy === r.id" class="ig-meta">Procesando…</span>
        <span v-if="msg[r.id]" class="ig-msg">{{ msg[r.id] }}</span>
      </div>

      <!-- Resultado del último merge (ÉXITO o ERROR visible — nunca silencioso, #334) -->
      <div v-if="!r.merged && r.merge_result && r.merge_result.ok === false && !r.merge_pending" class="ig-mergeerr">
        <strong>✗ El merge falló{{ r.merge_result.escalado ? ' — escalado a tu bandeja (requiere_irving)' : '' }}.</strong>
        <pre>{{ r.merge_result.salida }}</pre>
      </div>
      <div v-else-if="r.merged && r.merge_result && r.merge_result.ok" class="ig-mergeok">
        ✓ {{ r.merge_result.salida }}
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
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
        const autoMerge = ref(true);
        const hablando = ref(null);
        const vista = ref('radar');          // 'radar' | 'historial'
        const archivadasCount = ref(0);

        const lvClass = (n) => (n === 'A' ? 'ig-lvA' : n === 'B' ? 'ig-lvB' : n === 'C' ? 'ig-lvC' : 'ig-lvNone');
        const toggle = (id) => { open[id] = !open[id]; };
        const algunoMergeado = computed(() => ramas.value.some((r) => r.merged));
        const fechaCorta = (iso) => { try { return new Date(iso).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }); } catch (e) { return ''; } };

        async function load() {
            if (vista.value === 'historial') return loadHistorial();
            loading.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/integracion');
                ramas.value = data.ramas || [];
                modoIntegracion.value = data.modo_integracion || 'auto-merge';
                autoMerge.value = data.auto_merge !== undefined ? !!data.auto_merge : (modoIntegracion.value === 'auto-merge');
                archivadasCount.value = data.archivadas_count || 0;
            } finally {
                loading.value = false;
            }
        }

        async function loadHistorial() {
            loading.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/integracion/historial');
                ramas.value = data.ramas || [];
                archivadasCount.value = ramas.value.length;
            } finally {
                loading.value = false;
            }
        }

        function verRadar() { if (vista.value !== 'radar') { vista.value = 'radar'; load(); } }
        function verHistorial() { if (vista.value !== 'historial') { vista.value = 'historial'; loadHistorial(); } }

        async function archivar(r) {
            if (busy.value) return;
            busy.value = r.id;
            msg[r.id] = '';
            try {
                await axios.post('/api/roadmap/integracion/archivar', { id: r.id });
                await load();
            } catch (e) {
                msg[r.id] = 'Error al archivar: ' + (e.response?.data?.error || e.message || '');
            } finally {
                busy.value = null;
            }
        }

        async function archivarMergeados() {
            if (busy.value) return;
            if (!window.confirm('¿Archivar TODAS las ramas ya mergeadas? Quedan en el Historial (reversible).')) return;
            busy.value = 'masivo';
            try {
                const { data } = await axios.post('/api/roadmap/integracion/archivar', { todos_mergeados: true });
                await load();
                window.alert(`Se archivaron ${data.archivadas || 0} rama(s).`);
            } finally {
                busy.value = null;
            }
        }

        async function desarchivar(r) {
            if (busy.value) return;
            busy.value = r.id;
            try {
                await axios.post('/api/roadmap/integracion/desarchivar', { id: r.id });
                await loadHistorial();
            } finally {
                busy.value = null;
            }
        }

        async function toggleAutoMerge() {
            if (busy.value) return;
            busy.value = 'modo';
            try {
                const nuevo = autoMerge.value ? 'revisar-y-mergear' : 'auto-merge';
                const { data } = await axios.post('/api/roadmap/integracion/modo', { modo: nuevo });
                modoIntegracion.value = data.modo_integracion;
                autoMerge.value = data.modo_integracion === 'auto-merge';
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
                // El merge lo aplica el runner on-box (meganet) en segundos → polleamos el resultado.
                msg[r.id] = data.mensaje || 'Merge encolado…';
                await load();
                await pollMerge(r.id);
            } catch (e) {
                msg[r.id] = 'Error al encolar: ' + (e.response?.data?.error || e.message || '');
            } finally {
                busy.value = null;
            }
        }

        // Sondea hasta que la rama quede mergeada o tenga un resultado de error (escalado). El error
        // se pinta en la tarjeta (ig-mergeerr) — nunca silencioso.
        async function pollMerge(id, tries = 15) {
            for (let i = 0; i < tries; i++) {
                await new Promise((res) => setTimeout(res, 2500));
                await load();
                const r = ramas.value.find((x) => x.id === id);
                if (!r) return;
                if (r.merged) { msg[id] = 'Mergeada a dev ✓'; return; }
                if (r.merge_result && r.merge_result.ok === false && !r.merge_pending) {
                    msg[id] = r.merge_result.escalado ? 'Falló → escalada a tu bandeja (ver detalle).' : 'Falló el merge (ver detalle).';
                    return;
                }
            }
            msg[id] = 'Sigue en proceso… usa ↻ Actualizar para ver el resultado.';
        }

        async function rechazar(r) {
            if (busy.value) return;
            const comentario = window.prompt('Motivo del rechazo (OBLIGATORIO):', '');
            if (comentario === null) return;                       // canceló el prompt
            if (!comentario.trim()) { msg[r.id] = 'El comentario es obligatorio.'; return; }
            // Aceptar = BORRAR (cancela + archiva, fila conservada) · Cancelar = RECICLAR (vuelve al backlog).
            const borrar = window.confirm(
                'Aceptar = BORRAR (se cancela y archiva; la fila se conserva).\n' +
                'Cancelar = RECICLAR (vuelve al backlog para reintentar con tu comentario).'
            );
            const accion = borrar ? 'borrar' : 'reciclar';
            busy.value = r.id;
            msg[r.id] = '';
            try {
                await axios.post('/api/roadmap/integracion/rechazar', { id: r.id, comentario: comentario.trim(), accion });
                msg[r.id] = borrar ? 'Rechazada y archivada (borrada).' : 'Reciclada — volvió al backlog.';
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
            modoIntegracion, autoMerge, hablando, toggleAutoMerge, leer, marcarVersion,
            vista, archivadasCount, algunoMergeado, fechaCorta,
            verRadar, verHistorial, archivar, archivarMergeados, desarchivar,
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

/* Toggle Auto-merge ON/OFF */
.ig-toggle{display:inline-flex;align-items:center;gap:7px;font-size:12px;color:var(--ig-muted);cursor:pointer;user-select:none;}
.ig-toggle input{display:none;}
.ig-tk{width:34px;height:19px;border-radius:999px;background:#cbd5e1;position:relative;transition:background .15s;flex:0 0 auto;}
.ig-th{position:absolute;top:2px;left:2px;width:15px;height:15px;border-radius:50%;background:#fff;transition:left .15s;box-shadow:0 1px 2px rgba(0,0,0,.3);}
.ig-toggle-on .ig-tk{background:var(--ig-ok);}
.ig-toggle-on .ig-th{left:17px;}
.ig-toggle b{color:var(--ig-ink);}

/* Pill "en cola" + cajas de resultado del merge */
.ig-pill{font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:999px;}
.ig-pill-run{background:#fffbeb;color:#b45309;border:1px solid #fde68a;}
.ig-mergeerr{margin:10px 0 0 32px;padding:9px 11px;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#b91c1c;font-size:12px;}
.ig-mergeerr pre{margin:6px 0 0;white-space:pre-wrap;word-break:break-word;font-size:11px;color:#7f1d1d;max-height:180px;overflow:auto;}
.ig-mergeok{margin:10px 0 0 32px;font-size:12px;color:#047857;font-weight:600;}

/* Segmentado Radar / Historial */
.ig-seg{display:inline-flex;border:1px solid var(--ig-line);border-radius:8px;overflow:hidden;}
.ig-segbtn{font-size:12px;font-weight:600;padding:6px 11px;border:none;background:#fff;color:var(--ig-muted);cursor:pointer;}
.ig-segbtn-on{background:var(--ig-accent);color:#fff;}
.ig-segcount{display:inline-block;min-width:16px;text-align:center;background:rgba(0,0,0,.14);border-radius:999px;padding:0 5px;font-size:10.5px;margin-left:4px;}
.ig-arch-mass{border-color:#c7d2fe;color:#4338ca;}
.ig-hint-radar{font-size:11.5px;color:var(--ig-muted);margin:-4px 0 12px;line-height:1.4;}

/* Badge de clasificación + nota de revisión visual */
.ig-clz{margin-left:7px;font-size:10.5px;font-weight:700;padding:1px 7px;border-radius:6px;}
.ig-clz-ui{background:#eff6ff;color:#1d4ed8;}
.ig-clz-bk{background:#f1f5f9;color:#475569;}
.ig-uihint{margin:8px 0 0 32px;padding:8px 11px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:8px;font-size:12px;color:#1e40af;line-height:1.45;}
.ig-btn-arch{background:#eef2ff;color:#4338ca;border-color:#c7d2fe;}
.ig-worker{margin-left:6px;font-size:10.5px;font-weight:700;padding:1px 6px;border-radius:6px;background:rgba(13,148,136,.12);color:var(--ig-accent);}

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
.ig-dark .ig-tk{background:#334155;}
.ig-dark .ig-pill-run{background:rgba(251,191,36,.14);color:#fbbf24;border-color:#5b4a1f;}
.ig-dark .ig-mergeerr{background:rgba(248,113,113,.12);border-color:#5b2b2b;color:#f87171;}
.ig-dark .ig-mergeerr pre{color:#fca5a5;}
.ig-dark .ig-mergeok{color:#4ade80;}
.ig-dark .ig-segbtn{background:#0f172a;color:#8b97ab;}
.ig-dark .ig-segbtn-on{background:var(--ig-accent);color:#04211d;}
.ig-dark .ig-arch-mass{background:#0f172a;color:#a5b4fc;border-color:#3b3f6b;}
.ig-dark .ig-clz-ui{background:rgba(96,165,250,.14);color:#60a5fa;}
.ig-dark .ig-clz-bk{background:rgba(148,163,184,.14);color:#94a3b8;}
.ig-dark .ig-uihint{background:rgba(96,165,250,.10);border-color:#2b3f5b;color:#93c5fd;}
.ig-dark .ig-btn-arch{background:rgba(129,140,248,.14);color:#a5b4fc;border-color:#3b3f6b;}
</style>
