<template>
    <!--
      ENGRANE DE LA CABECERA — señal, no tablero.

      #648. Este botón abría su propio tablero de compuertas, con sus acciones, su pestaña de
      permisos y su bitácora. El engrane de la Torre abría OTRO panel con la configuración. Y la
      pestaña «Configuración» muestra hoy las dos cosas. Tres pantallas con la misma información
      envejecen por separado y acaban contradiciéndose — y cuando alguien descubre que una miente,
      deja de creerle también a las que sí eran ciertas.

      Lo que se conserva aquí es lo único que NO se puede ver desde otro lado: el aviso rojo con
      cuántas compuertas están bloqueando, visible desde cualquier pantalla del sistema. El clic
      lleva a la pestaña «Configuración», que es donde vive el detalle y los controles.
    -->
    <div class="d-inline-block">
        <a class="btn header-item position-relative" :href="destino" :title="titulo" data-spa-skip>
            <i class="fas fa-cog" :class="{ 'text-danger': hayBloqueo, 'fa-spin': cargando }"></i>
            <span v-if="hayBloqueo"
                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger hdr-badge hdr-badge-corner">
                {{ rojos }}
            </span>
        </a>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'TorreCompuertas',
    data() {
        return { cargando: false, linea: '', compuertas: [] };
    },
    computed: {
        destino() { return '/releases?tab=configuracion'; },
        rojos() { return this.compuertas.filter(c => c.semaforo === 'rojo').length; },
        hayBloqueo() { return this.rojos > 0; },
        titulo() {
            const base = this.linea || 'Configuración de la Torre';
            return this.hayBloqueo
                ? `${base} — ${this.rojos} compuerta(s) bloqueando. Clic para abrir la configuración.`
                : `${base} — clic para abrir la configuración.`;
        },
    },
    mounted() {
        // Una medición al cargar, para que el engrane avise sin que haya que abrir nada.
        // Si el usuario no tiene acceso al tablero, el error se traga en silencio: el engrane
        // simplemente no avisa, en vez de reventar la cabecera de todas las pantallas.
        this.medir();
    },
    methods: {
        async medir() {
            this.cargando = true;
            try {
                const { data } = await axios.get('/api/roadmap/torre/compuertas');
                this.linea = data.linea;
                this.compuertas = data.compuertas || [];
            } catch (e) {
                this.compuertas = [];
            } finally {
                this.cargando = false;
            }
        },
    },
};
</script>

<style scoped>
.header-item { display: inline-flex; align-items: center; justify-content: center; }
.header-item .fa-cog { font-size: 20px; line-height: 1; vertical-align: middle; }
.tc-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1090;
    display: flex; align-items: flex-start; justify-content: center; padding: 3vh 2vw; }
.tc-panel { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529);
    border-radius: .5rem; width: 100%; max-width: 1500px; max-height: 94vh; overflow: auto;
    box-shadow: 0 1rem 3rem rgba(0,0,0,.4); position: relative; }
.tc-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem;
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--bs-border-color, #dee2e6);
    position: sticky; top: 0; background: inherit; z-index: 2; }
.tc-linea { font-size: 1.35rem; font-weight: 700; letter-spacing: .01em; }
.tc-ok { color: #198754; } .tc-stop { color: #dc3545; }
.tc-sub { font-size: .78rem; opacity: .7; }
.tc-tabla { padding: .5rem 1.25rem 1.25rem; }
.tc-fila { display: grid; grid-template-columns: 34px 1.5fr 1.7fr .8fr 2.2fr 1.8fr;
    gap: .6rem; padding: .6rem .25rem; border-bottom: 1px solid var(--bs-border-color, #eee);
    align-items: start; font-size: .84rem; }
.tc-cabecera { font-weight: 600; text-transform: uppercase; font-size: .68rem; opacity: .6; }
.tc-rojo { background: rgba(220,53,69,.07); }
.tc-ambar { background: rgba(255,193,7,.07); }
.tc-luz { display: inline-block; width: 13px; height: 13px; border-radius: 50%; margin-top: .18rem; }
.luz-verde { background: #198754; } .luz-rojo { background: #dc3545; } .luz-ambar { background: #ffc107; }
.tc-nombre { font-weight: 600; }
.tc-valor { font-variant-numeric: tabular-nums; }
.tc-origen { font-size: .68rem; padding: .1rem .4rem; border-radius: .2rem;
    background: rgba(120,120,120,.16); white-space: nowrap; }
.tc-porque { opacity: .85; }
.tc-motivo { font-size: .72rem; margin: .25rem 0; padding: .3rem .45rem; border-radius: .25rem;
    line-height: 1.35; }
.tc-etiqueta { display: inline-block; font-weight: 700; font-size: .62rem; letter-spacing: .04em;
    margin-right: .3rem; }
.tc-m-sin_privilegio { background: rgba(13,110,253,.13); }
.tc-m-sin_permiso, .tc-m-permiso { background: rgba(220,53,69,.13); }
.tc-m-no_implementado { background: rgba(120,120,120,.15); font-style: italic; }
.tc-permiso { font-family: monospace; background: rgba(0,0,0,.12); padding: 0 .25rem; border-radius: .2rem; }
.tc-rec { font-size: .66rem; padding: .08rem .35rem; border-radius: .2rem; text-transform: uppercase; }
.rec-conceder { background: rgba(25,135,84,.18); }
.rec-revisar { background: rgba(255,193,7,.22); }
.rec-negar { background: rgba(220,53,69,.18); }
.tc-cmd code { display: block; font-size: .72rem; background: rgba(120,120,120,.14);
    padding: .3rem .4rem; border-radius: .25rem; cursor: pointer; word-break: break-all; }
.tc-quien { font-size: .68rem; opacity: .7; margin-top: .2rem; }
.tc-bitacora { padding: .5rem 1.25rem; max-height: 240px; overflow: auto;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6); }
.tc-confirm-back { position: absolute; inset: 0; background: rgba(0,0,0,.45);
    display: flex; align-items: center; justify-content: center; z-index: 3; }
.tc-confirm { background: var(--bs-body-bg, #fff); border-radius: .5rem; padding: 1.25rem;
    max-width: 460px; box-shadow: 0 .5rem 2rem rgba(0,0,0,.4); }
.tc-msg { position: sticky; bottom: 0; padding: .6rem 1.25rem; font-size: .85rem; }
.tc-msg.ok { background: rgba(25,135,84,.15); } .tc-msg.err { background: rgba(220,53,69,.15); }
@media (max-width: 1200px) {
    .tc-fila { grid-template-columns: 26px 1fr 1fr; }
    .tc-fila > div:nth-child(4), .tc-cabecera > div:nth-child(4) { grid-column: 2 / 4; }
    .tc-porque, .tc-accion { grid-column: 1 / 4; }
}
</style>
