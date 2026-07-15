<template>
  <div class="tc-wrap" :class="{ 'tc-dark': darkMode }">

    <!-- Estado EN VIVO del circuito (#335) + kill switch -->
    <div class="tc-statusbar">
      <div class="tc-left">
        <span class="tc-pill" :class="estadoClass">
          <span v-if="running" class="tc-livedot"></span>
          <span v-else class="tc-statdot" :class="estadoClass"></span>
          {{ estadoLabel }}
        </span>
        <div>
          <h1 class="tc-h1">
            <template v-if="running">Vuelta en curso<span v-if="live.current_item" class="tc-hnorm"> · tocando <span class="tc-idnum">#{{ live.current_item }}</span></span></template>
            <template v-else>Circuito CC · Torre de control</template>
          </h1>
          <div class="tc-meta">
            <template v-if="running">
              Corriendo hace <b class="tc-strong">{{ fmtClock(elapsedRunning) }}</b> · modo <b>{{ modo }}</b><span class="tc-beat"> · ♥ vivo hace {{ sinceBeat }}s</span>
            </template>
            <template v-else-if="pausado">
              Circuito detenido · no ejecuta hasta reanudar · {{ total }} items
            </template>
            <template v-else>
              <span v-if="ultimaHace">Última vuelta <b class="tc-strong">{{ ultimaHace }}</b></span><span v-else>Sin vueltas registradas aún</span><span v-if="proximaEn"> · próxima <b class="tc-strong">{{ proximaEn }}</b></span> · {{ total }} items · modo <b>{{ modo }}</b>
            </template>
          </div>
        </div>
      </div>
      <div class="tc-barbtns">
        <button v-if="canDisparar" class="tc-runbtn" :disabled="disparando || pausado || running"
          :title="pausado ? 'Reanuda el circuito para ejecutar' : (running ? 'Ya hay una vuelta en curso' : '')"
          @click="disparar">{{ disparando ? '⏳ Disparando…' : '▶ Ejecutar vuelta ahora' }}</button>
        <button class="tc-logbtn" :class="{ 'tc-logbtn-on': logOpen }" @click="toggleLog">{{ logOpen ? '✕ Ocultar log' : '👁 Ver log en vivo' }}</button>
        <button class="tc-killbtn" :class="{ 'tc-resume': pausado }" :disabled="toggling" @click="toggle">
          {{ toggling ? '…' : (pausado ? '▶ Reanudar circuito' : '⏸ Pausar circuito') }}
        </button>
      </div>
    </div>

    <!-- Feedback del disparo manual (#337) -->
    <div v-if="disparoMsg" class="tc-disparo-msg">{{ disparoMsg }}</div>

    <!-- #343: salvaguarda de pausa olvidada — puramente informativo, nunca reanuda solo -->
    <div v-if="pausaOlvidada" class="tc-alert">
      ⚠ <b>Circuito pausado hace {{ pausaHorasTxt }}</b><span v-if="pausadoInfo?.por"> por {{ pausadoInfo.por }}</span> — ¿reanudar?
      <button class="tc-killbtn tc-resume" style="margin-left:10px" :disabled="toggling" @click="toggle">{{ toggling ? '…' : '▶ Reanudar circuito' }}</button>
    </div>

    <!-- Aviso: posible circuito caído -->
    <div v-if="running && live.stale" class="tc-alert">
      ⚠ <b>Posible circuito caído</b> — la vuelta figura como corriendo, pero el latido no se actualiza hace {{ sinceBeat }}s. Revisa el ejecutor on-box (o pausa y reanuda).
    </div>
    <div v-else-if="cronCaido" class="tc-alert tc-alert-soft">
      ⚠ <b>Posible cron detenido</b> — el scheduler on-box no late hace {{ schedulerBeatSecs != null ? Math.round(schedulerBeatSecs/60) + ' min' : 'un rato' }}. Verifica el cron del ejecutor (meganet).
    </div>
    <div v-else-if="!pausado && !running && autoEjecutables > 0" class="tc-alert tc-alert-soft">
      ⏸ <b>Ocioso</b> — {{ autoEjecutables }} auto-ejecutable(s) en cola; el pool los tomará en segundos (o esperan que el revisor apruebe los B).
    </div>

    <!-- Visor "Trabajando ahora" (#349): stepper de fases por sesión + resumen de la vuelta -->
    <torre-trabajando-ahora :sesiones="sesiones" :resumen="resumenUltima" :now-ms="nowMs" :pausado="pausado" :dark="darkMode" />

    <!-- Log en vivo (#335): tail espejeado por BD -->
    <div v-if="logOpen" class="tc-livelog">
      <div class="tc-livelog-head">
        <span class="tc-livelog-tag"><span class="tc-livedot"></span>LOG EN VIVO</span>
        <span>{{ running ? 'streaming…' : 'última vuelta' }}<span v-if="live.current_item"> · #{{ live.current_item }}</span></span>
      </div>
      <pre ref="logPre" class="tc-livelog-pre">{{ logTail || 'Sin salida todavía…' }}</pre>
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

      <!-- #348: Cola ejecutable — pendientes/aprobados que el circuito corre por prioridad; 🔥 salta la fila -->
      <div class="tc-card" style="margin-bottom:14px">
        <h2 class="tc-h2">▶ Cola ejecutable — lo que el circuito auto-corre</h2>
        <div class="tc-execsum">
          <span class="tc-execsum-auto">{{ resumenCola.auto_ejecutables }} auto-ejecutables</span>
          <span class="tc-execsum-sep">·</span>
          <span class="tc-execsum-dec">{{ resumenCola.espera_decision }} esperan tu decisión</span>
          <template v-if="resumenCola.sin_clasificar"><span class="tc-execsum-sep">·</span><span class="tc-execsum-sin">{{ resumenCola.sin_clasificar }} sin clasificar</span></template>
        </div>
        <div class="tc-meta" style="margin:2px 0 10px">Solo A/B o ya aprobados por ti (los C/negocio están en tu bandeja). Ordenados por 🔥 urgente → prioridad (alta→media→baja) → antigüedad; el 🔥 salta la fila y dispara una vuelta ya.</div>
        <div v-if="!colaEjecutable.length" class="tc-meta">Nada en cola de ejecución ahora. ✓</div>
        <div v-for="it in colaEjecutable" :key="it.id" class="tc-exec-item">
          <span class="tc-tag" :class="lvClass(it.nivel_riesgo)">{{ it.nivel_riesgo || '—' }}</span>
          <span class="tc-prio" :class="'tc-prio-' + (it.priority || 'none')">{{ prioLabel(it.priority) }}</span>
          <div class="tc-exec-body">
            <span class="tc-idnum">#{{ it.id }}</span> {{ it.title }}
            <span class="tc-exec-est">{{ it.estado_aprobacion }}</span>
          </div>
          <button v-if="canDisparar" class="tc-btn tc-btn-urg" :class="{ 'tc-btn-urg-on': it.urgente }"
            :disabled="urgiendo === it.id" @click="marcarUrgente(it)"
            :title="it.urgente ? 'Quitar urgente' : 'Marcar urgente: salta toda la fila y dispara una vuelta ya'">
            {{ urgiendo === it.id ? '⏳' : (it.urgente ? '🔥 Urgente ✓' : '🔥 Urgente') }}</button>
        </div>
      </div>

      <div class="tc-grid">
        <!-- Bandeja: requiere_irving -->
        <div class="tc-card">
          <h2 class="tc-h2">⚑ Tu bandeja — requiere tu decisión ({{ cola.length }})</h2>
          <div v-if="!cola.length" class="tc-meta">Nada requiere tu decisión ahora. ✓</div>
          <div v-for="it in cola" :key="it.id" :id="'tc-item-' + it.id" class="tc-inbox-item tc-inbox-compact" :class="{ 'tc-highlight': highlightId === it.id }">
            <span class="tc-tag" :class="lvClass(it.nivel_riesgo)">{{ it.nivel_riesgo || '—' }}</span>
            <div class="tc-inbox-body">
              <!-- #477: tarjeta COMPACTA para TODA "Tu bandeja" (no solo nivel_riesgo=C: la bandeja
                   también trae B/sin-clasificar vía [BLOCKED-/PARKED-] o requiere_irving directo —
                   ver RoadmapItem::scopeBandeja) — encabezado corto + título, sin párrafos largos -->
              <div class="tc-t-compact">
                <span class="tc-idnum">#{{ it.id }}</span>
                <span class="tc-badge tc-badge-dec">{{ estadoLabelItem(it) }}</span>
                <span class="tc-prio" :class="'tc-prio-' + (it.priority || 'none')">{{ prioLabel(it.priority) }}</span>
                <span v-if="it.modulo" class="tc-modulo-chip">{{ it.modulo }}</span>
              </div>
              <div class="tc-titulo-c">{{ it.title }}</div>

              <!-- #432 Fase 3 — brief COMPLETO: TODAS las preguntas del item juntas, cada una con sus
                   opciones. #431: objetos {clave,texto,recomendada}; se marca por CLAVE estable.
                   Verde = RECOMENDADA; anillo = SELECCIONADA. Una sola Aprobar responde todo. -->
              <div v-if="it.preguntas && it.preguntas.length" class="tc-preguntas">
                <div class="tc-dec-label">Decisión requerida</div>
                <div v-for="(pg, pi) in it.preguntas" :key="pg.id" class="tc-pregunta">
                  <div v-if="pg.pregunta || pg.fase" class="tc-pregunta-t">
                    <span v-if="it.preguntas.length > 1" class="tc-pregunta-num">{{ pi + 1 }}.</span>{{ pg.pregunta }}
                    <span v-if="pg.fase" class="tc-fase-badge">se decide en {{ pg.fase }}</span>
                  </div>
                  <div v-if="pg.opciones && pg.opciones.length" class="tc-opts">
                    <button
                      v-for="op in pg.opciones" :key="op.clave" type="button"
                      class="tc-opt"
                      :class="{ 'tc-opt-sel': selPreg(it.id, pg.id) === op.clave, 'tc-opt-rec': op.recomendada }"
                      @click="elegirOpcion(it, pg, op)">
                      <span v-if="op.recomendada" class="tc-opt-badge">RECOMENDADA</span>{{ op.texto }}</button>
                  </div>
                </div>
              </div>
              <!-- #437 — semáforo "brief pendiente": el gate async encoló proponer-opciones en
                   background al escalar; hasta que ese job escriba `preguntas` no hay nada que
                   responder aquí. Ya no se limita a nivel C (el gate corre para toda la bandeja). -->
              <div v-else class="tc-noopts">Brief pendiente — el circuito está preparando las preguntas y opciones (puede tardar unos segundos)…</div>

              <textarea v-model="coment[it.id]" class="tc-coment" rows="2" placeholder="Comentario (opcional)…"></textarea>

              <div class="tc-actions">
                <button class="tc-btn tc-btn-voz" :title="hablando === it.id ? 'Detener' : 'Escuchar el resumen'" @click="leer(it)">{{ hablando === it.id ? '⏹ Detener' : '🔊 Escuchar' }}</button>
                <button class="tc-btn tc-btn-ver" :title="it.modulo_url ? ('Abrir ' + (it.modulo || 'la pantalla del módulo') + ' en pestaña nueva') : 'Abrir la Torre en pestaña nueva'" @click="verMas(it)">🔎 Ver más</button>
                <button class="tc-btn tc-btn-ok"  :disabled="deciding === it.id" @click="decidir(it, 'aprobar')">✓ Aprobar</button>
                <button class="tc-btn tc-btn-no"  :disabled="deciding === it.id" @click="decidir(it, 'rechazar')">✕ Rechazar</button>
                <button class="tc-btn tc-btn-mut" :disabled="deciding === it.id" @click="decidir(it, 'comentar')">💬 Comentar</button>
                <button class="tc-btn tc-btn-mut" :disabled="deciding === it.id" @click="decidir(it, 'cerrar')">✔ Cerrar</button>
                <button class="tc-btn tc-btn-mut" :disabled="deciding === it.id" @click="decidir(it, 'cancelar')">⊘ Cancelar</button>
                <button class="tc-btn tc-btn-seg" :disabled="deciding === it.id" @click="toggleSeg(it)">＋ Seguimiento</button>
                <button v-if="canDisparar" class="tc-btn tc-btn-urg" :class="{ 'tc-btn-urg-on': it.urgente }"
                  :disabled="urgiendo === it.id" @click="marcarUrgente(it)"
                  :title="it.urgente ? 'Quitar urgente' : 'Decisión urgente: subir al tope de tu bandeja (no ejecuta)'">
                  {{ urgiendo === it.id ? '⏳' : (it.urgente ? '🔥 Decisión ✓' : '🔥 Decisión urgente') }}</button>
                <span v-if="deciding === it.id" class="tc-meta">Guardando…</span>
                <span v-if="aviso[it.id]" class="tc-aviso" :class="'tc-aviso-' + aviso[it.id].tipo">{{ aviso[it.id].texto }}</span>
              </div>

              <!-- #477: descripción completa colapsada por defecto (toda "Tu bandeja" es compacta) -->
              <div v-if="tieneDescExtendida(it)" class="tc-desc-wrap">
                <button type="button" class="tc-desc-toggle" @click="descOpen[it.id] = !descOpen[it.id]">
                  {{ descOpen[it.id] ? 'Ocultar descripción ▲' : 'Ver descripción ▼' }}
                </button>
                <div v-if="descOpen[it.id]" class="tc-desc-panel">
                  <div v-if="it.description" class="tc-desc-block"><b>Descripción completa</b><p>{{ it.description }}</p></div>
                  <div v-if="it.alcance_autorizado" class="tc-desc-block"><b>Alcance</b><p>{{ it.alcance_autorizado }}</p></div>
                  <div v-if="it.fuera_de_alcance" class="tc-desc-block"><b>Fuera de alcance</b><p>{{ it.fuera_de_alcance }}</p></div>
                  <div v-if="it.recomendacion" class="tc-desc-block"><b>Contexto técnico / observaciones</b><p class="tc-desc-pre">{{ it.recomendacion }}</p></div>
                </div>
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

        <!-- FASE 1 — Cambios para que Irving pruebe (validación funcional, no código) -->
        <div class="tc-card" v-if="cambiosValidacion.length">
          <h2 class="tc-h2">✅ Cambios para que Irving pruebe ({{ cambiosValidacion.length }})</h2>
          <div class="tc-meta" style="margin-bottom:8px">Revisa el <b>resultado</b>, no el código. Pruébalo y dinos si funciona.</div>
          <div v-for="c in cambiosValidacion" :key="c.id" :id="'tc-val-' + c.id" class="tc-val">
            <div class="tc-val-head">
              <span class="tc-idnum">#{{ c.id }}</span>
              <span class="tc-tag" :class="lvClass(c.nivel_riesgo)">{{ c.nivel_riesgo || '—' }}</span>
              <b>{{ c.title }}</b>
              <span v-if="c.modulo" class="tc-modulo-chip">{{ c.modulo }}</span>
            </div>
            <div class="tc-val-grid">
              <div v-if="c.que_se_pidio"><span class="tc-val-k">Qué se pidió</span>{{ c.que_se_pidio }}</div>
              <div v-if="c.que_se_hizo"><span class="tc-val-k">Qué se hizo</span>{{ c.que_se_hizo }}</div>
              <div v-if="c.como_probar"><span class="tc-val-k">Cómo probarlo</span>{{ c.como_probar }}</div>
              <div v-if="c.resultado_esperado"><span class="tc-val-k">Resultado esperado</span>{{ c.resultado_esperado }}</div>
              <div v-if="c.que_no_se_toco"><span class="tc-val-k">Qué no se tocó</span>{{ c.que_no_se_toco }}</div>
              <div><span class="tc-val-k">Riesgo</span>{{ c.riesgo }}</div>
              <div><span class="tc-val-k">Integrado</span>{{ rel(c.integrado_at) }}<span v-if="c.merge_commit"> · {{ c.merge_commit.slice(0,10) }}</span></div>
            </div>
            <div class="tc-val-actions">
              <button class="tc-btn tc-btn-ver" @click="abrirYProbar(c)">🔎 Abrir y probar</button>
              <button class="tc-btn tc-btn-ok" :disabled="valBusy === c.id" @click="validarFunciona(c)">✓ Funciona correctamente</button>
              <button class="tc-btn tc-btn-warn" :disabled="valBusy === c.id" @click="reportarProblema(c)">⚠ Reportar problema</button>
            </div>
          </div>
        </div>

        <!-- Actividad reciente -->
        <div class="tc-card">
          <h2 class="tc-h2">Actividad reciente del circuito</h2>
          <div v-if="!actividad.length" class="tc-meta">Sin actividad reciente.</div>
          <div class="tc-feed">
            <div v-for="ev in actividad" :key="ev.id" class="tc-ev tc-ev-click" role="button" tabindex="0"
                 :title="'Abrir #' + ev.id + ' — ' + (ev.ubicacion || '')"
                 @click="openItem(ev)" @keyup.enter="openItem(ev)">
              <span class="tc-icn" :style="{ background: evColor(ev) }">{{ evIcon(ev) }}</span>
              <div class="tc-ev-main">
                <b>#{{ ev.id }} {{ ev.title }}</b><span v-if="ev.comentario"> — {{ ev.comentario }}</span>
                <div class="tc-ev-meta">
                  <span v-if="ev.nivel_riesgo" class="tc-ev-badge" :class="lvClass(ev.nivel_riesgo)">nivel {{ ev.nivel_riesgo }}</span>
                  <span class="tc-ev-badge tc-ev-estado">{{ estadoAprobLabel(ev.estado_aprobacion) }}</span>
                  <span v-if="ev.ubicacion" class="tc-ev-badge tc-ev-ubic">{{ ev.ubicacion }}</span>
                  <span v-if="ev.siguiente_accion" class="tc-ev-next">→ {{ ev.siguiente_accion }}</span>
                </div>
                <div class="tc-when">{{ rel(ev.cuando) }}<span v-if="ev.aprobado_por"> · {{ ev.aprobado_por }}</span></div>
              </div>
              <div class="tc-ev-actions">
                <button class="tc-btn tc-btn-ok" @click.stop="openItem(ev)">Abrir item</button>
                <button class="tc-btn tc-btn-ver" @click.stop="verRecorrido(ev)" title="Ver el detalle/recorrido del item en pestaña nueva">Ver recorrido</button>
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
              <span>{{ rel(e.started_at) }}</span> · <span>{{ e.modo }}</span>
              <span v-if="e.modelo"> · <span class="tc-tag-modelo">{{ e.modelo }}</span></span>
              · <span>{{ e.duracion_seg }}s</span>
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
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { darkMode } from '../../../../hook/appConfig.js';
import { useEscuchar, verMas } from './torreEscuchar.js';
import TorreTrabajandoAhora from './TorreTrabajandoAhora.vue';

export default {
    name: 'TorreControl',
    components: { TorreTrabajandoAhora },
    setup() {
        // 🔊 Escuchar + 🔎 Ver más de la bandeja: MISMA lógica que la tarjeta de Integración.
        const { hablando, vozTts, rateTts, leer, initVoces } = useEscuchar();
        const loading = ref(true);
        const toggling = ref(false);
        const pausado = ref(false);
        const pausadoInfo = ref(null);   // #343: {desde, por, horas, aviso_horas, olvidada} | null
        const generatedAt = ref(null);
        const resumen = ref({ total: 0, por_estado: {}, por_nivel: {} });
        const cola = ref([]);
        const colaEjecutable = ref([]);   // #348: SOLO auto-ejecutables (A/B o aprobados por Irving)
        const resumenCola = ref({ auto_ejecutables: 0, espera_decision: 0, sin_clasificar: 0 });
        const actividad = ref([]);
        // FASE 1 — Cambios para que Irving pruebe (validación funcional)
        const cambiosValidacion = ref([]);
        const valBusy = ref(null);
        const riesgos = ref([]);
        const auditItem = ref(null);
        const ejecuciones = ref([]);
        const modo = ref('aviso_previo');

        // Estado EN VIVO de la vuelta (#335)
        const live = ref({ running: false, stale: false, started_at: null, heartbeat_at: null, current_item: null });
        // Visor "Trabajando ahora" (#349): sesiones (array listo-para-N) + resumen última vuelta
        const sesiones = ref([]);
        const resumenUltima = ref(null);
        const logTail = ref('');
        const logOpen = ref(false);
        const proximaAt = ref(null);
        const ultimaAt = ref(null);
        const intervaloMin = ref(30);
        // Pool continuo (#334): estas 3 se USABAN sin declararse → `cronVivo is not defined` tumbaba
        // el render de la Torre (pantalla negra). Declaradas aquí. cronVivo default true = "no alarmar".
        const cronVivo = ref(true);
        const schedulerBeatSecs = ref(null);
        const autoEjecutables = ref(0);
        const logPre = ref(null);
        const nowMs = ref(Date.now());   // ticker local para animar cronómetro/heartbeat entre polls
        let estadoTimer = null;          // polling de /circuito/estado
        let tickTimer = null;            // ticker de 1s

        // Disparo manual + urgente (#337)
        const canDisparar = ref(false);
        const disparando = ref(false);
        const urgiendo = ref(null);      // id del item marcándose urgente
        const disparoMsg = ref('');
        let disparoMsgTimer = null;

        // Bandeja de decisiones interactiva (#313)
        const sel = reactive({});      // id -> { preguntaId: CLAVE estable } (#432 multi-pregunta)
        const coment = reactive({});   // id -> comentario
        const deciding = ref(null);    // id en proceso
        const aviso = reactive({});    // id -> {tipo:'err'|'ok'|'warn', texto} (#431, no falla en silencio)

        // #431 — aviso visible por item (autoborra los de éxito). Reemplaza el fallo-en-silencio.
        function setAviso(id, tipo, texto) {
            aviso[id] = { tipo, texto };
            if (tipo === 'ok') setTimeout(() => { if (aviso[id] && aviso[id].tipo === 'ok') delete aviso[id]; }, 3500);
        }

        // #432 — clave marcada para (item, pregunta).
        function selPreg(itemId, pregId) {
            return (sel[itemId] || {})[pregId];
        }
        const segOpen = reactive({});  // id -> form de seguimiento abierto
        const seg = reactive({});      // id -> {titulo, descripcion, nivel, cerrar}

        // #477 — tarjeta compacta para TODA "Tu bandeja": colapsa descripción/observaciones tras "Ver descripción".
        const descOpen = reactive({}); // id -> bool (desplegable abierto)
        const tieneDescExtendida = (it) => !!(it.description || it.alcance_autorizado || it.fuera_de_alcance || it.recomendacion);
        const estadoLabelItem = (it) => (it.estado_aprobacion === 'requiere_irving' ? 'requiere tu decisión' : (it.estado_aprobacion || 'requiere tu decisión'));

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

        // ── Estado en vivo derivado (#335) ──────────────────────────────────
        // Prioridad: pausado > corriendo > inactivo (el kill switch manda sobre todo).
        const running = computed(() => !pausado.value && !!live.value.running);
        const estadoClass = computed(() => (pausado.value ? 'tc-pause' : running.value ? 'tc-run' : 'tc-idle'));
        const estadoLabel = computed(() => (pausado.value ? 'Pausado' : running.value ? 'Ejecutando' : 'Inactivo'));

        const secsSince = (iso) => (iso ? Math.max(0, Math.floor((nowMs.value - new Date(iso).getTime()) / 1000)) : null);
        const elapsedRunning = computed(() => (running.value ? secsSince(live.value.started_at) : null));
        const sinceBeat = computed(() => secsSince(live.value.heartbeat_at));

        const fmtClock = (s) => {
            if (s == null) return '—';
            const m = Math.floor(s / 60), sec = s % 60;
            if (m < 60) return `${m}:${String(sec).padStart(2, '0')}`;
            const h = Math.floor(m / 60);
            return `${h}:${String(m % 60).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
        };

        const ultimaHaceMin = computed(() => {
            const s = secsSince(ultimaAt.value);
            return s == null ? null : Math.floor(s / 60);
        });
        const ultimaHace = computed(() => (ultimaAt.value ? rel(ultimaAt.value) : null));
        const proximaEn = computed(() => {
            if (!proximaAt.value) return null;
            const diff = new Date(proximaAt.value).getTime() - nowMs.value;
            const m = Math.round(diff / 60000);
            if (m <= 0) return 'en instantes';
            return m < 60 ? `en ${m} min` : `en ${Math.round(m / 60)} h`;
        });
        // Cron detenido REAL (#334 pool continuo): solo si el SCHEDULER dejó de latir (cronVivo=false).
        // Estar inactivo con el scheduler VIVO = ocioso por falta de trabajo seguro (no es cron caído).
        const cronCaido = computed(() => !pausado.value && cronVivo.value === false);
        // #343: salvaguarda de "pausa olvidada" — puramente informativo, nunca reanuda solo.
        const pausaOlvidada = computed(() => pausado.value && !!pausadoInfo.value?.olvidada);
        const pausaHorasTxt = computed(() => {
            const h = pausadoInfo.value?.horas;
            return h == null ? 'un buen rato' : (h < 1 ? 'menos de 1 h' : `${h} h`);
        });

        function applyEstado(data) {
            pausado.value = !!data.circuito_pausado;
            pausadoInfo.value = data.circuito_pausado_info || null;   // #343
            modo.value = data.circuito_modo || modo.value;
            live.value = data.live || { running: false, stale: false };
            if (data.trabajando) {
                sesiones.value = data.trabajando.sesiones || [];
                resumenUltima.value = data.trabajando.resumen_ultima_vuelta || null;
            }
            if (typeof data.log_tail === 'string') logTail.value = data.log_tail;
            proximaAt.value = data.proxima_vuelta_at || null;
            ultimaAt.value = data.ultima_vuelta_at || null;
            if (data.circuito_intervalo_min) intervaloMin.value = data.circuito_intervalo_min;
            if (typeof data.can_disparar === 'boolean') canDisparar.value = data.can_disparar;
            // Pool continuo (#334): latido del scheduler + auto-ejecutables en cola.
            cronVivo.value = data.cron_vivo !== false;
            schedulerBeatSecs.value = data.scheduler_beat_secs ?? null;
            autoEjecutables.value = data.auto_ejecutables ?? 0;
        }

        function flashDisparo(msg) {
            disparoMsg.value = msg;
            if (disparoMsgTimer) clearTimeout(disparoMsgTimer);
            disparoMsgTimer = setTimeout(() => { disparoMsg.value = ''; }, 8000);
        }

        // Dispara una vuelta inmediata (#337). El estado en vivo se enciende solo por el polling.
        async function disparar() {
            if (disparando.value || pausado.value || running.value) return;
            disparando.value = true;
            try {
                const { data } = await axios.post('/api/roadmap/circuito/disparar');
                flashDisparo('▶ ' + (data.mensaje || 'Disparo encolado.'));
                pollEstado();
            } catch (e) {
                flashDisparo('⚠ ' + (e.response?.data?.mensaje || 'No se pudo disparar (¿circuito en pausa?).'));
            } finally {
                disparando.value = false;
            }
        }

        // Etiqueta legible de prioridad (#348).
        function prioLabel(p) {
            return { alta: 'Alta', media: 'Media', baja: 'Baja' }[p] || 'Sin prioridad';
        }

        // Marca/desmarca un item como urgente (#337/#348). En items ejecutables dispara vuelta;
        // en items de la bandeja (requiere_irving) solo los sube al tope (decisión urgente).
        async function marcarUrgente(it) {
            if (urgiendo.value) return;
            urgiendo.value = it.id;
            const nuevo = !it.urgente;
            try {
                const { data } = await axios.post(`/api/roadmap/items/${it.id}/urgente`, { urgente: nuevo });
                it.urgente = data.urgente;
                if (!data.urgente) {
                    flashDisparo('#' + it.id + ' ya no es urgente.');
                } else if (data.modo === 'bandeja') {
                    flashDisparo('🔥 #' + it.id + ' subido al tope de tu bandeja (decisión urgente).');
                } else if (data.disparo?.mensaje) {
                    flashDisparo('🔥 #' + it.id + ' urgente · ' + data.disparo.mensaje);
                } else {
                    flashDisparo('🔥 #' + it.id + ' marcado urgente.');
                }
                load();        // re-ordena bandeja + cola ejecutable con el urgente al frente
                pollEstado();
            } catch (e) {
                flashDisparo('⚠ ' + (e.response?.data?.error || 'No se pudo marcar urgente.'));
            } finally {
                urgiendo.value = null;
            }
        }

        async function pollEstado() {
            try {
                const { data } = await axios.get('/api/roadmap/circuito/estado');
                const wasRunning = running.value;
                applyEstado(data);
                if (logOpen.value) nextTick(scrollLog);
                // Al TERMINAR una vuelta, refresca el panorama (nuevas ejecuciones/decisiones).
                if (wasRunning && !running.value) load();
            } catch (e) { /* silencioso: no romper la Torre por un poll */ }
        }

        function toggleLog() {
            logOpen.value = !logOpen.value;
            if (logOpen.value) nextTick(scrollLog);
        }
        function scrollLog() {
            if (logPre.value) logPre.value.scrollTop = logPre.value.scrollHeight;
        }

        async function load() {
            loading.value = true;
            try {
                const { data } = await axios.get('/api/roadmap/torre');
                generatedAt.value = data.generated_at;
                resumen.value = data.resumen || { total: 0, por_estado: {}, por_nivel: {} };
                cola.value = data.cola_requiere_irving || [];
                // #432 — pre-selecciona las respuestas ya persistidas por pregunta (sobrevive al recargar).
                cola.value.forEach((it) => {
                    (it.preguntas || []).forEach((pg) => {
                        if (pg.opcion_elegida) {
                            if (!sel[it.id]) sel[it.id] = {};
                            sel[it.id][pg.id] = pg.opcion_elegida;
                        }
                    });
                });
                vozTts.value = data.voz_tts || null;   // #424: misma voz guardada que Integración
                if (data.rate_tts) rateTts.value = Number(data.rate_tts);   // #424: misma velocidad
                colaEjecutable.value = data.cola_ejecutable || [];
                resumenCola.value = data.resumen_cola || { auto_ejecutables: 0, espera_decision: 0, sin_clasificar: 0 };
                actividad.value = data.actividad_reciente || [];
                cambiosValidacion.value = data.cambios_validacion || [];   // FASE 1
                riesgos.value = data.riesgos_auditoria || [];
                auditItem.value = data.auditoria_item_id || null;
                ejecuciones.value = data.ejecuciones || [];
                applyEstado(data);
                maybeDeepLink();   // #torre: deep-link /releases?item=NNN tras poblar la bandeja
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
                pollEstado();   // #343: refresca circuito_pausado_info (desde/por) al toque
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

        // Marca y PERSISTE la opción elegida de UNA pregunta al instante (sin cambiar el estado).
        // #431/#432: se guarda por CLAVE estable (op.clave) contra su pregunta; sobrevive al recargar.
        async function elegirOpcion(it, pg, op) {
            if (!sel[it.id]) sel[it.id] = {};
            sel[it.id][pg.id] = op.clave;
            delete aviso[it.id];
            try {
                await axios.post('/api/roadmap/circuito/elegir-opcion', { id: it.id, pregunta_id: pg.id, opcion: op.clave });
            } catch (e) {
                // No bloquea: la selección local queda aplicada, se reintenta al recargar.
            }
        }

        async function decidir(it, accion) {
            if (deciding.value) return;
            // Guard #431/#432: aprobar sin responder TODAS las preguntas con opciones → aviso, sin POST.
            if (accion === 'aprobar' && it.preguntas && it.preguntas.length) {
                const faltan = it.preguntas.filter(pg => pg.opciones && pg.opciones.length
                    && !selPreg(it.id, pg.id) && !pg.opcion_elegida);
                if (faltan.length) {
                    setAviso(it.id, 'warn', faltan.length === 1
                        ? 'Elige una opción antes de aprobar.'
                        : ('Responde las ' + faltan.length + ' preguntas antes de aprobar.'));
                    return;
                }
            }
            deciding.value = it.id;
            delete aviso[it.id];
            try {
                await axios.post('/api/roadmap/circuito/decidir', {
                    id: it.id,
                    accion,
                    respuestas: sel[it.id] || {},
                    comentario: coment[it.id] || null,
                });
                delete sel[it.id];
                delete coment[it.id];
                if (accion === 'comentar') setAviso(it.id, 'ok', 'Comentario guardado.');
                await load(); // refresca bandeja + panorama con el estado ya decidido
            } catch (e) {
                // #431: NUNCA fallar en silencio. Muestra el motivo (422 del guard, 403, etc.) y
                //        CONSERVA la selección/comentario para reintentar.
                const msg = (e && e.response && e.response.data && e.response.data.error)
                    || 'No se pudo guardar la decisión. Revisa tu conexión e intenta de nuevo.';
                setAviso(it.id, 'err', msg);
            } finally {
                deciding.value = null;
            }
        }

        // [BUG][UI/UX][TORRE] Navegación desde Actividad reciente a la ubicación ACTUAL del item.
        const highlightId = ref(null);
        let deepLinkDone = false;

        function estadoAprobLabel(e) {
            return {
                pendiente_revision: 'pendiente revisión',
                requiere_irving: 'requiere Irving',
                en_progreso: 'en progreso',
                aprobado_irving: 'aprobado',
                aprobado_claude: 'aprobado (auto)',
                completado: 'completado',
                cancelado: 'cancelado',
                rechazado: 'rechazado',
            }[e] || e || '—';
        }

        // Detalle read-only real del item (funciona en CUALQUIER estación) — fallback y "Ver recorrido".
        function verRecorrido(ev) {
            window.open('/roadmap/item/' + ev.id, '_blank', 'noopener');
        }

        // Resalta + hace scroll a la tarjeta del item si está en ESTA vista (bandeja/panorama).
        function highlightInPage(id) {
            const el = document.getElementById('tc-item-' + id);
            if (!el) return false;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            highlightId.value = id;
            setTimeout(() => { if (highlightId.value === id) highlightId.value = null; }, 3500);
            return true;
        }

        // Abre el item en su ubicación ACTUAL: bandeja (panorama) → resalta in-page; resto → detalle real.
        function openItem(ev) {
            if (ev && ev.ubicacion_tab === 'panorama' && cola.value.some((c) => c.id === ev.id)) {
                if (highlightInPage(ev.id)) return;
            }
            verRecorrido(ev);   // rule #5: nunca dejar al usuario en pantalla vacía
        }

        // Deep-link /releases?item=NNN — corre una vez tras cargar la bandeja: si el item está aquí lo
        // resalta; si no, abre su detalle real (misma pestaña) para no dejar pantalla vacía.
        function maybeDeepLink() {
            if (deepLinkDone) return;
            const params = new URLSearchParams(window.location.search);
            const raw = params.get('item');
            if (!raw) { deepLinkDone = true; return; }
            const id = parseInt(raw, 10);
            if (!id) { deepLinkDone = true; return; }
            deepLinkDone = true;
            nextTick(() => {
                if (cola.value.some((c) => c.id === id)) {
                    highlightInPage(id);
                } else {
                    window.location.assign('/roadmap/item/' + id);
                }
            });
        }

        // FASE 1 — Validación funcional por Irving.
        function abrirYProbar(c) {
            window.open(c.enlace_probar || ('/roadmap/item/' + c.id), '_blank', 'noopener');
        }
        async function validarFunciona(c) {
            if (valBusy.value) return;
            valBusy.value = c.id;
            try {
                await axios.post('/api/roadmap/validacion/aprobar', { id: c.id });
                cambiosValidacion.value = cambiosValidacion.value.filter((x) => x.id !== c.id);
            } catch (e) {
                aviso.value = 'No se pudo marcar como validado. Reintenta.';
            } finally {
                valBusy.value = null;
            }
        }
        async function reportarProblema(c) {
            const comentario = window.prompt('¿Qué NO hace lo solicitado? Se guarda y pasa a revisión técnica. (NO se revierte — eso es una fase aparte.)');
            if (!comentario || !comentario.trim()) return;
            if (valBusy.value) return;
            valBusy.value = c.id;
            try {
                await axios.post('/api/roadmap/validacion/reportar', { id: c.id, comentario: comentario.trim() });
                cambiosValidacion.value = cambiosValidacion.value.filter((x) => x.id !== c.id);
            } catch (e) {
                aviso.value = 'No se pudo reportar el problema. Reintenta.';
            } finally {
                valBusy.value = null;
            }
        }

        onMounted(() => {
            load();
            initVoces();
            // Polling en vivo del estado ligero + ticker local para el cronómetro/heartbeat.
            estadoTimer = setInterval(pollEstado, 4000);
            tickTimer = setInterval(() => { nowMs.value = Date.now(); }, 1000);
        });
        onUnmounted(() => {
            if (estadoTimer) clearInterval(estadoTimer);
            if (tickTimer) clearInterval(tickTimer);
            if (disparoMsgTimer) clearTimeout(disparoMsgTimer);
            if (window.speechSynthesis) window.speechSynthesis.cancel(); // no dejar voz sonando al salir de la Torre (spa-nav)
        });

        return {
            loading, toggling, pausado, pausadoInfo, pausaOlvidada, pausaHorasTxt, generatedAt, total, est, nivel, niveles, barH,
            cola, colaEjecutable, resumenCola, actividad, riesgos, auditItem, lvClass, sevLabel, sevClass, riskText,
            evIcon, evColor, rel, toggle,
            // #torre: Actividad reciente navegable (abrir item / deep-link / recorrido)
            openItem, verRecorrido, estadoAprobLabel, highlightId,
            // FASE 1: Cambios para que Irving pruebe (validación funcional)
            cambiosValidacion, valBusy, abrirYProbar, validarFunciona, reportarProblema,
            sel, coment, deciding, decidir, elegirOpcion, selPreg, aviso,
            // #477: tarjeta compacta nivel C
            descOpen, tieneDescExtendida, estadoLabelItem,
            // 🔊 Escuchar + 🔎 Ver más (compartido con Integración)
            hablando, leer, verMas,
            segOpen, seg, toggleSeg, crearSeguimiento,
            darkMode, ejecuciones, modo,
            // Estado en vivo (#335)
            live, running, estadoClass, estadoLabel, elapsedRunning, sinceBeat, fmtClock,
            ultimaHace, proximaEn, cronCaido, intervaloMin, schedulerBeatSecs, autoEjecutables,
            // Visor "Trabajando ahora" (#349)
            sesiones, resumenUltima, nowMs,
            logOpen, logTail, logPre, toggleLog,
            // Disparo manual + urgente (#337) · cola ejecutable + prioridad (#348)
            canDisparar, disparando, urgiendo, disparoMsg, disparar, marcarUrgente, prioLabel,
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
/* ── Estado en vivo (#335) ── */
.tc-idle{background:#f1f5f9;color:#475569;}
.tc-livedot{width:9px;height:9px;border-radius:50%;background:#10b981;flex:0 0 auto;animation:tc-pulse 1.3s infinite;}
@keyframes tc-pulse{0%{box-shadow:0 0 0 0 rgba(16,185,129,.5)}70%{box-shadow:0 0 0 7px rgba(16,185,129,0)}100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}}
.tc-statdot{width:8px;height:8px;border-radius:50%;background:currentColor;opacity:.7;flex:0 0 auto;}
.tc-hnorm{color:var(--tc-muted);font-weight:400;}
.tc-strong{color:var(--tc-ink);}
.tc-beat{color:var(--tc-ok);font-weight:600;}
.tc-barbtns{display:flex;gap:8px;align-items:center;flex:0 0 auto;}
.tc-logbtn{font-size:12.5px;font-weight:600;color:var(--tc-ink);background:var(--tc-surface);border:1px solid var(--tc-line);padding:7px 13px;border-radius:9px;cursor:pointer;}
.tc-logbtn-on{border-color:var(--tc-accent);color:var(--tc-accent);}
/* ── Disparo manual + urgente (#337) ── */
.tc-runbtn{font-size:12.5px;font-weight:700;color:#fff;background:var(--tc-accent);border:1px solid var(--tc-accent);padding:7px 13px;border-radius:9px;cursor:pointer;}
.tc-runbtn:disabled{opacity:.5;cursor:default;}
.tc-btn-urg{background:#fff7ed;color:#c2410c;border-color:#fed7aa;}
.tc-btn-urg-on{background:#fb923c;color:#fff;border-color:#f97316;}
.tc-btn-tts-on{background:var(--tc-accent);color:#fff;border-color:var(--tc-accent);}
.tc-disparo-msg{margin-top:12px;padding:9px 14px;border-radius:10px;font-size:12.8px;background:rgba(45,212,191,.12);color:#0f766e;border:1px solid #99f6e4;}
.tc-alert{margin-top:12px;padding:10px 14px;border-radius:10px;font-size:12.8px;line-height:1.4;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}
.tc-alert-soft{background:#fffbeb;color:#b45309;border-color:#fde68a;}
.tc-livelog{margin-top:12px;background:#0a1120;border:1px solid #1e293b;border-radius:12px;overflow:hidden;}
.tc-livelog-head{display:flex;align-items:center;justify-content:space-between;padding:9px 14px;border-bottom:1px solid #1e293b;font-size:12px;color:#8b97ab;}
.tc-livelog-tag{display:inline-flex;align-items:center;gap:7px;color:#4ade80;font-weight:700;}
.tc-livelog-pre{margin:0;padding:12px 14px;font-family:ui-monospace,"SF Mono",Menlo,Consolas,monospace;font-size:12px;line-height:1.55;color:#b8c4d8;height:240px;overflow:auto;white-space:pre-wrap;word-break:break-word;}
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
.tc-s{font-size:12px;color:var(--tc-muted);margin-top:2px;}
.tc-badge{display:inline-block;font-size:10px;font-weight:700;padding:1px 7px;border-radius:999px;vertical-align:middle;white-space:nowrap;}
.tc-badge-dec{background:#fffbeb;color:#b45309;}
.tc-btn-voz{background:#fff;color:var(--tc-accent);border-color:var(--tc-line);}
.tc-btn-ver{background:#f0fdfa;color:#0f766e;border-color:#99f6e4;}
.tc-idnum{color:var(--tc-accent);font-weight:700;}
.tc-inbox-body{flex:1;min-width:0;}
.tc-opts{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.tc-noopts{margin-top:8px;font-size:12px;font-style:italic;color:var(--tc-muted);}
/* #477 — tarjeta compacta nivel C */
.tc-t-compact{display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:12px;}
.tc-titulo-c{font-size:14px;font-weight:700;line-height:1.35;margin-top:4px;}
.tc-modulo-chip{font-size:11px;font-weight:600;color:var(--tc-muted);background:var(--tc-line);border-radius:6px;padding:1px 8px;}
.tc-dec-label{font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:var(--tc-muted);margin-bottom:2px;}
.tc-desc-wrap{margin-top:10px;}
.tc-desc-toggle{font-size:12px;font-weight:600;color:var(--tc-accent);background:none;border:none;padding:0;cursor:pointer;}
.tc-desc-toggle:hover{text-decoration:underline;}
.tc-desc-panel{margin-top:8px;padding:10px 12px;border:1px solid var(--tc-line);border-radius:8px;background:#f8fafc;}
.tc-desc-block{margin-bottom:10px;}
.tc-desc-block:last-child{margin-bottom:0;}
.tc-desc-block b{font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--tc-muted);}
.tc-desc-block p{margin:3px 0 0;font-size:12.5px;line-height:1.5;color:var(--tc-ink);}
.tc-desc-pre{white-space:pre-wrap;}
/* #432 — brief multi-pregunta: cada pregunta un bloque con su texto + fase + opciones. */
.tc-preguntas{margin-top:8px;display:flex;flex-direction:column;gap:12px;}
.tc-pregunta{border-left:3px solid var(--tc-line);padding-left:10px;}
.tc-pregunta-t{font-size:13px;font-weight:600;color:var(--tc-ink);}
.tc-pregunta-num{color:var(--tc-accent);margin-right:4px;}
.tc-fase-badge{display:inline-block;font-size:10px;font-weight:700;color:#3730a3;background:#e0e7ff;border-radius:6px;padding:1px 6px;margin-left:6px;vertical-align:middle;}
.tc-opt{font-size:12px;padding:5px 10px;border-radius:8px;border:1px solid var(--tc-line);background:#fff;color:var(--tc-ink);cursor:pointer;text-align:left;}
.tc-opt:hover{border-color:var(--tc-accent);}
.tc-opt-sel{border-color:var(--tc-accent);background:#f0fdfa;color:#0f766e;font-weight:600;box-shadow:0 0 0 2px #0d948822;}
/* #431 — RECOMENDADA (verde de marca) distinta de SELECCIONADA (borde/anillo). Pueden coexistir. */
.tc-opt-rec{border-color:#16a34a;background:#f0fdf4;}
.tc-opt-badge{display:inline-block;font-size:9px;font-weight:800;letter-spacing:.03em;color:#166534;background:#bbf7d0;border-radius:6px;padding:1px 5px;margin-right:6px;vertical-align:middle;}
.tc-opt-sel.tc-opt-rec{box-shadow:0 0 0 2px #0d948844;}
.tc-aviso{font-size:12px;font-weight:600;padding:2px 8px;border-radius:6px;}
.tc-aviso-err{color:#b91c1c;background:#fee2e2;}
.tc-aviso-ok{color:#166534;background:#dcfce7;}
.tc-aviso-warn{color:#92400e;background:#fef3c7;}
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
/* [BUG][UI/UX][TORRE] Actividad reciente navegable */
.tc-ev-click{cursor:pointer;border-radius:8px;transition:background .12s;}
.tc-ev-click:hover{background:#f8fafc;}
.tc-ev-main{flex:1 1 auto;min-width:0;}
.tc-ev-meta{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:4px 0 2px;}
.tc-ev-badge{font-size:10.5px;font-weight:700;padding:2px 7px;border-radius:999px;white-space:nowrap;}
.tc-ev-estado{background:#eff6ff;color:#1d4ed8;}
.tc-ev-ubic{background:#fefce8;color:#a16207;}
.tc-ev-next{font-size:11px;color:var(--tc-muted);}
.tc-ev-actions{flex:0 0 auto;display:flex;flex-direction:column;gap:6px;align-items:flex-end;}
@media (max-width:640px){.tc-ev{flex-wrap:wrap;}.tc-ev-actions{flex-direction:row;width:100%;justify-content:flex-end;}}
@keyframes tc-pulse{0%{box-shadow:0 0 0 0 rgba(59,130,246,.45);}100%{box-shadow:0 0 0 10px rgba(59,130,246,0);}}
.tc-highlight{background:#eff6ff!important;border-radius:8px;animation:tc-pulse 1s ease-out 2;outline:2px solid #3b82f6;outline-offset:2px;}
/* FASE 1 — Cambios para que Irving pruebe */
.tc-val{border:1px solid var(--tc-line);border-radius:10px;padding:11px 13px;margin-bottom:10px;background:#fbfefc;}
.tc-val-head{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:14px;}
.tc-val-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;margin:8px 0;font-size:12.5px;line-height:1.4;}
.tc-val-grid > div{min-width:0;}
.tc-val-k{display:block;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.02em;color:var(--tc-muted);}
.tc-val-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
.tc-btn-warn{background:#fffbeb;color:#b45309;border-color:#fde68a;}
@media (max-width:640px){.tc-val-grid{grid-template-columns:1fr;}}
.tc-dark .tc-val{background:rgba(74,222,128,.05);border-color:#243449;}
.tc-dark .tc-btn-warn{background:rgba(251,191,36,.12);color:#fbbf24;border-color:#5b4b1f;}
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
.tc-tag-modelo{font-weight:600;color:var(--tc-accent);}

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
.tc-dark .tc-badge-dec{background:rgba(251,191,36,.15);color:#fbbf24;}
.tc-dark .tc-btn-voz{background:#0f172a;color:#2dd4bf;border-color:#2a3550;}
.tc-dark .tc-btn-ver{background:rgba(45,212,191,.12);color:#2dd4bf;border-color:#265b57;}
.tc-dark .tc-ev-click:hover{background:rgba(148,163,184,.10);}
.tc-dark .tc-ev-estado{background:rgba(96,165,250,.15);color:#60a5fa;}
.tc-dark .tc-ev-ubic{background:rgba(251,191,36,.15);color:#fbbf24;}
.tc-dark .tc-highlight{background:rgba(59,130,246,.15)!important;outline-color:#3b82f6;}
.tc-dark .tc-idle{background:rgba(148,163,184,.15);color:#94a3b8;}
.tc-dark .tc-logbtn{background:#1c2740;color:#cbd5e1;border-color:#2a3550;}
.tc-dark .tc-logbtn-on{border-color:#2dd4bf;color:#2dd4bf;}
.tc-dark .tc-runbtn{background:#0f766e;border-color:#0f766e;color:#e8fffb;}
.tc-dark .tc-btn-urg{background:rgba(251,146,60,.14);color:#fdba74;border-color:#7c3a1d;}
.tc-dark .tc-btn-urg-on{background:#c2410c;color:#fff;border-color:#ea580c;}
.tc-dark .tc-disparo-msg{background:rgba(45,212,191,.12);color:#5eead4;border-color:#155e52;}
.tc-dark .tc-alert{background:rgba(248,113,113,.12);color:#f87171;border-color:#5b2b2b;}
.tc-dark .tc-alert-soft{background:rgba(251,191,36,.12);color:#fbbf24;border-color:#5b4a20;}
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
.tc-dark .tc-pregunta{border-left-color:#2a3550;}
.tc-dark .tc-modulo-chip{background:#1c2740;color:#8b97ab;}
.tc-dark .tc-desc-panel{background:#0f172a;border-color:#2a3550;}
.tc-dark .tc-fase-badge{color:#c7d2fe;background:rgba(99,102,241,.22);}
.tc-dark .tc-opt-rec{border-color:#22c55e;background:rgba(34,197,94,.12);}
.tc-dark .tc-opt-badge{color:#bbf7d0;background:rgba(34,197,94,.25);}
.tc-dark .tc-aviso-err{color:#fecaca;background:rgba(239,68,68,.18);}
.tc-dark .tc-aviso-ok{color:#bbf7d0;background:rgba(34,197,94,.18);}
.tc-dark .tc-aviso-warn{color:#fde68a;background:rgba(245,158,11,.18);}
.tc-dark .tc-coment{background:#0f172a;color:#e8edf6;border-color:#2a3550;}
.tc-dark .tc-coment::placeholder{color:#64748b;}
.tc-dark .tc-btn-ok{background:rgba(74,222,128,.14);color:#4ade80;border-color:#2b5b3b;}
.tc-dark .tc-btn-no{background:rgba(248,113,113,.14);color:#f87171;border-color:#5b2b2b;}
.tc-dark .tc-btn-mut{background:#1c2740;color:#cbd5e1;border-color:#2a3550;}
.tc-dark .tc-btn-seg{background:rgba(96,165,250,.14);color:#60a5fa;border-color:#2b3f5b;}
.tc-dark .tc-seg{border-color:#2a3550;}
.tc-dark .tc-seg-in,.tc-dark .tc-seg-sel{background:#0f172a;color:#e8edf6;border-color:#2a3550;}
/* ── Cola ejecutable + prioridad (#348) ── */
.tc-execsum{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:13px;font-weight:700;margin:-4px 0 4px;}
.tc-execsum-auto{color:#047857;} .tc-execsum-dec{color:#b45309;} .tc-execsum-sin{color:var(--tc-muted);font-weight:600;}
.tc-execsum-sep{color:var(--tc-muted);font-weight:400;}
.tc-dark .tc-execsum-auto{color:#4ade80;} .tc-dark .tc-execsum-dec{color:#fbbf24;}
.tc-exec-item{display:flex;gap:10px;align-items:center;padding:9px 0;border-top:1px solid var(--tc-line);}
.tc-exec-item:first-of-type{border-top:none;}
.tc-exec-body{flex:1;min-width:0;font-size:13.5px;font-weight:600;line-height:1.3;}
.tc-exec-est{font-size:11px;font-weight:500;color:var(--tc-muted);margin-left:8px;}
.tc-prio{flex:0 0 auto;font-size:11px;font-weight:700;padding:2px 7px;border-radius:6px;}
.tc-prio-alta{background:#fef2f2;color:#b91c1c;} .tc-prio-media{background:#fffbeb;color:#b45309;}
.tc-prio-baja{background:#eff6ff;color:#1d4ed8;} .tc-prio-none{background:#f1f5f9;color:#475569;}
.tc-dark .tc-prio-alta{background:rgba(248,113,113,.15);color:#f87171;} .tc-dark .tc-prio-media{background:rgba(251,191,36,.15);color:#fbbf24;}
.tc-dark .tc-prio-baja{background:rgba(96,165,250,.15);color:#60a5fa;} .tc-dark .tc-prio-none{background:rgba(148,163,184,.15);color:#94a3b8;}

@media(max-width:820px){.tc-kpis{grid-template-columns:repeat(2,1fr)}.tc-grid{grid-template-columns:1fr}}
</style>
