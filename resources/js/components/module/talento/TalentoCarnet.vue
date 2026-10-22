<template>
  <!-- Credencial estilo carnet (frente y reverso) — la misma que muestra
       Vendedores en la información del vendedor (InformationSeller.vue): mismas
       3 imágenes de /configuracion/credencial, mismo PDF. Cambia el título (el
       puesto real en vez de "Vendedor") y los datos salen de este colaborador. -->
  <div class="tc-carnet">
    <div class="d-flex flex-wrap justify-content-center gap-3">
      <div class="card m-0 p-2 tc-carnet-card"
           :style="{ backgroundImage: imgFront?.name ? `url(/credencial/${imgFront.name})` : 'url(/images/gafete-front.png)' }">
        <div class="card-body">
          <img class="tc-carnet-logo" :src="logoSrc" alt="logo de credencial" />
          <div class="d-flex justify-content-center mt-4">
            <img class="tc-carnet-perfil" :src="fotoSrc" alt="foto" />
          </div>
          <h3 class="tc-carnet-name text-uppercase">{{ nombreCompleto }}</h3>
          <h3 class="tc-carnet-title">{{ titulo }}</h3>
          <p class="tc-carnet-text">Teléfono: {{ user.phone || '—' }}</p>
          <p class="tc-carnet-text">Correo electrónico: {{ user.email || '—' }}</p>
          <p v-if="user.rfc" class="tc-carnet-text">RFC: {{ user.rfc }}</p>
        </div>
      </div>

      <div class="card m-0 p-2 tc-carnet-card"
           :style="{ backgroundImage: imgBack?.name ? `url(/credencial/${imgBack.name})` : 'url(/images/gafete-back.png)' }">
        <div class="card-body">
          <img class="tc-carnet-logo" :src="logoSrc" alt="logo de credencial" />
          <div class="tc-carnet-back">
            <h5 class="text-center">Dirección de la empresa:</h5>
            <p class="tc-carnet-text-black">
              Av. Hda La Purisima Mz3 Lt 54 Casa A Fracc. Ex Hacienda Santa Ines
              Nextlalpan Edo de Mexico, CP 55796.
            </p>
            <p class="text-center tc-carnet-signature"></p>
            <p class="text-center tc-carnet-firm">Firma</p>
          </div>
        </div>
      </div>
    </div>
    <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
      <a class="tc-btn tc-btn-ok" :href="`/talento/mi-ficha/${colaborador.id}/pdf`" target="_blank">
        <i class="fa fa-file-download me-1"></i> Descargar credencial
      </a>
      <button v-if="puedeEditar" type="button" class="tc-btn tc-btn-info" @click="abrirDatos">
        <i class="fa fa-pen me-1"></i> Editar foto y datos
      </button>
      <button v-if="puedeDiseno" type="button" class="tc-btn tc-btn-info" @click="abrirDiseno">
        <i class="fa fa-image me-1"></i> Cambiar diseño
      </button>
    </div>

    <!-- Foto y datos de la persona (se guardan en su usuario) -->
    <div v-if="datos.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.55);z-index:10001">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-id-card me-2"></i>Foto y datos de la credencial</h5>
            <button @click="datos.show = false" type="button" class="btn-close" :disabled="datos.saving"></button>
          </div>
          <div class="modal-body">
            <div v-if="datos.loading" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            <template v-else>
              <div class="d-flex align-items-center gap-3 mb-3">
                <img class="tc-carnet-perfil m-0" :src="datos.preview || fotoSrc" alt="foto" />
                <div>
                  <label class="form-label mb-1">Foto</label>
                  <input type="file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" @change="elegirFoto">
                </div>
              </div>
              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label">Nombre(s) *</label>
                  <input v-model.trim="datos.form.name" type="text" maxlength="100" class="form-control">
                </div>
                <div class="col-6">
                  <label class="form-label">Apellido paterno</label>
                  <input v-model.trim="datos.form.father_last_name" type="text" maxlength="100" class="form-control">
                </div>
                <div class="col-6">
                  <label class="form-label">Apellido materno</label>
                  <input v-model.trim="datos.form.mother_last_name" type="text" maxlength="100" class="form-control">
                </div>
                <div class="col-6">
                  <label class="form-label">Teléfono</label>
                  <input v-model.trim="datos.form.phone" type="text" maxlength="20" class="form-control">
                </div>
                <div class="col-6">
                  <label class="form-label">RFC</label>
                  <input v-model.trim="datos.form.rfc" type="text" maxlength="13" class="form-control text-uppercase">
                </div>
                <div class="col-12">
                  <label class="form-label">Correo electrónico</label>
                  <input v-model.trim="datos.form.email" type="email" maxlength="150" class="form-control">
                </div>
              </div>
              <div v-if="datos.error" class="alert alert-danger small mt-3 mb-0">{{ datos.error }}</div>
            </template>
          </div>
          <div class="modal-footer">
            <button @click="datos.show = false" class="btn btn-secondary" :disabled="datos.saving">Cancelar</button>
            <button @click="guardarDatos" class="tc-btn tc-btn-ok" :disabled="datos.saving || datos.loading">
              <span v-if="datos.saving" class="spinner-border spinner-border-sm me-1"></span>Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Diseño: uno para todos los carnets (Talento y Vendedores) -->
    <div v-if="diseno.show" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.55);z-index:10001">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa fa-image me-2"></i>Diseño de la credencial</h5>
            <button @click="diseno.show = false" type="button" class="btn-close" :disabled="diseno.saving"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">El diseño es el mismo para todas las credenciales (Talento y Vendedores): cambiarlo aquí lo cambia para todos.</p>
            <div v-for="parte in partesDiseno" :key="parte.type" class="mb-3">
              <label class="form-label">{{ parte.label }}</label>
              <input type="file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png" @change="(e) => (diseno.archivos[parte.type] = e.target.files[0] || null)">
            </div>
            <div v-if="diseno.error" class="alert alert-danger small mb-0">{{ diseno.error }}</div>
          </div>
          <div class="modal-footer">
            <button @click="diseno.show = false" class="btn btn-secondary" :disabled="diseno.saving">Cancelar</button>
            <button @click="guardarDiseno" class="tc-btn tc-btn-ok" :disabled="diseno.saving || !hayArchivosDiseno">
              <span v-if="diseno.saving" class="spinner-border spinner-border-sm me-1"></span>Guardar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { getImageFront, getImageBack, getImageLogo, updateCredential } from "../vendors/helper/request.js";

export default {
  name: "TalentoCarnet",
  props: {
    colaborador: { type: Object, required: true },
    // La persona misma o un administrador (resuelto en el servidor).
    puedeEditar: { type: Boolean, default: false },
    // El diseño es uno para todos; solo quien entra a Configuración › Ventas.
    puedeDiseno: { type: Boolean, default: false },
  },
  data() {
    return {
      imgFront: null, imgBack: null, imgLogo: null,
      datos: { show: false, loading: false, saving: false, error: '', form: {}, foto: null, preview: null },
      diseno: { show: false, saving: false, error: '', archivos: { frontal: null, reverso: null, logo: null } },
      partesDiseno: [
        { type: 'frontal', label: 'Frente' },
        { type: 'reverso', label: 'Reverso' },
        { type: 'logo', label: 'Logo' },
      ],
    };
  },
  computed: {
    user() {
      return this.colaborador.user ?? {};
    },
    nombreCompleto() {
      return [this.user.name, this.user.father_last_name, this.user.mother_last_name].filter(Boolean).join(" ");
    },
    titulo() {
      return this.colaborador.puesto_nombre ?? "Colaborador";
    },
    logoSrc() {
      return this.imgLogo?.name ? `/credencial/${this.imgLogo.name}` : "/images/logo_meganet.jpg";
    },
    fotoSrc() {
      if (!this.user.photography) return "/images/perfil.png";
      return this.user.url_photography || `/perfiles/${this.user.photography}`;
    },
    hayArchivosDiseno() {
      return Object.values(this.diseno.archivos).some(Boolean);
    },
  },
  methods: {
    async abrirDatos() {
      this.datos = { show: true, loading: true, saving: false, error: '', form: {}, foto: null, preview: null };
      try {
        const { data } = await axios.get(`/talento/api/colaboradores/${this.colaborador.id}/carnet`);
        this.datos.form = {
          name: data.name ?? '', father_last_name: data.father_last_name ?? '', mother_last_name: data.mother_last_name ?? '',
          phone: data.phone ?? '', email: data.email ?? '', rfc: data.rfc ?? '',
        };
      } catch (e) {
        this.datos.error = e?.response?.data?.message ?? 'No se pudieron cargar tus datos.';
      } finally {
        this.datos.loading = false;
      }
    },
    elegirFoto(e) {
      const archivo = e.target.files[0] || null;
      this.datos.foto = archivo;
      this.datos.preview = archivo ? URL.createObjectURL(archivo) : null;
    },
    async guardarDatos() {
      if (!this.datos.form.name) { this.datos.error = 'El nombre es obligatorio.'; return; }
      this.datos.saving = true;
      this.datos.error = '';
      try {
        const fd = new FormData();
        Object.entries(this.datos.form).forEach(([k, v]) => fd.append(k, v ?? ''));
        if (this.datos.foto) fd.append('foto', this.datos.foto);
        const { data } = await axios.post(`/talento/api/colaboradores/${this.colaborador.id}/carnet`, fd);
        // El carnet de arriba se actualiza con lo guardado.
        Object.assign(this.colaborador.user, data);
        this.datos.show = false;
      } catch (e) {
        const errores = e?.response?.data?.errors;
        this.datos.error = errores ? Object.values(errores).flat().join(' ') : (e?.response?.data?.error ?? e?.response?.data?.message ?? 'No se pudo guardar.');
      } finally {
        this.datos.saving = false;
      }
    },
    abrirDiseno() {
      this.diseno = { show: true, saving: false, error: '', archivos: { frontal: null, reverso: null, logo: null } };
    },
    async guardarDiseno() {
      this.diseno.saving = true;
      this.diseno.error = '';
      try {
        for (const [type, archivo] of Object.entries(this.diseno.archivos)) {
          if (!archivo) continue;
          const fd = new FormData();
          fd.append('image', archivo);
          fd.append('type', type);
          await updateCredential(fd);
        }
        [this.imgFront, this.imgBack, this.imgLogo] = await Promise.all([getImageFront(), getImageBack(), getImageLogo()]);
        this.diseno.show = false;
      } catch (e) {
        this.diseno.error = e?.response?.data?.message ?? 'No se pudo guardar el diseño.';
      } finally {
        this.diseno.saving = false;
      }
    },
  },
  async mounted() {
    [this.imgFront, this.imgBack, this.imgLogo] = await Promise.all([
      getImageFront(), getImageBack(), getImageLogo(),
    ]);
  },
};
</script>

<style scoped>
.tc-carnet-card {
  width: 100%;
  max-width: 300px;
  aspect-ratio: 380 / 600;
  overflow: hidden;
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
}
.tc-carnet-logo {
  object-fit: contain;
  height: 18%;
  width: 100%;
}
.tc-carnet-perfil {
  border-radius: 50%;
  width: clamp(70px, 30%, 120px);
  height: clamp(70px, 30%, 120px);
  border: 5px solid #004790;
  margin-bottom: 10px;
  object-fit: cover;
}
.tc-carnet-name {
  /* Baja el nombre a la franja azul del frente: arriba de ella el fondo es blanco
     y el texto blanco no se leía. */
  margin-top: 46px;
  font-size: 1.05rem;
  font-weight: bold;
  color: #fff;
  text-align: center;
  overflow-wrap: break-word;
  padding: 0 8px;
}
.tc-carnet-title {
  font-size: 0.75rem;
  color: #fff;
  text-align: center;
  text-transform: uppercase;
}
.tc-carnet-text {
  font-size: 0.8rem;
  color: #fff;
  text-align: center;
  overflow-wrap: break-word;
  padding: 0 10px;
  margin-bottom: 4px;
}
.tc-carnet-back {
  margin-top: 20px;
}
.tc-carnet-back h5 {
  color: #333;
  font-size: 0.95rem;
}
.tc-carnet-text-black {
  font-size: 0.8rem;
  color: #504f4f;
  text-align: justify;
}
.tc-carnet-signature {
  margin: 70px auto 0;
  border-bottom: 1px solid #000;
  width: 70%;
}
.tc-carnet-firm {
  font-size: 1.1rem;
  font-weight: bold;
  color: #333;
}
</style>
