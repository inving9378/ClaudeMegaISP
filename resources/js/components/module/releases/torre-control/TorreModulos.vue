<template>
  <div>
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
      <div class="btn-group btn-group-sm" role="group" aria-label="Vista de módulos">
        <button type="button" class="btn" :class="vista === 'modulo' ? 'btn-primary' : 'btn-outline-primary'" @click="vista = 'modulo'">
          <i class="bi bi-list-nested me-1"></i> Por módulo
        </button>
        <button type="button" class="btn" :class="vista === 'lista' ? 'btn-primary' : 'btn-outline-primary'" @click="vista = 'lista'">
          <i class="bi bi-map me-1"></i> Lista
        </button>
      </div>
      <span class="text-muted small" v-if="vista === 'lista'">Hoja de ruta completa: alta, edición, orden y subtareas.</span>
    </div>

    <template v-if="vista === 'modulo'">
      <div class="card mb-3">
        <div class="card-body py-2">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <strong class="small"><i class="bi bi-journal-text me-1"></i> Cómo debe funcionar</strong>
            <select v-model="moduloSel" class="form-select form-select-sm w-auto" @change="cargarFuncionamiento">
              <option value="">— elige un módulo —</option>
              <option v-for="m in modulos" :key="m" :value="m">{{ m }}</option>
            </select>
          </div>
          <div v-if="moduloSel" class="mt-2">
            <div v-if="cargando" class="small text-muted">Cargando…</div>
            <template v-else>
              <textarea v-model="texto" class="form-control form-control-sm" rows="5" :readonly="!puede"
                placeholder="Describe cómo debe funcionar este módulo…"></textarea>
              <div class="d-flex align-items-center gap-2 mt-2">
                <button v-if="puede" class="btn btn-sm btn-success" :disabled="guardando || !texto.trim() || texto === vigenteTexto" @click="guardar">
                  {{ guardando ? 'Guardando…' : 'Guardar nueva versión' }}
                </button>
                <span v-if="mensaje" class="small" :class="error ? 'text-danger' : 'text-success'">{{ mensaje }}</span>
                <button v-if="historial.length" class="btn btn-sm btn-link ms-auto" @click="verHistorial = !verHistorial">
                  Historial ({{ historial.length }})
                </button>
              </div>
              <ul v-if="verHistorial" class="list-unstyled small mt-2 mb-0">
                <li v-for="h in historial" :key="h.id" class="border-top py-1">
                  <span class="text-muted">{{ h.created_at }} · {{ h.autor_nombre || '—' }}</span>
                  <button class="btn btn-sm btn-link py-0" @click="texto = h.texto">cargar</button>
                  <div style="white-space:pre-wrap">{{ h.texto }}</div>
                </li>
              </ul>
            </template>
          </div>
        </div>
      </div>
      <torre-panorama-arbol />
    </template>

    <roadmap-tab v-else />
  </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import RoadmapTab from "./RoadmapTab.vue";
import TorrePanoramaArbol from "./TorrePanoramaArbol.vue";

// #1145 — pestaña «Módulos»: selector Por módulo (árbol) / Lista (Hoja de ruta) + «cómo debe funcionar».
export default {
  name: "TorreModulos",
  components: { RoadmapTab, TorrePanoramaArbol },
  setup() {
    const vista = ref("modulo");
    const modulos = ref([]);
    const moduloSel = ref("");
    const texto = ref("");
    const vigenteTexto = ref("");
    const historial = ref([]);
    const puede = ref(false);
    const cargando = ref(false);
    const guardando = ref(false);
    const mensaje = ref("");
    const error = ref(false);
    const verHistorial = ref(false);

    const cargarModulos = async () => {
      try {
        const { data } = await axios.get("/api/roadmap/torre/panorama-jerarquia", { params: { nivel: "modulo" } });
        modulos.value = (data.nodos || []).map((n) => n.titulo || n.id);
      } catch (e) { /* el árbol muestra su propio error */ }
    };
    const cargarFuncionamiento = async () => {
      mensaje.value = ""; verHistorial.value = false;
      if (!moduloSel.value) return;
      cargando.value = true;
      try {
        const { data } = await axios.get("/api/roadmap/torre/modulos/funcionamiento", { params: { modulo: moduloSel.value } });
        historial.value = data.historial || [];
        vigenteTexto.value = data.vigente ? data.vigente.texto : "";
        texto.value = vigenteTexto.value;
        puede.value = !!data.puede;
      } catch (e) {
        error.value = true; mensaje.value = "No se pudo cargar.";
      } finally { cargando.value = false; }
    };
    const guardar = async () => {
      guardando.value = true; mensaje.value = ""; error.value = false;
      try {
        await axios.post("/api/roadmap/torre/modulos/funcionamiento", { modulo: moduloSel.value, texto: texto.value });
        mensaje.value = "Versión guardada.";
        await cargarFuncionamiento();
        mensaje.value = "Versión guardada.";
      } catch (e) {
        error.value = true; mensaje.value = "No se pudo guardar.";
      } finally { guardando.value = false; }
    };

    onMounted(cargarModulos);
    return { vista, modulos, moduloSel, texto, vigenteTexto, historial, puede, cargando, guardando, mensaje, error, verHistorial, cargarFuncionamiento, guardar };
  },
};
</script>
