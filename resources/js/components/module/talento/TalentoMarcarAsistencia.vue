<template>
  <!-- Registrar entrada y salida en la pestaña Asistencia de la ficha propia (2026-10-24: "eso está
       en la parte de asistencia"; antes estaba en Mi día). Con ubicación, igual que la app, y con
       los mismos endpoints /talento/portal/asistencia/* (self-scoped por Actor). -->
  <div class="tc-card mb-3">
    <div class="tc-cardhead p-3 fw-semibold"><i class="fa fa-clock me-2"></i>Tu asistencia de hoy</div>
    <div class="p-3">
      <div v-if="aviso.texto" class="alert py-2 small d-flex align-items-center justify-content-between"
           :class="'alert-' + aviso.tipo">
        <span>{{ aviso.texto }}</span>
        <button type="button" class="btn-close btn-sm" @click="aviso.texto = ''"></button>
      </div>

      <div v-if="cargando" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Consultando…</div>
      <template v-else>
        <div v-if="yaSalio" class="small">
          <i class="fa fa-check-circle text-success me-1"></i>Turno cerrado.
          Entrada {{ hora(asistencia.check_in_at) }} · Salida {{ hora(asistencia.check_out_at) }}
        </div>
        <template v-else-if="turnoAbierto">
          <div v-if="asistencia.stale_day" class="alert alert-warning small py-2">
            Tienes un turno de un día anterior sin cerrar. Registra tu salida para poder iniciar uno nuevo.
          </div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="tc-status is-ok">Entrada {{ hora(asistencia.check_in_at) }}</span>
            <span v-if="asistencia.geocerca" class="tc-status is-info">{{ asistencia.geocerca }}</span>
            <span v-if="asistencia.a_mano" class="tc-status is-slate">Ubicación escrita</span>
            <span v-if="asistencia.flagged" class="tc-status is-warn">Marcada para revisión</span>
          </div>
          <button class="tc-btn tc-btn-warn" :disabled="accion" @click="registrarSalida">
            <span v-if="accion" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fa fa-sign-out-alt me-1"></i>Registrar salida
          </button>
        </template>
        <template v-else>
          <div class="small text-muted mb-2">Aún no registras tu entrada.</div>
          <button class="tc-btn tc-btn-ok" :disabled="accion" @click="registrarEntrada">
            <span v-if="accion" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fa fa-map-marker-alt me-1"></i>Registrar entrada
          </button>
          <div class="tc-muted-txt mt-2">Se usa tu ubicación para revisar que estés en tu lugar de trabajo.</div>

          <!-- Sin ubicación del navegador (2026-10-29): se escribe dónde está y se elige de la lista. -->
          <div v-if="manual.visible" class="mt-3">
            <label class="form-label small mb-1" for="asis-direccion">¿Dónde estás? Escribe la dirección o el lugar</label>
            <div class="d-flex gap-2">
              <input id="asis-direccion" v-model.trim="manual.texto" type="text" class="form-control form-control-sm"
                     placeholder="Ej. Calle, colonia, municipio" maxlength="255" @keyup.enter="buscarDireccion">
              <button class="tc-btn tc-btn-info" :disabled="manual.buscando || manual.texto.length < 3" @click="buscarDireccion">
                <span v-if="manual.buscando" class="spinner-border spinner-border-sm me-1"></span>Buscar
              </button>
            </div>
            <div v-if="manual.resultados.length" class="list-group mt-2">
              <button v-for="(r, i) in manual.resultados" :key="i" type="button"
                      class="list-group-item list-group-item-action small" :disabled="accion" @click="elegirDireccion(r)">
                <i class="fa fa-map-marker-alt me-1"></i>{{ r.direccion_formateada }}
              </button>
            </div>
            <div v-else-if="manual.buscado && !manual.buscando" class="small text-muted mt-2">
              No encontramos esa dirección. Prueba con la calle, la colonia y el municipio.
            </div>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>

<script>
function ubicacion() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) { reject({ code: "unsupported" }); return; }
    navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  });
}

export default {
  name: "TalentoMarcarAsistencia",
  emits: ["marcado"],
  data() {
    return {
      aviso: { tipo: "info", texto: "" },
      asistencia: null,
      cargando: true,
      accion: false,
      manual: { visible: false, texto: "", buscando: false, buscado: false, resultados: [] },
    };
  },
  computed: {
    yaSalio() { return !!this.asistencia?.check_out_at; },
    turnoAbierto() { return !!this.asistencia?.check_in_at && !this.yaSalio; },
  },
  mounted() {
    this.cargar();
  },
  methods: {
    avisar(tipo, texto) {
      this.aviso = { tipo, texto };
    },
    error(e, porDefecto) {
      const d = e?.response?.data;
      return d?.message || d?.error || porDefecto;
    },
    hora(dt) {
      if (!dt) return "";
      const d = new Date(String(dt).replace(" ", "T"));
      return isNaN(d.getTime()) ? "" : d.toLocaleTimeString("es-MX", { hour: "2-digit", minute: "2-digit" });
    },
    async cargar() {
      this.cargando = true;
      try {
        const { data } = await axios.get("/talento/portal/asistencia/hoy");
        this.asistencia = data ?? null;
      } catch (e) {
        this.asistencia = null;
      } finally {
        this.cargando = false;
      }
    },
    async registrarEntrada() {
      if (this.accion) return;
      this.accion = true;
      let pos;
      try {
        pos = await ubicacion();
      } catch (err) {
        // Sin ubicación (sin permiso, sin GPS o sin candado https): se escribe a mano, igual que en la app.
        this.accion = false;
        this.manual.visible = true;
        this.avisar("warning", "No pudimos obtener tu ubicación. Escribe dónde estás para registrar tu entrada.");
        return;
      }
      const { latitude, longitude, accuracy } = pos.coords;
      await this.enviarEntrada({ lat: latitude, lng: longitude, accuracy });
    },
    async buscarDireccion() {
      if (this.manual.texto.length < 3 || this.manual.buscando) return;
      this.manual.buscando = true;
      try {
        const { data } = await axios.get("/talento/portal/geocodificar", { params: { q: this.manual.texto } });
        this.manual.resultados = data?.resultados ?? [];
      } catch (e) {
        this.manual.resultados = [];
      } finally {
        this.manual.buscado = true;
        this.manual.buscando = false;
      }
    },
    async elegirDireccion(r) {
      await this.enviarEntrada({ lat: r.lat, lng: r.lng, location_source: "manual_address" });
    },
    async enviarEntrada(body) {
      this.accion = true;
      try {
        const { data } = await axios.post("/talento/portal/asistencia/checkin", body);
        this.manual = { visible: false, texto: "", buscando: false, buscado: false, resultados: [] };
        await this.cargar();
        this.$emit("marcado");
        if (data.status === "already_open") this.avisar("info", "Ya tenías una entrada activa hoy.");
        else if (data.flagged) this.avisar("warning", "Entrada registrada, marcada para revisión" + (data.flag_reason ? " (" + data.flag_reason + ")" : "") + ".");
        else this.avisar("success", "Entrada registrada" + (data.geocerca ? " en " + data.geocerca : "") + ".");
      } catch (e) {
        this.avisar("danger", this.error(e, "No se pudo registrar la entrada."));
      } finally {
        this.accion = false;
      }
    },
    async registrarSalida() {
      if (this.accion) return;
      this.accion = true;
      let body = {};
      try {
        const pos = await ubicacion();
        body = { lat: pos.coords.latitude, lng: pos.coords.longitude };
      } catch (e) { /* la salida se permite sin ubicación */ }
      try {
        await axios.post("/talento/portal/asistencia/checkout", body);
        await this.cargar();
        this.$emit("marcado");
        this.avisar("success", "Salida registrada. ¡Buen trabajo!");
      } catch (e) {
        this.avisar("danger", this.error(e, "No se pudo registrar la salida."));
      } finally {
        this.accion = false;
      }
    },
  },
};
</script>
