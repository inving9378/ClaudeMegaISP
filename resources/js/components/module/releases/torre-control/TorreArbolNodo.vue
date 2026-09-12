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

      <span v-if="node.nivel !== 'modulo' && node.worker_sid" class="pa-terminal">
        <i class="bi bi-hdd-stack"></i> {{ node.worker_sid }}
      </span>

      <span v-if="badge" class="pa-badge" :class="badge.clase">
        <i class="bi" :class="badge.icono"></i> {{ badge.texto }}
      </span>

      <span v-if="node.nivel === 'modulo'" class="pa-contadores">
        <span class="pa-c pa-c-activo" v-if="node.contador_hijos.activos">
          <i class="bi bi-play-fill"></i> {{ node.contador_hijos.activos }}
        </span>
        <span class="pa-c pa-c-cola" v-if="node.contador_hijos.en_cola">
          <i class="bi bi-hourglass-split"></i> {{ node.contador_hijos.en_cola }}
        </span>
        <span class="pa-c pa-c-detenido" v-if="node.contador_hijos.detenidos">
          <i class="bi bi-exclamation-octagon"></i> {{ node.contador_hijos.detenidos }}
        </span>
      </span>
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
      #9990973 (CIRC-09 Fase 5) — panel PLACEHOLDER de "Tu decisión". Muestra el JSON crudo
      relevante (por qué escaló + preguntas/opciones), SOLO LECTURA. TODO(CIRC-02b, #9990858):
      cuando el mecanismo de hilo de respuestas (roadmap_item_respuestas + re-encolado
      automático) esté mergeado a main, reemplazar este bloque por el panel real: opciones
      seleccionables con la recomendada marcada, campo de texto libre, casilla "solo comentar,
      no ejecutar todavía", y "qué se desbloquea" — escribiendo en el hilo de respuestas de
      CIRC-02b (nunca en comentarios_claude, que se pisa). Ver sub-item de seguimiento creado al
      cerrar este item.
    -->
    <div
      v-if="esTuDecision && node.panelDecisionAbierto"
      class="pa-decision"
      :style="{ marginLeft: ((profundidad + 1) * 18) + 'px' }"
    >
      <div v-if="node.panelDecisionCargando" class="pa-info-linea">
        <span class="spinner-border spinner-border-sm"></span> Cargando decisión…
      </div>
      <div v-else-if="node.panelDecisionError" class="pa-info-linea pa-error">
        <i class="bi bi-exclamation-triangle"></i> {{ node.panelDecisionError }}
      </div>
      <div v-else-if="node.panelDecisionData" class="pa-decision-body">
        <p class="pa-decision-aviso">
          <i class="bi bi-cone-striped"></i> Placeholder — panel real pendiente de CIRC-02b (#9990858).
        </p>

        <div class="pa-decision-bloque">
          <span class="pa-decision-etq">Por qué escaló</span>
          <pre class="pa-decision-pre">{{ node.panelDecisionData.comentarios_claude || "(sin comentarios registrados)" }}</pre>
        </div>

        <div v-if="preguntasPendientes.length" class="pa-decision-bloque">
          <span class="pa-decision-etq">Pregunta(s)</span>
          <div v-for="p in preguntasPendientes" :key="p.id" class="pa-decision-pregunta">
            <p class="pa-decision-pregunta-texto">{{ p.pregunta }}</p>
            <ul class="pa-decision-opciones">
              <li v-for="(op, i) in (p.opciones || [])" :key="i" :class="{ 'pa-opcion-recomendada': op.recomendada }">
                <i class="bi" :class="op.recomendada ? 'bi-star-fill' : 'bi-circle'"></i>
                {{ op.texto }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </li>
</template>

<script>
import { computed, inject } from "vue";
import TorreArbolNodo from "./TorreArbolNodo.vue";

/**
 * #9990970 (CIRC-09 Fase 3) — fila recursiva del árbol Módulo → Épica → Item → Sub-item.
 * Se auto-importa (patrón estándar para recursión en SFC con Options API) porque la
 * anidación de épicas es recursiva de verdad (una épica puede contener otra épica —
 * caso real MR-23 vía origen_item_id encadenado).
 */
export default {
    name: "TorreArbolNodo",
    components: { TorreArbolNodo },
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
        const paToggleDecision = inject("paToggleDecision");

        const clave = computed(() => props.node.nivel + ":" + props.node.id);
        const visible = computed(() => paEsVisible(props.node));
        const badge = computed(() => paEstadoVisual(props.node));
        const enfocado = computed(() => paEnfocado.value);
        // #9990973 (CIRC-09 Fase 5) — la marca "Tu decisión" es la que abre el panel placeholder.
        const esTuDecision = computed(() => !!badge.value && badge.value.texto === "Tu decisión");
        const preguntasPendientes = computed(() => {
            const data = props.node.panelDecisionData;
            if (!data || !Array.isArray(data.preguntas)) return [];
            return data.preguntas.filter((p) => !p.opcion_elegida);
        });

        function onClick() {
            paEnfocar(props.node);
            if (props.node.tiene_hijos) paToggle(props.node);
            if (esTuDecision.value) paToggleDecision(props.node);
        }

        return { clave, visible, badge, enfocado, onClick, paCargarMas, esTuDecision, preguntasPendientes };
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

.pa-contadores{ display:flex; gap:8px; flex:0 0 auto; font-size:11px; }
.pa-c{ display:inline-flex; align-items:center; gap:3px; font-weight:700; }
.pa-c-activo{ color:#16a34a; }
.pa-c-cola{ color:#64748b; }
.pa-c-detenido{ color:#dc2626; }

.pa-info-linea{ font-size:12px; color:var(--pa-muted,#64748b); padding:3px 8px; }
.pa-error{ color:#dc2626; }
.pa-vacio{ font-style:italic; }

.pa-cargar-mas{
  font-size:12px; border:1px dashed var(--pa-line,#e5e7eb); background:transparent; color:var(--pa-accent,#0d9488);
  border-radius:6px; padding:3px 10px; margin:2px 0 6px; cursor:pointer;
}

.pa-decision{
  margin:2px 8px 8px 0; padding:8px 10px; border-radius:8px;
  background:var(--pa-chip-bg,#f1f5f9); border:1px solid var(--pa-line,#e5e7eb);
}
.pa-decision-aviso{
  margin:0 0 8px; font-size:11px; font-weight:600; color:#92400e;
}
.pa-decision-bloque{ margin-bottom:8px; }
.pa-decision-bloque:last-child{ margin-bottom:0; }
.pa-decision-etq{
  display:block; font-size:11px; font-weight:700; text-transform:uppercase;
  color:var(--pa-muted,#64748b); margin-bottom:4px;
}
.pa-decision-pre{
  margin:0; font-size:12px; white-space:pre-wrap; word-break:break-word;
  max-height:220px; overflow-y:auto; font-family:inherit;
}
.pa-decision-pregunta{ margin-bottom:6px; }
.pa-decision-pregunta-texto{ margin:0 0 3px; font-size:13px; font-weight:600; }
.pa-decision-opciones{ margin:0; padding-left:18px; font-size:12px; }
.pa-opcion-recomendada{ font-weight:700; }
</style>
