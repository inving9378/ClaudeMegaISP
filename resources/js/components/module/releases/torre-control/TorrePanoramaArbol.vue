<template>
  <div class="pa-wrap" :class="{ 'pa-dark': dark }">

    <div class="pa-bar">
      <span class="pa-bar-title"><i class="bi bi-list-nested me-1"></i> Panorama en árbol</span>
      <span class="pa-bar-meta">{{ modulos.length }} módulo(s) con actividad</span>
      <button class="pa-refresh" type="button" :disabled="cargandoRaiz" @click="cargarRaiz(true)">
        <i class="bi" :class="cargandoRaiz ? 'bi-arrow-repeat pa-spin' : 'bi-arrow-repeat'"></i> Actualizar
      </button>
    </div>

    <div class="pa-filtros">
      <button
        v-for="f in FILTROS"
        :key="f.valor"
        type="button"
        class="pa-chip"
        :class="{ 'pa-chip-activo': filtro === f.valor }"
        @click="filtro = f.valor"
      >
        {{ f.texto }}
      </button>
      <input
        v-model="busqueda"
        type="text"
        class="pa-buscador"
        placeholder="Buscar por título, id, módulo o terminal…"
      />
    </div>
    <p v-if="filtro === 'todo'" class="pa-nota">
      <i class="bi bi-info-circle me-1"></i>
      Por ahora «Todo» se ve igual que «Vivo»: el endpoint (Fase 2, ya cerrada) solo entrega ítems
      vivos. Incluir completados/archivados requiere extender ese endpoint — decisión registrada
      en el item, pendiente para cuando alguien lo necesite de verdad.
    </p>

    <p v-if="errorRaiz" class="pa-error-raiz"><i class="bi bi-exclamation-triangle me-1"></i>{{ errorRaiz }}</p>

    <div v-if="cargandoRaiz && !modulos.length" class="pa-cargando-raiz">
      <span class="spinner-border spinner-border-sm"></span> Cargando módulos…
    </div>

    <div v-if="!cargandoRaiz && !modulos.length && !errorRaiz" class="pa-empty">
      <i class="bi bi-inbox pa-empty-ico"></i>
      <p class="pa-empty-h">Sin módulos con actividad</p>
    </div>

    <ul v-if="modulos.length" class="pa-raiz">
      <torre-arbol-nodo v-for="m in modulos" :key="'modulo:' + m.id" :node="m" :profundidad="0" />
    </ul>
  </div>
</template>

<script>
import { ref, reactive, computed, provide, onMounted, nextTick, watch } from "vue";
import axios from "axios";
import { darkMode } from "../../../../hook/appConfig.js";
import TorreArbolNodo from "./TorreArbolNodo.vue";

/**
 * #9990970 (CIRC-09 Fase 3) — Panorama en árbol, SOLO LECTURA: Módulo → Épica → Item → Sub-item,
 * lazy-load sobre el endpoint de la Fase 2 (GET .../torre/panorama-jerarquia). Sin paneles
 * desplegables (Fase 4/5) ni acciones/refresco en vivo (Fase 6) — eso lo agregan los hermanos.
 *
 * Filtros/búsqueda: client-side sobre lo ya cargado (autorizado por el propio spec del item:
 * "puede ser client-side... decidir con la regla de oro"). Eso implica un límite honesto: un
 * item que aún no se ha cargado (rama sin desplegar) no puede coincidir con la búsqueda ni con
 * los filtros — es el mismo trade-off que exige el lazy-load (no mandar 1,580 items al navegador).
 *
 * "Detenidos"/"Tu decisión"/"Decidido sin ti" necesitan 3 campos que el endpoint de la Fase 2 NO
 * traía (bucket propio del nodo, aprobado_por, worker_sid) — se agregaron aquí mismo de forma
 * ADITIVA al controller (nuevas llaves en el JSON, ninguna se quitó/renombró) porque son del
 * propio alcance de esta fase, no un cambio de comportamiento del endpoint congelado.
 *
 * "Todo" (completados/archivados) SÍ requeriría tocar el WHERE del endpoint (hoy fuerza "vivo"
 * sin excepción) — eso es un cambio de mayor alcance sobre un endpoint recién cerrado y probado;
 * se deja fuera de esta fase con nota honesta en la propia UI (ver <p class="pa-nota">).
 */
const FILTROS = [
    { valor: "vivo", texto: "Vivo" },
    { valor: "todo", texto: "Todo" },
    { valor: "detenidos", texto: "Detenidos" },
    { valor: "tu_decision", texto: "Tu decisión" },
    { valor: "decidido_sin_ti", texto: "Decidido sin ti" },
];

const STORAGE_KEY = "torre_panorama_arbol_v1";

function leerPersistencia() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const datos = raw ? JSON.parse(raw) : null;
        return {
            expandidos: (datos && Array.isArray(datos.expandidos)) ? datos.expandidos : [],
            filtro: (datos && datos.filtro) || "vivo",
            enfocado: (datos && datos.enfocado) || null,
        };
    } catch (e) {
        return { expandidos: [], filtro: "vivo", enfocado: null };
    }
}

export default {
    name: "TorrePanoramaArbol",
    components: { TorreArbolNodo },
    setup() {
        const persistido = leerPersistencia();

        const modulos = reactive([]);
        const cargandoRaiz = ref(false);
        const errorRaiz = ref(null);
        const filtro = ref(persistido.filtro);
        const busqueda = ref("");
        const enfocado = ref(persistido.enfocado);

        const busquedaNormalizada = computed(() => busqueda.value.trim().toLowerCase());

        function envolverNodo(raw, padre) {
            return reactive({
                ...raw,
                hijos: null,
                expandido: false,
                cargando: false,
                errorHijos: null,
                pagina: 1,
                total: null,
                moduloPadre: padre.nivel === "modulo" ? padre.id : (padre.moduloPadre || null),
            });
        }

        async function cargarHijosDe(node, { page = 1, append = false } = {}) {
            if (node.cargando) return;
            node.cargando = true;
            node.errorHijos = null;
            try {
                const nivelHijos = node.nivel === "modulo" ? "epica" : "item";
                const { data } = await axios.get("/api/roadmap/torre/panorama-jerarquia", {
                    params: { nivel: nivelHijos, parent_id: String(node.id), page, per_page: 200 },
                });
                const nuevos = (data.nodos || []).map((n) => envolverNodo(n, node));
                node.hijos = append ? [...(node.hijos || []), ...nuevos] : nuevos;
                node.pagina = data.pagina || page;
                node.total = typeof data.total === "number" ? data.total : node.hijos.length;
            } catch (e) {
                node.errorHijos = (e.response && e.response.data && e.response.data.mensaje)
                    || "No se pudieron cargar los sub-items.";
            } finally {
                node.cargando = false;
            }
        }

        async function paToggle(node) {
            if (!node.tiene_hijos) return;
            if (!node.expandido && node.hijos === null) {
                await cargarHijosDe(node);
            }
            node.expandido = !node.expandido;
            guardarPersistencia();
        }

        async function paCargarMas(node) {
            await cargarHijosDe(node, { page: (node.pagina || 1) + 1, append: true });
        }

        function paEnfocar(node) {
            enfocado.value = node.nivel + ":" + node.id;
            guardarPersistencia();
        }

        function esDecididoSinTi(node) {
            return !!node.aprobado_por && !String(node.aprobado_por).startsWith("irving:");
        }

        function paEstadoVisual(node) {
            if (node.nivel === "modulo") return null;
            if (node.bucket === "activo") {
                return { texto: "Trabajando", clase: "pa-badge-verde", icono: "bi-play-circle" };
            }
            if (node.bucket === "detenido") {
                if (node.estado === "requiere_irving") {
                    return { texto: "Tu decisión", clase: "pa-badge-ambar", icono: "bi-exclamation-triangle" };
                }
                return { texto: "Bloqueado", clase: "pa-badge-rojo", icono: "bi-slash-circle" };
            }
            if (esDecididoSinTi(node)) {
                return { texto: "Decidido sin ti", clase: "pa-badge-violeta", icono: "bi-check2-circle" };
            }
            return null;
        }

        function pasaFiltro(node) {
            if (filtro.value === "vivo" || filtro.value === "todo") return true;
            if (node.nivel === "modulo") {
                if (filtro.value === "detenidos" || filtro.value === "tu_decision") {
                    return (node.contador_hijos && node.contador_hijos.detenidos) > 0;
                }
                return true; // decidido_sin_ti: sin contador agregado a nivel módulo, no se oculta
            }
            if (filtro.value === "detenidos") {
                return node.bucket === "detenido" || (node.contador_hijos && node.contador_hijos.detenidos > 0);
            }
            if (filtro.value === "tu_decision") {
                return node.estado === "requiere_irving" || (node.contador_hijos && node.contador_hijos.detenidos > 0);
            }
            if (filtro.value === "decidido_sin_ti") {
                return esDecididoSinTi(node) || node.tiene_hijos;
            }
            return true;
        }

        function coincideBusqueda(node) {
            const q = busquedaNormalizada.value;
            if (!q) return true;
            const campos = [String(node.id), node.titulo, node.moduloPadre, node.worker_sid]
                .filter(Boolean).join(" ").toLowerCase();
            return campos.includes(q);
        }

        function tieneDescendiente(node, chequeo) {
            return (node.hijos || []).some((h) => chequeo(h) || tieneDescendiente(h, chequeo));
        }

        function paEsVisible(node) {
            if (!pasaFiltro(node) && !tieneDescendiente(node, pasaFiltro)) return false;
            if (busquedaNormalizada.value && !coincideBusqueda(node) && !tieneDescendiente(node, coincideBusqueda)) {
                return false;
            }
            return true;
        }

        function recolectarExpandidos(nodos) {
            let out = [];
            for (const n of nodos) {
                if (n.expandido) out.push(n.nivel + ":" + n.id);
                if (n.hijos && n.hijos.length) out = out.concat(recolectarExpandidos(n.hijos));
            }
            return out;
        }

        function guardarPersistencia() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    expandidos: recolectarExpandidos(modulos),
                    filtro: filtro.value,
                    enfocado: enfocado.value,
                }));
            } catch (e) {
                // localStorage lleno o deshabilitado (modo privado): no persiste, no rompe la UI.
            }
        }

        async function restaurarExpandidos(nodos, clavesGuardadas) {
            if (!clavesGuardadas.length) return;
            for (const n of nodos) {
                if (n.tiene_hijos && clavesGuardadas.includes(n.nivel + ":" + n.id)) {
                    if (n.hijos === null) await cargarHijosDe(n);
                    n.expandido = true;
                    if (n.hijos && n.hijos.length) await restaurarExpandidos(n.hijos, clavesGuardadas);
                }
            }
        }

        function enfocarEnDom() {
            if (!enfocado.value) return;
            nextTick(() => {
                const el = document.querySelector(`.pa-fila[data-clave="${enfocado.value}"]`);
                if (el) el.scrollIntoView({ block: "center", behavior: "smooth" });
            });
        }

        async function cargarRaiz(esRefresco = false) {
            cargandoRaiz.value = true;
            errorRaiz.value = null;
            try {
                const { data } = await axios.get("/api/roadmap/torre/panorama-jerarquia", { params: { nivel: "modulo" } });
                const nuevos = (data.nodos || []).map((n) => envolverNodo(n, { nivel: null, id: null, moduloPadre: null }));
                modulos.splice(0, modulos.length, ...nuevos);
                if (!esRefresco) {
                    await restaurarExpandidos(modulos, persistido.expandidos);
                    enfocarEnDom();
                }
            } catch (e) {
                errorRaiz.value = (e.response && e.response.data && e.response.data.mensaje)
                    || "No se pudo cargar el panorama en árbol.";
            } finally {
                cargandoRaiz.value = false;
            }
        }

        watch(filtro, guardarPersistencia);

        onMounted(() => cargarRaiz(false));

        provide("paToggle", paToggle);
        provide("paCargarMas", paCargarMas);
        provide("paEnfocar", paEnfocar);
        provide("paEsVisible", paEsVisible);
        provide("paEstadoVisual", paEstadoVisual);
        provide("paEnfocado", enfocado);

        return {
            FILTROS, modulos, cargandoRaiz, errorRaiz, filtro, busqueda, cargarRaiz, dark: darkMode,
        };
    },
};
</script>

<style scoped>
.pa-wrap{
  --pa-bg:#f8fafc; --pa-surface:#fff; --pa-ink:#0f172a; --pa-muted:#64748b; --pa-line:#e5e7eb;
  --pa-accent:#0d9488; --pa-hover:rgba(13,148,136,.08); --pa-focus:rgba(13,148,136,.16); --pa-chip-bg:#f1f5f9;
  color:var(--pa-ink);
}
.pa-wrap.pa-dark{
  --pa-bg:#0b1220; --pa-surface:#0f172a; --pa-ink:#e2e8f0; --pa-muted:#94a3b8; --pa-line:#1e293b;
  --pa-accent:#2dd4bf; --pa-hover:rgba(45,212,191,.12); --pa-focus:rgba(45,212,191,.22); --pa-chip-bg:#1e293b;
}

.pa-bar{ display:flex; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.pa-bar-title{ font-weight:700; font-size:15px; }
.pa-bar-meta{ font-size:12px; color:var(--pa-muted); flex:1 1 auto; }
.pa-refresh{
  border:1px solid var(--pa-line); background:var(--pa-surface); color:var(--pa-ink);
  border-radius:8px; padding:5px 10px; font-size:12px; cursor:pointer;
}
.pa-refresh:disabled{ opacity:.6; cursor:default; }
.pa-spin{ display:inline-block; animation:pa-spin 1s linear infinite; }
@keyframes pa-spin{ from{ transform:rotate(0); } to{ transform:rotate(360deg); } }

.pa-filtros{ display:flex; gap:8px; margin-bottom:6px; flex-wrap:wrap; align-items:center; }
.pa-chip{
  border:1px solid var(--pa-line); background:var(--pa-surface); color:var(--pa-muted);
  border-radius:999px; padding:4px 12px; font-size:12px; font-weight:600; cursor:pointer;
}
.pa-chip-activo{ background:var(--pa-accent); border-color:var(--pa-accent); color:#fff; }
.pa-buscador{
  border:1px solid var(--pa-line); background:var(--pa-surface); color:var(--pa-ink);
  border-radius:8px; padding:5px 12px; font-size:13px; flex:1 1 260px; min-width:200px;
}

.pa-nota{ font-size:12px; color:var(--pa-muted); background:var(--pa-chip-bg); border-radius:8px; padding:8px 12px; margin-bottom:10px; }
.pa-error-raiz{ color:#dc2626; font-size:13px; }

.pa-cargando-raiz{ padding:20px 0; color:var(--pa-muted); font-size:13px; }

.pa-empty{ text-align:center; padding:40px 20px; border:1px dashed var(--pa-line); border-radius:14px; background:var(--pa-surface); }
.pa-empty-ico{ font-size:30px; color:var(--pa-muted); }
.pa-empty-h{ font-weight:700; margin:8px 0 4px; }

.pa-raiz{ margin:0; padding:0; }
</style>
