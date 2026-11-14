<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-canales">
            <mkt-encabezado-pantalla
                titulo="Canales de publicación"
                subtitulo="Conecta tus cuentas para publicar automáticamente"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #acciones>
                    <mkt-boton
                        v-if="estado === 'listo' && channels.length"
                        tipo="guardar"
                        etiqueta="Validar todos"
                        :cargando="validatingAll"
                        :deshabilitado="hayValidando"
                        @click="validateAll"
                    />
                </template>

                <div v-if="flashMessage" class="mkt-canales__flash" :class="`mkt-canales__flash--${flashType}`" role="alert">
                    <q-icon :name="flashType === 'success' ? 'check_circle' : 'error_outline'" size="20px" aria-hidden="true" />
                    <span>{{ flashMessage }}</span>
                    <q-btn flat round dense icon="close" aria-label="Cerrar aviso" @click="flashMessage = ''" />
                </div>

                <mkt-estado-vacio
                    v-if="estado === 'sin_permiso'"
                    icono="lock"
                    titulo="No tienes acceso a esta sección"
                    texto="No tienes acceso a esta sección. Pídelo a Irving."
                />
                <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudieron cargar los canales" :texto="mensajeError" />
                <mkt-estado-vacio
                    v-else-if="estado === 'listo' && !channels.length"
                    icono="hub"
                    titulo="No hay canales registrados"
                    texto="Los canales de publicación se dan de alta al instalar el publicador."
                />
            </mkt-encabezado-pantalla>

            <div v-if="estado === 'cargando'" class="mkt-canales__rejilla" aria-busy="true">
                <mkt-tarjeta-panel v-for="n in 2" :key="n" cargando />
            </div>

            <div v-else-if="estado === 'listo' && channels.length" class="mkt-canales__rejilla">
                <mkt-tarjeta-panel
                    v-for="channel in channels"
                    :key="channel.id"
                    :titulo="channel.name"
                    :subtitulo="`${etiquetaPlataforma(channel.platform)} · ${channel.channel_type}`"
                    :tono="tonoPlataforma(channel.platform)"
                    :icono-fa="iconoFaPlataforma(channel.platform)"
                >
                    <template #acciones>
                        <mkt-insignia-estado :texto="channel.credentials_ready ? 'Listo' : 'Pendiente'" :tono="channel.credentials_ready ? 'exito' : 'aviso'" />
                    </template>
                    <div class="mkt-canales__cuerpo">
                        <p class="mkt-canales__estado">
                            <q-icon name="info" size="16px" aria-hidden="true" />
                            <span>{{ channel.credentials_status_message || 'Sin validar' }}</span>
                        </p>
                        <p v-if="channel.credentials_validated_at" class="mkt-canales__suave">
                            Validado: {{ formatoFecha(channel.credentials_validated_at, { conHora: true }) }}
                        </p>
                        <div class="mkt-canales__datos">
                            <span class="mkt-canales__suave">Formatos:</span>
                            <mkt-insignia-estado v-for="r in channel.supported_aspect_ratios || []" :key="r" :texto="r" tono="neutro" />
                        </div>
                        <p v-if="channel.max_duration_seconds" class="mkt-canales__suave">Duración máxima: {{ channel.max_duration_seconds }} s</p>
                    </div>
                    <template #pie>
                        <div class="mkt-canales__pie">
                            <mkt-boton
                                v-if="(channel.platform === 'facebook' || channel.platform === 'instagram') && puedeConectarMeta"
                                tipo="secundario"
                                chico
                                etiqueta="Conectar Meta"
                                href="/marketing/meta/oauth/start"
                            />
                            <a
                                v-else-if="channel.platform === 'whatsapp'"
                                class="btn btn-outline-secondary btn-sm waves-effect waves-light"
                                :href="evolutionUrl"
                                target="_blank"
                                rel="noopener"
                            >Abrir Evolution</a>
                            <mkt-boton
                                v-else-if="channel.platform === 'email'"
                                tipo="secundario"
                                chico
                                etiqueta="Cómo configurar el correo"
                                @click="showSmtpInfo = true"
                            />
                            <span class="mkt-canales__espacio"></span>
                            <mkt-boton
                                tipo="secundario"
                                chico
                                etiqueta="Validar"
                                :cargando="!!validating[channel.id]"
                                :deshabilitado="validatingAll"
                                @click="validateChannel(channel)"
                            />
                        </div>
                    </template>
                </mkt-tarjeta-panel>
            </div>

            <mkt-dialogo v-model="showSmtpInfo" titulo="Correo (SMTP)" :ancho="520">
                <p class="mkt-canales__texto">
                    El envío masivo por correo usa el servidor de correo de salida (SMTP) del sistema. Quien administra el
                    servidor debe dejar configurados: el servidor SMTP, el puerto (normalmente 587), el usuario, la contraseña,
                    el cifrado (TLS), el correo remitente y el nombre que verán los clientes («Meganet»).
                </p>
                <p class="mkt-canales__suave">Los valores reales nunca se escriben aquí. Después de cambiarlos se reinicia el servidor y se valida el canal.</p>
                <template #acciones>
                    <mkt-boton tipo="guardar" etiqueta="Entendido" @click="showSmtpInfo = false" />
                </template>
            </mkt-dialogo>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import { currentSearch } from "../../../../hook/comunValues";
import { mensajeDeError, formatoFecha, pareceIngles } from "../kit";

const ICONOS = { facebook: "fab fa-facebook", instagram: "fab fa-instagram", whatsapp: "fab fa-whatsapp", email: "email" };
const PLATAFORMAS = { facebook: "Facebook", instagram: "Instagram", whatsapp: "WhatsApp", email: "Correo" };

/** Canales de publicación (/marketing/publishing/setup) — Fase 1, rediseño con el kit, mismos endpoints. */
export default {
    name: "MarketingChannelsSetupView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            channels: [],
            validatingAll: false,
            validating: {},
            showSmtpInfo: false,
            flashMessage: "",
            flashType: "success",
        };
    },
    computed: {
        evolutionUrl() {
            return window.location.origin.replace("80", "8080");
        },
        /** Conectar Meta es configuración compartida (ruta con marketing.principal). */
        puedeConectarMeta() {
            return this.contexto?.capacidades?.editar_config_compartida !== false;
        },
        hayValidando() {
            return Object.values(this.validating).some(Boolean);
        },
    },
    mounted() {
        this.checkFlashMessages();
        this.loadChannels();
    },
    methods: {
        formatoFecha,
        async loadChannels() {
            this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/publishing/channels");
                this.channels = data?.channels ?? [];
                this.estado = "listo";
            } catch (e) {
                if (e?.response?.status === 403) {
                    this.estado = "sin_permiso";
                } else {
                    this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
                    this.estado = "error";
                }
            }
        },
        async validateChannel(channel, { silencioso = false } = {}) {
            if (this.validating[channel.id]) return;
            this.validating = { ...this.validating, [channel.id]: true };
            try {
                const { data } = await axios.post(`/api/marketing/publishing/channels/${channel.id}/validate`);
                const ch = this.channels.find((c) => c.id === channel.id);
                if (ch) {
                    ch.credentials_ready = data?.valid;
                    ch.credentials_status_message = pareceIngles(data?.message) ? null : data?.message;
                    ch.credentials_validated_at = new Date().toISOString();
                }
                if (!silencioso) message((!pareceIngles(data?.message) && data?.message) || (data?.valid ? "Canal válido." : "El canal no pasó la validación."), data?.valid ? "success" : "warning");
            } catch (e) {
                if (!silencioso) message(mensajeDeError(e, "No se pudo validar el canal."), "error");
            } finally {
                this.validating = { ...this.validating, [channel.id]: false };
            }
        },
        async validateAll() {
            if (this.validatingAll) return;
            this.validatingAll = true;
            for (const ch of this.channels) {
                await this.validateChannel(ch, { silencioso: true });
            }
            this.validatingAll = false;
            const listos = this.channels.filter((c) => c.credentials_ready).length;
            message(`${listos} de ${this.channels.length} canales listos.`, listos === this.channels.length ? "success" : "warning");
        },
        platformIcon(p) {
            return ICONOS[p] || "share";
        },
        /** Cabecera de color de cada canal (tonos de los bloques de la ficha de Cliente). */
        tonoPlataforma(p) {
            return { facebook: "azul", instagram: "rosa", whatsapp: "verde", email: "crema" }[p] || "gris";
        },
        iconoFaPlataforma(p) {
            return { facebook: "fa-thumbs-up", instagram: "fa-camera", whatsapp: "fa-comment", email: "fa-envelope" }[p] || "fa-share-alt";
        },
        etiquetaPlataforma(p) {
            return PLATAFORMAS[p] || p;
        },
        checkFlashMessages() {
            // Regreso del OAuth de Meta: ?success= / ?error=. La navegación SPA monta antes del pushState.
            const params = new URLSearchParams(currentSearch());
            if (params.get("success")) {
                this.flashMessage = params.get("success");
                this.flashType = "success";
            } else if (params.get("error")) {
                this.flashMessage = params.get("error");
                this.flashType = "error";
            }
        },
    },
};
</script>

<style scoped>
.mkt-canales {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-2);
    min-width: 0;
}
.mkt-canales > :first-child { margin-bottom: 0; }
.mkt-canales__flash {
    --tono: var(--mkt-exito);
    display: flex;
    align-items: center;
    gap: var(--mkt-esp-1);
    margin: 0 16px 16px;
    padding: 8px 8px 8px var(--mkt-esp-2);
    border: 1px solid color-mix(in srgb, var(--tono) 40%, var(--mkt-borde));
    border-radius: var(--mkt-radio);
    background: color-mix(in srgb, var(--tono) 10%, var(--mkt-fondo-tarjeta));
    color: var(--mkt-texto);
    font-size: 14px;
}
.mkt-canales__flash > span { flex: 1 1 auto; overflow-wrap: anywhere; }
.mkt-canales__flash > .q-icon { color: var(--tono); }
.mkt-canales__flash--error { --tono: var(--mkt-peligro); }
.mkt-canales__rejilla { display: grid; gap: 14px; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
.mkt-canales__cuerpo { display: flex; flex-direction: column; gap: 6px; }
.mkt-canales__estado { display: flex; align-items: flex-start; gap: 6px; margin: 0; font-size: 14px; color: var(--mkt-texto); }
.mkt-canales__estado > .q-icon { color: var(--mkt-texto-suave); margin-top: 2px; }
.mkt-canales__datos { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.mkt-canales__suave { margin: 0; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-canales__pie { display: flex; align-items: center; flex-wrap: wrap; gap: var(--mkt-esp-1); }
.mkt-canales__espacio { flex: 1 1 auto; }
.mkt-canales__texto { margin: 0 0 8px; font-size: 14px; line-height: 1.5; color: var(--mkt-texto); }
@media (max-width: 1023px) {
    .mkt-canales__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-canales__flash { margin: 0 12px 12px; }
}
</style>
