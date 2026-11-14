<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-multi">
            <mkt-encabezado-pantalla
                titulo="Video por nicho"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #acciones>
                    <mkt-boton tipo="secundario" etiqueta="Ver fila de producción" href="/marketing/video-queue" />
                </template>
            </mkt-encabezado-pantalla>

            <mkt-tarjeta-panel v-if="estado === 'sin_permiso'">
                <mkt-estado-vacio icono="lock" titulo="No tienes acceso a esta sección" texto="No tienes acceso a esta sección. Pídelo a Irving." />
            </mkt-tarjeta-panel>

            <mkt-tarjeta-panel v-else-if="estado === 'error'">
                <mkt-estado-vacio icono="cloud_off" titulo="No se pudieron cargar los nichos" :texto="mensajeError" />
            </mkt-tarjeta-panel>

            <div v-else class="mkt-multi__rejilla">
                <div class="mkt-multi__pasos">
                    <!-- Paso 1 -->
                    <section class="mkt-multi__paso" :class="{ 'mkt-multi__paso--activo': currentStep === 1 }">
                        <button type="button" class="mkt-multi__paso-cabeza" :aria-expanded="currentStep === 1 ? 'true' : 'false'" @click="currentStep = 1">
                            <span class="mkt-multi__numero" :class="{ 'mkt-multi__numero--hecho': step1Complete && currentStep !== 1 }">
                                <q-icon v-if="step1Complete && currentStep !== 1" name="check" size="16px" aria-hidden="true" />
                                <template v-else>1</template>
                            </span>
                            <span class="mkt-multi__paso-titulo">Producto</span>
                            <span v-if="step1Complete && currentStep !== 1" class="mkt-multi__paso-resumen">{{ form.plan_name }} · {{ form.plan_speed }}</span>
                        </button>
                        <div v-if="currentStep === 1" class="mkt-multi__paso-cuerpo">
                            <q-select
                                v-model="form.campaign_type"
                                :options="tiposCampana"
                                emit-value
                                map-options
                                option-disable="disable"
                                outlined
                                dense
                                options-dense
                                label="Tipo de campaña"
                            />
                            <div class="mkt-multi__campos">
                                <q-input v-model="form.plan_name" outlined dense label="Nombre del plan *" placeholder="Plan Mega 100" />
                                <q-input v-model="form.plan_speed" outlined dense label="Velocidad *" placeholder="100 MEGAS" />
                                <q-input v-model="form.plan_price" outlined dense label="Precio por mes (MXN) *" placeholder="599" prefix="$" suffix="MXN" />
                                <q-input v-model="form.phone_cta" outlined dense label="Teléfono de contacto *" placeholder="55 6817 5643" />
                                <q-input v-model="form.highlight_feature" outlined dense label="Característica destacada" placeholder="Sin contratos forzados" class="mkt-multi__ancho" />
                            </div>
                            <div class="mkt-multi__navegar">
                                <mkt-boton tipo="principal" etiqueta="Siguiente" :deshabilitado="!step1Complete" @click="currentStep = 2" />
                            </div>
                        </div>
                    </section>

                    <!-- Paso 2 -->
                    <section class="mkt-multi__paso" :class="{ 'mkt-multi__paso--activo': currentStep === 2 }">
                        <button type="button" class="mkt-multi__paso-cabeza" :disabled="!step1Complete" :aria-expanded="currentStep === 2 ? 'true' : 'false'" @click="step1Complete && (currentStep = 2)">
                            <span class="mkt-multi__numero">2</span>
                            <span class="mkt-multi__paso-titulo">Nichos de audiencia</span>
                            <mkt-insignia-estado :texto="`${selectedNiches.length} de ${activeNiches.length}`" tono="primario" />
                        </button>
                        <div v-if="currentStep === 2" class="mkt-multi__paso-cuerpo">
                            <p class="mkt-multi__suave">Elige para qué audiencias generar video. Por defecto van todas.</p>
                            <div v-if="estado === 'cargando'" class="mkt-multi__nichos">
                                <q-skeleton v-for="n in 6" :key="n" type="rect" height="84px" />
                            </div>
                            <mkt-estado-vacio
                                v-else-if="!activeNiches.length"
                                icono="groups"
                                titulo="No hay nichos activos"
                                texto="Los nichos de audiencia son parte del catálogo de Meganet."
                            />
                            <div v-else class="mkt-multi__nichos">
                                <button
                                    v-for="niche in activeNiches"
                                    :key="niche.id"
                                    type="button"
                                    class="mkt-multi__opcion"
                                    :class="{ 'mkt-multi__opcion--elegida': isNicheSelected(niche.slug) }"
                                    :aria-pressed="isNicheSelected(niche.slug) ? 'true' : 'false'"
                                    @click="toggleNiche(niche.slug)"
                                >
                                    <span class="mkt-multi__opcion-cabeza">
                                        <q-icon :name="nicheIcon(niche.slug)" size="20px" aria-hidden="true" />
                                        <span class="mkt-multi__opcion-nombre">{{ niche.name }}</span>
                                        <q-icon v-if="isNicheSelected(niche.slug)" name="check_circle" size="18px" class="mkt-multi__check" aria-hidden="true" />
                                    </span>
                                    <span v-if="niche.emotional_tone" class="mkt-multi__suave">{{ niche.emotional_tone }}</span>
                                    <span v-if="(niche.vocabulary || []).length" class="mkt-multi__palabras">{{ (niche.vocabulary || []).slice(0, 3).join(' · ') }}</span>
                                </button>
                            </div>
                            <div class="mkt-multi__navegar">
                                <mkt-boton tipo="volver" etiqueta="Anterior" @click="currentStep = 1" />
                                <mkt-boton tipo="principal" etiqueta="Siguiente" :deshabilitado="selectedNiches.length === 0" @click="currentStep = 3" />
                            </div>
                        </div>
                    </section>

                    <!-- Paso 3 -->
                    <section class="mkt-multi__paso" :class="{ 'mkt-multi__paso--activo': currentStep === 3 }">
                        <button type="button" class="mkt-multi__paso-cabeza" :disabled="currentStep < 3 && !(step1Complete && selectedNiches.length)" :aria-expanded="currentStep === 3 ? 'true' : 'false'" @click="step1Complete && selectedNiches.length && (currentStep = 3)">
                            <span class="mkt-multi__numero">3</span>
                            <span class="mkt-multi__paso-titulo">Formato de video</span>
                            <span class="mkt-multi__paso-resumen">{{ selectedAspectRatios.join(', ') }}</span>
                        </button>
                        <div v-if="currentStep === 3" class="mkt-multi__paso-cuerpo">
                            <div class="mkt-multi__formatos">
                                <button
                                    v-for="ar in aspectRatioOptions"
                                    :key="ar.value"
                                    type="button"
                                    class="mkt-multi__opcion mkt-multi__opcion--formato"
                                    :class="{ 'mkt-multi__opcion--elegida': selectedAspectRatios.includes(ar.value) }"
                                    :aria-pressed="selectedAspectRatios.includes(ar.value) ? 'true' : 'false'"
                                    @click="toggleAspectRatio(ar.value)"
                                >
                                    <q-icon :name="ar.icon" size="28px" aria-hidden="true" />
                                    <span class="mkt-multi__opcion-nombre">{{ ar.value }}</span>
                                    <span class="mkt-multi__suave">{{ ar.label }}</span>
                                </button>
                            </div>
                            <div class="mkt-multi__navegar">
                                <mkt-boton tipo="volver" etiqueta="Anterior" @click="currentStep = 2" />
                                <mkt-boton tipo="principal" etiqueta="Siguiente" :deshabilitado="selectedAspectRatios.length === 0" @click="currentStep = 4" />
                            </div>
                        </div>
                    </section>

                    <!-- Paso 4 -->
                    <section class="mkt-multi__paso" :class="{ 'mkt-multi__paso--activo': currentStep === 4 }">
                        <button type="button" class="mkt-multi__paso-cabeza" :disabled="currentStep < 4" :aria-expanded="currentStep === 4 ? 'true' : 'false'" @click="currentStep >= 4 && (currentStep = 4)">
                            <span class="mkt-multi__numero">4</span>
                            <span class="mkt-multi__paso-titulo">Resumen y generar</span>
                        </button>
                        <div v-if="currentStep === 4" class="mkt-multi__paso-cuerpo">
                            <div class="mkt-multi__resumen">
                                <div><span class="mkt-multi__cifra">{{ totalVideos }}</span><span class="mkt-multi__suave">videos</span></div>
                                <div><span class="mkt-multi__cifra">{{ selectedNiches.length }} × {{ selectedAspectRatios.length }}</span><span class="mkt-multi__suave">nichos × formatos</span></div>
                                <div><span class="mkt-multi__cifra">~{{ estimatedMinutes }} min</span><span class="mkt-multi__suave">tiempo estimado</span></div>
                            </div>
                            <mkt-tabla-ejecutiva :columnas="columnasResumen" :filas="selectedNicheObjects" fila-key="slug" :buscador="false" :menu="false">
                                <template #body-cell-name="props">
                                    <q-td :props="props"><span class="mkt-multi__fuerte">{{ props.row.name }}</span></q-td>
                                </template>
                                <template #body-cell-formatos="props">
                                    <q-td :props="props">{{ selectedAspectRatios.join(', ') }}</q-td>
                                </template>
                                <template #body-cell-preferred_voice_id="props">
                                    <q-td :props="props"><mkt-insignia-estado :texto="props.value || 'Predeterminada'" tono="neutro" /></q-td>
                                </template>
                            </mkt-tabla-ejecutiva>
                            <q-input v-model="form.name" outlined dense label="Nombre de la campaña" :placeholder="`${form.plan_name} — ${formatoFecha(new Date())}`" />
                            <div class="mkt-multi__navegar">
                                <mkt-boton tipo="volver" etiqueta="Anterior" :deshabilitado="generating" @click="currentStep = 3" />
                                <mkt-boton
                                    v-if="puedeCrear"
                                    tipo="guardar"
                                    :etiqueta="`Generar ${totalVideos} videos`"
                                    :cargando="generating"
                                    @click="generate"
                                />
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Campañas recientes -->
                <mkt-tarjeta-panel titulo="Campañas recientes" tono="gris" icono-fa="fa-history" class="mkt-multi__lateral">
                    <template #acciones>
                        <q-btn flat round dense icon="refresh" aria-label="Actualizar campañas" :loading="loadingCampaigns" @click="loadCampaigns" />
                    </template>
                    <div v-if="loadingCampaigns && !campaigns.length" class="mkt-multi__lista-esqueleto">
                        <q-skeleton v-for="n in 3" :key="n" type="rect" height="48px" />
                    </div>
                    <mkt-estado-vacio v-else-if="errorCampaigns" icono="cloud_off" titulo="No se pudieron cargar" :texto="errorCampaigns" />
                    <mkt-estado-vacio v-else-if="!campaigns.length" icono="movie_filter" titulo="Sin campañas todavía" texto="La primera que generes aparece aquí." />
                    <ul v-else class="mkt-multi__campanas">
                        <li v-for="c in campaigns" :key="c.id" class="mkt-multi__campana">
                            <div class="mkt-multi__campana-cabeza">
                                <span class="mkt-multi__fuerte">{{ c.name }}</span>
                                <mkt-insignia-estado :texto="estadoCampana(c.status).label" :tono="estadoCampana(c.status).tono" />
                            </div>
                            <q-linear-progress v-if="c.status === 'generating'" indeterminate rounded size="4px" class="mkt-multi__barra" />
                            <div class="mkt-multi__suave">
                                {{ fechaCorta(c.created_at) }} · {{ c.variants_succeeded || 0 }}/{{ (c.variant_content_ids || []).length }} videos
                                · <a class="mkt-multi__enlace" :href="`/marketing/video-queue?campaign=${c.id}`">Ver videos</a>
                            </div>
                        </li>
                    </ul>
                </mkt-tarjeta-panel>
            </div>

            <!-- Éxito -->
            <mkt-dialogo v-model="exitoAbierto" titulo="Campaña creada" :ancho="440">
                <div class="mkt-multi__exito">
                    <q-icon name="check_circle" size="40px" aria-hidden="true" />
                    <div class="mkt-multi__fuerte">Generando {{ videosCreados }} videos</div>
                    <p class="mkt-multi__suave">Se producen en segundo plano. Puedes cerrar esta ventana y seguir trabajando.</p>
                </div>
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="Seguir generando" @click="exitoAbierto = false" />
                    <mkt-boton tipo="guardar" etiqueta="Ver fila de producción" href="/marketing/video-queue" />
                </template>
            </mkt-dialogo>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import { mensajeDeError, formatoFecha, puedePermiso, pareceIngles } from "./kit";

const ESTADOS_CAMPANA = {
    draft: { label: "Borrador", tono: "neutro" },
    generating: { label: "Generando", tono: "info" },
    ready: { label: "Lista", tono: "exito" },
    completed: { label: "Completa", tono: "exito" },
    partially_failed: { label: "Con fallas", tono: "aviso" },
    failed: { label: "Fallida", tono: "peligro" },
};

/**
 * Generador de campaña multivariante (/marketing/campaigns/generate) — Fase 1, rediseño con el kit.
 * Destino de «Nueva campaña» / «Crear campaña con IA». Mismos endpoints y la misma carga útil.
 * Se quitó el «costo estimado» en USD (0.05 por video, cifra fija inventada) — Fase 1: todo el dinero en MXN.
 */
export default {
    name: "MarketingCampaignGeneratorView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            currentStep: 1,
            generating: false,
            loadingCampaigns: false,
            errorCampaigns: "",
            niches: [],
            campaigns: [],
            selectedNiches: [],
            selectedAspectRatios: ["9:16"],
            exitoAbierto: false,
            videosCreados: 0,
            form: {
                name: "",
                campaign_type: "multivariant_plan_promo",
                plan_name: "",
                plan_speed: "",
                plan_price: "",
                phone_cta: "",
                highlight_feature: "Sin contratos forzados",
            },
            tiposCampana: [
                { label: "Promoción de plan de internet", value: "multivariant_plan_promo" },
                { label: "Anuncio de cobertura (próximamente)", value: "multivariant_coverage", disable: true },
            ],
            aspectRatioOptions: [
                { value: "9:16", label: "Reels e historias", icon: "smartphone" },
                { value: "1:1", label: "Publicación cuadrada", icon: "crop_square" },
                { value: "16:9", label: "Horizontal / YouTube", icon: "desktop_windows" },
            ],
            columnasResumen: [
                { name: "name", label: "Nicho", field: "name", align: "left" },
                { name: "formatos", label: "Formato", field: "slug", align: "left" },
                { name: "preferred_voice_id", label: "Voz", field: "preferred_voice_id", align: "left", ocultarEnMovil: true },
            ],
        };
    },
    computed: {
        activeNiches() {
            return this.niches.filter((n) => n.active);
        },
        step1Complete() {
            return !!(this.form.plan_name && this.form.plan_speed && this.form.plan_price && this.form.phone_cta);
        },
        totalVideos() {
            return this.selectedNiches.length * this.selectedAspectRatios.length;
        },
        estimatedMinutes() {
            return Math.ceil(this.totalVideos * 2);
        },
        selectedNicheObjects() {
            return this.activeNiches.filter((n) => this.selectedNiches.includes(n.slug));
        },
        puedeCrear() {
            return puedePermiso(this.$store, "create-marketing-campaigns");
        },
    },
    mounted() {
        this.loadNiches();
        this.loadCampaigns();
    },
    methods: {
        formatoFecha,
        /** Fecha del sistema: dd/mm/aaaa. */
        fechaCorta(v) {
            if (!v) return "—";
            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return "—";
            return new Intl.DateTimeFormat("es-MX", { day: "2-digit", month: "2-digit", year: "numeric", timeZone: "America/Mexico_City" }).format(d);
        },
        async loadNiches() {
            this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/niches");
                this.niches = data?.data || data || [];
                if (this.selectedNiches.length === 0) {
                    this.selectedNiches = this.niches.filter((n) => n.active).map((n) => n.slug);
                }
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
        async loadCampaigns() {
            this.loadingCampaigns = true;
            this.errorCampaigns = "";
            try {
                const { data } = await axios.get("/api/marketing/multivariant-campaigns");
                this.campaigns = (data?.data || data || []).slice(0, 5);
            } catch (e) {
                this.errorCampaigns = mensajeDeError(e, "Intenta de nuevo en un momento.");
            } finally {
                this.loadingCampaigns = false;
            }
        },
        isNicheSelected(slug) {
            return this.selectedNiches.includes(slug);
        },
        toggleNiche(slug) {
            const idx = this.selectedNiches.indexOf(slug);
            if (idx >= 0) this.selectedNiches.splice(idx, 1);
            else this.selectedNiches.push(slug);
        },
        toggleAspectRatio(ar) {
            const idx = this.selectedAspectRatios.indexOf(ar);
            if (idx >= 0) this.selectedAspectRatios.splice(idx, 1);
            else this.selectedAspectRatios.push(ar);
        },
        async generate() {
            if (this.generating) return;
            this.generating = true;
            const name = this.form.name || `${this.form.plan_name} — ${formatoFecha(new Date())}`;
            const total = this.totalVideos;
            try {
                await axios.post("/api/marketing/multivariant-campaigns", {
                    ...this.form,
                    name,
                    niche_slugs: this.selectedNiches,
                    aspect_ratios: this.selectedAspectRatios,
                });
                this.videosCreados = total;
                this.exitoAbierto = true;
                this.loadCampaigns();
                this.currentStep = 1;
                Object.assign(this.form, { plan_name: "", plan_speed: "", plan_price: "", phone_cta: "", name: "" });
            } catch (e) {
                const d = e?.response?.data;
                const texto = e?.response?.status === 403
                    ? "No tienes permiso para generar videos por nicho. Si lo necesitas, pídelo a Irving."
                    : !d?.mensaje && typeof d?.error === "string" && d.error.includes(" ") && !pareceIngles(d.error)
                        ? d.error
                        : mensajeDeError(e, "No se pudo generar la campaña.");
                message(texto, "error");
            } finally {
                this.generating = false;
            }
        },
        nicheIcon(slug) {
            return {
                gamer: "sports_esports",
                familia: "family_restroom",
                home_office: "laptop_mac",
                streamer: "videocam",
                pequeno_negocio: "storefront",
                adulto_mayor: "elderly",
            }[slug] || "person";
        },
        estadoCampana(status) {
            return ESTADOS_CAMPANA[status] || { label: status || "—", tono: "neutro" };
        },
    },
};
</script>

<style scoped>
.mkt-multi { min-width: 0; }
.mkt-multi__barra { color: var(--mkt-info); }
.mkt-multi__rejilla {
    display: grid;
    gap: 14px;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    align-items: start;
}
.mkt-multi__pasos { display: flex; flex-direction: column; gap: var(--mkt-esp-2); min-width: 0; }
.mkt-multi__paso {
    background: var(--mkt-fondo-tarjeta);
    border: 1px solid var(--mkt-borde);
    border-radius: var(--mkt-radio);
    box-shadow: var(--mkt-sombra);
    min-width: 0;
}
.mkt-multi__paso--activo { border-color: var(--mkt-primario); }
.mkt-multi__paso-cabeza {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--mkt-esp-1) 12px;
    width: 100%;
    padding: var(--mkt-esp-2) var(--mkt-esp-3);
    border: 0;
    background: transparent;
    color: var(--mkt-texto);
    font: inherit;
    text-align: left;
    cursor: pointer;
}
.mkt-multi__paso-cabeza:disabled { cursor: default; opacity: 0.6; }
.mkt-multi__paso-cabeza:focus-visible { outline: 2px solid var(--mkt-primario); outline-offset: -2px; border-radius: var(--mkt-radio); }
.mkt-multi__numero {
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    color: var(--mkt-primario);
    background: color-mix(in srgb, var(--mkt-primario) 12%, transparent);
}
.mkt-multi__numero--hecho { color: #fff; background: var(--mkt-exito); }
.mkt-multi__paso-titulo { font-size: 16px; font-weight: 600; }
.mkt-multi__paso-resumen { font-size: 13px; color: var(--mkt-texto-suave); margin-left: auto; }
.mkt-multi__paso-cuerpo {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-2);
    padding: 0 var(--mkt-esp-3) var(--mkt-esp-3);
}
.mkt-multi__campos { display: grid; gap: var(--mkt-esp-1) var(--mkt-esp-2); grid-template-columns: repeat(2, minmax(0, 1fr)); }
.mkt-multi__ancho { grid-column: 1 / -1; }
.mkt-multi__navegar { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: var(--mkt-esp-1); }
.mkt-multi__nichos { display: grid; gap: var(--mkt-esp-1); grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
.mkt-multi__formatos { display: grid; gap: var(--mkt-esp-1); grid-template-columns: repeat(3, minmax(0, 1fr)); }
.mkt-multi__opcion {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px var(--mkt-esp-2);
    border: 1px solid var(--mkt-borde);
    border-radius: var(--mkt-radio);
    background: var(--mkt-fondo-tarjeta);
    color: var(--mkt-texto);
    font: inherit;
    text-align: left;
    cursor: pointer;
    min-width: 0;
}
.mkt-multi__opcion:hover { border-color: var(--mkt-primario); }
.mkt-multi__opcion:focus-visible { outline: 2px solid var(--mkt-primario); outline-offset: 2px; }
.mkt-multi__opcion--elegida {
    border-color: var(--mkt-primario);
    background: color-mix(in srgb, var(--mkt-primario) 6%, var(--mkt-fondo-tarjeta));
}
.mkt-multi__opcion--formato { align-items: center; text-align: center; }
.mkt-multi__opcion-cabeza { display: flex; align-items: center; gap: var(--mkt-esp-1); color: var(--mkt-primario); }
.mkt-multi__opcion-nombre { font-weight: 600; color: var(--mkt-texto); overflow-wrap: anywhere; }
.mkt-multi__check { margin-left: auto; color: var(--mkt-primario); }
.mkt-multi__palabras { font-size: 12px; color: var(--mkt-texto-suave); overflow-wrap: anywhere; }
.mkt-multi__resumen {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--mkt-esp-1);
    padding: var(--mkt-esp-2);
    border-radius: var(--mkt-radio);
    background: var(--mkt-fondo-suave);
}
.mkt-multi__resumen > div { display: flex; flex-direction: column; min-width: 0; }
.mkt-multi__cifra { font-size: 22px; line-height: 30px; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--mkt-texto); }
.mkt-multi__suave { margin: 0; font-size: 13px; line-height: 1.5; color: var(--mkt-texto-suave); }
.mkt-multi__fuerte { font-weight: 600; color: var(--mkt-texto); overflow-wrap: anywhere; }
.mkt-multi__lista-esqueleto { display: flex; flex-direction: column; gap: var(--mkt-esp-1); }
.mkt-multi__campanas { list-style: none; margin: 0; padding: 0; }
.mkt-multi__campana { display: flex; flex-direction: column; gap: 6px; padding: 12px 0; }
.mkt-multi__campana + .mkt-multi__campana { border-top: 1px solid var(--mkt-borde); }
.mkt-multi__campana-cabeza { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--mkt-esp-1); }
.mkt-multi__enlace { text-decoration: none; font-weight: 500; }
.mkt-multi__enlace:hover { text-decoration: underline; }
.mkt-multi__exito { display: flex; flex-direction: column; align-items: center; gap: var(--mkt-esp-1); text-align: center; color: var(--mkt-exito); }
@media (max-width: 1023px) {
    .mkt-multi__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-multi__rejilla { gap: var(--mkt-esp-2); }
    .mkt-multi__paso-cabeza { padding: var(--mkt-esp-2); }
    .mkt-multi__paso-cuerpo { padding: 0 var(--mkt-esp-2) var(--mkt-esp-2); }
    .mkt-multi__campos,
    .mkt-multi__formatos,
    .mkt-multi__resumen { grid-template-columns: minmax(0, 1fr); }
    .mkt-multi__paso-resumen { margin-left: 0; width: 100%; }
    .mkt-multi__navegar :deep(.btn) { flex: 1 1 auto; }
}
</style>
