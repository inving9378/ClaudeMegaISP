<template>
  <div class="rid-wrap" :class="{ 'rid-dark': darkMode }">
    <div class="rid-head">
      <span class="rid-tag" :class="lvClass(item.nivel_riesgo)">{{ item.nivel_riesgo || '—' }}</span>
      <span class="rid-badge" :class="estadoCls(item.estado_aprobacion)">{{ estadoTxt(item.estado_aprobacion) }}</span>
      <h1 class="rid-title"><span class="rid-idnum">#{{ item.id }}</span> {{ item.title }}</h1>
    </div>

    <div class="rid-meta">
      <span v-if="item.modulo">📦 {{ item.modulo }}</span>
      <span v-if="item.branch"><code>{{ item.branch }}</code></span>
      <span v-if="item.worker_sid">🛠 {{ item.worker_nombre || item.worker_sid }}</span>
      <span v-if="item.merge_commit" class="rid-merged">● mergeado ({{ item.merge_commit.substring(0, 8) }})</span>
      <span v-else class="rid-pending">○ sin mergear</span>
      <span v-if="item.target_version">🏷 {{ item.target_version }}</span>
      <span v-if="item.priority">Prioridad: {{ item.priority }}</span>
      <span v-if="item.status">Status: {{ item.status }}</span>
    </div>

    <div v-if="item.resumen" class="rid-section rid-resumen">{{ item.resumen }}</div>

    <div v-if="item.descripcion" class="rid-section">
      <h2 class="rid-h2">Descripción</h2>
      <p class="rid-txt">{{ item.descripcion }}</p>
    </div>

    <div v-if="item.reporte_tecnico" class="rid-section">
      <h2 class="rid-h2">Reporte técnico</h2>
      <p class="rid-txt">{{ item.reporte_tecnico }}</p>
    </div>

    <div v-if="item.reporte_coloquial" class="rid-section">
      <h2 class="rid-h2">Reporte coloquial</h2>
      <p class="rid-txt">{{ item.reporte_coloquial }}</p>
    </div>

    <div v-if="item.reporte" class="rid-section">
      <h2 class="rid-h2">Reporte del ejecutor</h2>
      <p class="rid-txt">{{ item.reporte }}</p>
    </div>

    <div v-if="item.opciones && item.opciones.length" class="rid-section">
      <h2 class="rid-h2">Opciones</h2>
      <ul class="rid-list">
        <li v-for="(op, i) in item.opciones" :key="i" :class="{ 'rid-elegida': esElegida(op) }">
          {{ textoOpcion(op) }}
          <span v-if="esElegida(op)" class="rid-badge rid-badge-ok">elegida</span>
        </li>
      </ul>
    </div>

    <div v-if="item.subtasks && item.subtasks.length" class="rid-section">
      <h2 class="rid-h2">Subtareas</h2>
      <ul class="rid-list">
        <li v-for="(s, i) in item.subtasks" :key="i" :class="{ 'rid-done': s.completed }">
          {{ s.completed ? '✓' : '○' }} {{ s.title }}
        </li>
      </ul>
    </div>

    <div v-if="item.log && item.log.length" class="rid-section">
      <h2 class="rid-h2">Bitácora</h2>
      <ul class="rid-list rid-log">
        <li v-for="(l, i) in item.log" :key="i">{{ textoLog(l) }}</li>
      </ul>
    </div>

    <div class="rid-section rid-fechas">
      <span v-if="item.created_at">Creado: {{ fecha(item.created_at) }}</span>
      <span v-if="item.started_at">Iniciado: {{ fecha(item.started_at) }}</span>
      <span v-if="item.completed_at">Completado: {{ fecha(item.completed_at) }}</span>
      <span v-if="item.updated_at">Actualizado: {{ fecha(item.updated_at) }}</span>
    </div>
  </div>
</template>

<script>
import { darkMode } from '../../../hook/appConfig.js';

export default {
    name: 'RoadmapItemDetalle',
    props: {
        item: { type: String, required: true },
    },
    setup(props) {
        const item = JSON.parse(props.item);

        const lvClass = (n) => (n === 'A' ? 'rid-lvA' : n === 'B' ? 'rid-lvB' : n === 'C' ? 'rid-lvC' : 'rid-lvNone');

        const ESTADOS = {
            pendiente_revision: 'pendiente de revisión',
            aprobado_claude:    'aprobado (Claude)',
            aprobado_revisor:   'aprobado (revisor)',
            requiere_irving:    'requiere decisión de Irving',
            aprobado_irving:    'aprobado (Irving)',
            rechazado:          'rechazado',
            en_progreso:        'en progreso',
            completado:         'completado',
            cancelado:          'cancelado',
        };
        const estadoTxt = (e) => ESTADOS[e] || e || '—';
        const estadoCls = (e) => {
            if (e === 'completado') return 'rid-badge-ok';
            if (e === 'rechazado' || e === 'cancelado') return 'rid-badge-err';
            if (e === 'requiere_irving') return 'rid-badge-wait';
            if (e === 'en_progreso') return 'rid-badge-run';
            return 'rid-badge-idle';
        };

        const textoOpcion = (op) => (typeof op === 'string' ? op : (op?.titulo || op?.title || op?.texto || JSON.stringify(op)));
        const esElegida = (op) => {
            if (!item.opcion_elegida) return false;
            // #431: opcion_elegida es la CLAVE estable; las opciones traen {clave,texto,recomendada}.
            if (op && typeof op === 'object' && op.clave) return op.clave === item.opcion_elegida;
            const val = typeof op === 'string' ? op : (op?.titulo || op?.title || op?.texto);
            return val === item.opcion_elegida;
        };

        const textoLog = (l) => {
            if (typeof l === 'string') return l;
            const cuando = l?.at || l?.fecha || l?.date;
            const texto = l?.text || l?.mensaje || l?.msg || JSON.stringify(l);
            return cuando ? `${fecha(cuando)} — ${texto}` : texto;
        };

        function fecha(iso) {
            try {
                return new Date(iso).toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            } catch (e) {
                return iso;
            }
        }

        return { item, darkMode, lvClass, estadoTxt, estadoCls, textoOpcion, esElegida, textoLog, fecha };
    },
};
</script>

<style scoped>
.rid-wrap{max-width:920px;margin:24px auto;padding:24px;font-family:inherit;color:#1f2937;}
.rid-dark{color:#e5e7eb;}
.rid-head{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:8px;}
.rid-title{font-size:1.5rem;font-weight:700;margin:0;flex:1 1 auto;}
.rid-idnum{color:#6b7280;font-weight:500;margin-right:6px;}
.rid-dark .rid-idnum{color:#9ca3af;}

.rid-tag{font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid transparent;}
.rid-lvA{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
.rid-lvB{background:#fef9c3;color:#854d0e;border-color:#fde68a;}
.rid-lvC{background:#fee2e2;color:#991b1b;border-color:#fecaca;}
.rid-lvNone{background:#e5e7eb;color:#374151;border-color:#d1d5db;}

.rid-badge{font-size:.72rem;font-weight:600;padding:2px 8px;border-radius:999px;}
.rid-badge-ok{background:#dcfce7;color:#166534;}
.rid-badge-err{background:#fee2e2;color:#991b1b;}
.rid-badge-wait{background:#fef9c3;color:#854d0e;}
.rid-badge-run{background:#dbeafe;color:#1e40af;}
.rid-badge-idle{background:#e5e7eb;color:#374151;}

.rid-meta{display:flex;flex-wrap:wrap;gap:14px;font-size:.85rem;color:#4b5563;margin-bottom:16px;}
.rid-dark .rid-meta{color:#9ca3af;}
.rid-meta code{background:#f3f4f6;padding:1px 6px;border-radius:4px;}
.rid-dark .rid-meta code{background:#374151;color:#e5e7eb;}
.rid-merged{color:#166534;}
.rid-pending{color:#92400e;}

.rid-section{margin-bottom:20px;}
.rid-resumen{font-size:1.05rem;font-weight:500;background:#f9fafb;border-left:3px solid #6366f1;padding:10px 14px;border-radius:4px;}
.rid-dark .rid-resumen{background:#1f2937;}
.rid-h2{font-size:.95rem;font-weight:700;margin:0 0 6px;color:#374151;}
.rid-dark .rid-h2{color:#d1d5db;}
.rid-txt{white-space:pre-wrap;line-height:1.5;margin:0;}

.rid-list{list-style:none;padding:0;margin:0;}
.rid-list li{padding:4px 0;border-bottom:1px solid #f3f4f6;}
.rid-dark .rid-list li{border-color:#374151;}
.rid-elegida{font-weight:600;}
.rid-done{color:#166534;text-decoration:line-through;text-decoration-color:#9ca3af;}
.rid-log li{font-size:.85rem;color:#4b5563;}
.rid-dark .rid-log li{color:#9ca3af;}

.rid-fechas{display:flex;flex-wrap:wrap;gap:14px;font-size:.78rem;color:#9ca3af;border-top:1px solid #f3f4f6;padding-top:12px;}
.rid-dark .rid-fechas{border-color:#374151;}
</style>
