<template>
  <div class="tc-wrap" :class="{ 'tc-dark': darkMode }">
    <div class="d-flex justify-content-between align-items-center">
      <nav aria-label="breadcrumb" class="small">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="/talento">Colaboradores</a></li>
          <li class="breadcrumb-item active" aria-current="page">
            {{ colaborador?.user?.name ?? ('#' + id) }}
          </li>
        </ol>
      </nav>
      <div class="d-flex gap-2 mb-3">
        <button
          @click="goToPrevious"
          class="tc-btn tc-btn-seg"
          :disabled="isFirst"
          title="Colaborador anterior"
        >
          <i class="bi bi-chevron-left"></i>
        </button>
        <button
          @click="goToNext"
          class="tc-btn tc-btn-seg"
          :disabled="isLast"
          title="Colaborador siguiente"
        >
          <i class="bi bi-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Quién se está viendo — mismo patrón que Menu.vue (Vendedores, item #9990601): sin esto,
         abrir la ficha equivocada se ve idéntico a que el módulo esté roto. -->
    <div v-if="colaborador" class="tc-sellerbar" :class="{ 'tc-sellerbar--warn': colaborador.status !== 'active' }">
      <i class="bi bi-person-badge"></i>
      <strong>{{ colaborador.user?.name }}</strong>
      <span class="tc-muted-txt">
        · colaborador #{{ colaborador.id }} · {{ colaborador.type }}
        <template v-if="colaborador.department">· {{ colaborador.department }}</template>
      </span>
      <span v-if="colaborador.status !== 'active'" class="tc-badge-warn ms-auto">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Colaborador {{ statusLabel(colaborador.status) }} — es normal que no tenga movimientos
      </span>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
    </div>

    <template v-else-if="colaborador">
      <!-- Pestañas estilo Torre (mismo patrón que Menu.vue de Vendedores) -->
      <ul class="nav nav-tabs tc-tabs">
        <li class="nav-item">
          <a class="nav-link" :class="{ active: activeTab === 'informacion' }" href="#"
             @click.prevent="setActiveTab('informacion')">
            <i class="bi bi-person me-1"></i> Información
          </a>
        </li>
        <li class="nav-item" v-if="mostrarPortal">
          <a class="nav-link" :class="{ active: activeTab === 'mi_trabajo' }" href="#"
             @click.prevent="setActiveTab('mi_trabajo')">
            <i class="bi bi-phone-vibrate me-1"></i> Mi trabajo (Portal)
          </a>
        </li>
        <li class="nav-item" v-if="mostrarTabOrdenes">
          <a class="nav-link" :class="{ active: activeTab === 'ordenes' }" href="#"
             @click.prevent="setActiveTab('ordenes')">
            <i class="bi bi-clipboard-check me-1"></i> Órdenes de trabajo
          </a>
        </li>
        <li class="nav-item" v-if="permisos.compensacion">
          <a class="nav-link" :class="{ active: activeTab === 'compensacion' }" href="#"
             @click.prevent="setActiveTab('compensacion')">
            <i class="bi bi-coin me-1"></i> Compensación
          </a>
        </li>
        <li class="nav-item" v-if="permisos.liquidaciones">
          <a class="nav-link" :class="{ active: activeTab === 'liquidaciones' }" href="#"
             @click.prevent="setActiveTab('liquidaciones')">
            <i class="bi bi-file-earmark-medical me-1"></i> Liquidaciones
          </a>
        </li>
        <li class="nav-item" v-if="permisos.asistencia">
          <a class="nav-link" :class="{ active: activeTab === 'asistencia' }" href="#"
             @click.prevent="setActiveTab('asistencia')">
            <i class="bi bi-calendar-check me-1"></i> Asistencia
          </a>
        </li>
        <li class="nav-item" v-if="permisos.campo && esTecnico">
          <a class="nav-link" :class="{ active: activeTab === 'campo' }" href="#"
             @click.prevent="setActiveTab('campo')">
            <i class="bi bi-cone-striped me-1"></i> Flujo de campo
          </a>
        </li>
        <li class="nav-item" v-if="permisos.cajas && esTecnico">
          <a class="nav-link" :class="{ active: activeTab === 'cajas' }" href="#"
             @click.prevent="setActiveTab('cajas')">
            <i class="bi bi-reception-4 me-1"></i> Cajas ODB
          </a>
        </li>
        <li class="nav-item" v-if="permisos.rutas && esTecnico">
          <a class="nav-link" :class="{ active: activeTab === 'rutas' }" href="#"
             @click.prevent="setActiveTab('rutas')">
            <i class="bi bi-signpost-split me-1"></i> Rutas planta
          </a>
        </li>
        <li class="nav-item" v-if="permisos.calidad && esTecnico">
          <a class="nav-link" :class="{ active: activeTab === 'calidad' }" href="#"
             @click.prevent="setActiveTab('calidad')">
            <i class="bi bi-patch-check me-1"></i> Calidad de caja
          </a>
        </li>
        <li class="nav-item" v-if="permisos.proyectos">
          <a class="nav-link" :class="{ active: activeTab === 'proyectos' }" href="#"
             @click.prevent="setActiveTab('proyectos')">
            <i class="bi bi-diagram-3 me-1"></i> Proyectos
          </a>
        </li>
        <li class="nav-item" v-if="permisos.penalizaciones">
          <a class="nav-link" :class="{ active: activeTab === 'penalizaciones' }" href="#"
             @click.prevent="setActiveTab('penalizaciones')">
            <i class="bi bi-exclamation-octagon me-1"></i> Penalizaciones
          </a>
        </li>
        <li class="nav-item" v-if="permisos.credenciales">
          <a class="nav-link" :class="{ active: activeTab === 'credenciales' }" href="#"
             @click.prevent="setActiveTab('credenciales')">
            <i class="bi bi-person-vcard me-1"></i> Credenciales
          </a>
        </li>
        <li class="nav-item" v-if="permisos.finiquito">
          <a class="nav-link" :class="{ active: activeTab === 'finiquito' }" href="#"
             @click.prevent="setActiveTab('finiquito')">
            <i class="bi bi-cash-coin me-1"></i> Préstamos/Finiquito
          </a>
        </li>
        <li class="nav-item" v-if="permisos.custodia">
          <a class="nav-link" :class="{ active: activeTab === 'custodia' }" href="#"
             @click.prevent="setActiveTab('custodia')">
            <i class="bi bi-box-seam me-1"></i> Custodia
          </a>
        </li>
        <li class="nav-item" v-if="permisos.dispositivos">
          <a class="nav-link" :class="{ active: activeTab === 'dispositivos' }" href="#"
             @click.prevent="setActiveTab('dispositivos')">
            <i class="bi bi-phone me-1"></i> Dispositivos
          </a>
        </li>
        <li class="nav-item" v-if="permisos.roles_multiples">
          <a class="nav-link" :class="{ active: activeTab === 'roles_multiples' }" href="#"
             @click.prevent="setActiveTab('roles_multiples')">
            <i class="bi bi-link-45deg me-1"></i> Roles múltiples
          </a>
        </li>
      </ul>

      <!-- Paneles — montaje perezoso del activo, como Menu.vue -->
      <div class="tc-panel">
        <div v-if="activeTab === 'informacion'">
          <talento-ficha-informacion :colaborador="colaborador" />
        </div>
        <div v-if="activeTab === 'mi_trabajo'" class="tc-portal-embed">
          <iframe src="/talento/portal" title="Portal de Colaborador — Mi trabajo" loading="lazy"></iframe>
        </div>
        <div v-if="activeTab === 'ordenes'">
          <talento-ordenes :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''" :puede-gestionar="permisos.ordenes_manage" />
        </div>
        <div v-if="activeTab === 'compensacion'">
          <talento-compensacion :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''" />
        </div>
        <div v-if="activeTab === 'liquidaciones'">
          <talento-liquidaciones :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'asistencia'">
          <talento-asistencia :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'campo'">
          <talento-campo :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'cajas'">
          <talento-cajas :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'rutas'">
          <talento-rutas :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'calidad'">
          <talento-calidad :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'proyectos'">
          <talento-proyectos :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'penalizaciones'">
          <talento-penalizaciones :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'credenciales'">
          <talento-credenciales :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'finiquito'">
          <talento-finiquito :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'custodia'">
          <talento-custodia :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'dispositivos'">
          <talento-dispositivos :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'roles_multiples'">
          <talento-embajadores :colaborador-id="id" />
        </div>
      </div>
    </template>

    <div v-else class="alert alert-danger">No se encontró el colaborador.</div>
  </div>
</template>

<script>
import { darkMode } from "../../../hook/appConfig.js";
import TalentoFichaInformacion from "./TalentoFichaInformacion.vue";
import TalentoOrdenes from "./TalentoOrdenes.vue";
import TalentoCompensacion from "./TalentoCompensacion.vue";
import TalentoLiquidaciones from "./TalentoLiquidaciones.vue";
import TalentoAsistencia from "./TalentoAsistencia.vue";
import TalentoCampo from "./TalentoCampo.vue";
import TalentoCajas from "./TalentoCajas.vue";
import TalentoRutas from "./TalentoRutas.vue";
import TalentoCalidad from "./TalentoCalidad.vue";
import TalentoProyectos from "./TalentoProyectos.vue";
import TalentoPenalizaciones from "./TalentoPenalizaciones.vue";
import TalentoCredenciales from "./TalentoCredenciales.vue";
import TalentoFiniquito from "./TalentoFiniquito.vue";
import TalentoCustodia from "./TalentoCustodia.vue";
import TalentoDispositivos from "./TalentoDispositivos.vue";
import TalentoEmbajadores from "./TalentoEmbajadores.vue";

export default {
  name: "TalentoColaboradorFicha",
  // Registro LOCAL explícito de cada pestaña reusada — necesario aunque estos
  // mismos componentes YA estén en el objeto `components` de createApp() en
  // app.js: ese registro es local a la instancia RAÍZ (options del objeto que
  // recibe createApp()), no global vía app.component(), así que un componente
  // anidado (como este, usado dentro de <talento-colaborador-ficha>) NO lo
  // hereda — solo ve sus propios `components` + lo registrado con
  // app.component() de verdad. Sin esto cada <talento-xxx> se renderiza
  // literal sin resolver (bug real encontrado con Playwright 28-sep-2026).
  components: {
    TalentoFichaInformacion,
    TalentoOrdenes,
    TalentoCompensacion,
    TalentoLiquidaciones,
    TalentoAsistencia,
    TalentoCampo,
    TalentoCajas,
    TalentoRutas,
    TalentoCalidad,
    TalentoProyectos,
    TalentoPenalizaciones,
    TalentoCredenciales,
    TalentoFiniquito,
    TalentoCustodia,
    TalentoDispositivos,
    TalentoEmbajadores,
  },
  props: {
    id: { type: [Number, String], required: true },
    // Flags de permiso del VIEWER, resueltas server-side en la vista Blade
    // (mismo criterio que @can en el resto del proyecto) — evita depender
    // de un store de permisos en el cliente solo para decidir qué pestaña
    // mostrar.
    permisos: {
      type: Object,
      default: () => ({}),
    },
    // Resuelto server-side: true si quien mira esta ficha ES el mismo
    // colaborador (independiente de si tiene acceso al Portal o no) — decide
    // qué endpoint usar en load() (ver TalentoColaboradorController::miFicha()).
    // Pese al nombre, cubre DOS casos desde el servidor (ficha()): verse a
    // uno mismo, O ser el supervisor directo viendo a su subordinado — ambos
    // usan el endpoint self/supervisor-scoped (mi-ficha) en vez del de
    // roster completo. Solo decide QUÉ ENDPOINT llamar; mostrarPortal (abajo)
    // es la propiedad realmente restringida a "uno mismo, nunca el supervisor".
    esPropia: {
      type: Boolean,
      default: false,
    },
    // Literal "uno mismo" (nunca el supervisor) + permiso portal.colaborador.
    mostrarPortal: {
      type: Boolean,
      default: false,
    },
  },
  setup() {
    return { darkMode };
  },
  data() {
    return {
      colaborador: null,
      loading: true,
      activeTab: "informacion",
      lista: [],
    };
  },
  computed: {
    esTecnico() {
      const roles = this.colaborador?.user?.role_names ?? [];
      return ["TECNICO", "TECNICO_INSTALADOR", "TECNICO_PLANTA"].some(r => roles.includes(r));
    },
    // Un técnico viendo SU PROPIA ficha ya tiene sus órdenes dentro de "Mi
    // trabajo (Portal)" — la pestaña admin (con crear/validar) solo aplica
    // si además gestiona órdenes, o si está viendo la ficha de alguien más
    // (oversight de staff, sin cambios respecto a como estaba).
    mostrarTabOrdenes() {
      if (!this.permisos.ordenes) return false;
      if (this.esPropia && !this.permisos.ordenes_manage) return false;
      return true;
    },
    isFirst() {
      const idx = this.lista.findIndex(c => String(c.id) === String(this.id));
      return idx <= 0;
    },
    isLast() {
      const idx = this.lista.findIndex(c => String(c.id) === String(this.id));
      return idx === -1 || idx === this.lista.length - 1;
    },
  },
  mounted() {
    const savedTab = localStorage.getItem("talentoFichaActiveTab");
    if (savedTab) this.activeTab = savedTab;
    this.load();
    this.loadLista();
  },
  watch: {
    id() {
      this.load();
    },
  },
  methods: {
    setActiveTab(tab) {
      this.activeTab = tab;
      localStorage.setItem("talentoFichaActiveTab", tab);
    },
    async load() {
      this.loading = true;
      try {
        // Self/supervisor-scoped (sin talento.employees.view) cuando `esPropia`
        // vino true desde ficha() — cubre TANTO verse a uno mismo como al
        // supervisor viendo a su subordinado directo (miFicha() distingue por
        // el {id}; ver TalentoColaboradorController::miFicha()).
        const url = this.esPropia
          ? `/talento/mi-ficha/${this.id}`
          : `/talento/api/colaboradores/${this.id}`;
        const { data } = await axios.get(url);
        this.colaborador = data;
      } catch (e) {
        this.colaborador = null;
      } finally {
        this.loading = false;
      }
    },
    async loadLista() {
      try {
        const { data } = await axios.get("/talento/api/colaboradores", { params: { per_page: 1000 } });
        this.lista = data?.data ?? [];
      } catch (e) {}
    },
    goToPrevious() {
      const idx = this.lista.findIndex(c => String(c.id) === String(this.id));
      if (idx > 0) window.location.href = `/talento/colaborador/${this.lista[idx - 1].id}`;
    },
    goToNext() {
      const idx = this.lista.findIndex(c => String(c.id) === String(this.id));
      if (idx !== -1 && idx < this.lista.length - 1) {
        window.location.href = `/talento/colaborador/${this.lista[idx + 1].id}`;
      }
    },
    statusLabel(s) {
      return { active: "Activo", inactive: "Inactivo", suspended: "Suspendido" }[s] ?? s;
    },
  },
};
</script>

<style scoped>
.tc-portal-embed {
  width: 100%;
  height: 80vh;
  min-height: 560px;
}
.tc-portal-embed iframe {
  width: 100%;
  height: 100%;
  border: 0;
  border-radius: 8px;
}
</style>
