<template>
  <div class="av-wrap" :class="{ 'av-dark': darkMode }">
    <div class="av-head">
      <h2 class="av-h2">Armar versión · Previsualización (Sub-item A)</h2>
      <button class="av-refresh" :disabled="loading" @click="load">↻ Actualizar</button>
    </div>

    <p class="av-hint">
      Aquí se previsualiza lo que ya marcaste con 🏷 <b>«Marcar para versión»</b> en la pestaña
      <b>Integración</b>, agrupado por versión objetivo. Esta vista es <b>solo lectura</b>: todavía
      no compone ni etiqueta nada — eso llega en las siguientes fases (orquestación de merge a
      <code>release/vX.Y</code> y su integración con el módulo Releases/tag).
    </p>

    <div v-if="loading" class="av-meta">Cargando…</div>
    <div v-else-if="!total" class="av-meta">
      Nada marcado todavía. Ve a <b>Integración</b> y usa 🏷 «Marcar para versión» en las ramas ya mergeadas que quieras incluir.
    </div>

    <div v-for="(items, version) in grupos" :key="version" class="av-grupo">
      <div class="av-grupo-head">
        <span class="av-grupo-ver">{{ version }}</span>
        <span class="av-grupo-count">{{ items.length }} item(s)</span>
      </div>
      <div v-for="r in items" :key="r.id" class="av-card">
        <div class="av-card-head">
          <span class="av-tag" :class="lvClass(r.nivel_riesgo)">{{ r.nivel_riesgo || '—' }}</span>
          <div class="av-titleblock">
            <div class="av-title"><span class="av-idnum">#{{ r.id }}</span> {{ r.title }}</div>
            <div class="av-sub">
              <code>{{ r.branch }}</code>
              <span class="av-commit" :title="r.merge_commit">{{ (r.merge_commit || '').slice(0, 10) }}</span>
              <span v-if="r.worker_sid">· 🛠 {{ r.worker_nombre || r.worker_sid }}</span>
            </div>
          </div>
        </div>
        <div v-if="r.resumen" class="av-resumen">{{ r.resumen }}</div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { darkMode } from '../../../../hook/appConfig.js';

export default {
    name: 'ArmarVersion',
    setup() {
        const loading = ref(true);
        const grupos = ref({});
        const total = ref(0);

        const lvClass = (n) => (n === 'A' ? 'av-lvA' : n === 'B' ? 'av-lvB' : n === 'C' ? 'av-lvC' : 'av-lvNone');

        async function load() {
            loading.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/armar-version');
                grupos.value = data.grupos || {};
                total.value = data.total || 0;
            } finally {
                loading.value = false;
            }
        }

        onMounted(load);

        return { darkMode, loading, grupos, total, load, lvClass };
    },
};
</script>

<style scoped>
.av-wrap{
  --av-surface:#fff; --av-ink:#111827; --av-muted:#6b7280; --av-line:#e5e7eb; --av-accent:#0d9488;
  max-width:1160px;margin:0 auto;color:var(--av-ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
}
.av-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.av-h2{font-size:14px;font-weight:700;margin:0;}
.av-refresh{font-size:12px;font-weight:600;padding:6px 11px;border-radius:8px;border:1px solid var(--av-line);background:#fff;color:var(--av-ink);cursor:pointer;}
.av-hint{font-size:12.5px;color:var(--av-muted);line-height:1.5;margin:0 0 16px;}
.av-hint code{font-size:11.5px;background:#f8fafc;border:1px solid var(--av-line);border-radius:5px;padding:1px 5px;}
.av-meta{font-size:12.5px;color:var(--av-muted);}
.av-grupo{margin-bottom:20px;}
.av-grupo-head{display:flex;align-items:baseline;gap:8px;margin-bottom:8px;}
.av-grupo-ver{font-size:13px;font-weight:700;color:var(--av-accent);}
.av-grupo-count{font-size:11.5px;color:var(--av-muted);}
.av-card{background:var(--av-surface);border:1px solid var(--av-line);border-radius:12px;padding:12px 14px;margin-bottom:10px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
.av-card-head{display:flex;align-items:flex-start;gap:10px;}
.av-tag{flex:0 0 auto;font-size:11px;font-weight:700;padding:2px 7px;border-radius:6px;margin-top:1px;}
.av-lvC{background:#fef2f2;color:#b91c1c;} .av-lvB{background:#fffbeb;color:#b45309;} .av-lvA{background:#ecfdf5;color:#047857;} .av-lvNone{background:#f1f5f9;color:#475569;}
.av-titleblock{min-width:0;}
.av-title{font-size:13.5px;font-weight:600;line-height:1.3;}
.av-idnum{color:var(--av-accent);font-weight:700;}
.av-sub{font-size:11.5px;color:var(--av-muted);margin-top:2px;word-break:break-all;}
.av-sub code{font-size:11px;color:#0369a1;font-weight:600;}
.av-commit{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;color:var(--av-muted);}
.av-resumen{margin:8px 0 0 0;font-size:13px;line-height:1.5;color:var(--av-ink);}

/* Modo oscuro */
.av-dark{
  --av-surface:#151d2e; --av-ink:#e8edf6; --av-muted:#8b97ab; --av-line:#2a3550; --av-accent:#2dd4bf;
}
.av-dark .av-card{box-shadow:0 1px 2px rgba(0,0,0,.35);}
.av-dark .av-refresh{background:#0f172a;color:#e8edf6;border-color:#2a3550;}
.av-dark .av-hint code{background:#0f172a;border-color:#2a3550;}
.av-dark .av-lvA{background:rgba(74,222,128,.15);color:#4ade80;} .av-dark .av-lvB{background:rgba(251,191,36,.15);color:#fbbf24;} .av-dark .av-lvC{background:rgba(248,113,113,.15);color:#f87171;} .av-dark .av-lvNone{background:rgba(148,163,184,.15);color:#94a3b8;}
.av-dark .av-sub code{color:#7dd3fc;}
</style>
