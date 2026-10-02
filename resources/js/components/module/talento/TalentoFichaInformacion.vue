<template>
  <div class="tc-card p-3">
    <div class="row g-4">
      <div class="col-md-6">
        <h6 class="text-muted small text-uppercase mb-3">Datos generales</h6>
        <dl class="row mb-0 small">
          <dt class="col-5">Nombre</dt><dd class="col-7">{{ colaborador.user?.name ?? '—' }}</dd>
          <dt class="col-5">Email</dt><dd class="col-7">{{ colaborador.user?.email ?? '—' }}</dd>
          <dt class="col-5">Tipo</dt><dd class="col-7">{{ colaborador.type ?? '—' }}</dd>
          <dt class="col-5">Departamento</dt><dd class="col-7">{{ colaborador.department ?? '—' }}</dd>
          <dt class="col-5">Puesto</dt><dd class="col-7">{{ colaborador.puesto?.nombre ?? colaborador.job_title ?? '—' }}</dd>
          <dt class="col-5">Supervisor</dt><dd class="col-7">{{ colaborador.supervisor?.user?.name ?? '—' }}</dd>
          <dt class="col-5">Ingreso</dt><dd class="col-7">{{ formatDate(colaborador.hire_date) }}</dd>
          <dt class="col-5">Status</dt><dd class="col-7">
            <span class="tc-status" :class="statusBadge(colaborador.status)">{{ statusLabel(colaborador.status) }}</span>
          </dd>
        </dl>
      </div>
      <div class="col-md-6">
        <h6 class="text-muted small text-uppercase mb-3">Roles del sistema</h6>
        <div v-if="colaborador.user?.role_names?.length">
          <span v-for="r in colaborador.user.role_names" :key="r" class="tc-status is-slate me-1 mb-1">
            {{ r }}
          </span>
        </div>
        <div v-else class="text-muted small">Sin roles asignados.</div>

        <h6 class="text-muted small text-uppercase mb-2 mt-4">Subordinados</h6>
        <div v-if="colaborador.subordinados?.length" class="small">
          <div v-for="s in colaborador.subordinados" :key="s.id">{{ s.user?.name }}</div>
        </div>
        <div v-else class="text-muted small">Sin subordinados.</div>

        <div v-if="puedeGestionarAcceso" class="mt-4">
          <a :href="`/administracion/user/${colaborador.user_id}/editar`" target="_blank" class="tc-btn tc-btn-info">
            <i class="fa fa-key me-1"></i> Gestión de acceso (Administradores)
          </a>
        </div>
      </div>
    </div>

    <!-- Credencial/gafete -- David (2-oct): "faltan las credenciales como
         mismo esta en vendedores". Mismo template compartido que ya usa
         Vendedores (App\Models\Credential + /configuracion/credencial/*),
         nada nuevo que configurar; solo cambia el titulo (puesto real en vez
         de "Vendedor") y los datos vienen de este colaborador. -->
    <div class="row mt-4">
      <div class="col-md-6">
        <h6 class="text-muted small text-uppercase mb-3">Mi credencial</h6>
        <div class="d-flex flex-wrap gap-3 align-items-start">
          <div
            class="card m-0 p-2 tc-credential"
            :style="{
              backgroundImage: imgFront?.name
                ? `url(/credencial/${imgFront.name})`
                : 'url(/images/gafete-front.png)',
            }"
          >
            <div class="card-body">
              <img
                class="tc-credential-logo"
                :src="imgLogo?.name ? `/credencial/${imgLogo.name}` : '/images/logo_meganet.jpg'"
                alt="logo"
              />
              <div class="d-flex justify-content-center mt-3">
                <img
                  class="tc-credential-perfil"
                  :src="colaborador.user?.photography ? `/perfiles/${colaborador.user.photography}` : '/images/perfil.png'"
                />
              </div>
              <h3 class="tc-credential-name text-uppercase">
                {{ colaborador.user?.name }} {{ colaborador.user?.father_last_name }} {{ colaborador.user?.mother_last_name }}
              </h3>
              <h3 class="tc-credential-title">{{ colaborador.puesto?.nombre ?? colaborador.job_title ?? 'Colaborador' }}</h3>
              <p class="tc-credential-text">Teléfono: {{ colaborador.user?.phone }}</p>
              <p class="tc-credential-text">Correo electrónico: {{ colaborador.user?.email }}</p>
            </div>
          </div>
          <div class="align-self-center">
            <a
              class="tc-btn tc-btn-ok"
              :href="`/talento/mi-ficha/${colaborador.id}/pdf`"
              target="_blank"
            >
              <i class="fa fa-file-download me-1"></i> Descargar credencial
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { getImageFront, getImageBack, getImageLogo } from "../vendors/helper/request.js";

export default {
  name: "TalentoFichaInformacion",
  props: {
    colaborador: { type: Object, required: true },
    // David (29-sep): el link a /administracion/user/{id}/editar exige
    // user_edit_user (solo roles admin) — sin este flag se mostraba a
    // cualquiera, incluido un técnico viendo su propia ficha, que al
    // hacer clic caía en 403/redirect silencioso.
    puedeGestionarAcceso: { type: Boolean, default: false },
  },
  data() {
    return { imgFront: null, imgBack: null, imgLogo: null };
  },
  async mounted() {
    // Mismo template compartido que Vendedores -- NO se duplica config ni imagenes.
    this.imgFront = await getImageFront();
    this.imgBack = await getImageBack();
    this.imgLogo = await getImageLogo();
  },
  methods: {
    statusBadge(s) {
      return { active: "is-ok", inactive: "is-slate", suspended: "is-warn" }[s] ?? "is-slate";
    },
    statusLabel(s) {
      return { active: "Activo", inactive: "Inactivo", suspended: "Suspendido" }[s] ?? s;
    },
    formatDate(d) {
      if (!d) return "—";
      return new Date(d).toLocaleDateString("es-MX", { year: "numeric", month: "short", day: "numeric" });
    },
  },
};
</script>

<style scoped>
.tc-credential {
  width: 220px;
  height: 380px;
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
}
.tc-credential-logo {
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
  object-fit: contain;
  height: 16%;
  width: 100%;
}
.tc-credential-perfil {
  border-radius: 50%;
  width: 72px;
  height: 72px;
  border: 3px solid #004790;
}
.tc-credential-name {
  margin-top: 8px;
  font-size: 0.95rem;
  font-weight: bold;
  color: #fff;
  text-align: center;
}
.tc-credential-title {
  font-size: 0.65rem;
  color: #fff;
  text-align: center;
  text-transform: uppercase;
}
.tc-credential-text {
  font-size: 0.75rem;
  color: #fff;
  text-align: center;
}
</style>
