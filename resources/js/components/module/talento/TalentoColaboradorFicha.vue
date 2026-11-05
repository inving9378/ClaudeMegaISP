<template>
  <div class="tc-wrap" :class="{ 'tc-dark': darkMode }">
    <!-- Ruta y flechas solo para quien ve la lista de colaboradores; quien entra a su propia
         ficha como un técnico (también el Mostrador) va directo a su tablero (2026-10-23). -->
    <div v-if="permisos.lista_colaboradores" class="d-flex justify-content-between align-items-center">
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
      <!-- Pestañas (2026-10-28, video de Irving: «¿qué es esto, de dónde sale?»): las del día a día a
           la vista y el resto en «Más», cada una con una línea que dice para qué sirve. -->
      <ul class="nav nav-tabs tc-tabs">
        <li v-for="t in principales" :key="t.key" class="nav-item">
          <a class="nav-link" :class="{ active: activeTab === t.key }" href="#"
             @click.prevent="setActiveTab(t.key)">
            <i class="bi me-1" :class="t.icon"></i> {{ t.label }}
          </a>
        </li>
        <li v-if="secundarias.length" ref="mas" class="nav-item tc-mas">
          <a class="nav-link" :class="{ active: activaEnMas }" href="#" @click.prevent="alternarMas">
            <i class="bi me-1" :class="activaEnMas ? pestanaActiva.icon : 'bi-three-dots'"></i>
            {{ activaEnMas ? pestanaActiva.label : 'Más' }}
            <i class="bi bi-chevron-down ms-1 tc-mas-flecha"></i>
          </a>
          <div v-if="masAbierto" class="tc-mas-menu" :class="{ 'tc-mas-menu--der': masALaDerecha }">
            <a v-for="t in secundarias" :key="t.key" href="#" class="tc-mas-item"
               :class="{ active: activeTab === t.key }" @click.prevent="setActiveTab(t.key)">
              <i class="bi" :class="t.icon"></i>
              <span><strong>{{ t.label }}</strong><small>{{ t.ayuda }}</small></span>
            </a>
          </div>
        </li>
      </ul>
      <p v-if="pestanaActiva" class="tc-ayuda"><i class="bi bi-info-circle me-1"></i>{{ pestanaActiva.ayuda }}</p>

      <!-- Paneles — montaje perezoso del activo, como Menu.vue -->
      <div class="tc-panel">
        <div v-if="activeTab === 'informacion'">
          <!-- La credencial va dentro de la tarjeta de Datos generales, no en una pestaña aparte
               (2026-10-24). -->
          <talento-ficha-informacion :colaborador="colaborador"
            :puede-gestionar-acceso="permisos.informacion_gestion_acceso">
            <talento-credenciales v-if="permisos.credenciales" compacto
              :colaborador-id="id" :colaborador="colaborador" :puede-gestionar="permisos.credenciales_manage"
              :puede-editar-carnet="!!permisos.carnet_editar" :puede-diseno-carnet="!!permisos.carnet_diseno" />
          </talento-ficha-informacion>
          <!-- "Mi progreso" nativo (2026-10-09, pedido de Irving): sin iframe, sin ir al
               Portal — se muestra directo si el colaborador de ESTA ficha es técnico
               (el propio componente no pinta nada si no lo es). -->
          <talento-ficha-calidad v-if="conTablero" :colaborador-id="id" />
          <!-- El «Mi progreso» del vendedor que tenía el Portal: la misma pantalla de «Mis ventas»
               (self-scoped), solo en su propia ficha (2026-10-23, al retirar el Portal). -->
          <template v-if="!conTablero && secciones.includes('ventas')">
            <h6 class="text-muted small text-uppercase mb-3 mt-4">Mi progreso</h6>
            <talento-ventas />
          </template>
        </div>
        <div v-if="activeTab === 'mi_dia'">
          <talento-mi-dia />
        </div>
        <div v-if="activeTab === 'mi_dinero'">
          <talento-mi-dinero />
        </div>
        <div v-if="activeTab === 'mis_prospectos'">
          <talento-mis-prospectos :puede-abrir-crm="!!permisos.crm_editar" />
        </div>
        <div v-if="activeTab === 'mi_equipo'">
          <talento-mi-equipo />
        </div>
        <div v-if="activeTab === 'ordenes'">
          <talento-ordenes :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''" :puede-gestionar="permisos.ordenes_manage" />
        </div>
        <div v-if="activeTab === 'compensacion'">
          <talento-compensacion :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''" :puede-gestionar="permisos.compensacion_manage" />
        </div>
        <div v-if="activeTab === 'liquidaciones'">
          <talento-liquidaciones :colaborador-id="id" :puede-gestionar="permisos.liquidaciones_manage" />
        </div>
        <div v-if="activeTab === 'asistencia'">
          <talento-asistencia :colaborador-id="id" :es-propia="esUnoMismoLiteral"
            :puede-marcar="secciones.includes('mi_dia')" />
        </div>
        <div v-if="activeTab === 'campo'">
          <talento-campo :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''"
            :puede-gestionar="permisos.ordenes_manage"
            :puede-subir-evidencia="permisos.campo_evidencia_manage" />
        </div>
        <div v-if="activeTab === 'cajas'">
          <talento-cajas :colaborador-id="id" />
        </div>
        <div v-if="activeTab === 'rutas'">
          <talento-rutas :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''" :puede-gestionar="permisos.rutas_manage" />
        </div>
        <div v-if="activeTab === 'calidad'">
          <talento-calidad :colaborador-id="id"
            :puede-validar="permisos.calidad_validar"
            :puede-gestionar-estandares="permisos.calidad_estandares_manage" />
        </div>
        <div v-if="activeTab === 'proyectos'">
          <talento-proyectos :colaborador-id="id" :puede-gestionar="permisos.proyectos_manage"
            :puede-otorgar-bono="permisos.proyectos_bono" />
        </div>
        <div v-if="activeTab === 'penalizaciones'">
          <talento-penalizaciones :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''" :puede-gestionar="permisos.penalizaciones_manage" />
        </div>
        <div v-if="activeTab === 'finiquito'">
          <talento-finiquito :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''"
            :puede-gestionar-prestamos="permisos.prestamos_manage"
            :puede-gestionar-finiquito="permisos.settlement_manage" />
        </div>
        <div v-if="activeTab === 'custodia'">
          <talento-custodia :colaborador-id="id" :puede-gestionar="permisos.custodia_buscador" />
        </div>
        <div v-if="activeTab === 'dispositivos'">
          <talento-dispositivos :colaborador-id="id" :colaborador-nombre="colaborador?.user?.name ?? ''"
            :puede-gestionar="permisos.dispositivos_buscador" />
        </div>
        <div v-if="activeTab === 'roles_multiples'">
          <talento-embajadores :colaborador-id="id"
            :colaborador-nombre="colaborador?.user?.name ?? ''"
            :colaborador-email="colaborador?.user?.email ?? ''"
            :colaborador-type="colaborador?.type ?? ''"
            :puede-gestionar="permisos.embajadores_buscador" />
        </div>
        <div v-if="activeTab === 'documentos'">
          <!-- David (28-sep): MISMO componente que ya usan Vendedores/el
               modal admin (TalentoColaboradores.vue) — "igual que en
               vendedor" para firmas/huecos, no una copia aparte. -->
          <talento-expediente-documentos :colaborador-id="Number(id)"
            :puede-gestionar="permisos.documentos_gestionar" />
        </div>
        <div v-if="activeTab === 'academia'">
          <!-- David (29-sep): "debe mostrar los cursos para poder entrar,
               no solo el resumen — el progreso debe poder empezarlos ahí
               mismo". TalentoAcademia.vue (catálogo completo: ver curso,
               tomar examen) es SIEMPRE self-scoped por auth()->id() en el
               backend — no se le puede apuntar al colaborador de la
               ficha, así que solo tiene sentido para uno mismo (nunca el
               supervisor viendo a su subordinado: "tomar el examen por
               él" falsificaría el resultado, mismo criterio que el
               Portal). Con eso, el resumen de solo lectura
               (TalentoFichaAcademia.vue) sigue siendo lo correcto para
               cuando el que mira NO es el propio colaborador. -->
          <template v-if="esUnoMismoLiteral">
            <talento-academia />
            <!-- David (29-sep): "no estaría mal poner debajo lo que
                 estaba antes, el progreso y las etapas del curso" — el
                 catálogo (arriba) no reemplaza el resumen, se agrega. -->
            <hr class="my-4">
            <talento-ficha-academia :colaborador-id="id" />
          </template>
          <talento-ficha-academia v-else :colaborador-id="id" />
        </div>
      </div>
    </template>

    <div v-else class="alert alert-danger">No se encontró el colaborador.</div>
  </div>
</template>

<script>
import { currentSearch } from "../../../hook/comunValues.js";
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
import TalentoFichaAcademia from "./TalentoFichaAcademia.vue";
import TalentoFichaCalidad from "./TalentoFichaCalidad.vue";
import TalentoMiDia from "./TalentoMiDia.vue";
import TalentoMiDinero from "./TalentoMiDinero.vue";
import TalentoMisProspectos from "./TalentoMisProspectos.vue";
import TalentoMiEquipo from "./TalentoMiEquipo.vue";
import TalentoVentas from "./TalentoVentas.vue";
// David (29-sep): a diferencia de talento-expediente-documentos (registrado
// globalmente vía app.component(), visible en cualquier SFC hijo),
// TalentoAcademia vive en el objeto components:{} gigante de createApp() en
// app.js — eso lo registra LOCAL a ese componente raíz únicamente (no en
// cascada a SFCs hijos como este). Sin este import local, Vue no podía
// resolverlo aquí ("Failed to resolve component: talento-academia",
// el tag quedaba vacío en el DOM).
import TalentoAcademia from "./TalentoAcademia.vue";

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
    TalentoFichaAcademia,
    TalentoAcademia,
    TalentoFichaCalidad,
    TalentoMiDia,
    TalentoMiDinero,
    TalentoMisProspectos,
    TalentoMiEquipo,
    TalentoVentas,
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
    // roster completo. Solo decide QUÉ ENDPOINT llamar; esUnoMismoLiteral y
    // secciones (abajo) son lo realmente restringido a "uno mismo, nunca el supervisor".
    esPropia: {
      type: Boolean,
      default: false,
    },
    // Literal "uno mismo" (nunca el supervisor) — decide si la pestaña Academia monta el catálogo
    // completo (talento-academia, self-scoped) o el resumen de solo
    // lectura (talento-ficha-academia).
    esUnoMismoLiteral: {
      type: Boolean,
      default: false,
    },
    // Secciones del Portal que ahora son pestañas de la propia ficha (2026-10-23): llegan solo
    // cuando quien mira es el propio colaborador (ver ficha() en el controlador).
    secciones: {
      type: Array,
      default: () => [],
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
      masAbierto: false,
      masALaDerecha: false,
      lista: [],
    };
  },
  computed: {
    esTecnico() {
      const roles = this.colaborador?.user?.role_names ?? [];
      return ["TECNICO", "TECNICO_INSTALADOR", "TECNICO_PLANTA"].some(r => roles.includes(r));
    },
    // El tablero «Mi progreso» es del técnico y también del Mostrador (2026-10-23); las pestañas
    // de campo siguen siendo solo del técnico.
    conTablero() {
      const roles = this.colaborador?.user?.role_names ?? [];
      return this.esTecnico || ["Mostrador", "SUPERVISOR_MOSTRADOR"].some(r => roles.includes(r));
    },
    // Todas las pestañas que esta persona puede ver aquí, en orden, con la línea de para qué sirve.
    pestanas() {
      const p = this.permisos, s = this.secciones, tec = this.esTecnico;
      return [
        { key: "mi_dia", label: "Mi día", icon: "bi-sun", ver: s.includes("mi_dia"),
          ayuda: "Lo de hoy: tu entrada y salida, y tus órdenes con su evidencia y firmas." },
        { key: "mi_dinero", label: "Mi dinero", icon: "bi-wallet2", ver: s.includes("mi_dinero"),
          ayuda: "Lo que se te va a pagar esta semana y de qué se compone: unidades, sueldo base, bonos y descuentos." },
        { key: "informacion", label: "Información", icon: "bi-person", ver: true,
          ayuda: "Datos, puesto, credencial para imprimir y avance del trabajo." },
        { key: "asistencia", label: "Asistencia", icon: "bi-calendar-check", ver: !!p.asistencia,
          ayuda: "Entradas, salidas y faltas. Cada falta descuenta una parte del sueldo base." },
        { key: "mis_prospectos", label: "Mis prospectos", icon: "bi-people", ver: s.includes("prospectos"),
          ayuda: "Los clientes potenciales que registraste y en qué van." },
        { key: "mi_equipo", label: "Mi equipo", icon: "bi-diagram-2", ver: s.includes("equipo"),
          ayuda: "Las personas a tu cargo y cómo van hoy." },
        { key: "ordenes", label: "Órdenes", icon: "bi-clipboard-check", ver: this.mostrarTabOrdenes,
          ayuda: "Órdenes de trabajo: crearlas, asignarlas y revisarlas. Las terminadas con su evidencia cuentan para el pago." },
        { key: "compensacion", label: "Compensación", icon: "bi-coin", ver: !!p.compensacion,
          ayuda: "Regla de pago: sueldo base de la semana, unidades que le tocan y cuánto vale cada unidad extra." },
        { key: "liquidaciones", label: "Liquidaciones", icon: "bi-file-earmark-medical", ver: !!p.liquidaciones,
          ayuda: "El pago de cada semana: se calcula con lo terminado y se cierra para pagarlo. De aquí sale el comprobante." },
        { key: "penalizaciones", label: "Penalizaciones", icon: "bi-exclamation-octagon", ver: !!p.penalizaciones,
          ayuda: "Penalizaciones aplicadas; se descuentan del pago de la semana." },
        { key: "finiquito", label: "Préstamos/Finiquito", icon: "bi-cash-coin", ver: !!p.finiquito,
          ayuda: "Préstamos y adelantos, que se descuentan cada semana, y el finiquito al dejar de trabajar." },
        { key: "documentos", label: "Documentos", icon: "bi-folder2-open", ver: !!p.documentos,
          ayuda: "Contratos y documentos para firmar." },
        { key: "campo", label: "Flujo de campo", icon: "bi-cone-striped", ver: !!p.campo && tec,
          ayuda: "Órdenes de campo con fotos de evidencia y firma del cliente." },
        { key: "cajas", label: "Cajas ODB", icon: "bi-reception-4", ver: !!p.cajas && tec,
          ayuda: "Lecturas de señal de las cajas de fibra: si el cliente no pierde señal, hay bono de salud de red." },
        { key: "rutas", label: "Rutas planta", icon: "bi-signpost-split", ver: !!p.rutas && tec,
          ayuda: "Rutas de trabajo de planta interna." },
        { key: "calidad", label: "Calidad de caja", icon: "bi-patch-check", ver: !!p.calidad && tec,
          ayuda: "Revisión de las cajas instaladas contra el estándar; la valida el supervisor." },
        { key: "proyectos", label: "Proyectos", icon: "bi-diagram-3", ver: !!p.proyectos,
          ayuda: "Proyectos de planta externa y su avance." },
        { key: "custodia", label: "Custodia", icon: "bi-box-seam", ver: !!p.custodia,
          ayuda: "Herramienta y material a cargo de la persona." },
        { key: "dispositivos", label: "Dispositivos", icon: "bi-phone", ver: !!p.dispositivos,
          ayuda: "La app de Talento para el teléfono y los teléfonos donde está abierta." },
        { key: "roles_multiples", label: "Roles múltiples", icon: "bi-link-45deg", ver: !!p.roles_multiples,
          ayuda: "Si además vende o es embajador: sus datos y ventas como vendedor." },
        { key: "academia", label: "Academia", icon: "bi-mortarboard", ver: !!p.academia,
          ayuda: "Cursos y exámenes." },
      ].filter((t) => t.ver);
    },
    // A la vista: en la propia ficha, lo del día a día; viendo a otra persona, lo que se revisa
    // para pagarle. Lo demás queda en «Más».
    principales() {
      const claves = this.esUnoMismoLiteral && this.secciones.length
        ? ["mi_dia", "mi_dinero", "informacion", "asistencia", "mis_prospectos", "mi_equipo", "documentos"]
        : ["informacion", "asistencia", "ordenes", "compensacion", "liquidaciones", "penalizaciones", "documentos"];
      return this.pestanas.filter((t) => claves.includes(t.key));
    },
    secundarias() {
      return this.pestanas.filter((t) => !this.principales.includes(t));
    },
    pestanaActiva() {
      return this.pestanas.find((t) => t.key === this.activeTab) ?? null;
    },
    activaEnMas() {
      return this.secundarias.some((t) => t.key === this.activeTab);
    },
    // Un técnico viendo SU PROPIA ficha ya tiene sus órdenes de hoy en "Mi
    // día" — la pestaña admin (con crear/validar) solo aplica
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
    this.load();
    if (this.permisos.lista_colaboradores) this.loadLista();
    document.addEventListener("click", this.cerrarMas);
  },
  beforeUnmount() {
    document.removeEventListener("click", this.cerrarMas);
  },
  watch: {
    id() {
      this.load();
    },
  },
  methods: {
    setActiveTab(tab) {
      this.activeTab = tab;
      this.masAbierto = false;
      try { localStorage.setItem("talentoFichaActiveTab", tab); } catch (e) {}
    },
    // El menú abre hacia la derecha; si no cabe, se alinea al borde derecho de «Más».
    alternarMas() {
      if (!this.masAbierto) {
        const r = this.$refs.mas?.getBoundingClientRect();
        this.masALaDerecha = !!r && r.left + 360 > window.innerWidth - 16;
      }
      this.masAbierto = !this.masAbierto;
    },
    cerrarMas(e) {
      if (this.masAbierto && !this.$refs.mas?.contains(e.target)) this.masAbierto = false;
    },
    // ?tab= (atajo «Mi día» del menú de usuario, la campanita) manda; la propia ficha abre siempre en
    // «Mi día», que es donde está la entrada y lo de hoy; viendo a otra persona, la última pestaña.
    pestanaInicial() {
      const claves = this.pestanas.map((t) => t.key);
      const pedida = new URLSearchParams(currentSearch()).get("tab");
      if (pedida && claves.includes(pedida)) return pedida;
      if (claves.includes("mi_dia")) return "mi_dia";
      let recordada = null;
      try { recordada = localStorage.getItem("talentoFichaActiveTab"); } catch (e) {}
      return recordada && claves.includes(recordada) ? recordada : "informacion";
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
        if (this.colaborador) this.activeTab = this.pestanaInicial();
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
.tc-tabs { position: relative; }
.tc-mas { position: relative; }
.tc-mas-flecha { font-size: 10px; }
.tc-mas-menu--der { left: auto; right: 0; }
.tc-mas-menu {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: 30;
  width: 360px;
  max-width: calc(100vw - 32px);
  padding: 6px;
  background: var(--tc-surface);
  border: 1px solid var(--tc-line);
  border-radius: 10px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.14);
}
.tc-mas-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  color: var(--tc-ink);
  text-decoration: none;
}
.tc-mas-item:hover { background: var(--tc-bg2); }
.tc-mas-item.active { color: var(--tc-accent); background: var(--tc-bg2); }
.tc-mas-item i { margin-top: 2px; }
.tc-mas-item strong { display: block; font-size: 13px; }
.tc-mas-item small { display: block; font-size: 12px; line-height: 1.35; color: var(--tc-muted); }
.tc-ayuda { margin: 10px 0 0; font-size: 12.5px; color: var(--tc-muted); }
@media (max-width: 575.98px) {
  .tc-mas { position: static; }
  .tc-mas-menu { left: 0; right: 0; width: auto; }
}
</style>
