<template>
    <mkt-dialogo :model-value="modelValue" :titulo="`Editar ${etiqueta}`" :guardando="guardando" :ancho="960" persistente @update:model-value="(v) => $emit('update:modelValue', v)">
        <div v-if="g" class="mkt-editor">
            <div class="mkt-editor__fila2">
                <q-input v-model="g.titulo" outlined dense label="Título del guion *" :maxlength="lim.titulo" counter :error="!!errCampo('titulo')" :error-message="errCampo('titulo')" />
                <q-input v-model="g.gancho" outlined dense label="Gancho (lo primero que se oye o lee)" :maxlength="lim.gancho" counter :error="!!errCampo('gancho')" :error-message="errCampo('gancho')" />
            </div>

            <div class="mkt-editor__resumen" :class="{ 'mkt-editor__resumen--mal': !totalOk || !escenasOk }" role="status" aria-live="polite">
                <span><q-icon name="view_agenda" size="18px" aria-hidden="true" /> {{ g.escenas.length }} escenas <small>({{ limites.minEscenas }}–{{ limites.maxEscenas }})</small></span>
                <span><q-icon name="timer" size="18px" aria-hidden="true" /> Total {{ revision.total }} s <small>({{ limites.minTotal }}–{{ limites.maxTotal }} s)</small></span>
                <span>{{ limites.formato }}</span>
            </div>

            <ol class="mkt-editor__escenas">
                <li v-for="(e, i) in g.escenas" :key="e._k" class="mkt-editor__escena" :class="{ 'mkt-editor__escena--mal': (revision.porEscena[i] || []).length || errEscena(i).length }">
                    <header class="mkt-editor__escena-cabeza">
                        <span class="mkt-editor__num">Escena {{ i + 1 }}</span>
                        <span class="mkt-editor__escena-acciones">
                            <q-btn flat dense round icon="arrow_upward" :disable="i === 0 || guardando" :aria-label="`Subir escena ${i + 1}`" @click="mover(i, -1)" />
                            <q-btn flat dense round icon="arrow_downward" :disable="i === g.escenas.length - 1 || guardando" :aria-label="`Bajar escena ${i + 1}`" @click="mover(i, 1)" />
                            <q-btn flat dense round icon="delete" color="negative" :disable="g.escenas.length <= 1 || guardando" :aria-label="`Quitar escena ${i + 1}`" @click="quitar(i)" />
                        </span>
                    </header>
                    <div class="mkt-editor__campos">
                        <q-input
                            v-model.number="e.duracion_s"
                            outlined
                            dense
                            type="number"
                            inputmode="numeric"
                            :min="limEsc.minDuracion"
                            :max="limEsc.maxDuracion"
                            suffix="s"
                            label="Duración"
                            class="mkt-editor__dur"
                        />
                        <q-select
                            v-model="e.visual.tipo"
                            outlined
                            dense
                            emit-value
                            map-options
                            :options="tiposVisual"
                            label="Visual"
                            class="mkt-editor__tipo"
                            @update:model-value="(t) => alCambiarTipo(e, t)"
                        >
                            <template #option="scope">
                                <q-item v-bind="scope.itemProps" class="mkt-editor__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                            </template>
                        </q-select>
                        <q-select
                            v-if="e.visual.tipo === 'broll'"
                            v-model="e.visual.categoria_broll"
                            outlined
                            dense
                            emit-value
                            map-options
                            :options="opcionesBroll"
                            label="Categoría del video de apoyo"
                            class="mkt-editor__broll"
                        >
                            <template #option="scope">
                                <q-item v-bind="scope.itemProps" class="mkt-editor__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                            </template>
                        </q-select>
                    </div>
                    <q-input
                        v-model="e.texto_pantalla"
                        outlined
                        dense
                        :label="e.visual.tipo === 'logo' ? 'Texto en pantalla (el logo no lleva texto)' : 'Texto en pantalla'"
                        :disable="e.visual.tipo === 'logo'"
                        :counter="topeDe(e) !== null"
                        :maxlength="topeDe(e) !== null ? topeDe(e) : undefined"
                        :hint="topeDe(e) !== null ? `Máximo ${topeDe(e)} caracteres para que quepa en pantalla. Sin emojis.` : ''"
                    />
                    <q-input
                        v-model="e.voz_en_off"
                        outlined
                        dense
                        autogrow
                        type="textarea"
                        label="Voz en off"
                        counter
                        :maxlength="limEsc.vozEnOff"
                    />
                    <div class="mkt-editor__fila2">
                        <q-input v-model="e.toma" outlined dense autogrow type="textarea" label="Toma (indicación para quien graba)" counter :maxlength="limEsc.toma" />
                        <q-input v-model="e.musica_sonido" outlined dense label="Música / sonido" counter :maxlength="limEsc.musicaSonido" />
                    </div>
                    <ul v-if="mensajesEscena(i).length" class="mkt-editor__errores" role="alert">
                        <li v-for="(m, j) in mensajesEscena(i)" :key="j">{{ m }}</li>
                    </ul>
                    <ul v-if="(revision.avisos[i] || []).length" class="mkt-editor__avisos">
                        <li v-for="(m, j) in revision.avisos[i]" :key="j"><q-icon name="info" size="16px" aria-hidden="true" /> {{ m }}</li>
                    </ul>
                </li>
            </ol>

            <mkt-boton tipo="secundario" chico icono-fa="fa-plus" etiqueta="Agregar escena" class="mkt-editor__agregar" :deshabilitado="g.escenas.length >= limites.maxEscenas || guardando" @click="agregar" />

            <div class="mkt-editor__fila3">
                <q-select v-model="g.musica.ambiente" outlined dense emit-value map-options :options="musicas" label="Música de fondo">
                    <template #option="scope">
                        <q-item v-bind="scope.itemProps" class="mkt-editor__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                    </template>
                </q-select>
                <q-select v-model="g.voz.id" outlined dense emit-value map-options :options="voces" label="Voz">
                    <template #option="scope">
                        <q-item v-bind="scope.itemProps" class="mkt-editor__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                    </template>
                </q-select>
                <q-input v-model="g.cta_final.texto" outlined dense label="Llamada a la acción final *" counter :maxlength="limEsc.ctaFinal" :error="!!errCampo('cta_final')" :error-message="errCampo('cta_final')" />
            </div>

            <q-banner v-if="mostrarErrores && (revision.errores.length || error)" rounded class="mkt-editor__banner" role="alert">
                <div v-if="error">{{ error }}</div>
                <ul v-if="revision.errores.length"><li v-for="(m, i) in revision.errores" :key="i">{{ m }}</li></ul>
            </q-banner>
        </div>
        <template #acciones>
            <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="guardando" @click="$emit('update:modelValue', false)" />
            <mkt-boton tipo="guardar" icono-fa="fa-save" etiqueta="Guardar guion" :cargando="guardando" @click="guardar" />
        </template>
    </mkt-dialogo>
</template>

<script>
import axios from "axios";
import { MktDialogo, mensajeDeError, erroresPorCampo } from "../kit";
import {
    ETIQUETAS_PIEZA,
    LIMITES_GUION,
    LIMITES_ESCENA,
    TIPOS_VISUAL,
    VOCES,
    MUSICAS,
    revisarGuion,
    escenaNueva,
    clonar,
    topeTextoPantalla,
} from "./catalogos360";

let contador = 0;
const clave = () => `e${++contador}`;

/**
 * Editor de guion por escenas (§2.5.5): agregar, quitar y reordenar escenas; contadores por tipo; total en vivo contra el
 * límite. PUT …/piezas/{piezaId} con `contenido` (el backend renumera y recalcula la duración total).
 */
export default {
    name: "EditorGuionEscenas",
    components: { MktDialogo },
    props: {
        modelValue: { type: Boolean, default: false },
        campanaId: { type: Number, required: true },
        pieza: { type: Object, default: null },
        nichos: { type: Array, default: () => [] },
    },
    emits: ["update:modelValue", "guardada"],
    data() {
        return {
            g: null,
            guardando: false,
            error: "",
            errores: {},
            mostrarErrores: false,
            tiposVisual: TIPOS_VISUAL,
            voces: VOCES,
            musicas: MUSICAS,
            limEsc: LIMITES_ESCENA,
            lim: LIMITES_ESCENA,
        };
    },
    computed: {
        etiqueta() {
            const e = ETIQUETAS_PIEZA[this.pieza?.pieza] || "guion";
            return this.pieza?.variante ? `${e} ${this.pieza.variante}` : e;
        },
        limites() {
            return LIMITES_GUION[this.pieza?.pieza] || LIMITES_GUION.reel_guion;
        },
        revision() {
            return this.g ? revisarGuion(this.g, this.pieza?.pieza) : { errores: [], porEscena: {}, avisos: {}, total: 0 };
        },
        totalOk() {
            return this.revision.total >= this.limites.minTotal && this.revision.total <= this.limites.maxTotal;
        },
        escenasOk() {
            const n = this.g?.escenas?.length || 0;
            return n >= this.limites.minEscenas && n <= this.limites.maxEscenas;
        },
        opcionesBroll() {
            const lista = this.nichos.map((n) => ({ value: n.value, label: n.label }));
            // Si el guion trae una categoría que ya no está en la lista, se conserva visible.
            (this.g?.escenas || []).forEach((e) => {
                const c = e?.visual?.categoria_broll;
                if (c && !lista.some((x) => x.value === c)) lista.push({ value: c, label: c });
            });
            return lista;
        },
    },
    watch: {
        modelValue(v) {
            if (v) this.iniciar();
        },
    },
    methods: {
        iniciar() {
            const c = clonar(this.pieza?.contenido) || {};
            const escenas = (Array.isArray(c.escenas) ? c.escenas : []).map((e) => ({
                ...escenaNueva(),
                ...e,
                visual: { tipo: e?.visual?.tipo || "texto", categoria_broll: e?.visual?.categoria_broll ?? null },
                texto_pantalla: e?.texto_pantalla || "",
                voz_en_off: e?.voz_en_off || "",
                toma: e?.toma || "",
                musica_sonido: e?.musica_sonido || "",
                _k: clave(),
            }));
            this.g = {
                ...c,
                titulo: c.titulo || "",
                gancho: c.gancho || "",
                formato: c.formato || this.limites.formato,
                escenas,
                musica: { ambiente: c.musica?.ambiente || "sin_musica" },
                voz: { id: c.voz?.id || "nova" },
                cta_final: { texto: c.cta_final?.texto || "" },
            };
            this.error = "";
            this.errores = {};
            this.mostrarErrores = false;
        },
        topeDe(e) {
            return topeTextoPantalla(e?.visual?.tipo);
        },
        alCambiarTipo(e, tipo) {
            if (tipo !== "broll") e.visual.categoria_broll = null;
            else if (!e.visual.categoria_broll && this.opcionesBroll.length) e.visual.categoria_broll = this.opcionesBroll[0].value;
            if (tipo === "logo") e.texto_pantalla = "";
        },
        mover(i, delta) {
            const j = i + delta;
            if (j < 0 || j >= this.g.escenas.length) return;
            const lista = [...this.g.escenas];
            [lista[i], lista[j]] = [lista[j], lista[i]];
            this.g.escenas = lista;
        },
        quitar(i) {
            this.g.escenas = this.g.escenas.filter((_, k) => k !== i);
        },
        agregar() {
            const nueva = { ...escenaNueva("texto"), _k: clave() };
            const lista = [...this.g.escenas];
            const ultimaEsCierre = lista.length && lista[lista.length - 1]?.visual?.tipo === "cta";
            lista.splice(ultimaEsCierre ? lista.length - 1 : lista.length, 0, nueva);
            this.g.escenas = lista;
        },
        /** Error 422 del backend: "contenido.titulo", "contenido.escenas.2.texto_pantalla", … */
        errCampo(c) {
            const k = Object.keys(this.errores).find((x) => x === `contenido.${c}` || x.startsWith(`contenido.${c}.`));
            return k ? this.errores[k] : "";
        },
        errEscena(i) {
            const p = `contenido.escenas.${i}.`;
            return Object.entries(this.errores).filter(([k]) => k.startsWith(p)).map(([, v]) => v);
        },
        mensajesEscena(i) {
            const locales = this.mostrarErrores ? this.revision.porEscena[i] || [] : [];
            return [...new Set([...locales, ...this.errEscena(i)])];
        },
        contenidoParaGuardar() {
            const escenas = this.g.escenas.map((e, i) => {
                const { _k, ...resto } = e;
                return {
                    ...resto,
                    numero: i + 1,
                    duracion_s: Number.parseInt(e.duracion_s, 10) || 0,
                    visual: { tipo: e.visual.tipo, categoria_broll: e.visual.tipo === "broll" ? e.visual.categoria_broll : null },
                    texto_pantalla: e.visual.tipo === "logo" ? "" : String(e.texto_pantalla || "").trim(),
                    voz_en_off: String(e.voz_en_off || "").trim(),
                    toma: String(e.toma || "").trim(),
                    musica_sonido: String(e.musica_sonido || "").trim(),
                };
            });
            return {
                ...this.g,
                titulo: this.g.titulo.trim(),
                gancho: this.g.gancho.trim(),
                escenas,
                cta_final: { texto: this.g.cta_final.texto.trim() },
                duracion_total_s: this.revision.total,
            };
        },
        async guardar() {
            if (this.guardando || !this.g) return;
            this.mostrarErrores = true;
            this.errores = {};
            this.error = "";
            if (this.revision.errores.length) return;
            this.guardando = true;
            try {
                const { data } = await axios.put(`/api/marketing/campanas-360/${this.campanaId}/piezas/${this.pieza.id}`, {
                    contenido: this.contenidoParaGuardar(),
                });
                this.$emit("guardada", data?.data || null);
                this.$emit("update:modelValue", false);
            } catch (e) {
                this.errores = erroresPorCampo(e);
                this.error = mensajeDeError(e, "No se pudo guardar el guion.");
            } finally {
                this.guardando = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-editor { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-editor__fila2 { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(2, minmax(0, 1fr)); }
.mkt-editor__fila3 { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(3, minmax(0, 1fr)); }
.mkt-editor__resumen {
    display: flex;
    flex-wrap: wrap;
    gap: var(--mkt-esp-1) var(--mkt-esp-3);
    padding: 8px 12px;
    font-size: 14px;
    font-weight: 500;
    border-radius: var(--mkt-radio);
    background: color-mix(in srgb, var(--mkt-exito) 10%, var(--mkt-fondo-tarjeta));
    border: 1px solid color-mix(in srgb, var(--mkt-exito) 35%, transparent);
}
.mkt-editor__resumen span { display: inline-flex; align-items: center; gap: 4px; }
.mkt-editor__resumen small { color: var(--mkt-texto-suave); font-weight: 400; }
.mkt-editor__resumen--mal {
    background: color-mix(in srgb, var(--mkt-peligro) 10%, var(--mkt-fondo-tarjeta));
    border-color: color-mix(in srgb, var(--mkt-peligro) 40%, transparent);
}
.mkt-editor__escenas { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-editor__escena {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-1);
    padding: 12px;
    border: 1px solid var(--mkt-borde);
    border-radius: var(--mkt-radio);
    background: var(--mkt-fondo-tarjeta);
}
.mkt-editor__escena--mal { border-color: color-mix(in srgb, var(--mkt-peligro) 60%, transparent); }
.mkt-editor__escena-cabeza { display: flex; align-items: center; justify-content: space-between; }
.mkt-editor__num { font-size: 13px; font-weight: 600; color: var(--mkt-primario); }
.mkt-editor__escena-acciones { display: inline-flex; gap: 2px; }
.mkt-editor__campos { display: grid; gap: var(--mkt-esp-1); grid-template-columns: 120px minmax(0, 1fr) minmax(0, 1fr); }
.mkt-editor__errores { margin: 0; padding-left: 18px; font-size: 13px; color: var(--mkt-peligro); }
.mkt-editor__avisos { list-style: none; margin: 0; padding: 0; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-editor__avisos li { display: flex; align-items: center; gap: 4px; }
.mkt-editor__agregar { align-self: flex-start; }
.mkt-editor__banner { background: color-mix(in srgb, var(--mkt-peligro) 10%, var(--mkt-fondo-tarjeta)); color: var(--mkt-texto); font-size: 14px; }
.mkt-editor__banner ul { margin: 4px 0 0; padding-left: 18px; }
.mkt-editor__opcion { --bs-gutter-x: 0; }
.mkt-editor__opcion > * { width: auto; max-width: none; margin-top: 0; }

@media (max-width: 767px) {
    .mkt-editor__fila2, .mkt-editor__fila3 { grid-template-columns: minmax(0, 1fr); }
    .mkt-editor__campos { grid-template-columns: 110px minmax(0, 1fr); }
    .mkt-editor__broll { grid-column: 1 / -1; }
    .mkt-editor__agregar { align-self: stretch; }
}
</style>
