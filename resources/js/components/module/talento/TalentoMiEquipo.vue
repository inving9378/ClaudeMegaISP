<template>
  <!-- "Mi equipo" dentro de la ficha (2026-10-23): lo que el Portal le daba a un supervisor — cómo
       va su equipo y las penalizaciones por confirmar. Mismos endpoints del Portal; el servidor
       decide a quién ve (supervisor: su equipo directo; super admin: todos). -->
  <div class="talento-mi-equipo">
    <ul class="nav nav-pills mb-3 gap-1">
      <li class="nav-item">
        <a class="nav-link py-1 px-3 small" :class="{ active: tab === 'progreso' }" href="#" @click.prevent="tab = 'progreso'">Cómo va mi equipo</a>
      </li>
      <li class="nav-item">
        <a class="nav-link py-1 px-3 small" :class="{ active: tab === 'penalizaciones' }" href="#" @click.prevent="tab = 'penalizaciones'">
          Penalizaciones
          <span v-if="pen && pen.por_confirmar.length" class="badge bg-danger ms-1">{{ pen.por_confirmar.length }}</span>
        </a>
      </li>
    </ul>

    <div v-if="aviso.texto" class="alert py-2 small" :class="'alert-' + aviso.tipo">{{ aviso.texto }}</div>

    <!-- Progreso del equipo -->
    <div v-if="tab === 'progreso'">
      <div v-if="cargandoEquipo" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else-if="errorEquipo" class="alert alert-warning small">{{ errorEquipo }}</div>
      <template v-else-if="equipo">
        <div class="tc-muted-txt mb-2">
          {{ equipo.ver_todo ? 'Todos los colaboradores activos' : 'Tu equipo directo' }} · últimos {{ equipo.period_days }} días
        </div>
        <div v-if="equipo.equipo && equipo.equipo.length" class="tc-card">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead><tr><th>Colaborador</th><th>Trabajo</th><th class="text-end">Avance</th></tr></thead>
              <tbody>
                <tr v-for="m in equipo.equipo" :key="m.colaborador_id">
                  <td class="small fw-semibold"><a :href="`/talento/colaborador/${m.colaborador_id}`">{{ m.nombre }}</a></td>
                  <td class="small text-muted">{{ track(m.track) }}</td>
                  <td class="small text-end">
                    <template v-if="m.metric_label"><strong>{{ m.metric_value }}</strong> <span class="text-muted">{{ m.metric_label }}</span></template>
                    <span v-else class="text-muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="text-center text-muted small py-4">
          {{ equipo.ver_todo ? 'No hay colaboradores activos.' : 'Todavía no tienes colaboradores a tu cargo.' }}
        </div>
      </template>
    </div>

    <!-- Penalizaciones del equipo -->
    <div v-if="tab === 'penalizaciones'">
      <div v-if="cargandoPen" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      <div v-else-if="errorPen" class="alert alert-warning small">{{ errorPen }}</div>
      <template v-else-if="pen">
        <h6 class="tc-h2">Por confirmar</h6>
        <div class="tc-muted-txt mb-2">
          Las reglas semanales las proponen; el descuento solo se aplica cuando las confirmas.
          {{ pen.ver_todo ? 'Todos los colaboradores.' : 'Tu equipo directo.' }}
        </div>
        <div v-if="pen.por_confirmar.length" class="tc-card">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Colaborador</th><th>Regla y semana</th><th class="text-end">Monto</th><th></th></tr></thead>
              <tbody>
                <tr v-for="e in pen.por_confirmar" :key="e.id">
                  <td class="small fw-semibold">{{ e.colaborador }}</td>
                  <td class="small">
                    {{ e.regla }} · semana del {{ semana(e) }}
                    <div class="tc-muted-txt">{{ unidades(e) }}</div>
                  </td>
                  <td class="small text-end text-danger fw-semibold">{{ money(e.monto) }}</td>
                  <td class="text-end">
                    <button class="tc-btn tc-btn-bad btn-xs" :disabled="confirmando === e.id" @click="confirmar(e)">
                      <span v-if="confirmando === e.id" class="spinner-border spinner-border-sm me-1"></span>Confirmar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="text-center text-muted small py-3 mb-3">
          <i class="fa fa-check-circle text-success me-1"></i>No hay penalizaciones por confirmar.
        </div>

        <h6 class="tc-h2 mt-4">Aplicadas en los últimos 60 días</h6>
        <div v-if="pen.recientes.length" class="tc-card">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Colaborador</th><th>Tipo</th><th>Fecha</th><th>Aplicó</th><th class="text-end">Monto</th><th>Estado</th></tr></thead>
              <tbody>
                <tr v-for="p in pen.recientes" :key="p.id">
                  <td class="small fw-semibold">{{ p.colaborador }}</td>
                  <td class="small">{{ p.tipo }}</td>
                  <td class="small text-muted">{{ dia(p.fecha) }}</td>
                  <td class="small text-muted">{{ p.aplicado_por }}</td>
                  <td class="small text-end">{{ money(p.monto) }}</td>
                  <td><span class="tc-status" :class="estadoPen(p.status).clase">{{ estadoPen(p.status).label }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="tc-muted-txt">Sin penalizaciones aplicadas.</div>
      </template>
    </div>
  </div>
</template>

<script>
import Swal from "sweetalert2";

const TRACKS = {
  tecnico_planta_interna: "Técnico", tecnico_planta_externa: "Técnico",
  vendedor: "Vendedor", mostrador: "Mostrador", generico: "Sin tablero propio",
};
const ESTADOS_PEN = {
  applied: { label: "Aplicada", clase: "is-bad" },
  appealed: { label: "En apelación", clase: "is-warn" },
  overturned: { label: "Revocada", clase: "is-ok" },
  upheld: { label: "Mantenida", clase: "is-info" },
};
const UNIDADES = {
  tecnico: "Puntos validados", mostrador: "Pagos capturados", calidad_senal: "Instalaciones fuera de rango",
};

export default {
  name: "TalentoMiEquipo",
  data() {
    return {
      tab: "progreso",
      aviso: { tipo: "success", texto: "" },
      equipo: null,
      cargandoEquipo: true,
      errorEquipo: "",
      pen: null,
      cargandoPen: true,
      errorPen: "",
      confirmando: null,
    };
  },
  mounted() {
    this.cargarEquipo();
    this.cargarPen();
  },
  methods: {
    msg(e, porDefecto) {
      const d = e?.response?.data;
      return d?.error || d?.message || porDefecto;
    },
    async cargarEquipo() {
      this.cargandoEquipo = true;
      this.errorEquipo = "";
      try {
        const { data } = await axios.get("/talento/portal/mi-equipo-progreso");
        this.equipo = data;
      } catch (e) {
        this.errorEquipo = this.msg(e, "No se pudo cargar tu equipo.");
      } finally {
        this.cargandoEquipo = false;
      }
    },
    async cargarPen() {
      this.cargandoPen = true;
      this.errorPen = "";
      try {
        const { data } = await axios.get("/talento/portal/equipo/penalizaciones");
        this.pen = data;
      } catch (e) {
        this.errorPen = this.msg(e, "No se pudieron cargar las penalizaciones.");
      } finally {
        this.cargandoPen = false;
      }
    },
    async confirmar(e) {
      const r = await Swal.fire({
        title: "Confirmar penalización",
        text: `Se descontarán ${this.money(e.monto)} a ${e.colaborador} en la semana del ${this.semana(e)}. ¿Confirmas?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Confirmar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#dc2626",
      });
      if (!r.isConfirmed) return;
      this.confirmando = e.id;
      try {
        await axios.post(`/talento/portal/equipo/penalizaciones/${e.id}/confirmar`, {});
        this.aviso = { tipo: "success", texto: "Penalización confirmada." };
        this.cargarPen();
      } catch (err) {
        this.aviso = { tipo: "danger", texto: this.msg(err, "No se pudo confirmar.") };
      } finally {
        this.confirmando = null;
      }
    },
    track(t) { return TRACKS[t] ?? TRACKS.generico; },
    estadoPen(s) { return ESTADOS_PEN[s] ?? { label: s, clase: "is-info" }; },
    unidades(e) {
      const base = e.metric_type === "ventas"
        ? `Prospectos: ${e.prospectos} · Ventas: ${e.unidades}`
        : `${UNIDADES[e.metric_type] ?? "Unidades"}: ${e.unidades}`;
      return e.piso != null ? `${base} (mínimo ${e.piso})` : base;
    },
    money(n) {
      const v = Number(n || 0);
      return (v < 0 ? "-$" : "$") + Math.abs(v).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    // "2026-10-10" → "10 oct" en hora local (new Date('2026-10-10') sería el día anterior en México).
    dia(d) {
      const m = String(d || "").match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (!m) return d || "";
      return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])).toLocaleDateString("es-MX", { day: "numeric", month: "short" });
    },
    semana(e) { return this.dia(e.week_start) + " al " + this.dia(e.week_end); },
  },
};
</script>
