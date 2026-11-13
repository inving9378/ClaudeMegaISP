<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="(v) => $emit('update:modelValue', v)">
        <q-card class="mkt-dlg-card">
            <mkt-raiz :color-marca="form.color_primario" class="mkt-dlg">
                <form novalidate @submit.prevent="guardar">
                    <header class="mkt-dlg__cabeza">
                        <h2 class="mkt-dlg__titulo">{{ esEdicion ? "Editar empresa" : "Nueva empresa" }}</h2>
                        <q-btn flat round dense icon="close" aria-label="Cerrar" :disable="guardando" @click="cerrar" />
                    </header>

                    <div class="mkt-dlg__cuerpo">
                        <q-banner v-if="errorGeneral" dense rounded class="mkt-dlg__aviso" role="alert">
                            <template #avatar><q-icon name="error_outline" /></template>
                            {{ errorGeneral }}
                        </q-banner>

                        <q-input
                            v-model="form.nombre"
                            label="Nombre *"
                            outlined
                            dense
                            maxlength="120"
                            autofocus
                            :error="!!errores.nombre"
                            :error-message="errores.nombre"
                            @update:model-value="alCambiarNombre"
                        />
                        <q-input
                            v-model="form.slug"
                            label="Identificador (slug)"
                            outlined
                            dense
                            maxlength="60"
                            hint="Minúsculas, números y guiones. Se usa en direcciones y reportes."
                            :error="!!errores.slug"
                            :error-message="errores.slug"
                            @update:model-value="slugTocado = true"
                        />

                        <div class="mkt-dlg__colores">
                            <q-input
                                v-for="campo in camposColor"
                                :key="campo.key"
                                v-model="form[campo.key]"
                                :label="campo.label"
                                outlined
                                dense
                                clearable
                                maxlength="7"
                                placeholder="#RRGGBB"
                                :error="!!errores[campo.key]"
                                :error-message="errores[campo.key]"
                            >
                                <template #prepend>
                                    <span
                                        class="mkt-dlg__muestra"
                                        :class="{ 'mkt-dlg__muestra--vacia': !colorValido(form[campo.key]) }"
                                        :style="{ background: colorValido(form[campo.key]) ? form[campo.key] : 'transparent' }"
                                        aria-hidden="true"
                                    ></span>
                                </template>
                                <template #append>
                                    <input
                                        type="color"
                                        class="mkt-dlg__picker"
                                        :value="colorValido(form[campo.key]) ? form[campo.key] : campo.defecto"
                                        :aria-label="`Elegir ${campo.label.toLowerCase()}`"
                                        @input="form[campo.key] = $event.target.value.toUpperCase()"
                                    />
                                </template>
                            </q-input>
                        </div>

                        <div v-if="esEdicion" class="mkt-dlg__activo">
                            <q-toggle
                                v-model="form.activo"
                                label="Empresa activa"
                                color="positive"
                                :disable="esPrincipal"
                            />
                            <p class="mkt-dlg__nota">
                                {{ esPrincipal
                                    ? "Meganet es la empresa principal y no se puede desactivar."
                                    : "Si la desactivas, nadie podrá trabajar en ella hasta reactivarla. No se borra nada." }}
                            </p>
                        </div>
                    </div>

                    <footer class="mkt-dlg__pie">
                        <q-btn flat no-caps label="Cancelar" :disable="guardando" @click="cerrar" />
                        <q-btn
                            type="submit"
                            unelevated
                            no-caps
                            color="primary"
                            :label="esEdicion ? 'Guardar cambios' : 'Crear empresa'"
                            :loading="guardando"
                        />
                    </footer>
                </form>
            </mkt-raiz>
        </q-card>
    </q-dialog>
</template>

<script>
import axios from "axios";
import { MktRaiz, esColorHex, mensajeDeError } from "../kit";

const SLUG_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export function aSlug(texto) {
    return String(texto || "")
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .slice(0, 60)
        .replace(/-+$/g, "");
}

const vacio = () => ({ nombre: "", slug: "", color_primario: null, color_secundario: null, activo: true });

export default {
    name: "EmpresaFormDialog",
    components: { MktRaiz },
    props: {
        modelValue: { type: Boolean, default: false },
        /** null = crear; objeto (forma de la ruta 4) = editar */
        empresa: { type: Object, default: null },
    },
    emits: ["update:modelValue", "guardada"],
    data() {
        return {
            form: vacio(),
            errores: {},
            errorGeneral: "",
            guardando: false,
            slugTocado: false,
            camposColor: [
                { key: "color_primario", label: "Color primario", defecto: "#0066CC" },
                { key: "color_secundario", label: "Color secundario", defecto: "#FF6600" },
            ],
        };
    },
    computed: {
        esEdicion() {
            return !!this.empresa?.id;
        },
        esPrincipal() {
            return this.empresa?.id === 1 || this.empresa?.es_principal === true;
        },
    },
    watch: {
        modelValue(abierto) {
            if (abierto) this.reiniciar();
        },
    },
    methods: {
        colorValido: esColorHex,
        reiniciar() {
            this.errores = {};
            this.errorGeneral = "";
            this.guardando = false;
            this.slugTocado = this.esEdicion;
            this.form = this.esEdicion
                ? {
                      nombre: this.empresa.nombre || "",
                      slug: this.empresa.slug || "",
                      color_primario: this.empresa.color_primario || null,
                      color_secundario: this.empresa.color_secundario || null,
                      activo: this.empresa.activo !== false,
                  }
                : vacio();
        },
        alCambiarNombre(v) {
            if (!this.slugTocado) this.form.slug = aSlug(v);
        },
        cerrar() {
            if (!this.guardando) this.$emit("update:modelValue", false);
        },
        normalizar() {
            const f = { ...this.form };
            f.nombre = (f.nombre || "").trim();
            f.slug = (f.slug || "").trim();
            ["color_primario", "color_secundario"].forEach((k) => {
                f[k] = f[k] ? String(f[k]).trim() : null;
                if (f[k] === "") f[k] = null;
            });
            return f;
        },
        validar(f) {
            const e = {};
            if (f.nombre.length < 2) e.nombre = "Escribe un nombre de al menos 2 caracteres.";
            else if (f.nombre.length > 120) e.nombre = "El nombre no puede pasar de 120 caracteres.";
            if (f.slug && (!SLUG_RE.test(f.slug) || f.slug.length < 2 || f.slug.length > 60)) {
                e.slug = "Usa de 2 a 60 caracteres: minúsculas, números y guiones (sin guion al inicio o al final).";
            }
            ["color_primario", "color_secundario"].forEach((k) => {
                if (f[k] && !esColorHex(f[k])) e[k] = "Usa el formato #RRGGBB (por ejemplo #0066CC).";
            });
            return e;
        },
        cuerpo(f) {
            if (!this.esEdicion) {
                const body = { nombre: f.nombre, color_primario: f.color_primario, color_secundario: f.color_secundario };
                if (f.slug) body.slug = f.slug;
                return body;
            }
            // Edición: solo lo que cambió (el contrato acepta cualquier subconjunto).
            const body = {};
            ["nombre", "slug", "color_primario", "color_secundario"].forEach((k) => {
                if ((f[k] || null) !== (this.empresa[k] || null)) body[k] = f[k];
            });
            if (!this.esPrincipal && f.activo !== (this.empresa.activo !== false)) body.activo = f.activo;
            return body;
        },
        async guardar() {
            if (this.guardando) return; // doble clic / doble Enter
            const f = this.normalizar();
            this.errores = this.validar(f);
            this.errorGeneral = "";
            if (Object.keys(this.errores).length) return;

            const body = this.cuerpo(f);
            if (this.esEdicion && !Object.keys(body).length) {
                this.$emit("update:modelValue", false);
                return;
            }

            this.guardando = true;
            try {
                const { data } = this.esEdicion
                    ? await axios.put(`/api/marketing/empresas/${this.empresa.id}`, body)
                    : await axios.post("/api/marketing/empresas", body);
                this.guardando = false;
                this.$emit("guardada", data?.data || null, { creada: !this.esEdicion });
                this.$emit("update:modelValue", false);
            } catch (e) {
                const d = e?.response?.data || {};
                if (e?.response?.status === 422 && d.errors) {
                    const porCampo = {};
                    Object.entries(d.errors).forEach(([k, v]) => {
                        porCampo[k] = Array.isArray(v) ? v[0] : String(v);
                    });
                    this.errores = porCampo;
                    const conocidos = ["nombre", "slug", "color_primario", "color_secundario"];
                    const otros = Object.keys(porCampo).filter((k) => !conocidos.includes(k));
                    if (otros.length) this.errorGeneral = porCampo[otros[0]];
                } else {
                    this.errorGeneral = mensajeDeError(e, "No se pudo guardar la empresa.");
                }
                this.guardando = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-dlg-card {
    width: 560px;
    max-width: calc(100vw - 32px);
    padding: 0;
    border-radius: var(--mkt-radio, 8px);
    overflow: hidden;
}
.mkt-dlg { background: var(--mkt-fondo-tarjeta); }
.mkt-dlg__cabeza {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 24px;
    border-bottom: 1px solid var(--mkt-borde);
    border-top: 4px solid var(--mkt-marca);
}
.mkt-dlg__titulo { margin: 0; font-size: 18px; line-height: 28px; font-weight: 600; color: var(--mkt-texto); }
.mkt-dlg__cuerpo { display: grid; gap: 16px; padding: 24px; }
.mkt-dlg__colores { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
/* Dentro de q-field__prepend/append (que son `.row`): selector más específico que el arreglo `.row > *` de MktRaiz. */
.mkt-dlg-card .q-field__prepend > .mkt-dlg__muestra,
.mkt-dlg-card .q-field__append > .mkt-dlg__picker { flex: 0 0 auto; }
.mkt-dlg-card .q-field__prepend > .mkt-dlg__muestra { width: 18px; min-width: 18px; }
.mkt-dlg-card .q-field__append > .mkt-dlg__picker { width: 32px; min-width: 32px; }
.mkt-dlg__muestra {
    display: inline-block;
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 1px solid var(--mkt-borde);
}
.mkt-dlg__muestra--vacia {
    background-image: linear-gradient(45deg, transparent 45%, var(--mkt-peligro) 45%, var(--mkt-peligro) 55%, transparent 55%) !important;
}
.mkt-dlg__picker {
    width: 28px;
    height: 28px;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
}
.mkt-dlg__activo { display: grid; gap: 4px; }
.mkt-dlg__nota { margin: 0 0 0 8px; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-dlg__aviso {
    color: var(--mkt-peligro);
    background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent);
}
.mkt-dlg__pie {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 16px 24px;
    border-top: 1px solid var(--mkt-borde);
}
@media (max-width: 599px) {
    .mkt-dlg__cabeza, .mkt-dlg__pie { padding-left: 16px; padding-right: 16px; }
    .mkt-dlg__cuerpo { padding: 16px; }
    .mkt-dlg__colores { grid-template-columns: minmax(0, 1fr); }
    .mkt-dlg__pie :deep(.q-btn) { flex: 1 1 auto; }
}
</style>
