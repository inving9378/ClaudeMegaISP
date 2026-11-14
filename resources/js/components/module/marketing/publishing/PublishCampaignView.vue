<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-publicar">
            <mkt-encabezado-pantalla
                titulo="Publicar"
                subtitulo="Manda los videos de una campaña multivariante a tus canales"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #acciones>
                    <mkt-boton tipo="secundario" etiqueta="Ver fila de publicación" href="/marketing/publishing/queue" />
                </template>

                <div class="mkt-publicar__cuerpo-tarjeta">
                <mkt-estado-vacio
                    v-if="estado === 'sin_permiso'"
                    icono="lock"
                    titulo="No tienes acceso a esta sección"
                    texto="No tienes acceso a esta sección. Pídelo a Irving."
                />
                <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudieron cargar las campañas" :texto="mensajeError" />
                <div v-else-if="estado === 'cargando'" class="mkt-publicar__cargando" aria-busy="true" aria-label="Cargando">
                    <q-skeleton type="text" width="90%" />
                    <q-skeleton type="text" width="75%" />
                    <q-skeleton type="text" width="60%" />
                </div>
                <mkt-estado-vacio
                    v-else-if="!campaigns.length"
                    icono="movie_filter"
                    titulo="No hay campañas listas para publicar"
                    texto="Primero genera una campaña con IA; cuando sus videos terminen, aparece aquí." />

                <q-stepper v-else v-model="step" flat animated active-color="info" done-color="positive" inactive-color="grey-6" class="mkt-publicar__pasos" header-nav>
                    <!-- 1 -->
                    <q-step :name="1" title="Campaña" icon="campaign" :done="step > 1">
                        <div class="mkt-publicar__cuerpo">
                            <q-select
                                v-model="selectedCampaign"
                                :options="campaigns"
                                option-value="id"
                                option-label="name"
                                emit-value
                                map-options
                                outlined
                                dense
                                label="Campaña multivariante"
                                @update:model-value="loadRouting"
                            />
                            <p v-if="campanaElegida" class="mkt-publicar__suave">
                                {{ campanaElegida.variants_succeeded || 0 }} variantes listas
                            </p>
                            <p v-if="errorRouting" class="mkt-publicar__error" role="alert">{{ errorRouting }}</p>
                        </div>
                        <q-stepper-navigation class="mkt-publicar__navegar">
                            <mkt-boton tipo="principal" etiqueta="Siguiente" :cargando="loadingRouting" :deshabilitado="!selectedCampaign || loadingRouting || routing.length === 0" @click="step = 2" />
                        </q-stepper-navigation>
                    </q-step>

                    <!-- 2 -->
                    <q-step :name="2" title="Canales" icon="route" :done="step > 2" :disable="!routing.length">
                        <div class="mkt-publicar__cuerpo">
                            <div v-for="variant in routing" :key="variant.content_id" class="mkt-publicar__variante">
                                <div class="mkt-publicar__fuerte">
                                    Nicho: {{ variant.niche }}
                                    <span class="mkt-publicar__suave">({{ variant.aspect_ratio }}, {{ variant.duration_sec }} s)</span>
                                </div>
                                <div class="mkt-publicar__chips">
                                    <mkt-insignia-estado
                                        v-for="ch in variant.channels"
                                        :key="ch.channel_id"
                                        :texto="ch.channel_name"
                                        :tono="ch.can_publish_now ? 'exito' : (ch.credentials_ready ? 'aviso' : 'neutro')"
                                        :title="ch.reason || 'Listo'"
                                    />
                                </div>
                            </div>
                            <div class="mkt-publicar__separador"></div>
                            <div class="mkt-publicar__fuerte">¿Dónde publicar?</div>
                            <mkt-estado-vacio
                                v-if="!availableChannels.length"
                                icono="hub"
                                titulo="No hay canales activos"
                                texto="Activa y valida tus canales en Configuración de canales." />
                            <div v-else class="mkt-publicar__canales">
                                <q-checkbox
                                    v-for="ch in availableChannels"
                                    :key="ch.id"
                                    v-model="selectedChannelIds"
                                    :val="ch.id"
                                    dense
                                    :label="ch.credentials_ready ? ch.name : `${ch.name} (credenciales pendientes)`"
                                />
                            </div>
                        </div>
                        <q-stepper-navigation class="mkt-publicar__navegar">
                            <mkt-boton tipo="volver" etiqueta="Atrás" @click="step = 1" />
                            <mkt-boton tipo="principal" etiqueta="Siguiente" :deshabilitado="selectedChannelIds.length === 0" @click="step = 3" />
                        </q-stepper-navigation>
                    </q-step>

                    <!-- 3 -->
                    <q-step :name="3" title="Texto" icon="edit" :done="step > 3" :disable="!selectedChannelIds.length">
                        <div class="mkt-publicar__cuerpo">
                            <q-input
                                v-model="caption"
                                type="textarea"
                                outlined
                                rows="4"
                                counter
                                maxlength="2200"
                                label="Texto de la publicación (se usa en todas)"
                                :error="!!errores.caption"
                                :error-message="errores.caption"
                            />
                            <q-select
                                v-model="hashtags"
                                outlined
                                use-input
                                use-chips
                                multiple
                                hide-dropdown-icon
                                input-debounce="0"
                                new-value-mode="add-unique"
                                label="Hashtags"
                                hint="Escribe un hashtag y presiona Enter"
                            />
                        </div>
                        <q-stepper-navigation class="mkt-publicar__navegar">
                            <mkt-boton tipo="volver" etiqueta="Atrás" @click="step = 2" />
                            <mkt-boton tipo="principal" etiqueta="Siguiente" @click="step = 4" />
                        </q-stepper-navigation>
                    </q-step>

                    <!-- 4 -->
                    <q-step :name="4" title="Cuándo" icon="schedule" :done="step > 4" :disable="!selectedChannelIds.length">
                        <div class="mkt-publicar__cuerpo">
                            <div class="mkt-publicar__radios">
                                <q-radio v-model="scheduleMode" val="now" label="Publicar ahora" />
                                <q-radio v-model="scheduleMode" val="scheduled" label="Programar fecha y hora" />
                            </div>
                            <div v-if="scheduleMode === 'scheduled'" class="mkt-publicar__fecha">
                                <q-input
                                    :model-value="fechaVisible(scheduledDate)"
                                    readonly
                                    outlined
                                    dense
                                    label="Fecha"
                                    placeholder="dd/mm/aaaa"
                                    :error="!!errores.scheduled_for"
                                    :error-message="errores.scheduled_for"
                                >
                                    <template #append><q-icon name="event" class="cursor-pointer" aria-hidden="true" /></template>
                                    <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                        <q-date v-model="scheduledDate" mask="YYYY-MM-DD" :locale="calendarioEs">
                                            <div class="mkt-publicar__fecha-pie">
                                                <q-btn v-close-popup flat no-caps label="Listo" />
                                            </div>
                                        </q-date>
                                    </q-popup-proxy>
                                </q-input>
                                <q-input v-model="scheduledTime" type="time" outlined dense label="Hora" />
                            </div>
                            <div class="mkt-publicar__consejo" role="note">
                                <q-icon name="tips_and_updates" size="18px" aria-hidden="true" />
                                <span><strong>Mejores horarios:</strong> sábado y domingo 9–11 p. m. · miércoles 7–9 p. m. · evita lunes 8–10 a. m.</span>
                            </div>
                        </div>
                        <q-stepper-navigation class="mkt-publicar__navegar">
                            <mkt-boton tipo="volver" etiqueta="Atrás" @click="step = 3" />
                            <mkt-boton tipo="principal" etiqueta="Revisar" :deshabilitado="scheduleMode === 'scheduled' && !scheduledDate" @click="step = 5" />
                        </q-stepper-navigation>
                    </q-step>

                    <!-- 5 -->
                    <q-step :name="5" title="Confirmar" icon="check_circle" :disable="!selectedChannelIds.length">
                        <dl class="mkt-publicar__resumen">
                            <div><dt>Campaña</dt><dd>{{ campanaElegida?.name || '—' }}</dd></div>
                            <div><dt>Canales</dt><dd>{{ selectedChannelIds.length }}</dd></div>
                            <div><dt>Publicaciones</dt><dd>~{{ routing.length * selectedChannelIds.length }}</dd></div>
                            <div><dt>Cuándo</dt><dd>{{ scheduleMode === 'now' ? 'Ahora' : `${fechaVisible(scheduledDate)} ${scheduledTime}` }}</dd></div>
                        </dl>
                        <q-stepper-navigation class="mkt-publicar__navegar">
                            <mkt-boton tipo="volver" etiqueta="Atrás" :deshabilitado="submitting" @click="step = 4" />
                            <mkt-boton tipo="guardar" etiqueta="Publicar campaña" :cargando="submitting" @click="submit" />
                        </q-stepper-navigation>
                    </q-step>
                </q-stepper>
                </div>
            </mkt-encabezado-pantalla>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import { mensajeDeError, erroresPorCampo } from "../kit";

/** Publicar campaña (/marketing/publishing/campaign) — Fase 1, rediseño con el kit, mismos endpoints y carga útil. */
export default {
    name: "MarketingPublishCampaignView",
    data() {
        return {
            /** Calendario en español (q-date), mismo formato de fecha del sistema. */
            calendarioEs: {
                days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
                daysShort: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
                months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
                monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
                firstDayOfWeek: 1,
            },
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            step: 1,
            campaigns: [],
            selectedCampaign: null,
            routing: [],
            errorRouting: "",
            availableChannels: [],
            selectedChannelIds: [],
            caption: "",
            hashtags: ["meganet", "internet"],
            scheduleMode: "now",
            scheduledDate: "",
            scheduledTime: "20:00",
            loadingRouting: false,
            submitting: false,
            errores: {},
        };
    },
    computed: {
        campanaElegida() {
            return this.campaigns.find((c) => c.id === this.selectedCampaign) || null;
        },
    },
    mounted() {
        this.iniciar();
    },
    methods: {
        /** «2026-12-01» → «01/12/2026» (formato del sistema; solo para mostrar). */
        fechaVisible(d) {
            const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(d || ""));
            return m ? `${m[3]}/${m[2]}/${m[1]}` : d || "";
        },
        async iniciar() {
            this.estado = "cargando";
            try {
                const [camp, chan] = await Promise.all([
                    axios.get("/api/marketing/multivariant-campaigns"),
                    axios.get("/api/marketing/publishing/channels"),
                ]);
                this.campaigns = (camp.data?.data ?? []).filter((c) => c.status === "completed");
                this.availableChannels = (chan.data?.channels ?? []).filter((c) => c.active);
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
        async loadRouting() {
            this.routing = [];
            this.errorRouting = "";
            if (!this.selectedCampaign) return;
            this.loadingRouting = true;
            try {
                const { data } = await axios.get(`/api/marketing/publishing/campaigns/${this.selectedCampaign}/route`);
                this.routing = data?.routing ?? [];
                if (!this.routing.length) this.errorRouting = "Esta campaña no tiene videos listos para publicar.";
            } catch (e) {
                this.errorRouting = e?.response?.status === 404
                    ? "No se encontró la campaña."
                    : mensajeDeError(e, "No se pudo calcular a qué canales va cada video.");
            } finally {
                this.loadingRouting = false;
            }
        },
        async submit() {
            if (this.submitting) return;
            this.submitting = true;
            this.errores = {};
            try {
                let scheduledFor = null;
                if (this.scheduleMode === "scheduled" && this.scheduledDate) {
                    scheduledFor = `${this.scheduledDate} ${this.scheduledTime}:00`;
                }
                const { data } = await axios.post(`/api/marketing/publishing/campaigns/${this.selectedCampaign}/publish`, {
                    channel_ids: this.selectedChannelIds,
                    scheduled_for: scheduledFor,
                    caption: this.caption,
                    hashtags: this.hashtags,
                });
                message(`${data?.queued ?? 0} publicaciones creadas.`);
                window.location.href = "/marketing/publishing/queue";
            } catch (e) {
                this.errores = erroresPorCampo(e);
                if (this.errores.caption) this.step = 3;
                else if (this.errores.scheduled_for) this.step = 4;
                message(mensajeDeError(e, "No se pudo publicar la campaña."), "error");
                this.submitting = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-publicar { min-width: 0; }
.mkt-publicar__cuerpo-tarjeta { padding: 0 16px 8px; min-width: 0; }
.mkt-publicar__cargando { display: flex; flex-direction: column; gap: 8px; padding: 8px 0 16px; }
.mkt-publicar__pasos { background: transparent; color: var(--mkt-texto); }
.mkt-publicar__pasos :deep(.q-stepper__header) { border-bottom: 1px solid var(--mkt-borde); }
.mkt-publicar__pasos :deep(.q-stepper__tab) { padding: 16px 12px; }
.mkt-publicar__pasos :deep(.q-stepper__step-inner) { padding: var(--mkt-esp-3) 0 var(--mkt-esp-1); }
.mkt-publicar__cuerpo { display: flex; flex-direction: column; gap: var(--mkt-esp-2); max-width: 760px; }
.mkt-publicar__navegar { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: var(--mkt-esp-1); padding: var(--mkt-esp-2) 0 0; }
.mkt-publicar__suave { margin: 0; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-publicar__error { margin: 0; font-size: 14px; color: var(--mkt-peligro); }
.mkt-publicar__fuerte { font-weight: 600; color: var(--mkt-texto); }
.mkt-publicar__variante { display: flex; flex-direction: column; gap: 6px; }
.mkt-publicar__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.mkt-publicar__separador { border-top: 1px solid var(--mkt-borde); }
.mkt-publicar__canales { display: grid; gap: 4px var(--mkt-esp-2); grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
.mkt-publicar__fecha-pie { display: flex; justify-content: flex-end; }
.mkt-publicar__radios { display: flex; flex-wrap: wrap; gap: var(--mkt-esp-2); }
.mkt-publicar__fecha { display: grid; gap: var(--mkt-esp-2); grid-template-columns: minmax(0, 200px) minmax(0, 150px); }
.mkt-publicar__consejo {
    display: flex;
    align-items: flex-start;
    gap: var(--mkt-esp-1);
    padding: 12px var(--mkt-esp-2);
    border-radius: var(--mkt-radio);
    background: var(--mkt-fondo-suave);
    font-size: 13px;
    color: var(--mkt-texto);
}
.mkt-publicar__consejo > .q-icon { color: var(--mkt-info); flex: 0 0 auto; }
.mkt-publicar__resumen { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--mkt-esp-2); margin: 0; }
.mkt-publicar__resumen dt { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; color: var(--mkt-texto-suave); }
.mkt-publicar__resumen dd { margin: 0; font-size: 15px; font-weight: 600; color: var(--mkt-texto); overflow-wrap: anywhere; }
@media (max-width: 1023px) {
    .mkt-publicar__resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 599px) {
    .mkt-publicar__cuerpo-tarjeta { padding: 0 8px 8px; }
    .mkt-publicar__pasos :deep(.q-stepper__header) { flex-wrap: wrap; }
    .mkt-publicar__pasos :deep(.q-stepper__tab) { flex: 1 1 auto; padding: 8px; min-height: 0; }
    .mkt-publicar__fecha { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    .mkt-publicar__navegar :deep(.btn) { flex: 1 1 auto; }
}
</style>
