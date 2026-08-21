<template>
  <div class="rid-wrap" :class="{ 'rid-dark': darkMode }">
    <div class="rid-head">
      <span class="rid-tag" :class="lvClass(item.nivel_riesgo)">{{ item.nivel_riesgo || '—' }}</span>
      <span class="rid-badge" :class="estadoCls(item.estado_aprobacion)">{{ estadoTxt(item.estado_aprobacion) }}</span>
      <h1 class="rid-title"><span class="rid-idnum">#{{ item.id }}</span> {{ item.title }}</h1>
    </div>

    <!-- #936 — depende de #935 (DiagnosticoItemService): item.diagnostico = {causa, explicacion, accion, procedencia}.
         Ausente/null mientras #935 no exista → el bloque simplemente no se renderiza (no-op). -->
    <div v-if="item.diagnostico" class="rid-diag" :class="diagCls(item.diagnostico.causa)">
      <span class="rid-diag-icon">{{ diagIcon(item.diagnostico.causa) }}</span>
      <span class="rid-diag-txt">{{ item.diagnostico.explicacion }}</span>
      <q-icon v-if="item.diagnostico.procedencia" name="info" size="16px" class="rid-diag-info">
        <q-tooltip>{{ item.diagnostico.procedencia }}</q-tooltip>
      </q-icon>
      <span v-if="diagAcciones(item.diagnostico.accion).length" class="rid-diag-acciones">
        <q-btn
          v-for="(ac, ai) in diagAcciones(item.diagnostico.accion)"
          :key="ai"
          size="sm" dense no-caps flat
          color="primary"
          :disable="!ac.disponible"
          :label="ac.disponible ? ac.texto : `${ac.texto} — disponible en la fase ${ac.fase}`"
        />
      </span>
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

    <!-- #432 Fase 3 — preguntas (multi o 1 fallback), read-only -->
    <div v-if="item.preguntas && item.preguntas.length" class="rid-section">
      <h2 class="rid-h2">{{ item.preguntas.length > 1 ? 'Preguntas de decisión' : 'Opciones' }}</h2>
      <div v-for="(pg, pi) in item.preguntas" :key="pg.id" class="rid-pregunta">
        <p v-if="pg.pregunta" class="rid-pregunta-t">
          <strong v-if="item.preguntas.length > 1">{{ pi + 1 }}.</strong> {{ pg.pregunta }}
          <span v-if="pg.fase" class="rid-badge">se decide en {{ pg.fase }}</span>
        </p>
        <ul class="rid-list">
          <li v-for="(op, i) in pg.opciones" :key="i" :class="{ 'rid-elegida': op.clave === pg.opcion_elegida }">
            {{ op.texto }}
            <span v-if="op.recomendada" class="rid-badge">recomendada</span>
            <span v-if="op.clave === pg.opcion_elegida" class="rid-badge rid-badge-ok">elegida</span>
          </li>
        </ul>
      </div>
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

        // #936 — depende de #935 (DiagnosticoItemService::para()): causas del plan (item #877 sección 1).
        const DIAG_ICONS = {
            espera_resolucion: '⏳',
            aprobado_no_despachable: '🚦',
            reclamo_huerfano: '👻',
            tope_duro: '🛑',
            sin_terminal: '🖥️',
            override_consumido: '🔁',
            motor_caido: '⚠️',
            sin_causa: '❓',
            no_determinado: '❓',
        };
        const diagIcon = (causa) => DIAG_ICONS[causa] || 'ℹ️';

        const DIAG_CLS = {
            tope_duro: 'rid-diag-c-err',
            motor_caido: 'rid-diag-c-err',
            aprobado_no_despachable: 'rid-diag-c-warn',
            reclamo_huerfano: 'rid-diag-c-warn',
            override_consumido: 'rid-diag-c-warn',
            espera_resolucion: 'rid-diag-c-info',
            sin_terminal: 'rid-diag-c-info',
        };
        const diagCls = (causa) => DIAG_CLS[causa] || 'rid-diag-c-idle';

        // Normaliza item.diagnostico.accion (string | objeto {texto,disponible,fase} | array) a botones.
        const diagAcciones = (accion) => {
            if (!accion) return [];
            const lista = Array.isArray(accion) ? accion : [accion];
            return lista
                .map((a) => (typeof a === 'string'
                    ? { texto: a, disponible: true, fase: null }
                    : { texto: a?.texto || a?.label || '', disponible: a?.disponible !== false, fase: a?.fase ?? null }))
                .filter((a) => a.texto);
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

        return { item, darkMode, lvClass, estadoTxt, estadoCls, textoOpcion, esElegida, textoLog, fecha, diagIcon, diagCls, diagAcciones };
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

.rid-diag{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.9rem;padding:8px 12px;border-radius:6px;margin-bottom:14px;background:#f9fafb;border-left:3px solid #9ca3af;}
.rid-dark .rid-diag{background:#1f2937;}
.rid-diag-icon{font-size:1rem;line-height:1;}
.rid-diag-txt{flex:1 1 auto;}
.rid-diag-info{cursor:help;color:#6b7280;}
.rid-dark .rid-diag-info{color:#9ca3af;}
.rid-diag-acciones{display:flex;gap:6px;flex-wrap:wrap;}
.rid-diag-c-err{border-left-color:#dc2626;background:#fef2f2;}
.rid-dark .rid-diag-c-err{background:#3f1d1d;}
.rid-diag-c-warn{border-left-color:#d97706;background:#fffbeb;}
.rid-dark .rid-diag-c-warn{background:#3f2d0f;}
.rid-diag-c-info{border-left-color:#2563eb;background:#eff6ff;}
.rid-dark .rid-diag-c-info{background:#1e293b;}
.rid-diag-c-idle{border-left-color:#9ca3af;}

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
