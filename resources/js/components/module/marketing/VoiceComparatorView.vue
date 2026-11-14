<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-voces">
            <mkt-encabezado-pantalla
                titulo="Voces"
                subtitulo="Escucha cada voz y modelo con el mismo texto y elige la de cada nicho"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <!-- Acción principal (UNA, arriba a la derecha). -->
                <template v-if="estado !== 'sin_permiso' && estado !== 'error' && puedeProbar" #acciones>
                    <mkt-boton
                        tipo="guardar"
                        etiqueta="Generar muestras"
                        :cargando="generating"
                        :deshabilitado="!combinaciones || !testText"
                        @click="generateSamples"
                    />
                </template>
                <mkt-estado-vacio
                    v-if="estado === 'sin_permiso'"
                    icono="lock"
                    titulo="No puedes usar el comparador de voces"
                    texto="No tienes acceso a esta sección. Pídelo a Irving."
                />
                <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudieron cargar las voces" :texto="mensajeError" />
            </mkt-encabezado-pantalla>

            <template v-if="estado !== 'sin_permiso' && estado !== 'error'">
                <div class="mkt-voces__rejilla">
                    <!-- Configuración de la prueba -->
                    <mkt-tarjeta-panel titulo="Prueba" subtitulo="El texto se convierte a voz con cada combinación de voz y modelo" tono="lila" icono-fa="fa-microphone" :cargando="estado === 'cargando'">
                        <div class="mkt-voces__prueba">
                            <q-input v-model="testText" type="textarea" outlined autogrow label="Texto de prueba" />

                            <fieldset class="mkt-voces__grupo">
                                <legend class="mkt-voces__leyenda">Voces</legend>
                                <div class="mkt-voces__opciones">
                                    <q-checkbox
                                        v-for="v in voices"
                                        :key="v.id"
                                        v-model="selectedVoices"
                                        :val="v.id"
                                        dense
                                        :label="`${v.id} — ${v.description} (${v.gender})`"
                                    />
                                </div>
                            </fieldset>

                            <fieldset class="mkt-voces__grupo">
                                <legend class="mkt-voces__leyenda">Modelos</legend>
                                <div class="mkt-voces__opciones">
                                    <q-checkbox v-for="m in models" :key="m.id" v-model="selectedModels" :val="m.id" dense :label="m.name" />
                                </div>
                            </fieldset>
                        </div>
                        <template #pie>
                            <div class="mkt-voces__pie">
                                <span>{{ combinaciones }} combinaciones · el costo real se muestra al generar</span>
                            </div>
                        </template>
                    </mkt-tarjeta-panel>

                    <!-- Voces por nicho -->
                    <mkt-tarjeta-panel titulo="Voz por nicho" subtitulo="La que usan los videos de cada audiencia" tono="azul" icono-fa="fa-sliders-h" :cargando="estado === 'cargando'">
                        <mkt-tabla-ejecutiva
                            :columnas="nicheColumns"
                            :filas="niches"
                            :buscador="false"
                            vacio-icono="groups"
                            vacio-titulo="No hay nichos activos"
                            vacio-texto="Los nichos de audiencia forman parte del catálogo de Meganet."
                        >
                            <template #body-cell-name="props">
                                <q-td :props="props"><span class="mkt-voces__fuerte">{{ props.row.name }}</span></q-td>
                            </template>
                            <template #body-cell-preferred_voice_id="props">
                                <q-td :props="props">
                                    <mkt-insignia-estado :texto="props.value || 'Sin asignar'" :tono="props.value ? 'primario' : 'neutro'" />
                                </q-td>
                            </template>
                            <template #body-cell-preferred_voice_model="props">
                                <q-td :props="props">{{ nombreModelo(props.value || 'tts-1') }}</q-td>
                            </template>
                            <template #body-cell-actions="props">
                                <q-td :props="props">
                                    <q-btn
                                        v-if="puedeAsignar"
                                        flat
                                        dense
                                        round
                                        icon="edit"
                                        :aria-label="`Cambiar voz de ${props.row.name}`"
                                        @click="openEditModal(props.row)"
                                    >
                                        <q-tooltip>Cambiar voz</q-tooltip>
                                    </q-btn>
                                </q-td>
                            </template>
                        </mkt-tabla-ejecutiva>
                    </mkt-tarjeta-panel>
                </div>

                <!-- Error de una acción -->
                <q-banner v-if="errorMsg" class="mkt-voces__error" role="alert" dense>
                    <template #avatar><q-icon name="error_outline" /></template>
                    {{ errorMsg }}
                </q-banner>

                <!-- Muestras -->
                <mkt-tarjeta-panel v-if="generating || samples.length" titulo="Muestras" :subtitulo="subtituloMuestras" tono="verde" icono-fa="fa-check-double" :cargando="generating">
                    <ul class="mkt-voces__muestras">
                        <li v-for="sample in samples" :key="sample.label || `${sample.voice}-${sample.model}`" class="mkt-voces__muestra">
                            <div class="mkt-voces__muestra-info">
                                <span class="mkt-voces__fuerte">{{ sample.voice }}</span>
                                <span class="mkt-voces__suave">
                                    {{ nombreModelo(sample.model) }}<span v-if="Number(sample.duration_sec) > 0"> · {{ Number(sample.duration_sec).toFixed(1) }} s</span>
                                </span>
                            </div>
                            <audio :src="sample.url" controls preload="none" class="mkt-voces__audio" />
                            <q-select
                                v-if="puedeAsignar"
                                v-model="sample.assignTo"
                                :options="nicheOptions"
                                label="Asignar a nicho"
                                dense
                                outlined
                                emit-value
                                map-options
                                clearable
                                :loading="asignando === sample"
                                :disable="!!asignando"
                                class="mkt-voces__asignar"
                                @update:model-value="(v) => v && assignToNiche(sample, v)"
                            />
                        </li>
                    </ul>
                </mkt-tarjeta-panel>
            </template>

            <!-- Editar voz por nicho -->
            <mkt-dialogo v-model="editModal" :titulo="`Voz de ${editingNiche?.name || 'nicho'}`" :guardando="savingEdit" :ancho="420">
                <q-select v-model="editForm.voice_id" :options="voiceOptions" label="Voz" outlined dense emit-value map-options />
                <q-select v-model="editForm.voice_model" :options="modelOptions" label="Modelo" outlined dense emit-value map-options />
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="savingEdit" @click="editModal = false" />
                    <mkt-boton tipo="guardar" etiqueta="Guardar" :cargando="savingEdit" @click="saveEdit" />
                </template>
            </mkt-dialogo>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import { mensajeDeError, formatoMXN, puedePermiso } from "./kit";

/**
 * Comparador de voces (/marketing/voice-comparator) — Fase 1, rediseño con el kit, mismos endpoints.
 * Se quitó la estimación previa en USD con tipo de cambio fijo (×20): el costo que se muestra es el
 * `total_cost_mxn` que devuelve el backend al generar.
 */
export default {
    name: "VoiceComparatorView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            testText:
                "Hola, soy Meganet. Llámanos al cinco, cinco, seis, ocho, uno, siete, cinco, seis, cuatro, tres para contratar tu plan de internet.",
            voices: [],
            models: [],
            niches: [],
            samples: [],
            generating: false,
            errorMsg: "",
            lastTotalMxn: null,
            selectedVoices: ["alloy", "echo", "fable", "onyx", "nova", "shimmer"],
            selectedModels: ["tts-1", "tts-1-hd"],
            asignando: null,
            editModal: false,
            editingNiche: null,
            savingEdit: false,
            editForm: { voice_id: "", voice_model: "tts-1" },
            nicheColumns: [
                { name: "name", label: "Nicho", field: "name", align: "left" },
                { name: "preferred_voice_id", label: "Voz", field: "preferred_voice_id", align: "left" },
                { name: "preferred_voice_model", label: "Modelo", field: "preferred_voice_model", align: "left", ocultarEnMovil: true },
                { name: "actions", label: "", field: "id", align: "right" },
            ],
        };
    },
    computed: {
        combinaciones() {
            return this.selectedVoices.length * this.selectedModels.length;
        },
        puedeProbar() {
            return puedePermiso(this.$store, "test-voices");
        },
        puedeAsignar() {
            return this.contexto?.capacidades?.editar_config_compartida !== false;
        },
        nicheOptions() {
            return this.niches.map((n) => ({ label: n.name, value: n.id }));
        },
        voiceOptions() {
            return this.voices.map((v) => ({ label: `${v.id} — ${v.description}`, value: v.id }));
        },
        modelOptions() {
            return this.models.map((m) => ({ label: m.name, value: m.id }));
        },
        subtituloMuestras() {
            if (this.generating) return "Generando…";
            const n = `${this.samples.length} muestras`;
            return this.lastTotalMxn !== null ? `${n} · costo ${formatoMXN(this.lastTotalMxn)}` : n;
        },
    },
    mounted() {
        this.loadInitial();
    },
    methods: {
        nombreModelo(id) {
            return this.models.find((m) => m.id === id)?.name || id;
        },
        async loadInitial({ silencioso = false } = {}) {
            if (!silencioso) this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/voice-comparator/voices");
                this.voices = data?.voices ?? [];
                // Solo para mostrar: el nombre del modelo en español («Standard» → «Estándar»); el id no cambia.
                this.models = (data?.models ?? []).map((m) => ({ ...m, name: String(m?.name ?? m?.id ?? "").replace(/\bStandard\b/g, "Estándar") }));
                this.niches = data?.niches ?? [];
                this.estado = "listo";
            } catch (e) {
                if (silencioso && this.estado === "listo") return;
                if (e?.response?.status === 403) {
                    this.estado = "sin_permiso";
                } else {
                    this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
                    this.estado = "error";
                }
            }
        },
        async generateSamples() {
            if (this.generating) return;
            this.generating = true;
            this.errorMsg = "";
            this.samples = [];
            this.lastTotalMxn = null;
            try {
                const { data } = await axios.post("/api/marketing/voice-comparator/generate-samples", {
                    text: this.testText,
                    voices: this.selectedVoices,
                    models: this.selectedModels,
                });
                this.samples = (data?.samples ?? []).map((s) => ({ ...s, assignTo: null }));
                this.lastTotalMxn = data?.total_cost_mxn ?? null;
            } catch (e) {
                this.errorMsg = mensajeDeError(e, "No se pudieron generar las muestras.");
            } finally {
                this.generating = false;
            }
        },
        async assignToNiche(sample, nicheId) {
            if (this.asignando) return;
            this.asignando = sample;
            this.errorMsg = "";
            try {
                await axios.post("/api/marketing/voice-comparator/assign-niche", {
                    niche_id: nicheId,
                    voice_id: sample.voice,
                    voice_model: sample.model,
                });
                message("Voz asignada al nicho.");
                await this.loadInitial({ silencioso: true });
            } catch (e) {
                this.errorMsg = mensajeDeError(e, "No se pudo asignar la voz.");
            } finally {
                sample.assignTo = null;
                this.asignando = null;
            }
        },
        openEditModal(niche) {
            this.editingNiche = niche;
            this.editForm = {
                voice_id: niche.preferred_voice_id || "nova",
                voice_model: niche.preferred_voice_model || "tts-1",
            };
            this.editModal = true;
        },
        async saveEdit() {
            if (this.savingEdit || !this.editingNiche) return;
            this.savingEdit = true;
            try {
                await axios.post("/api/marketing/voice-comparator/assign-niche", {
                    niche_id: this.editingNiche.id,
                    voice_id: this.editForm.voice_id,
                    voice_model: this.editForm.voice_model,
                });
                this.editModal = false;
                message("Voz del nicho actualizada.");
                await this.loadInitial({ silencioso: true });
            } catch (e) {
                message(mensajeDeError(e, "No se pudo guardar la voz."), "error");
            } finally {
                this.savingEdit = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-voces {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-2);
    min-width: 0;
}
.mkt-voces > :first-child { margin-bottom: 0; }
.mkt-voces__rejilla {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
}
.mkt-voces__prueba { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-voces__grupo { border: 0; margin: 0; padding: 0; min-width: 0; }
.mkt-voces__leyenda { font-size: 13px; font-weight: 600; color: var(--mkt-texto-suave); margin-bottom: 4px; padding: 0; }
.mkt-voces__opciones { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 6px var(--mkt-esp-2); }
/* q-checkbox es `row inline no-wrap`: su etiqueta se salía a la otra columna. Aquí se acomoda en varias líneas. */
.mkt-voces__opciones :deep(.q-checkbox) { display: flex; align-items: flex-start; min-width: 0; max-width: 100%; }
.mkt-voces__opciones :deep(.q-checkbox__inner) { flex: 0 0 auto; }
.mkt-voces__opciones :deep(.q-checkbox__label) { flex: 1 1 auto; min-width: 0; white-space: normal; overflow-wrap: anywhere; line-height: 1.35; padding-top: 1px; }
.mkt-voces__pie { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--mkt-esp-1); padding: 4px 0; }
.mkt-voces__error {
    border-radius: var(--mkt-radio);
    color: var(--mkt-peligro);
    background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent);
}
.mkt-voces__muestras { list-style: none; margin: 0; padding: 0; }
.mkt-voces__muestra {
    display: grid;
    grid-template-columns: minmax(140px, 200px) minmax(0, 1fr) minmax(160px, 220px);
    align-items: center;
    gap: var(--mkt-esp-2);
    padding: 10px 0;
}
.mkt-voces__muestra + .mkt-voces__muestra { border-top: 1px solid var(--mkt-borde); }
.mkt-voces__muestra-info { display: flex; flex-direction: column; min-width: 0; }
.mkt-voces__audio { width: 100%; height: 36px; }
.mkt-voces__fuerte { font-weight: 600; color: var(--mkt-texto); }
.mkt-voces__suave { font-size: 13px; color: var(--mkt-texto-suave); }
@media (max-width: 1023px) {
    .mkt-voces__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-voces__rejilla { gap: 12px; }
    .mkt-voces__opciones { grid-template-columns: minmax(0, 1fr); }
    .mkt-voces__muestra { grid-template-columns: minmax(0, 1fr); gap: var(--mkt-esp-1); }
    .mkt-voces__pie :deep(.btn) { width: 100%; }
}
</style>
