<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-pub">
            <mkt-encabezado-pantalla
                titulo="Resumen"
                subtitulo="Estado del publicador multicanal"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #acciones>
                    <mkt-boton tipo="secundario" etiqueta="Ver fila de publicación" href="/marketing/publishing/queue" />
                    <mkt-boton tipo="crear" etiqueta="Nueva publicación" href="/marketing/publishing/campaign" />
                </template>
            </mkt-encabezado-pantalla>

            <mkt-tarjeta-panel v-if="estado === 'sin_permiso'">
                <mkt-estado-vacio icono="lock" titulo="No tienes acceso a esta sección" texto="No tienes acceso a esta sección. Pídelo a Irving." />
            </mkt-tarjeta-panel>

            <mkt-tarjeta-panel v-else-if="estado === 'error'">
                <mkt-estado-vacio icono="cloud_off" titulo="No se pudo cargar el tablero" :texto="mensajeError" />
            </mkt-tarjeta-panel>

            <template v-else>
                <section class="mkt-pub__kpis" aria-label="Resumen de publicaciones">
                    <mkt-tarjeta-kpi etiqueta="Publicadas" :valor="stats.published" icono="check_circle" :cargando="cargando" />
                    <mkt-tarjeta-kpi etiqueta="En fila" :valor="stats.queued" icono="schedule" :cargando="cargando" />
                    <mkt-tarjeta-kpi etiqueta="Fallidas" :valor="stats.failed" icono="error_outline" :cargando="cargando" />
                    <mkt-tarjeta-kpi etiqueta="Esperando credenciales" :valor="stats.waiting_credentials" icono="key" :cargando="cargando" />
                </section>

                <div class="mkt-pub__rejilla">
                    <mkt-tarjeta-panel titulo="Canales" subtitulo="Si cada canal tiene sus credenciales listas" :cargando="cargando">
                        <template #acciones>
                            <mkt-boton tipo="secundario" chico etiqueta="Configurar" href="/marketing/publishing/setup" />
                        </template>
                        <mkt-estado-vacio
                            v-if="!byChannel.length"
                            icono="hub"
                            titulo="Sin canales"
                            texto="Configura los canales para empezar a publicar."
                        />
                        <ul v-else class="mkt-pub__lista">
                            <li v-for="ch in byChannel" :key="ch.channel_name" class="mkt-pub__fila">
                                <q-icon :name="platformIcon(ch.platform)" size="20px" class="mkt-pub__plataforma" aria-hidden="true" />
                                <div class="mkt-pub__fila-texto">
                                    <span class="mkt-pub__fuerte">{{ ch.channel_name }}</span>
                                    <span class="mkt-pub__suave">{{ formatoNumero(ch.published_count) }} publicadas</span>
                                </div>
                                <mkt-insignia-estado :texto="ch.credentials_ready ? 'Listo' : 'Pendiente'" :tono="ch.credentials_ready ? 'exito' : 'aviso'" />
                            </li>
                        </ul>
                    </mkt-tarjeta-panel>

                    <mkt-tarjeta-panel titulo="Publicaciones recientes" :subtitulo="`${formatoNumero(stats.total)} en total`" :cargando="cargando">
                        <template #acciones>
                            <mkt-boton tipo="secundario" chico etiqueta="Ver toda la fila" href="/marketing/publishing/queue" />
                        </template>
                        <mkt-estado-vacio
                            v-if="!recent.length"
                            icono="campaign"
                            titulo="Sin publicaciones todavía"
                            texto="Cuando publiques una campaña, aquí verás lo último que salió." />
                        <ul v-else class="mkt-pub__lista">
                            <li v-for="pub in recent" :key="pub.id" class="mkt-pub__fila">
                                <q-icon :name="platformIcon(pub.platform)" size="20px" class="mkt-pub__plataforma" aria-hidden="true" />
                                <div class="mkt-pub__fila-texto">
                                    <span class="mkt-pub__fuerte">{{ pub.channel_name }}</span>
                                    <span class="mkt-pub__suave">
                                        <template v-if="pub.ab_variant_tag">{{ pub.ab_variant_tag }} · </template>{{ formatoFecha(pub.published_at, { conHora: true }) }}
                                    </span>
                                </div>
                                <span v-if="pub.metrics" class="mkt-pub__metricas">
                                    <span v-if="pub.metrics.likes"><q-icon name="favorite" size="14px" aria-hidden="true" /> {{ formatoNumero(pub.metrics.likes) }}</span>
                                    <span v-if="pub.metrics.views"><q-icon name="visibility" size="14px" aria-hidden="true" /> {{ formatoNumero(pub.metrics.views) }}</span>
                                </span>
                                <q-btn
                                    v-if="pub.external_post_url"
                                    flat
                                    round
                                    dense
                                    icon="open_in_new"
                                    :href="pub.external_post_url"
                                    target="_blank"
                                    rel="noopener"
                                    aria-label="Ver publicación"
                                />
                            </li>
                        </ul>
                    </mkt-tarjeta-panel>
                </div>
            </template>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { mensajeDeError, formatoFecha, formatoNumero } from "../kit";

const ICONOS = { facebook: "fab fa-facebook", instagram: "fab fa-instagram", whatsapp: "fab fa-whatsapp", email: "email" };

/** Tablero de publicación (/marketing/publishing) — Fase 1, rediseño con el kit, mismos endpoints. */
export default {
    name: "MarketingPublishingDashboardView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            stats: { total: null, published: null, queued: null, failed: null, waiting_credentials: null },
            byChannel: [],
            recent: [],
        };
    },
    computed: {
        cargando() {
            return this.estado === "cargando";
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        formatoFecha,
        formatoNumero,
        async load() {
            this.estado = "cargando";
            try {
                const [statsRes, recentRes] = await Promise.all([
                    axios.get("/api/marketing/publishing/dashboard/stats"),
                    axios.get("/api/marketing/publishing/dashboard/recent"),
                ]);
                this.stats = statsRes.data?.stats ?? this.stats;
                this.byChannel = statsRes.data?.by_channel ?? [];
                this.recent = recentRes.data?.publications ?? [];
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
        platformIcon(platform) {
            return ICONOS[platform] || "share";
        },
    },
};
</script>

<style scoped>
.mkt-pub {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-2);
    min-width: 0;
}
.mkt-pub > :first-child { margin-bottom: 0; }
.mkt-pub__kpis { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(4, minmax(0, 1fr)); }
.mkt-pub__rejilla { display: grid; gap: var(--mkt-esp-2); grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); align-items: start; }
.mkt-pub__lista { list-style: none; margin: 0; padding: 0; }
.mkt-pub__fila { display: flex; align-items: center; gap: 12px; padding: 10px 0; min-width: 0; }
.mkt-pub__fila + .mkt-pub__fila { border-top: 1px solid var(--mkt-borde); }
.mkt-pub__plataforma { color: var(--mkt-texto-suave); flex: 0 0 auto; }
.mkt-pub__fila-texto { display: flex; flex-direction: column; flex: 1 1 auto; min-width: 0; }
.mkt-pub__fuerte { font-weight: 600; color: var(--mkt-texto); overflow-wrap: anywhere; }
.mkt-pub__suave { font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-pub__metricas { display: inline-flex; gap: var(--mkt-esp-1); font-size: 13px; color: var(--mkt-texto-suave); white-space: nowrap; }
.mkt-pub__metricas > span { display: inline-flex; align-items: center; gap: 2px; }
@media (max-width: 1023px) {
    .mkt-pub__kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .mkt-pub__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-pub__kpis { gap: var(--mkt-esp-1); }
    .mkt-pub__rejilla { gap: var(--mkt-esp-2); }
    .mkt-pub__fila { flex-wrap: wrap; }
}
</style>
