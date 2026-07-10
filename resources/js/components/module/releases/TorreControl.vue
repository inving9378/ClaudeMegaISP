<template>
  <div class="tc-wrap">

    <!-- Estado del circuito + kill switch -->
    <div class="tc-statusbar">
      <div class="tc-left">
        <span class="tc-pill" :class="pausado ? 'tc-pause' : 'tc-run'">
          <span v-if="!pausado" class="tc-dotlive"></span>{{ pausado ? 'Circuito en pausa' : 'Circuito activo' }}
        </span>
        <div>
          <h1 class="tc-h1">Circuito CC · Torre de control</h1>
          <div class="tc-meta">{{ total }} items en la Hoja de Ruta<span v-if="generatedAt"> · actualizado {{ rel(generatedAt) }}</span></div>
        </div>
      </div>
      <button class="tc-killbtn" :class="{ 'tc-resume': pausado }" :disabled="toggling" @click="toggle">
        {{ toggling ? '…' : (pausado ? '▶ Reanudar circuito' : '⏸ Pausar circuito') }}
      </button>
    </div>

    <div v-if="loading" class="tc-meta tc-loading">Cargando datos del circuito…</div>

    <template v-else>
      <!-- KPIs por estado -->
      <div class="tc-kpis">
        <div class="tc-kpi"><div class="tc-n">{{ total }}</div><div class="tc-l">Items totales</div><div class="tc-bar" style="background:var(--tc-accent)"></div></div>
        <div class="tc-kpi"><div class="tc-n" style="color:var(--tc-slate)">{{ est('pendiente_revision') }}</div><div class="tc-l">Pendiente revisión</div><div class="tc-bar" style="background:var(--tc-slate)"></div></div>
        <div class="tc-kpi"><div class="tc-n" style="color:var(--tc-warn)">{{ est('requiere_irving') }}</div><div class="tc-l">Requiere Irving</div><div class="tc-bar" style="background:var(--tc-warn)"></div></div>
        <div class="tc-kpi"><div class="tc-n" style="color:var(--tc-info)">{{ est('en_progreso') }}</div><div class="tc-l">En progreso</div><div class="tc-bar" style="background:var(--tc-info)"></div></div>
        <div class="tc-kpi"><div class="tc-n" style="color:var(--tc-ok)">{{ est('completado') }}</div><div class="tc-l">Completado</div><div class="tc-bar" style="background:var(--tc-ok)"></div></div>
      </div>

      <div class="tc-grid">
        <!-- Bandeja: requiere_irving -->
        <div class="tc-card">
          <h2 class="tc-h2">⚑ Tu bandeja — requiere tu decisión ({{ cola.length }})</h2>
          <div v-if="!cola.length" class="tc-meta">Nada requiere tu decisión ahora. ✓</div>
          <div v-for="it in cola" :key="it.id" class="tc-inbox-item">
            <span class="tc-tag" :class="lvClass(it.nivel_riesgo)">{{ it.nivel_riesgo || '—' }}</span>
            <div>
              <div class="tc-t"><span class="tc-idnum">#{{ it.id }}</span> {{ it.title }}</div>
              <div class="tc-s" v-if="it.comentario">{{ it.comentario }}</div>
            </div>
          </div>
        </div>

        <!-- Actividad reciente -->
        <div class="tc-card">
          <h2 class="tc-h2">Actividad reciente del circuito</h2>
          <div v-if="!actividad.length" class="tc-meta">Sin actividad reciente.</div>
          <div class="tc-feed">
            <div v-for="ev in actividad" :key="ev.id" class="tc-ev">
              <span class="tc-icn" :style="{ background: evColor(ev) }">{{ evIcon(ev) }}</span>
              <div>
                <b>#{{ ev.id }} {{ ev.title }}</b><span v-if="ev.comentario"> — {{ ev.comentario }}</span>
                <div class="tc-when">{{ rel(ev.cuando) }}<span v-if="ev.nivel_riesgo"> · nivel {{ ev.nivel_riesgo }}</span><span v-if="ev.aprobado_por"> · {{ ev.aprobado_por }}</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="tc-grid">
        <!-- Items por nivel -->
        <div class="tc-card">
          <h2 class="tc-h2">Items por nivel de riesgo</h2>
          <div class="tc-chartrow">
            <div v-for="col in niveles" :key="col.key" class="tc-col">
              <div class="tc-coln">{{ col.n }}</div>
              <div class="tc-colbar" :style="{ height: barH(col.n) + 'px', background: col.color }"></div>
              <div class="tc-coll">{{ col.label }}</div>
            </div>
          </div>
          <div class="tc-meta" style="margin-top:10px">{{ nivel('sin_clasificar') }} sin clasificar → el circuito los triará (dudosos → requiere_irving).</div>
        </div>

        <!-- Riesgos de la auditoría -->
        <div class="tc-card">
          <h2 class="tc-h2">Riesgos de acoplamiento — auditoría <span v-if="auditItem">#{{ auditItem }}</span></h2>
          <div v-if="!riesgos.length" class="tc-meta">Sin auditoría registrada aún.</div>
          <div v-for="(r, i) in riesgos" :key="i" class="tc-risk">
            <span class="tc-sev" :class="sevClass(r)">{{ sevLabel(r) }}</span>
            <div>{{ riskText(r) }}</div>
          </div>
        </div>
      </div>

      <p class="tc-foot">Torre de control del Circuito · datos en vivo de la Hoja de Ruta (dev).</p>
    </template>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'TorreControl',
    setup() {
        const loading = ref(true);
        const toggling = ref(false);
        const pausado = ref(false);
        const generatedAt = ref(null);
        const resumen = ref({ total: 0, por_estado: {}, por_nivel: {} });
        const cola = ref([]);
        const actividad = ref([]);
        const riesgos = ref([]);
        const auditItem = ref(null);

        const total = computed(() => resumen.value.total || 0);
        const est = (k) => (resumen.value.por_estado && resumen.value.por_estado[k]) || 0;
        const nivel = (k) => (resumen.value.por_nivel && resumen.value.por_nivel[k]) || 0;

        const niveles = computed(() => [
            { key: 'sin_clasificar', label: 'Sin clasificar', n: nivel('sin_clasificar'), color: 'var(--tc-slate)' },
            { key: 'A', label: 'A · seguro',    n: nivel('A'), color: 'var(--tc-ok)' },
            { key: 'B', label: 'B · confirmar', n: nivel('B'), color: 'var(--tc-warn)' },
            { key: 'C', label: 'C · diseño',    n: nivel('C'), color: 'var(--tc-bad)' },
        ]);
        const barMax = computed(() => Math.max(1, ...niveles.value.map((c) => c.n)));
        const barH = (n) => Math.max(6, Math.round((n / barMax.value) * 120));

        const lvClass = (n) => (n === 'A' ? 'tc-lvA' : n === 'B' ? 'tc-lvB' : n === 'C' ? 'tc-lvC' : 'tc-lvNone');

        const sevLabel = (r) => {
            const s = String(r).toUpperCase();
            if (s.includes('ALTO')) return 'ALTO';
            if (s.includes('MEDIO')) return 'MEDIO';
            if (s.includes('LIMITACION') || s.includes('NOTA')) return 'NOTA';
            return 'BAJO';
        };
        const sevClass = (r) => {
            const l = sevLabel(r);
            return l === 'ALTO' ? 'tc-sevA' : l === 'MEDIO' ? 'tc-sevM' : 'tc-sevB';
        };
        const riskText = (r) => String(r).replace(/^R\d+\s*[A-Za-zÁ-ú\-]*\s*-\s*/, '');

        const evIcon = (ev) => {
            if (ev.estado_aprobacion === 'completado') return '✓';
            if (ev.estado_aprobacion === 'en_progreso') return '▶';
            if (ev.estado_aprobacion === 'requiere_irving') return '⚑';
            return '•';
        };
        const evColor = (ev) => {
            if (ev.estado_aprobacion === 'completado') return 'var(--tc-ok)';
            if (ev.estado_aprobacion === 'en_progreso') return 'var(--tc-info)';
            if (ev.estado_aprobacion === 'requiere_irving') return 'var(--tc-warn)';
            return 'var(--tc-accent)';
        };

        const rel = (iso) => {
            if (!iso) return '';
            const diff = Date.now() - new Date(iso).getTime();
            const m = Math.round(diff / 60000);
            if (m < 1) return 'hace segundos';
            if (m < 60) return `hace ${m} min`;
            const h = Math.round(m / 60);
            if (h < 24) return `hace ${h} h`;
            return `hace ${Math.round(h / 24)} d`;
        };

        async function load() {
            loading.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/torre');
                pausado.value = !!data.circuito_pausado;
                generatedAt.value = data.generated_at;
                resumen.value = data.resumen || { total: 0, por_estado: {}, por_nivel: {} };
                cola.value = data.cola_requiere_irving || [];
                actividad.value = data.actividad_reciente || [];
                riesgos.value = data.riesgos_auditoria || [];
                auditItem.value = data.auditoria_item_id || null;
            } finally {
                loading.value = false;
            }
        }

        async function toggle() {
            if (toggling.value) return;
            toggling.value = true;
            try {
                const { data } = await axios.post('/api/roadmap/circuito/toggle');
                pausado.value = !!data.circuito_pausado;
            } finally {
                toggling.value = false;
            }
        }

        onMounted(load);

        return {
            loading, toggling, pausado, generatedAt, total, est, nivel, niveles, barH,
            cola, actividad, riesgos, auditItem, lvClass, sevLabel, sevClass, riskText,
            evIcon, evColor, rel, toggle,
        };
    },
};
</script>

<style scoped>
.tc-wrap{
  --tc-surface:#fff; --tc-ink:#111827; --tc-muted:#6b7280; --tc-line:#e5e7eb;
  --tc-ok:#16a34a; --tc-info:#2563eb; --tc-warn:#d97706; --tc-bad:#dc2626; --tc-slate:#64748b; --tc-accent:#0d9488;
  max-width:1160px;margin:0 auto;color:var(--tc-ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
}
.tc-statusbar{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--tc-surface);border:1px solid var(--tc-line);border-radius:14px;padding:14px 18px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
.tc-left{display:flex;align-items:center;gap:12px;}
.tc-pill{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;padding:5px 11px;border-radius:999px;}
.tc-run{background:#ecfdf5;color:#047857;}
.tc-pause{background:#fef2f2;color:#b91c1c;}
.tc-dotlive{width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 0 3px #10b98133;}
.tc-h1{font-size:16px;margin:0;font-weight:700;}
.tc-meta{font-size:12.5px;color:var(--tc-muted);}
.tc-loading{padding:24px 4px;}
.tc-killbtn{font-size:12.5px;font-weight:600;color:#b91c1c;background:#fff;border:1px solid #fecaca;padding:7px 13px;border-radius:9px;cursor:pointer;}
.tc-killbtn.tc-resume{color:#047857;border-color:#bbf7d0;background:#f0fdf4;}
.tc-killbtn:disabled{opacity:.6;cursor:default;}
.tc-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-top:14px;}
.tc-kpi{background:var(--tc-surface);border:1px solid var(--tc-line);border-radius:12px;padding:13px 15px;}
.tc-n{font-size:26px;font-weight:700;line-height:1.1;}
.tc-l{font-size:12px;color:var(--tc-muted);margin-top:3px;}
.tc-bar{height:3px;border-radius:2px;margin-top:9px;}
.tc-grid{display:grid;grid-template-columns:1.15fr 1fr;gap:14px;margin-top:14px;}
.tc-card{background:var(--tc-surface);border:1px solid var(--tc-line);border-radius:14px;padding:16px 18px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
.tc-h2{font-size:12.5px;letter-spacing:.04em;text-transform:uppercase;color:var(--tc-muted);margin:0 0 12px;font-weight:700;}
.tc-inbox-item{display:flex;gap:11px;align-items:flex-start;padding:10px 0;border-top:1px solid var(--tc-line);}
.tc-inbox-item:first-of-type{border-top:none;}
.tc-tag{flex:0 0 auto;font-size:11px;font-weight:700;padding:2px 7px;border-radius:6px;margin-top:1px;}
.tc-lvC{background:#fef2f2;color:#b91c1c;} .tc-lvB{background:#fffbeb;color:#b45309;} .tc-lvA{background:#ecfdf5;color:#047857;} .tc-lvNone{background:#f1f5f9;color:#475569;}
.tc-t{font-size:13.5px;font-weight:600;line-height:1.3;}
.tc-s{font-size:12px;color:var(--tc-muted);margin-top:2px;}
.tc-idnum{color:var(--tc-accent);font-weight:700;}
.tc-feed{display:flex;flex-direction:column;}
.tc-ev{display:flex;gap:11px;padding:9px 0;border-top:1px solid var(--tc-line);font-size:13px;line-height:1.35;}
.tc-ev:first-child{border-top:none;}
.tc-icn{flex:0 0 auto;width:22px;height:22px;border-radius:6px;display:grid;place-items:center;font-size:12px;color:#fff;font-weight:700;margin-top:1px;}
.tc-when{font-size:11.5px;color:var(--tc-muted);}
.tc-chartrow{display:flex;align-items:flex-end;gap:14px;height:150px;padding:6px 4px 0;}
.tc-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;justify-content:flex-end;}
.tc-colbar{width:100%;max-width:54px;border-radius:5px 5px 3px 3px;}
.tc-coln{font-size:13px;font-weight:700;} .tc-coll{font-size:11.5px;color:var(--tc-muted);text-align:center;}
.tc-risk{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-top:1px solid var(--tc-line);font-size:12.8px;line-height:1.35;}
.tc-risk:first-of-type{border-top:none;}
.tc-sev{flex:0 0 auto;font-size:10.5px;font-weight:700;padding:2px 6px;border-radius:5px;margin-top:1px;}
.tc-sevA{background:#fef2f2;color:#b91c1c;} .tc-sevM{background:#fffbeb;color:#b45309;} .tc-sevB{background:#f1f5f9;color:#475569;}
.tc-foot{font-size:11.5px;color:var(--tc-muted);margin-top:16px;text-align:center;}
@media(max-width:820px){.tc-kpis{grid-template-columns:repeat(2,1fr)}.tc-grid{grid-template-columns:1fr}}
</style>
