<template>
    <mkt-tarjeta-panel :titulo="titulo" :subtitulo="subtitulo" tono="rosa" icono-fa="fa-film">
        <template #acciones>
            <mkt-insignia-estado :texto="estadoPieza.label" :tono="estadoPieza.tono" />
            <template v-if="pieza.estado === 'lista'">
                <mkt-boton tipo="secundario" chico icono-fa="fa-edit" etiqueta="Editar" @click="editor = true" />
                <mkt-boton v-if="puedeGenerar" tipo="secundario" chico icono-fa="fa-redo" etiqueta="Regenerar" @click="regenerar = true" />
                <mkt-boton tipo="secundario" chico icono-fa="fa-film" etiqueta="Producir en Video MVM" :deshabilitado="produccionEnCurso" @click="producir = true">
                    <q-tooltip v-if="produccionEnCurso">Ese guion ya se está produciendo.</q-tooltip>
                </mkt-boton>
            </template>
            <mkt-boton v-else-if="pieza.estado === 'error' && puedeGenerar" tipo="secundario" chico icono-fa="fa-redo" etiqueta="Regenerar" @click="regenerar = true" />
        </template>

        <!-- Generando / pendiente -->
        <div v-if="pieza.estado === 'generando' || pieza.estado === 'pendiente'" class="mkt-guion__esqueleto" aria-busy="true">
            <q-skeleton type="text" width="50%" />
            <q-skeleton v-for="n in 4" :key="n" type="rect" height="36px" />
            <span class="mkt-guion__suave">{{ pieza.estado === "pendiente" ? "En espera de la IA…" : "La IA está escribiendo este guion…" }}</span>
        </div>

        <!-- Error -->
        <mkt-estado-vacio v-else-if="pieza.estado === 'error'" icono="error_outline" titulo="No se pudo generar este guion" :texto="pieza.error || 'La IA no respondió como se esperaba. Usa «Regenerar».'" />

        <!-- Lista -->
        <template v-else>
            <p v-if="contenido.gancho" class="mkt-guion__gancho"><span>Gancho:</span> {{ contenido.gancho }}</p>

            <div class="mkt-guion__tabla-caja">
                <table class="mkt-guion__tabla">
                    <caption class="mkt-guion__oculto">Escenas de {{ titulo }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="mkt-guion__c-num">#</th>
                            <th scope="col" class="mkt-guion__c-dur">Duración</th>
                            <th scope="col">Toma / visual</th>
                            <th scope="col">Texto en pantalla</th>
                            <th scope="col">Voz en off</th>
                            <th scope="col">Música / sonido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(e, i) in escenas" :key="i">
                            <td data-etiqueta="Escena" class="mkt-guion__c-num">{{ e.numero || i + 1 }}</td>
                            <td data-etiqueta="Duración" class="mkt-guion__c-dur">{{ formatoDuracion(e.duracion_s) }}</td>
                            <td data-etiqueta="Toma / visual">
                                <div class="mkt-guion__celda">
                                    <div>{{ e.toma || "—" }}</div>
                                    <div class="mkt-guion__visual">
                                        <q-icon :name="iconoVisual(e.visual?.tipo)" size="14px" aria-hidden="true" />
                                        <span>{{ etiquetaVisual(e.visual?.tipo) }}<template v-if="e.visual?.categoria_broll"> · {{ nombreNicho(e.visual.categoria_broll) }}</template></span>
                                    </div>
                                </div>
                            </td>
                            <td data-etiqueta="Texto en pantalla" class="mkt-guion__pantalla">{{ e.texto_pantalla || "—" }}</td>
                            <td data-etiqueta="Voz en off">{{ e.voz_en_off || "—" }}</td>
                            <td data-etiqueta="Música / sonido" class="mkt-guion__suave">{{ e.musica_sonido || "—" }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2">Total</th>
                            <td colspan="4" class="mkt-guion__total">{{ formatoDuracion(total) }} · {{ escenas.length }} escenas</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <dl class="mkt-guion__meta">
                <div><dt>Música</dt><dd>{{ etiquetaMusica(contenido.musica?.ambiente) }}</dd></div>
                <div><dt>Voz</dt><dd>{{ etiquetaVoz(contenido.voz?.id) }}</dd></div>
                <div><dt>Cierre</dt><dd>{{ contenido.cta_final?.texto || "—" }}</dd></div>
                <div v-if="pieza.editada"><dt>Editado</dt><dd>{{ formatoFecha(pieza.editada_at, { conHora: true }) }}</dd></div>
            </dl>
        </template>

        <!-- Videos producidos de este guion -->
        <template v-if="videos.length" #pie>
            <div class="mkt-guion__videos">
                <h3 class="mkt-guion__h3">Videos de este guion</h3>
                <article v-for="v in videos" :key="v.id" class="mkt-guion__video" :class="`mkt-guion__video--${v.formato === '16:9' ? 'horizontal' : 'vertical'}`">
                    <div class="mkt-guion__video-cabeza">
                        <mkt-insignia-estado :texto="v.estado_etiqueta || estadoVideo(v).label" :tono="estadoVideo(v).tono" />
                        <span class="mkt-guion__suave">{{ v.formato }} · {{ v.duracion_s ? formatoDuracion(v.duracion_s) : "—" }}</span>
                        <mkt-insignia-estado v-if="v.sin_voz" texto="Sin voz" tono="aviso" />
                        <span class="mkt-guion__suave mkt-guion__fecha">{{ formatoFecha(v.creado_at || v.en_cola_desde, { conHora: true }) }}</span>
                    </div>

                    <q-linear-progress
                        v-if="v.estado === 'en_cola' || v.estado === 'produciendo'"
                        :indeterminate="v.estado === 'produciendo'"
                        :value="0"
                        color="primary"
                        track-color="grey-3"
                        rounded
                        size="6px"
                        :aria-label="estadoVideo(v).label"
                    />
                    <p v-if="v.estado === 'en_cola' && !v.demorado" class="mkt-guion__suave">Esperando turno en el procesador de videos…</p>
                    <p v-if="v.estado === 'produciendo' && !v.demorado" class="mkt-guion__suave">Armando escenas, voz y música…</p>

                    <q-banner v-if="v.demorado" dense rounded class="mkt-guion__demorado" role="status">
                        <template #avatar><q-icon name="hourglass_bottom" size="20px" /></template>
                        {{ v.estado === "en_cola" ? avisoDemorado : "La producción lleva mucho sin avanzar. Avísale a Irving." }}
                    </q-banner>

                    <q-banner v-if="v.estado === 'error'" dense rounded class="mkt-guion__error-video" role="alert">
                        <template #avatar><q-icon name="error" size="20px" /></template>
                        {{ v.error || "No se pudo producir el video." }} Puedes volver a producirlo.
                    </q-banner>

                    <p v-if="v.sin_voz && v.estado === 'listo'" class="mkt-guion__suave">El video salió sin voz en off: revisa que la IA de voz esté asignada.</p>

                    <video
                        v-if="v.estado === 'listo' && v.url_ver"
                        class="mkt-guion__reproductor"
                        controls
                        preload="none"
                        playsinline
                        :poster="v.url_miniatura || undefined"
                        :src="v.url_ver"
                        :aria-label="`Video ${v.formato} de ${titulo}`"
                    ></video>
                </article>
            </div>
        </template>

        <editor-guion-escenas v-model="editor" :campana-id="campanaId" :pieza="pieza" :nichos="nichos" @guardada="alGuardar" />
        <dialogo-regenerar-pieza v-model="regenerar" :campana-id="campanaId" :pieza="pieza" @regenerada="alRegenerar" />
        <dialogo-producir-video v-model="producir" :campana-id="campanaId" :pieza="pieza" @producido="alProducir" />
    </mkt-tarjeta-panel>
</template>

<script>
import { message } from "../../../../helpers/toastMsg";
import { MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, formatoDuracion, formatoFecha } from "../kit";
import {
    ETIQUETAS_PIEZA,
    ESTADOS_PIEZA,
    ESTADOS_VIDEO,
    AVISO_DEMORADO,
    etiquetaVisual,
    etiquetaVoz,
    etiquetaMusica,
    duracionTotal,
} from "./catalogos360";
import EditorGuionEscenas from "./EditorGuionEscenas.vue";
import DialogoRegenerarPieza from "./DialogoRegenerarPieza.vue";
import DialogoProducirVideo from "./DialogoProducirVideo.vue";

const ICONOS_VISUAL = { logo: "verified", broll: "videocam", texto: "title", cta: "ads_click" };

/**
 * Tarjeta de un guion de Reel o Video (§2.5.5): tabla de escenas, total, Editar / Regenerar / Producir en Video MVM y el
 * estado de sus videos (en cola, produciendo, listo con reproductor, error, sin voz, demorado).
 */
export default {
    name: "TarjetaGuion",
    components: { MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, EditorGuionEscenas, DialogoRegenerarPieza, DialogoProducirVideo },
    props: {
        campanaId: { type: Number, required: true },
        pieza: { type: Object, required: true },
        videos: { type: Array, default: () => [] },
        nichos: { type: Array, default: () => [] },
        puedeGenerar: { type: Boolean, default: true },
    },
    emits: ["actualizada", "en-proceso", "video-creado"],
    data() {
        return { editor: false, regenerar: false, producir: false, avisoDemorado: AVISO_DEMORADO };
    },
    computed: {
        contenido() {
            return this.pieza?.contenido && typeof this.pieza.contenido === "object" ? this.pieza.contenido : {};
        },
        escenas() {
            return Array.isArray(this.contenido.escenas) ? this.contenido.escenas : [];
        },
        total() {
            return this.contenido.duracion_total_s ?? duracionTotal(this.escenas);
        },
        titulo() {
            const e = this.pieza.pieza_etiqueta || ETIQUETAS_PIEZA[this.pieza.pieza] || "Guion";
            return this.pieza.variante ? `${e} ${this.pieza.variante}` : e;
        },
        subtitulo() {
            if (this.pieza.estado !== "lista") return "";
            const partes = [this.contenido.titulo, this.contenido.formato, formatoDuracion(this.total)];
            return partes.filter(Boolean).join(" · ");
        },
        estadoPieza() {
            return ESTADOS_PIEZA[this.pieza.estado] || ESTADOS_PIEZA.pendiente;
        },
        produccionEnCurso() {
            return this.videos.some((v) => v.estado === "en_cola" || v.estado === "produciendo");
        },
    },
    methods: {
        formatoDuracion,
        formatoFecha,
        etiquetaVisual,
        etiquetaVoz,
        etiquetaMusica,
        iconoVisual: (t) => ICONOS_VISUAL[t] || "crop_square",
        estadoVideo: (v) => ESTADOS_VIDEO[v?.estado] || ESTADOS_VIDEO.en_cola,
        nombreNicho(slug) {
            return this.nichos.find((n) => n.value === slug)?.label || slug;
        },
        alGuardar(pieza) {
            if (pieza) this.$emit("actualizada", pieza);
            message("Guion guardado.");
        },
        alRegenerar(pieza) {
            this.$emit("actualizada", { ...pieza, estado: pieza?.estado || "generando" });
            this.$emit("en-proceso");
            message("Regenerando el guion.");
        },
        alProducir(video) {
            if (video) this.$emit("video-creado", video);
            message("Video en cola de producción.");
        },
    },
};
</script>

<style scoped>
.mkt-guion__esqueleto { display: flex; flex-direction: column; gap: 6px; }
.mkt-guion__suave { font-size: 13px; color: var(--mkt-texto-suave); margin: 0; }
.mkt-guion__gancho { margin: 0 0 var(--mkt-esp-2); font-size: 15px; font-weight: 600; overflow-wrap: anywhere; }
.mkt-guion__gancho span { font-size: 0.72rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.4px; color: #94a3b8; margin-right: 4px; }
.mkt-guion__tabla-caja { overflow-x: auto; }
.mkt-guion__tabla { width: 100%; border-collapse: collapse; font-size: 14px; }
.mkt-guion__tabla th {
    font-size: 12px; font-weight: 500; text-align: left; padding: 8px;
    border-bottom: 1px solid var(--mkt-borde);
}
.mkt-guion__tabla td { padding: 10px 8px; border-bottom: 1px solid var(--mkt-borde); vertical-align: top; overflow-wrap: anywhere; }
.mkt-guion__tabla tfoot th, .mkt-guion__tabla tfoot td { background: transparent; border-bottom: 0; font-size: 14px; color: var(--mkt-texto); text-transform: none; letter-spacing: 0; }
.mkt-guion__c-num { width: 40px; font-weight: 600; color: var(--mkt-primario); }
.mkt-guion__c-dur { width: 84px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.mkt-guion__pantalla { font-weight: 600; }
.mkt-guion__visual { display: flex; align-items: flex-start; gap: 4px; margin-top: 4px; font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-guion__total { font-weight: 600; font-variant-numeric: tabular-nums; }
.mkt-guion__oculto { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.mkt-guion__meta { display: flex; flex-wrap: wrap; gap: var(--mkt-esp-1) var(--mkt-esp-3); margin: var(--mkt-esp-2) 0 0; font-size: 14px; }
.mkt-guion__meta div { display: flex; gap: 6px; }
.mkt-guion__meta dt { color: var(--mkt-texto-suave); font-weight: 500; }
.mkt-guion__meta dd { margin: 0; }
.mkt-guion__videos { display: flex; flex-wrap: wrap; gap: var(--mkt-esp-2); align-items: flex-start; }
.mkt-guion__h3 { flex: 1 1 100%; margin: 0; font-size: 14px; font-weight: 600; color: var(--mkt-texto); }
.mkt-guion__video {
    display: flex; flex-direction: column; gap: var(--mkt-esp-1);
    padding: 12px; border: 1px solid var(--mkt-borde); border-radius: var(--mkt-radio); background: var(--mkt-fondo-tarjeta);
    width: 100%; max-width: 320px;
}
.mkt-guion__video--horizontal { max-width: 520px; }
.mkt-guion__video-cabeza { display: flex; flex-wrap: wrap; align-items: center; gap: 6px var(--mkt-esp-1); }
.mkt-guion__fecha { margin-left: auto; }
.mkt-guion__reproductor { width: 100%; border-radius: 6px; background: #000; }
.mkt-guion__video--vertical .mkt-guion__reproductor { aspect-ratio: 9 / 16; max-height: 520px; }
.mkt-guion__video--horizontal .mkt-guion__reproductor { aspect-ratio: 16 / 9; }
.mkt-guion__demorado {
    background: color-mix(in srgb, var(--mkt-aviso) 16%, var(--mkt-fondo-tarjeta));
    color: var(--mkt-texto); font-size: 13px;
    border: 1px solid color-mix(in srgb, var(--mkt-aviso) 50%, transparent);
}
.mkt-guion__error-video {
    background: color-mix(in srgb, var(--mkt-peligro) 10%, var(--mkt-fondo-tarjeta));
    color: var(--mkt-texto); font-size: 13px;
}

/* Móvil: cada escena es una tarjeta «etiqueta: valor». */
@media (max-width: 599px) {
    .mkt-guion__tabla thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
    .mkt-guion__tabla, .mkt-guion__tabla tbody, .mkt-guion__tabla tr, .mkt-guion__tabla td { display: block; width: 100%; }
    .mkt-guion__tabla tbody tr { border: 1px solid var(--mkt-borde); border-radius: var(--mkt-radio); margin-bottom: var(--mkt-esp-1); padding: 4px 0; }
    .mkt-guion__tabla td { border-bottom: 0; padding: 4px 12px; display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 8px; }
    .mkt-guion__tabla td::before { content: attr(data-etiqueta); font-size: 12px; font-weight: 600; color: var(--mkt-texto-suave); }
    .mkt-guion__c-num, .mkt-guion__c-dur { width: auto; }
    .mkt-guion__tabla tfoot, .mkt-guion__tabla tfoot tr { display: flex; gap: 8px; }
    .mkt-guion__tabla tfoot th, .mkt-guion__tabla tfoot td { display: inline; padding: 4px 0; }
    .mkt-guion__video, .mkt-guion__video--horizontal { max-width: none; }
    .mkt-guion__fecha { margin-left: 0; }
}
</style>
