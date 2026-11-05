<template>
  <li v-if="visible" class="pa-nodo">
    <div
      class="pa-fila"
      :class="{ 'pa-fila-enfocada': enfocado === clave, 'pa-fila-modulo': node.nivel === 'modulo' }"
      :style="{ paddingLeft: (profundidad * 18) + 'px' }"
      :data-clave="clave"
      @click="onClick"
    >
      <i
        v-if="node.tiene_hijos"
        class="bi pa-caret"
        :class="node.expandido ? 'bi-chevron-down' : 'bi-chevron-right'"
      ></i>
      <i v-else class="pa-caret pa-caret-vacio"></i>

      <span class="pa-titulo" :title="node.titulo">
        <span v-if="node.nivel !== 'modulo'" class="pa-id">#{{ node.id }}</span>
        {{ node.titulo }}
      </span>

      <!-- 2026-10-26 — el item cuelga de un proyecto de otro módulo (o de uno ya cerrado). -->
      <small v-if="node.nivel === 'epica' && node.origen_id" class="pa-origen" :title="node.origen_titulo">
        ↳ de #{{ node.origen_id }}
      </small>
      <small v-if="moduloDistinto" class="pa-modulo">{{ node.modulo }}</small>

      <!-- #9991165 — indicador de adjuntos: abre la ficha, donde están los enlaces de descarga. -->
      <a
        v-if="node.nivel !== 'modulo' && node.adjuntos_n"
        class="pa-adj"
        :href="'/roadmap/item/' + node.id"
        target="_blank"
        rel="noopener"
        :title="node.adjuntos_n + ' adjunto(s) — abre la ficha para descargarlos'"
        @click.stop
      >📎 {{ node.adjuntos_n }}</a>
      <!-- Solo mientras corre: un worker_sid que quedó de una vuelta vieja no es "está en wt-N". -->
      <span v-if="node.nivel !== 'modulo' && node.worker_sid && node.bucket === 'activo'" class="pa-terminal">
        <i class="bi bi-hdd-stack"></i> {{ node.worker_sid }}
      </span>

      <!--
        Globos y contadores van en <b>, no en <span>: dark_mode.scss pinta de blanco con !important
        todo <span> en modo oscuro y dejaba «Tu decisión»/«Trabajando» blanco sobre pastel.
      -->
      <b v-if="badge" class="pa-badge" :class="badge.clase">
        <i class="bi" :class="badge.icono"></i> {{ badge.texto }}
      </b>

      <button
        v-if="node.nivel !== 'modulo'"
        type="button"
        class="pa-seg-btn"
        :class="{ 'pa-seg-btn-activo': node.panelSeguimientoAbierto }"
        title="Por qué no avanza, qué decisión falta y meterlo al circuito"
        @click.stop="paToggleSeguimiento(node)"
      >
        <i class="bi bi-signpost-split"></i> Seguimiento
      </button>

      <b v-if="node.nivel === 'modulo'" class="pa-contadores" title="Trabajando · en cola · detenidos">
        <b class="pa-c pa-c-activo" v-if="node.contador_hijos.activos" title="Trabajando ahora">
          <i class="bi bi-play-fill"></i> {{ node.contador_hijos.activos }}
        </b>
        <b class="pa-c pa-c-cola" v-if="node.contador_hijos.en_cola" title="En cola">
          <i class="bi bi-hourglass-split"></i> {{ node.contador_hijos.en_cola }}
        </b>
        <b class="pa-c pa-c-detenido" v-if="node.contador_hijos.detenidos" title="Detenidos">
          <i class="bi bi-exclamation-octagon"></i> {{ node.contador_hijos.detenidos }}
        </b>
      </b>
    </div>

    <div v-if="node.cargando" class="pa-info-linea" :style="{ paddingLeft: ((profundidad + 1) * 18) + 'px' }">
      <span class="spinner-border spinner-border-sm"></span> Cargando…
    </div>
    <div v-if="node.errorHijos" class="pa-info-linea pa-error" :style="{ paddingLeft: ((profundidad + 1) * 18) + 'px' }">
      <i class="bi bi-exclamation-triangle"></i> {{ node.errorHijos }}
    </div>
    <div
      v-if="node.expandido && node.hijos && node.hijos.length === 0 && !node.cargando && !node.errorHijos"
      class="pa-info-linea pa-vacio"
      :style="{ paddingLeft: ((profundidad + 1) * 18) + 'px' }"
    >
      Sin sub-items.
    </div>

    <ul v-if="node.expandido && node.hijos && node.hijos.length" class="pa-hijos">
      <torre-arbol-nodo
        v-for="hijo in node.hijos"
        :key="hijo.nivel + ':' + hijo.id"
        :node="hijo"
        :profundidad="profundidad + 1"
      />
    </ul>

    <button
      v-if="node.expandido && node.hijos && node.total > node.hijos.length"
      class="pa-cargar-mas"
      :style="{ marginLeft: ((profundidad + 1) * 18) + 'px' }"
      type="button"
      :disabled="node.cargando"
      @click.stop="paCargarMas(node)"
    >
      Cargar más ({{ node.hijos.length }} / {{ node.total }})
    </button>

    <!--
      2026-10-26 (Irving) — panel «Seguimiento»: reemplaza el placeholder de "Tu decisión" (#9990973).
      Dice por qué no avanza y quién lo tiene que mover, muestra las preguntas con la recomendada
      marcada y, si se puede, lo mete al circuito con la opción elegida.
    -->
    <div
      v-if="node.panelSeguimientoAbierto"
      class="pa-panel pa-seg"
      :style="{ marginLeft: ((profundidad + 1) * 18) + 'px' }"
    >
      <div v-if="node.panelSeguimientoCargando && !seg" class="pa-info-linea">
        <span class="spinner-border spinner-border-sm"></span> Cargando seguimiento…
      </div>
      <div v-else-if="node.panelSeguimientoError" class="pa-info-linea pa-error">
        <i class="bi bi-exclamation-triangle"></i> {{ node.panelSeguimientoError }}
      </div>
      <div v-else-if="seg" class="pa-panel-body">
        <div class="pa-seg-cabeza">
          <b class="pa-badge" :class="claseSituacion">{{ seg.titulo }}</b>
          <b class="pa-seg-quien">{{ textoQuien }}</b>
        </div>
        <p class="pa-panel-texto">{{ seg.explicacion }}</p>

        <div v-if="seg.dependencias.length" class="pa-panel-bloque">
          <b class="pa-panel-etq">Espera a</b>
          <ul class="pa-seg-lista">
            <li v-for="d in seg.dependencias" :key="d.id" class="pa-seg-fila" :title="d.explicacion">
              <div class="pa-seg-fila-texto">
                <a :href="'/roadmap/item/' + d.id" target="_blank" rel="noopener" @click.stop>#{{ d.id }}</a>
                {{ d.titulo }} <small class="pa-seg-estado">· {{ d.resumen }}</small>
              </div>
              <button
                v-if="d.accion && seg.puede_actuar"
                type="button"
                class="pa-seg-mini"
                :disabled="node.seguimientoEnviando"
                @click.stop="paAccionSeguimiento(node, d.accion)"
              >{{ d.accion.etiqueta }}</button>
            </li>
          </ul>
        </div>

        <div v-if="seg.hijos_abiertos.length" class="pa-panel-bloque">
          <b class="pa-panel-etq">Sub-items abiertos</b>
          <ul class="pa-seg-lista">
            <li v-for="h in seg.hijos_abiertos" :key="h.id" class="pa-seg-fila" :title="h.explicacion">
              <div class="pa-seg-fila-texto">
                <a :href="'/roadmap/item/' + h.id" target="_blank" rel="noopener" @click.stop>#{{ h.id }}</a>
                {{ h.titulo }} <small class="pa-seg-estado">· {{ h.resumen }}</small>
              </div>
              <button
                v-if="h.accion && seg.puede_actuar"
                type="button"
                class="pa-seg-mini"
                :disabled="node.seguimientoEnviando"
                @click.stop="paAccionSeguimiento(node, h.accion)"
              >{{ h.accion.etiqueta }}</button>
            </li>
            <li v-if="seg.hijos_abiertos_total > seg.hijos_abiertos.length" class="pa-seg-estado">
              y {{ seg.hijos_abiertos_total - seg.hijos_abiertos.length }} más
            </li>
          </ul>
        </div>

        <div v-for="p in preguntasConOpciones" :key="p.id" class="pa-panel-bloque pa-decision-pregunta">
          <p class="pa-decision-pregunta-texto">{{ p.pregunta || "Decisión" }}</p>
          <label
            v-for="op in p.opciones"
            :key="op.clave"
            class="pa-opcion"
            :class="{ 'pa-opcion-recomendada': op.recomendada, 'pa-opcion-elegida': node.seguimientoEleccion[p.id] === op.clave }"
            @click.stop
          >
            <input
              v-model="node.seguimientoEleccion[p.id]"
              type="radio"
              :name="'seg-' + node.id + '-' + p.id"
              :value="op.clave"
              :disabled="!puedeElegir"
            />
            <div class="pa-opcion-cuerpo">
              <div class="pa-opcion-texto">{{ op.texto }}</div>
              <div class="pa-opcion-meta">
                <b v-if="op.recomendada" class="pa-badge pa-badge-verde"><i class="bi bi-star-fill"></i> Recomendada</b>
                <small v-if="op.confianza">confianza {{ op.confianza }}</small>
                <small v-if="op.reversible === true">reversible</small>
                <small v-else-if="op.reversible === false">no reversible</small>
              </div>
            </div>
          </label>
          <small v-if="p.opcion_elegida" class="pa-seg-nota">Ya tiene una opción elegida; puedes cambiarla antes de meterlo.</small>
        </div>

        <p v-if="seg.aviso_meter" class="pa-seg-aviso"><i class="bi bi-exclamation-triangle"></i> {{ seg.aviso_meter }}</p>

        <div class="pa-seg-acciones">
          <template v-if="seg.puede_actuar">
            <button
              v-for="a in seg.acciones"
              :key="a.tipo"
              type="button"
              :class="a.tipo === 'forzar' ? 'pa-seg-link pa-seg-forzar' : 'pa-seg-meter'"
              :disabled="node.seguimientoEnviando"
              @click.stop="paAccionSeguimiento(node, a)"
            >
              <span v-if="node.seguimientoEnviando && a.tipo !== 'forzar'" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bi" :class="ICONOS_ACCION[a.tipo]"></i> {{ a.etiqueta }}
            </button>
          </template>
          <small v-else-if="seg.acciones.length" class="pa-seg-nota">Tu usuario no tiene permiso para decidir items.</small>
          <a v-if="seg.enlaces.terminales" :href="seg.enlaces.terminales" class="pa-seg-link" @click.stop>
            <i class="bi bi-hdd-stack"></i> Ver en Terminales
          </a>
          <a v-if="seg.enlaces.integracion" :href="seg.enlaces.integracion" class="pa-seg-link" @click.stop>
            <i class="bi bi-git"></i> Ver diff en Integración
          </a>
          <a :href="seg.enlaces.ficha" target="_blank" rel="noopener" class="pa-seg-link" @click.stop>
            <i class="bi bi-box-arrow-up-right"></i> Abrir ficha
          </a>
          <button type="button" class="pa-seg-link" :disabled="node.panelSeguimientoCargando" @click.stop="paRecargarSeguimiento(node)">
            <i class="bi bi-arrow-repeat"></i> Actualizar
          </button>
        </div>
        <p v-if="node.seguimientoMsg" class="pa-seg-msg" :class="'pa-seg-msg-' + node.seguimientoMsgTipo">
          {{ node.seguimientoMsg }}
        </p>
      </div>
    </div>

    <!--
      #9990971 (CIRC-09 Fase 4) — panel de lectura de una fila "Trabajando": pipeline de 6 fases
      (solo triage/decisión/rama tienen columna real en BD hoy — el resto se muestra "sin dato",
      nunca simulado), reloj de la vuelta, rama y últimas líneas del log.
    -->
    <div
      v-if="esTrabajando && node.panelTrabajandoAbierto"
      class="pa-panel"
      :style="{ marginLeft: ((profundidad + 1) * 18) + 'px' }"
    >
      <div v-if="node.panelTrabajandoCargando" class="pa-info-linea">
        <span class="spinner-border spinner-border-sm"></span> Cargando…
      </div>
      <div v-else-if="node.panelTrabajandoError" class="pa-info-linea pa-error">
        <i class="bi bi-exclamation-triangle"></i> {{ node.panelTrabajandoError }}
      </div>
      <div v-else-if="node.panelTrabajandoData" class="pa-panel-body">
        <div class="pa-panel-meta">
          <span v-if="node.panelTrabajandoData.segundos_transcurridos != null" class="pa-panel-chip">
            <i class="bi bi-stopwatch"></i> {{ fmtClock(node.panelTrabajandoData.segundos_transcurridos) }}
          </span>
          <span v-if="node.panelTrabajandoData.branch" class="pa-panel-chip" :title="node.panelTrabajandoData.branch">
            <i class="bi bi-git"></i> {{ node.panelTrabajandoData.branch }}
          </span>
          <span v-if="node.panelTrabajandoData.worker_sid" class="pa-panel-chip">
            <i class="bi bi-hdd-stack"></i> {{ node.panelTrabajandoData.worker_sid }}
          </span>
        </div>

        <div class="pa-stepper">
          <template v-for="(f, i) in node.panelTrabajandoData.fases" :key="f.key">
            <div class="pa-step" :class="{ 'pa-step-done': f.alcanzada, 'pa-step-sindato': f.alcanzada === null }">
              <b class="pa-step-dot">{{ f.alcanzada ? '●' : '○' }}</b>
              <span class="pa-step-label">{{ f.label }}</span>
              <span v-if="f.at" class="pa-step-at">{{ fmtHora(f.at) }}</span>
            </div>
            <span v-if="i < node.panelTrabajandoData.fases.length - 1" class="pa-step-line"></span>
          </template>
        </div>
        <p v-if="!node.panelTrabajandoData.fase_actual_conocida" class="pa-panel-nota">
          <i class="bi bi-info-circle"></i> {{ node.panelTrabajandoData.fase_actual_nota }}
        </p>

        <div v-if="(node.panelTrabajandoData.log_tail || []).length" class="pa-panel-bloque">
          <span class="pa-panel-etq">Últimas líneas del log</span>
          <div v-for="(l, i) in node.panelTrabajandoData.log_tail" :key="i" class="pa-log-linea">
            <span class="pa-log-hora">{{ fmtHora(l.ts) }}</span>
            <span class="pa-log-por">{{ l.por || '—' }}</span>
            <span class="pa-log-accion">{{ l.accion }}</span>
            <span v-if="l.detalle" class="pa-log-detalle" :title="l.detalle">{{ l.detalle }}</span>
          </div>
        </div>
      </div>
    </div>

    <!--
      #9990971 (CIRC-09 Fase 4) — panel de lectura de una fila "Decidido sin ti": qué decidió el
      autopilot/revisor, con qué confianza, si es reversible, cuándo y en qué terminal.
    -->
    <div
      v-if="esDecididoSinTi && node.panelDecididoAbierto"
      class="pa-panel"
      :style="{ marginLeft: ((profundidad + 1) * 18) + 'px' }"
    >
      <div v-if="node.panelDecididoCargando" class="pa-info-linea">
        <span class="spinner-border spinner-border-sm"></span> Cargando…
      </div>
      <div v-else-if="node.panelDecididoError" class="pa-info-linea pa-error">
        <i class="bi bi-exclamation-triangle"></i> {{ node.panelDecididoError }}
      </div>
      <div v-else-if="node.panelDecididoData && !node.panelDecididoData.decidido" class="pa-info-linea pa-vacio">
        Sin decisión automática registrada para este item.
      </div>
      <div v-else-if="node.panelDecididoData && node.panelDecididoData.decision" class="pa-panel-body">
        <div class="pa-panel-bloque">
          <span class="pa-panel-etq">Qué decidió</span>
          <p class="pa-panel-texto">{{ node.panelDecididoData.decision.que_decidio }}</p>
        </div>
        <div class="pa-panel-bloque">
          <span class="pa-panel-etq">Por qué</span>
          <p class="pa-panel-texto">{{ node.panelDecididoData.decision.porque }}</p>
        </div>
        <div class="pa-panel-meta">
          <span v-if="node.panelDecididoData.decision.confianza" class="pa-panel-chip">
            <i class="bi bi-speedometer2"></i> confianza {{ node.panelDecididoData.decision.confianza }}
          </span>
          <span class="pa-panel-chip">
            <i class="bi" :class="node.panelDecididoData.decision.reversible ? 'bi-arrow-counterclockwise' : 'bi-lock'"></i>
            {{ node.panelDecididoData.decision.reversible ? 'reversible' : 'no reversible' }}
          </span>
          <span v-if="node.panelDecididoData.decision.terminal" class="pa-panel-chip">
            <i class="bi bi-hdd-stack"></i> {{ node.panelDecididoData.decision.terminal }}
          </span>
          <span v-if="node.panelDecididoData.decision.cuando" class="pa-panel-chip">
            <i class="bi bi-clock-history"></i> {{ fmtHora(node.panelDecididoData.decision.cuando) }}
          </span>
        </div>
      </div>
    </div>
  </li>
</template>

<script>
import { computed, inject } from "vue";

/**
 * #9990970 (CIRC-09 Fase 3) — fila recursiva del árbol Módulo → Épica → Item → Sub-item.
 * La anidación de épicas es recursiva de verdad (una épica puede contener otra épica —
 * caso real MR-23 vía origen_item_id encadenado). En Vue 3, un componente recursivo se
 * resuelve SOLO por su `name` (Vue resuelve <torre-arbol-nodo> en su propio template por
 * nombre) — NO debe importarse a sí mismo: eso crea una dependencia circular
 * (`__WEBPACK_DEFAULT_EXPORT__` accedido antes de inicializar) que tumba el bundle entero.
 */
export default {
    name: "TorreArbolNodo",
    props: {
        node: { type: Object, required: true },
        profundidad: { type: Number, default: 0 },
    },
    setup(props) {
        const paToggle = inject("paToggle");
        const paCargarMas = inject("paCargarMas");
        const paEnfocar = inject("paEnfocar");
        const paEsVisible = inject("paEsVisible");
        const paEstadoVisual = inject("paEstadoVisual");
        const paEnfocado = inject("paEnfocado");
        const paToggleSeguimiento = inject("paToggleSeguimiento");
        const paRecargarSeguimiento = inject("paRecargarSeguimiento");
        const paAccionSeguimiento = inject("paAccionSeguimiento");
        const ICONOS_ACCION = { meter: "bi-send", mergear: "bi-git", forzar: "bi-skip-forward" };
        const paToggleTrabajando = inject("paToggleTrabajando");
        const paToggleDecidido = inject("paToggleDecidido");

        const clave = computed(() => props.node.nivel + ":" + props.node.id);
        const visible = computed(() => paEsVisible(props.node));
        const badge = computed(() => paEstadoVisual(props.node));
        const enfocado = computed(() => paEnfocado.value);
        // La marca "Tu decisión" abre el panel de seguimiento al hacer clic en la fila.
        const esTuDecision = computed(() => !!badge.value && badge.value.texto === "Tu decisión");
        // #9990971 (CIRC-09 Fase 4) — "Trabajando" y "Decidido sin ti" abren sus propios paneles.
        const esTrabajando = computed(() => !!badge.value && badge.value.texto === "Trabajando");
        const esDecididoSinTi = computed(() => !!badge.value && badge.value.texto === "Decidido sin ti");
        const seg = computed(() => props.node.panelSeguimientoData);
        const preguntasConOpciones = computed(() =>
            ((seg.value && seg.value.preguntas) || []).filter((p) => (p.opciones || []).length));
        const puedeElegir = computed(() => !!seg.value && seg.value.puede_meter && seg.value.puede_actuar);
        const claseSituacion = computed(() => ({
            trabajando: "pa-badge-verde", cerrado: "pa-badge-verde", en_cola: "pa-badge-azul",
            decision: "pa-badge-ambar", espera_merge: "pa-badge-violeta",
            dependencia: "pa-badge-gris", paraguas: "pa-badge-gris", colision: "pa-badge-gris",
            agendado: "pa-badge-gris", descartado: "pa-badge-gris",
        }[seg.value && seg.value.situacion] || "pa-badge-rojo"));
        const textoQuien = computed(() => ({
            tu: "Lo tienes que mover tú", circuito: "Lo mueve el circuito",
            otro_item: "Depende de otro item", nadie: "Nada pendiente",
        }[seg.value && seg.value.quien] || ""));
        // Chip de módulo cuando el item es de un módulo distinto al de la rama del árbol donde aparece.
        const moduloDistinto = computed(() => props.node.nivel !== "modulo" && !!props.node.modulo
            && String(props.node.modulo).toLowerCase() !== String(props.node.moduloPadre || "").toLowerCase());

        function onClick() {
            paEnfocar(props.node);
            if (props.node.tiene_hijos) paToggle(props.node);
            if (esTuDecision.value && !props.node.panelSeguimientoAbierto) paToggleSeguimiento(props.node);
            if (esTrabajando.value) paToggleTrabajando(props.node);
            if (esDecididoSinTi.value) paToggleDecidido(props.node);
        }

        /** #9990971 — "Xm Ys" / "Xh Ym", igual que TorreTrabajandoAhora.vue::fmtClock. */
        function fmtClock(segundos) {
            if (segundos == null) return "—";
            if (segundos < 60) return `${segundos}s`;
            const m = Math.floor(segundos / 60), s = segundos % 60;
            if (m < 60) return `${m}m ${String(s).padStart(2, "0")}s`;
            const h = Math.floor(m / 60);
            return `${h}h ${String(m % 60).padStart(2, "0")}m`;
        }

        /** #9990971 — "HH:MM" de un ISO, igual que TorreTrabajandoAhora.vue::hhmm. */
        function fmtHora(iso) {
            if (!iso) return "";
            const d = new Date(iso);
            return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
        }

        return {
            clave, visible, badge, enfocado, onClick, paCargarMas, esTuDecision,
            esTrabajando, esDecididoSinTi, fmtClock, fmtHora,
            paToggleSeguimiento, paRecargarSeguimiento, paAccionSeguimiento, ICONOS_ACCION,
            seg, preguntasConOpciones, puedeElegir, claseSituacion, textoQuien, moduloDistinto,
        };
    },
};
</script>

<style scoped>
.pa-nodo{ list-style:none; }
.pa-hijos{ margin:0; padding:0; }

.pa-fila{
  display:flex; align-items:center; gap:8px; padding:4px 8px; border-radius:6px;
  cursor:pointer; font-size:13px;
}
.pa-fila:hover{ background:var(--pa-hover, rgba(13,148,136,.08)); }
.pa-fila-modulo{ font-weight:700; }
.pa-fila-enfocada{ background:var(--pa-focus, rgba(13,148,136,.16)); }

.pa-caret{ width:14px; text-align:center; flex:0 0 auto; color:var(--pa-muted,#64748b); }
.pa-caret-vacio{ visibility:hidden; }

.pa-titulo{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1 1 auto; min-width:80px; }
.pa-id{ color:var(--pa-muted,#64748b); font-weight:600; margin-right:4px; }

.pa-adj{flex:0 0 auto;font-size:11px;font-weight:700;color:var(--pa-ink,#0f172a);background:var(--pa-chip-bg,#f1f5f9);border-radius:6px;padding:1px 6px;text-decoration:none;white-space:nowrap;}
.pa-adj:hover{text-decoration:underline;}
.pa-terminal{
  flex:0 0 auto; font-size:11px; color:var(--pa-muted,#64748b);
  background:var(--pa-chip-bg,#f1f5f9); border-radius:6px; padding:1px 6px; white-space:nowrap;
}

.pa-badge{
  flex:0 0 auto; font-size:11px; font-weight:700; border-radius:6px; padding:2px 8px; white-space:nowrap;
}
.pa-badge-verde{ background:#dcfce7; color:#166534; }
.pa-badge-ambar{ background:#fef3c7; color:#92400e; }
.pa-badge-violeta{ background:#ede9fe; color:#5b21b6; }
.pa-badge-rojo{ background:#fee2e2; color:#991b1b; }
.pa-badge-azul{ background:#dbeafe; color:#1e40af; }
.pa-badge-gris{ background:#e5e7eb; color:#374151; }
/* Modo oscuro: fondo translúcido y texto claro del mismo tono (el pastel deslumbraba y, con la regla
   global de <span> blanco, ni siquiera se leía). */
.pa-dark .pa-badge-verde{ background:rgba(34,197,94,.18); color:#86efac; }
.pa-dark .pa-badge-ambar{ background:rgba(245,158,11,.2); color:#fcd34d; }
.pa-dark .pa-badge-violeta{ background:rgba(139,92,246,.22); color:#c4b5fd; }
.pa-dark .pa-badge-rojo{ background:rgba(239,68,68,.2); color:#fca5a5; }
.pa-dark .pa-badge-azul{ background:rgba(59,130,246,.2); color:#93c5fd; }
.pa-dark .pa-badge-gris{ background:rgba(148,163,184,.18); color:#cbd5e1; }

.pa-origen{ flex:0 0 auto; font-size:11px; color:var(--pa-muted,#64748b); white-space:nowrap; }
.pa-modulo{
  flex:0 0 auto; font-size:11px; color:var(--pa-muted,#64748b); white-space:nowrap;
  background:var(--pa-chip-bg,#f1f5f9); border-radius:6px; padding:1px 6px;
}

.pa-seg-btn{
  flex:0 0 auto; font-size:11px; font-weight:600; white-space:nowrap; cursor:pointer;
  border:1px solid var(--pa-line,#e5e7eb); background:var(--pa-surface,#fff); color:var(--pa-accent,#0d9488);
  border-radius:6px; padding:1px 8px;
}
.pa-seg-btn:hover, .pa-seg-btn-activo{ background:var(--pa-accent,#0d9488); border-color:var(--pa-accent,#0d9488); color:#fff; }
.pa-dark .pa-seg-btn:hover, .pa-dark .pa-seg-btn-activo{ color:#0b1220; }

.pa-contadores{ display:flex; gap:8px; flex:0 0 auto; font-size:11px; }
.pa-c{ display:inline-flex; align-items:center; gap:3px; font-weight:700; }
.pa-c-activo{ color:#16a34a; }
.pa-dark .pa-c-activo{ color:#4ade80; }
.pa-c-cola{ color:var(--pa-muted,#64748b); }
.pa-c-detenido{ color:var(--pa-danger,#dc2626); }

.pa-info-linea{ font-size:12px; color:var(--pa-muted,#64748b); padding:3px 8px; }
.pa-error{ color:var(--pa-danger,#dc2626); }
.pa-vacio{ font-style:italic; }

.pa-cargar-mas{
  font-size:12px; border:1px dashed var(--pa-line,#e5e7eb); background:transparent; color:var(--pa-accent,#0d9488);
  border-radius:6px; padding:3px 10px; margin:2px 0 6px; cursor:pointer;
}

.pa-decision-pregunta{ margin-bottom:6px; }
.pa-decision-pregunta-texto{ margin:0 0 6px; font-size:13px; font-weight:600; }

/* 2026-10-26 — panel «Seguimiento». */
.pa-seg-cabeza{ display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:6px; }
.pa-seg-quien{ font-size:12px; font-weight:600; color:var(--pa-muted,#64748b); }
.pa-seg-lista{ margin:0; padding-left:18px; font-size:12px; }
.pa-seg-fila{ display:flex; gap:8px; align-items:center; justify-content:space-between; padding:2px 0; }
.pa-seg-fila-texto{ flex:1 1 auto; min-width:0; }
.pa-seg-mini{
  flex:0 0 auto; font-size:11px; font-weight:700; white-space:nowrap; cursor:pointer;
  border:1px solid var(--pa-accent,#0d9488); background:transparent; color:var(--pa-accent,#0d9488);
  border-radius:6px; padding:1px 8px;
}
.pa-seg-mini:hover{ background:var(--pa-accent,#0d9488); color:#fff; }
.pa-dark .pa-seg-mini:hover{ color:#0b1220; }
.pa-seg-mini:disabled{ opacity:.6; cursor:default; }
.pa-seg-forzar{ color:var(--pa-warn,#d97706); border-color:var(--pa-warn,#d97706); }
.pa-seg-lista a{ color:var(--pa-accent,#0d9488); font-weight:700; }
.pa-seg-estado{ color:var(--pa-muted,#64748b); }
.pa-opcion{
  display:flex; gap:8px; align-items:flex-start; margin:0 0 6px; padding:6px 8px; cursor:pointer;
  font-size:12px; font-weight:400; border:1px solid var(--pa-line,#e5e7eb); border-radius:8px;
  background:var(--pa-surface,#fff); color:var(--pa-ink,#0f172a);
}
.pa-opcion input{ margin-top:3px; flex:0 0 auto; }
.pa-opcion-cuerpo{ flex:1 1 auto; min-width:0; }
.pa-opcion-meta{ display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-top:4px; }
.pa-opcion-meta small{ color:var(--pa-muted,#64748b); }
.pa-opcion-recomendada{ border-color:#16a34a; }
.pa-dark .pa-opcion-recomendada{ border-color:#4ade80; }
.pa-opcion-elegida{ box-shadow:inset 0 0 0 2px var(--pa-accent,#0d9488); }
.pa-seg-aviso{ margin:4px 0; font-size:12px; color:var(--pa-warn,#d97706); }
.pa-seg-acciones{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:8px; }
.pa-seg-meter{
  display:inline-flex; gap:6px; align-items:center; border:none; border-radius:8px; padding:5px 12px;
  font-size:12px; font-weight:700; cursor:pointer; background:var(--pa-accent,#0d9488); color:#fff;
}
.pa-dark .pa-seg-meter{ color:#0b1220; }
.pa-seg-meter:disabled{ opacity:.6; cursor:default; }
.pa-seg-link{
  display:inline-flex; gap:4px; align-items:center; font-size:12px; cursor:pointer; text-decoration:none;
  color:var(--pa-accent,#0d9488); background:transparent; border:1px solid var(--pa-line,#e5e7eb);
  border-radius:8px; padding:4px 10px;
}
.pa-seg-link:disabled{ opacity:.6; cursor:default; }
.pa-seg-nota{ display:block; font-size:11px; color:var(--pa-muted,#64748b); }
.pa-seg-msg{ margin:8px 0 0; font-size:12px; font-weight:600; }
.pa-seg-msg-ok{ color:#15803d; }
.pa-dark .pa-seg-msg-ok{ color:#4ade80; }
.pa-seg-msg-error{ color:var(--pa-danger,#dc2626); }

/* #9990971 (CIRC-09 Fase 4) — paneles de lectura "Trabajando" / "Decidido sin ti". */
.pa-panel{
  margin:2px 8px 8px 0; padding:8px 10px; border-radius:8px;
  background:var(--pa-chip-bg,#f1f5f9); border:1px solid var(--pa-line,#e5e7eb);
}
.pa-panel-meta{ display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
.pa-panel-chip{
  display:inline-flex; align-items:center; gap:4px; font-size:11px; color:var(--pa-muted,#64748b);
  background:var(--pa-surface,#fff); border:1px solid var(--pa-line,#e5e7eb); border-radius:6px; padding:2px 8px;
}
.pa-panel-bloque{ margin-bottom:8px; }
.pa-panel-bloque:last-child{ margin-bottom:0; }
.pa-panel-etq{
  display:block; font-size:11px; font-weight:700; text-transform:uppercase;
  color:var(--pa-muted,#64748b); margin-bottom:4px;
}
.pa-panel-texto{ margin:0; font-size:13px; }
.pa-panel-nota{ margin:6px 0 0; font-size:11px; color:var(--pa-muted,#64748b); }

.pa-stepper{ display:flex; align-items:center; flex-wrap:wrap; gap:2px; margin-bottom:2px; }
.pa-step{ display:flex; flex-direction:column; align-items:center; font-size:10px; min-width:56px; }
.pa-step-dot{ font-size:14px; line-height:1; color:var(--pa-muted,#64748b); }
.pa-step-done .pa-step-dot{ color:var(--pa-accent,#0d9488); }
.pa-step-sindato .pa-step-dot{ color:var(--pa-line,#e5e7eb); }
.pa-step-label{ margin-top:2px; text-align:center; }
.pa-step-at{ color:var(--pa-muted,#64748b); font-size:9px; }
.pa-step-line{ flex:1 1 8px; height:1px; background:var(--pa-line,#e5e7eb); min-width:8px; margin-bottom:14px; }

.pa-log-linea{
  display:flex; gap:6px; font-size:11px; padding:2px 0; border-bottom:1px dashed var(--pa-line,#e5e7eb);
  align-items:baseline;
}
.pa-log-linea:last-child{ border-bottom:none; }
.pa-log-hora{ color:var(--pa-muted,#64748b); flex:0 0 auto; }
.pa-log-por{ font-weight:700; flex:0 0 auto; }
.pa-log-accion{ flex:0 0 auto; }
.pa-log-detalle{
  color:var(--pa-muted,#64748b); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1 1 auto;
}
</style>
