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
            <img class="tc-carnet-perfil"
                 :src="user.photography ? `/perfiles/${user.photography}` : '/images/perfil.png'"
                 alt="foto" />
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
    <div class="text-center mt-3">
      <a class="tc-btn tc-btn-ok" :href="`/talento/mi-ficha/${colaborador.id}/pdf`" target="_blank">
        <i class="fa fa-file-download me-1"></i> Descargar credencial
      </a>
    </div>
  </div>
</template>

<script>
import { getImageFront, getImageBack, getImageLogo } from "../vendors/helper/request.js";

export default {
  name: "TalentoCarnet",
  props: {
    colaborador: { type: Object, required: true },
  },
  data() {
    return { imgFront: null, imgBack: null, imgLogo: null };
  },
  computed: {
    user() {
      return this.colaborador.user ?? {};
    },
    nombreCompleto() {
      return [this.user.name, this.user.father_last_name, this.user.mother_last_name].filter(Boolean).join(" ");
    },
    titulo() {
      return this.colaborador.puesto?.nombre ?? this.colaborador.job_title ?? "Colaborador";
    },
    logoSrc() {
      return this.imgLogo?.name ? `/credencial/${this.imgLogo.name}` : "/images/logo_meganet.jpg";
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
