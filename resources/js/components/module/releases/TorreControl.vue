<template>
  <div class="tc-wrap" :class="{ 'tc-dark': darkMode }">

    <!-- Estado del circuito + kill switch -->
    <div class="tc-statusbar">
      <div class="tc-left">
        <span class="tc-pill" :class="pausado ? 'tc-pause' : 'tc-run'">
          <span v-if="!pausado" class="tc-dotlive"></span>{{ pausado ? 'Circuito en pausa' : 'Circuito activo' }}
        </span>
        <div>
          <h1 class="tc-h1">Circuito CC · Torre de control</h1>
          <div class="tc-meta">{{ total }} items · modo <b>{{ modo }}</b><span v-if="generatedAt"> · actualizado {{ rel(generatedAt) }}</span></div>
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
            <div class="tc-inbox-body">
              <div class="tc-t"><span class="tc-idnum">#{{ it.id }}</span> {{ it.title }}</div>
              <div class="tc-s" v-if="it.recomendacion">{{ it.recomendacion }}</div>

              <!-- Opciones (forks) que dejó el decisor -->
              <div v-if="it.opciones && it.opciones.length" class="tc-opts">
                <button
                  v-for="(op, oi) in it.opciones" :key="oi" type="button"
                  class="tc-opt" :class="{ 'tc-opt-sel': sel[it.id] === op }"
                  @click="sel[it.id] = op">{{ op }}</button>
              </div>

              <textarea v-model="coment[it.id]" class="tc-coment" rows="2" placeholder="Comentario (opcional)…"></textarea>

              <div class="tc-actions">
                <button class="tc-btn tc-btn-ok"  :disabled="deciding === it.id" @click="decidir(it, 'aprobar')">✓ Aprobar</button>
                <button class="tc-btn tc-btn-no"  :disabled="deciding === it.id" @click="decidir(it, 'rechazar')">✕ Rechazar</button>
                <button class="tc-btn tc-btn-mut" :disabled="deciding === it.id" @click="decidir(it, 'comentar')">💬 Comentar</button>
                <button class="tc-btn tc-btn-mut" :disabled="deciding === it.id" @click="decidir(it, 'cerrar')">✔ Cerrar</button>
                <button class="tc-btn tc-btn-mut" :disabled="deciding === it.id" @click="decidir(it, 'cancelar')">⊘ Cancelar</button>
                <button class="tc-btn tc-btn-seg" :disabled="deciding === it.id" @click="toggleSeg(it)">＋ Seguimiento</button>
                <span v-if="deciding === it.id" class="tc-meta">Guardando…</span>
              </div>

              <!-- Crear seguimiento vinculado (#320) -->
              <div v-if="segOpen[it.id]" class="tc-seg">
                <input v-model="seg[it.id].titulo" class="tc-seg-in" placeholder="Título del ítem de seguimiento…" />
                <textarea v-model="seg[it.id].descripcion" class="tc-seg-in" rows="2" placeholder="Descripción (prellenada con el contexto)…"></textarea>
                <div class="tc-seg-row">
                  <select v-model="seg[it.id].nivel" class="tc-seg-sel">
                    <option value="">nivel…</option><option>A</option><option>B</option><option>C</option>
                  </select>
                  <label class="tc-seg-chk"><input type="checkbox" v-model="seg[it.id].cerrar"> cerrar #{{ it.id }} al crear</label>
                  <button class="tc-btn tc-btn-ok" :disabled="deciding === it.id || !seg[it.id].titulo" @click="crearSeguimiento(it)">Crear seguimiento</button>
                </div>
              </div>
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

      <!-- Ejecuciones del cron (#319) -->
      <div class="tc-card" style="margin-top:14px">
        <h2 class="tc-h2">Ejecuciones del circuito (cron)</h2>
        <div v-if="!ejecuciones.length" class="tc-meta">Sin ejecuciones registradas todavía.</div>
        <div v-for="e in ejecuciones" :key="e.id" class="tc-ejec">
          <span class="tc-tag" :class="e.pausado ? 'tc-lvC' : (e.ejecuto ? 'tc-lvA' : 'tc-lvNone')">
            {{ e.pausado ? 'pausa' : (e.ejecuto ? 'ejecutó' : 'propuso') }}
          </span>
          <div class="tc-ejec-body">
            <div class="tc-ejec-head">
              <span>{{ rel(e.started_at) }}</span> · <span>{{ e.modo }}</span> · <span>{{ e.duracion_seg }}s</span>
              <span v-if="e.n_propuestas"> · {{ e.n_propuestas }} prop.</span>
              <span v-if="e.n_decisiones"> · {{ e.n_decisiones }} dec.</span>
              <span v-if="e.items_tocados && e.items_tocados.length"> · items {{ e.items_tocados.join(', ') }}</span>
              <span v-if="e.rc" class="tc-ejec-rc"> · rc={{ e.rc }}</span>
            </div>
            <div v-if="e.resumen" class="tc-s">{{ e.resumen }}</div>
          </div>
        </div>
      </div>

      <p class="tc-foot">Torre de control del Circuito · datos en vivo de la Hoja de Ruta (dev).</p>
    </template>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { darkMode } from '../../../hook/appConfig.js';

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
        const ejecuciones = ref([]);
        const modo = ref('aviso_previo');

        // Bandeja de decisiones interactiva (#313)
        const sel = reactive({});      // id -> opción elegida
        const coment = reactive({});   // id -> comentario
        const deciding = ref(null);    // id en proceso
        const segOpen = reactive({});  // id -> form de seguimiento abierto
        const seg = reactive({});      // id -> {titulo, descripcion, nivel, cerrar}

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
                ejecuciones.value = data.ejecuciones || [];
                modo.value = data.circuito_modo || 'aviso_previo';
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

        function toggleSeg(it) {
            if (!seg[it.id]) {
                seg[it.id] = {
                    titulo: '',
                    descripcion: `Origen #${it.id}: ${it.title}\n\n${it.recomendacion || ''}`.trim(),
                    nivel: it.nivel_riesgo || '',
                    cerrar: true,
                };
            }
            segOpen[it.id] = !segOpen[it.id];
        }

        async function crearSeguimiento(it) {
            if (deciding.value || !seg[it.id] || !seg[it.id].titulo) return;
            deciding.value = it.id;
            try {
                const s = seg[it.id];
                await axios.post('/api/roadmap/circuito/seguimiento', {
                    origen_item_id: it.id,
                    titulo: s.titulo,
                    descripcion: s.descripcion || null,
                    nivel_riesgo: s.nivel || null,
                    cerrar_origen: !!s.cerrar,
                    comentario: coment[it.id] || null,
                });
                delete seg[it.id];
                segOpen[it.id] = false;
                await load();
            } finally {
                deciding.value = null;
            }
        }

        async function decidir(it, accion) {
            if (deciding.value) return;
            deciding.value = it.id;
            try {
                await axios.post('/api/roadmap/circuito/decidir', {
                    id: it.id,
                    accion,
                    opcion_elegida: sel[it.id] || null,
                    comentario: coment[it.id] || null,
                });
                delete sel[it.id];
                delete coment[it.id];
                await load(); // refresca bandeja + panorama con el estado ya decidido
            } finally {
                deciding.value = null;
            }
        }

        onMounted(load);

        return {
            loading, toggling, pausado, generatedAt, total, est, nivel, niveles, barH,
            cola, actividad, riesgos, auditItem, lvClass, sevLabel, sevClass, riskText,
            evIcon, evColor, rel, toggle,
            sel, coment, deciding, decidir,
            segOpen, seg, toggleSeg, crearSeguimiento,
            darkMode, ejecuciones, modo,
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
.tc-inbox-body{flex:1;min-width:0;}
.tc-opts{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.tc-opt{font-size:12px;padding:5px 10px;border-radius:8px;border:1px solid var(--tc-line);background:#fff;color:var(--tc-ink);cursor:pointer;text-align:left;}
.tc-opt:hover{border-color:var(--tc-accent);}
.tc-opt-sel{border-color:var(--tc-accent);background:#f0fdfa;color:#0f766e;font-weight:600;box-shadow:0 0 0 2px #0d948822;}
.tc-coment{width:100%;margin-top:8px;font-size:12.5px;padding:7px 9px;border:1px solid var(--tc-line);border-radius:8px;resize:vertical;font-family:inherit;}
.tc-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-top:8px;}
.tc-btn{font-size:12px;font-weight:600;padding:6px 12px;border-radius:8px;border:1px solid transparent;cursor:pointer;}
.tc-btn:disabled{opacity:.6;cursor:default;}
.tc-btn-ok{background:#ecfdf5;color:#047857;border-color:#bbf7d0;}
.tc-btn-no{background:#fef2f2;color:#b91c1c;border-color:#fecaca;}
.tc-btn-mut{background:#f8fafc;color:#475569;border-color:var(--tc-line);}
.tc-btn-seg{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
.tc-seg{margin-top:8px;padding:10px;border:1px dashed var(--tc-line);border-radius:8px;}
.tc-seg-in{width:100%;margin-bottom:6px;font-size:12.5px;padding:6px 9px;border:1px solid var(--tc-line);border-radius:7px;font-family:inherit;}
.tc-seg-row{display:flex;align-items:center;flex-wrap:wrap;gap:8px;}
.tc-seg-sel{font-size:12px;padding:5px 8px;border:1px solid var(--tc-line);border-radius:7px;background:#fff;}
.tc-seg-chk{font-size:12px;color:var(--tc-muted);display:flex;align-items:center;gap:4px;}
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
.tc-ejec{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-top:1px solid var(--tc-line);}
.tc-ejec:first-of-type{border-top:none;}
.tc-ejec-body{min-width:0;}
.tc-ejec-head{font-size:12px;color:var(--tc-muted);}
.tc-ejec-rc{color:var(--tc-bad);font-weight:600;}

/* ── Modo oscuro (responde al toggle de tema del proyecto: body[data-layout-mode] → darkMode) ── */
.tc-dark{
  --tc-surface:#151d2e; --tc-ink:#e8edf6; --tc-muted:#8b97ab; --tc-line:#2a3550;
  --tc-ok:#4ade80; --tc-info:#60a5fa; --tc-warn:#fbbf24; --tc-bad:#f87171; --tc-slate:#94a3b8; --tc-accent:#2dd4bf;
}
.tc-dark .tc-statusbar,.tc-dark .tc-kpi,.tc-dark .tc-card{box-shadow:0 1px 2px rgba(0,0,0,.35);}
.tc-dark .tc-run{background:rgba(74,222,128,.14);color:#4ade80;}
.tc-dark .tc-pause{background:rgba(248,113,113,.14);color:#f87171;}
.tc-dark .tc-killbtn{background:#1c2740;color:#f87171;border-color:#5b2b2b;}
.tc-dark .tc-killbtn.tc-resume{background:#132a1f;color:#4ade80;border-color:#2b5b3b;}
.tc-dark .tc-lvA{background:rgba(74,222,128,.15);color:#4ade80;}
.tc-dark .tc-lvB{background:rgba(251,191,36,.15);color:#fbbf24;}
.tc-dark .tc-lvC{background:rgba(248,113,113,.15);color:#f87171;}
.tc-dark .tc-lvNone{background:rgba(148,163,184,.15);color:#94a3b8;}
.tc-dark .tc-sevA{background:rgba(248,113,113,.15);color:#f87171;}
.tc-dark .tc-sevM{background:rgba(251,191,36,.15);color:#fbbf24;}
.tc-dark .tc-sevB{background:rgba(148,163,184,.15);color:#cbd5e1;}
.tc-dark .tc-opt{background:#0f172a;color:#e8edf6;border-color:#2a3550;}
.tc-dark .tc-opt:hover{border-color:#2dd4bf;}
.tc-dark .tc-opt-sel{background:rgba(45,212,191,.15);color:#5eead4;border-color:#2dd4bf;box-shadow:0 0 0 2px rgba(45,212,191,.25);}
.tc-dark .tc-coment{background:#0f172a;color:#e8edf6;border-color:#2a3550;}
.tc-dark .tc-coment::placeholder{color:#64748b;}
.tc-dark .tc-btn-ok{background:rgba(74,222,128,.14);color:#4ade80;border-color:#2b5b3b;}
.tc-dark .tc-btn-no{background:rgba(248,113,113,.14);color:#f87171;border-color:#5b2b2b;}
.tc-dark .tc-btn-mut{background:#1c2740;color:#cbd5e1;border-color:#2a3550;}
.tc-dark .tc-btn-seg{background:rgba(96,165,250,.14);color:#60a5fa;border-color:#2b3f5b;}
.tc-dark .tc-seg{border-color:#2a3550;}
.tc-dark .tc-seg-in,.tc-dark .tc-seg-sel{background:#0f172a;color:#e8edf6;border-color:#2a3550;}

@media(max-width:820px){.tc-kpis{grid-template-columns:repeat(2,1fr)}.tc-grid{grid-template-columns:1fr}}
</style>
