<template>
  <div class="tai-card" :class="{ 'tai-dark': oscuro }">
    <div class="tai-title">
      <i class="bi bi-clipboard-check me-1"></i>
      {{ items.length > 1 ? `${items.length} items por aprobar` : 'Item por aprobar' }}
    </div>

    <ul v-if="avisos.length" class="tai-avisos">
      <li v-for="(a, i) in avisos" :key="i">{{ a }}</li>
    </ul>

    <!-- Lista de items (lectura) -->
    <ol v-if="!editando" class="tai-items">
      <li v-for="it in items" :key="it.indice" class="tai-item" :class="{ 'tai-item-creado': creadoDe(it.indice) }">
        <div class="tai-item-head">
          <strong class="tai-item-titulo">{{ it.titulo }}</strong>
          <span class="tai-badge" :class="'tai-n-' + it.nivel_riesgo">Nivel {{ it.nivel_riesgo }}</span>
        </div>
        <dl class="tai-dl">
          <dt>Qué cambia</dt><dd>{{ it.que_cambia }}</dd>
          <dt>Dónde</dt><dd>{{ it.donde }}</dd>
          <dt>Cómo se valida</dt><dd>{{ it.como_se_valida }}</dd>
        </dl>
        <div class="tai-meta">
          <span>{{ it.modulo }}</span>
          <span>· prioridad {{ it.prioridad }}</span>
          <span v-if="it.al_inicio">· al inicio de la cola</span>
        </div>

        <div v-if="creadoDe(it.indice)" class="tai-creado">
          <i class="bi bi-check-circle-fill me-1"></i>
          Creado <a :href="creadoDe(it.indice).url" target="_blank" rel="noopener">#{{ creadoDe(it.indice).id }}</a>
          <span v-if="creadoDe(it.indice).en_cola === false"> · queda en tu bandeja, no en la cola</span>
          <button
            v-if="puedeDeshacer(creadoDe(it.indice))"
            type="button"
            class="btn btn-sm btn-outline-danger tai-undo"
            :disabled="ocupado"
            @click="$emit('deshacer', creadoDe(it.indice))"
          >Cancelar item</button>
        </div>
        <div v-else-if="rechazoDe(it.indice)" class="tai-rechazo">
          <i class="bi bi-x-octagon me-1"></i>{{ rechazoDe(it.indice).motivo }}
        </div>
        <template v-else>
          <div v-if="it.duplicados && it.duplicados.length" class="tai-aviso-box">
            <div class="tai-aviso-t"><i class="bi bi-exclamation-triangle me-1"></i>Parece que ya existe algo parecido:</div>
            <ul>
              <li v-for="d in it.duplicados" :key="d.id">
                <a :href="d.url" target="_blank" rel="noopener">#{{ d.id }}</a> {{ d.titulo }}
              </li>
            </ul>
            <label class="tai-check">
              <input type="checkbox" v-model="dupOk[it.indice]" :disabled="ocupado"> Crearlo de todos modos
            </label>
          </div>
          <div v-if="necesitaSegunda(it)" class="tai-aviso-box tai-aviso-c">
            <div class="tai-aviso-t"><i class="bi bi-shield-exclamation me-1"></i>Hace falta tu segunda confirmación</div>
            <p class="mb-1">{{ motivoSegunda(it) }}</p>
            <p class="mb-1 small">Si lo confirmas, queda en tu bandeja para que lo revises; no entra a la cola.</p>
            <label class="tai-check">
              <input type="checkbox" v-model="segundaOk[it.indice]" :disabled="ocupado"> Entiendo y lo confirmo
            </label>
          </div>
        </template>
      </li>
    </ol>

    <!-- Edición en línea -->
    <div v-else class="tai-items">
      <div v-for="(it, k) in borrador" :key="it.indice" class="tai-item">
        <label class="tai-lbl">Título</label>
        <input v-model="borrador[k].titulo" type="text" class="form-control form-control-sm" maxlength="255">
        <label class="tai-lbl">Qué cambia</label>
        <textarea v-model="borrador[k].que_cambia" rows="2" class="form-control form-control-sm"></textarea>
        <label class="tai-lbl">Dónde</label>
        <input v-model="borrador[k].donde" type="text" class="form-control form-control-sm">
        <label class="tai-lbl">Cómo se valida</label>
        <textarea v-model="borrador[k].como_se_valida" rows="2" class="form-control form-control-sm"></textarea>
        <div class="tai-row">
          <div>
            <label class="tai-lbl">Prioridad</label>
            <select v-model="borrador[k].prioridad" class="form-select form-select-sm">
              <option value="alta">alta</option><option value="media">media</option><option value="baja">baja</option>
            </select>
          </div>
          <label class="tai-check tai-check-inline">
            <input type="checkbox" v-model="borrador[k].al_inicio"> Al inicio
          </label>
        </div>
      </div>
    </div>

    <div v-if="mensaje" class="tai-msg">{{ mensaje }}</div>
    <div v-if="error" class="tai-error"><i class="bi bi-exclamation-circle me-1"></i>{{ error }}</div>

    <!-- Acciones -->
    <div v-if="editando" class="tai-actions">
      <button type="button" class="btn btn-secondary btn-sm" :disabled="ocupado" @click="editando = false">Volver</button>
      <button type="button" class="btn btn-primary btn-sm flex-fill" :disabled="ocupado" @click="guardarEdicion">
        <span v-if="ocupado" class="spinner-border spinner-border-sm me-1"></span>Guardar cambios
      </button>
    </div>
    <div v-else-if="pendientes.length && puedeCrear" class="tai-actions">
      <button type="button" class="btn btn-success btn-sm" :disabled="!listo || ocupado" @click="crear(false)">
        <span v-if="ocupado" class="spinner-border spinner-border-sm me-1"></span>{{ items.length > 1 ? 'Aprobar lista' : 'Crear' }}
      </button>
      <button type="button" class="btn btn-outline-success btn-sm" :disabled="!listo || ocupado" @click="crear(true)">
        {{ items.length > 1 ? 'Aprobar y poner al inicio' : 'Crear y poner al inicio' }}
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="ocupado" @click="abrirEdicion">Editar</button>
      <button type="button" class="btn btn-link btn-sm tai-cancel" :disabled="ocupado" @click="$emit('descartar')">Cancelar</button>
    </div>
    <div v-else-if="pendientes.length && !puedeCrear" class="tai-nopermiso">
      No tienes permiso para crear items desde aquí.
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed } from "vue";

/**
 * Tarjeta genérica de aprobación de una lista de items propuestos (#1111). Sin nada propio de
 * Jarvis: recibe datos por props y avisa por eventos; quien la monta hace las llamadas.
 * Reutilizable (#1071: aprobar en bloque la lista de una épica).
 *
 * Props: items[], avisos[], puedeCrear, ocupado, oscuro, mensaje, error,
 *        creados[] ({indice,id,url,en_cola,cancelable_hasta}), rechazados[] ({indice,motivo}),
 *        requierenConfirmacion[] ({indice,motivo}).
 * Eventos: crear({alInicio, confirmadosC}) · editar(items) · descartar · deshacer(creado).
 */
export default {
  name: "TarjetaAprobacionItems",
  props: {
    items: { type: Array, default: () => [] },
    avisos: { type: Array, default: () => [] },
    puedeCrear: { type: Boolean, default: true },
    ocupado: { type: Boolean, default: false },
    oscuro: { type: Boolean, default: false },
    mensaje: { type: String, default: "" },
    error: { type: String, default: "" },
    creados: { type: Array, default: () => [] },
    rechazados: { type: Array, default: () => [] },
    requierenConfirmacion: { type: Array, default: () => [] },
  },
  emits: ["crear", "editar", "descartar", "deshacer"],
  setup(props, { emit }) {
    const segundaOk = reactive({});
    const dupOk = reactive({});
    const editando = ref(false);
    const borrador = ref([]);

    const creadoDe = (i) => props.creados.find((c) => c.indice === i);
    const rechazoDe = (i) => props.rechazados.find((r) => r.indice === i);
    const confirmDe = (i) => props.requierenConfirmacion.find((r) => r.indice === i);
    const necesitaSegunda = (it) => !!it.requiere_segunda_confirmacion || !!confirmDe(it.indice);
    const motivoSegunda = (it) =>
      confirmDe(it.indice)?.motivo || it.motivo_confirmacion || "Este item toca algo sensible y necesita tu visto bueno.";
    const puedeDeshacer = (c) => !!c.cancelable_hasta && new Date(c.cancelable_hasta).getTime() > Date.now();

    const pendientes = computed(() => props.items.filter((it) => !creadoDe(it.indice) && !rechazoDe(it.indice)));
    const listo = computed(() =>
      pendientes.value.every(
        (it) =>
          (!necesitaSegunda(it) || segundaOk[it.indice]) &&
          (!(it.duplicados && it.duplicados.length) || dupOk[it.indice])
      )
    );

    function crear(alInicio) {
      const confirmadosC = pendientes.value.filter((it) => necesitaSegunda(it) && segundaOk[it.indice]).map((it) => it.indice);
      emit("crear", { alInicio, confirmadosC });
    }
    function abrirEdicion() {
      borrador.value = pendientes.value.map((it) => ({ ...it }));
      editando.value = true;
    }
    function guardarEdicion() {
      emit("editar", borrador.value.map((it) => ({ ...it })));
      editando.value = false;
    }

    return {
      segundaOk, dupOk, editando, borrador, creadoDe, rechazoDe, necesitaSegunda, motivoSegunda, puedeDeshacer,
      pendientes, listo, crear, abrirEdicion, guardarEdicion,
    };
  },
};
</script>

<style scoped>
.tai-card {
    --tai-bg: #fff; --tai-fg: #0f172a; --tai-muted: #475569; --tai-bd: #cbd5e1; --tai-soft: #f8fafc;
    --tai-warn-bg: #fef3c7; --tai-warn-fg: #78350f; --tai-c-bg: #fee2e2; --tai-c-fg: #7f1d1d;
    --tai-ok: #15803d; --tai-link: #1d4ed8; --tai-err: #b91c1c;
    margin-top: 6px; border: 1px solid var(--tai-bd); border-radius: 10px; background: var(--tai-bg);
    color: var(--tai-fg); padding: 10px; font-size: 12.5px; max-width: 100%;
}
.tai-card.tai-dark {
    --tai-bg: #172033; --tai-fg: #e2e8f0; --tai-muted: #cbd5e1; --tai-bd: #475569; --tai-soft: #0f172a;
    --tai-warn-bg: #422006; --tai-warn-fg: #fde68a; --tai-c-bg: #450a0a; --tai-c-fg: #fecaca;
    --tai-ok: #4ade80; --tai-link: #93c5fd; --tai-err: #fca5a5;
}
.tai-title { font-weight: 700; margin-bottom: 6px; }
.tai-avisos { margin: 0 0 6px; padding-left: 18px; color: var(--tai-muted); }
.tai-items { margin: 0; padding-left: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }
.tai-item { background: var(--tai-soft); border: 1px solid var(--tai-bd); border-radius: 8px; padding: 8px; }
.tai-item-creado { border-color: var(--tai-ok); }
.tai-item-head { display: flex; justify-content: space-between; gap: 6px; align-items: flex-start; }
.tai-item-titulo { overflow-wrap: anywhere; }
.tai-badge { font-size: 10.5px; font-weight: 700; border-radius: 999px; padding: 1px 8px; white-space: nowrap; background: #dcfce7; color: #14532d; }
.tai-n-B { background: #fef3c7; color: #78350f; }
.tai-n-C { background: #fee2e2; color: #7f1d1d; }
.tai-dl { margin: 6px 0 4px; display: grid; grid-template-columns: auto 1fr; gap: 2px 8px; }
.tai-dl dt { font-weight: 600; color: var(--tai-muted); }
.tai-dl dd { margin: 0; overflow-wrap: anywhere; }
.tai-meta { color: var(--tai-muted); font-size: 11.5px; display: flex; gap: 4px; flex-wrap: wrap; }
.tai-creado { margin-top: 6px; color: var(--tai-ok); font-weight: 600; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.tai-creado a, .tai-aviso-box a { color: var(--tai-link); }
.tai-undo { margin-left: auto; }
.tai-rechazo { margin-top: 6px; color: var(--tai-err); }
.tai-aviso-box { margin-top: 6px; background: var(--tai-warn-bg); color: var(--tai-warn-fg); border-radius: 6px; padding: 6px 8px; }
.tai-aviso-box ul { margin: 2px 0 4px; padding-left: 18px; }
.tai-aviso-c { background: var(--tai-c-bg); color: var(--tai-c-fg); }
.tai-aviso-t { font-weight: 700; margin-bottom: 2px; }
.tai-check { display: flex; gap: 6px; align-items: center; margin: 0; cursor: pointer; }
.tai-check-inline { align-self: flex-end; padding-bottom: 4px; }
.tai-lbl { display: block; font-weight: 600; margin: 4px 0 2px; color: var(--tai-muted); }
.tai-row { display: flex; gap: 10px; align-items: flex-start; }
.tai-card .form-control, .tai-card .form-select { background: var(--tai-bg); color: var(--tai-fg); border-color: var(--tai-bd); }
.tai-msg { margin-top: 6px; color: var(--tai-muted); }
.tai-error { margin-top: 6px; color: var(--tai-err); font-weight: 600; }
.tai-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.tai-cancel { color: var(--tai-muted); }
.tai-nopermiso { margin-top: 8px; color: var(--tai-muted); font-style: italic; }
</style>
