<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="(v) => $emit('update:modelValue', v)">
        <q-card class="mkt-dlg-card">
            <mkt-raiz :color-marca="empresa?.color_primario" class="mkt-dlg">
                <form novalidate @submit.prevent="asignar">
                    <header class="mkt-dlg__cabeza">
                        <div class="mkt-dlg__titulos">
                            <h2 class="mkt-dlg__titulo">Agregar persona</h2>
                            <p class="mkt-dlg__subtitulo">a {{ empresa?.nombre }}</p>
                        </div>
                        <q-btn flat round dense icon="close" aria-label="Cerrar" :disable="guardando" @click="cerrar" />
                    </header>

                    <div class="mkt-dlg__cuerpo">
                        <q-banner v-if="errorGeneral" dense rounded class="mkt-dlg__aviso" role="alert">
                            <template #avatar><q-icon name="error_outline" /></template>
                            {{ errorGeneral }}
                        </q-banner>

                        <!-- Persona elegida -->
                        <div v-if="elegida" class="mkt-dlg__elegida">
                            <span class="mkt-dlg__avatar" aria-hidden="true">{{ iniciales(elegida.nombre) }}</span>
                            <div class="mkt-dlg__persona">
                                <div class="mkt-dlg__persona-nombre">{{ elegida.nombre }}</div>
                                <div class="mkt-dlg__persona-dato">{{ elegida.login_user }} · {{ elegida.email || "sin correo" }}</div>
                            </div>
                            <q-btn flat dense no-caps color="primary" label="Cambiar" :disable="guardando" @click="elegida = null" />
                        </div>

                        <!-- Búsqueda -->
                        <template v-else>
                            <q-input
                                v-model="buscar"
                                label="Buscar por nombre, usuario o correo"
                                outlined
                                dense
                                autofocus
                                maxlength="60"
                                :loading="buscando"
                                :error="!!errores.buscar"
                                :error-message="errores.buscar"
                                :hint="buscar.trim().length < 2 ? 'Escribe al menos 2 letras.' : ''"
                                @update:model-value="programarBusqueda"
                            >
                                <template #prepend><q-icon name="search" /></template>
                            </q-input>

                            <div class="mkt-dlg__resultados" aria-live="polite">
                                <p v-if="estadoBusqueda === 'error'" class="mkt-dlg__nota mkt-dlg__nota--error">
                                    {{ mensajeBusqueda }}
                                </p>
                                <p v-else-if="estadoBusqueda === 'vacio'" class="mkt-dlg__nota">
                                    Nadie disponible con «{{ ultimaBusqueda }}». Solo aparecen personas activas del
                                    sistema que todavía no están en esta empresa.
                                </p>
                                <ul v-else-if="resultados.length" class="mkt-dlg__lista" role="listbox" aria-label="Personas encontradas">
                                    <li v-for="p in resultados" :key="p.id" role="option" aria-selected="false">
                                        <button type="button" class="mkt-dlg__resultado" @click="elegir(p)">
                                            <span class="mkt-dlg__avatar" aria-hidden="true">{{ iniciales(p.nombre) }}</span>
                                            <span class="mkt-dlg__persona">
                                                <span class="mkt-dlg__persona-nombre">{{ p.nombre }}</span>
                                                <span class="mkt-dlg__persona-dato">{{ p.login_user }} · {{ p.email || "sin correo" }}</span>
                                            </span>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </template>

                        <q-select
                            v-model="rol"
                            :options="roles"
                            emit-value
                            map-options
                            outlined
                            dense
                            label="Rol en esta empresa *"
                            :error="!!errores.rol"
                            :error-message="errores.rol"
                        >
                            <template #option="scope">
                                <q-item v-bind="scope.itemProps" class="mkt-dlg__opcion">
                                    <q-item-section>
                                        <q-item-label>{{ scope.opt.label }}</q-item-label>
                                        <q-item-label caption>{{ scope.opt.descripcion }}</q-item-label>
                                    </q-item-section>
                                </q-item>
                            </template>
                        </q-select>
                        <p v-if="errores.user_id" class="mkt-dlg__nota mkt-dlg__nota--error" role="alert">{{ errores.user_id }}</p>
                    </div>

                    <footer class="mkt-dlg__pie">
                        <q-btn flat no-caps label="Cancelar" :disable="guardando" @click="cerrar" />
                        <q-btn
                            type="submit"
                            unelevated
                            no-caps
                            color="primary"
                            label="Agregar"
                            :disable="!elegida"
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
import { MktRaiz, ROLES_EMPRESA, mensajeDeError } from "../kit";

export default {
    name: "AsignarUsuarioDialog",
    components: { MktRaiz },
    props: {
        modelValue: { type: Boolean, default: false },
        empresa: { type: Object, default: null },
    },
    emits: ["update:modelValue", "asignado"],
    data() {
        return {
            buscar: "",
            ultimaBusqueda: "",
            resultados: [],
            buscando: false,
            estadoBusqueda: "inicial", // inicial | listo | vacio | error
            mensajeBusqueda: "",
            elegida: null,
            rol: "ventas", // mínimo privilegio por defecto
            roles: ROLES_EMPRESA,
            errores: {},
            errorGeneral: "",
            guardando: false,
            temporizador: null,
            turno: 0,
        };
    },
    watch: {
        modelValue(abierto) {
            if (abierto) this.reiniciar();
        },
    },
    beforeUnmount() {
        clearTimeout(this.temporizador);
    },
    methods: {
        reiniciar() {
            clearTimeout(this.temporizador);
            this.turno++;
            Object.assign(this, {
                buscar: "",
                ultimaBusqueda: "",
                resultados: [],
                buscando: false,
                estadoBusqueda: "inicial",
                mensajeBusqueda: "",
                elegida: null,
                rol: "ventas",
                errores: {},
                errorGeneral: "",
                guardando: false,
            });
        },
        iniciales(nombre) {
            return String(nombre || "?")
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((p) => p.charAt(0).toUpperCase())
                .join("");
        },
        cerrar() {
            if (!this.guardando) this.$emit("update:modelValue", false);
        },
        programarBusqueda() {
            clearTimeout(this.temporizador);
            this.errores = { ...this.errores, buscar: "" };
            const q = this.buscar.trim();
            if (q.length < 2) {
                this.turno++; // descarta respuestas en vuelo
                this.resultados = [];
                this.estadoBusqueda = "inicial";
                this.buscando = false;
                return;
            }
            this.temporizador = setTimeout(() => this.ejecutarBusqueda(q), 350);
        },
        async ejecutarBusqueda(q) {
            const miTurno = ++this.turno;
            this.buscando = true;
            try {
                const { data } = await axios.get(`/api/marketing/empresas/${this.empresa.id}/usuarios-disponibles`, {
                    params: { buscar: q },
                });
                if (miTurno !== this.turno) return; // llegó tarde: ya se escribió otra cosa
                this.resultados = Array.isArray(data?.data) ? data.data : [];
                this.ultimaBusqueda = q;
                this.estadoBusqueda = this.resultados.length ? "listo" : "vacio";
            } catch (e) {
                if (miTurno !== this.turno) return;
                this.resultados = [];
                if (e?.response?.status === 422 && e.response.data?.errors?.buscar) {
                    this.errores = { ...this.errores, buscar: e.response.data.errors.buscar[0] };
                    this.estadoBusqueda = "inicial";
                } else {
                    this.mensajeBusqueda = mensajeDeError(e, "No se pudo buscar. Intenta de nuevo.");
                    this.estadoBusqueda = "error";
                }
            } finally {
                if (miTurno === this.turno) this.buscando = false;
            }
        },
        elegir(p) {
            this.elegida = p;
            this.errores = { ...this.errores, user_id: "" };
            this.errorGeneral = "";
        },
        async asignar() {
            if (this.guardando || !this.elegida) return;
            this.guardando = true;
            this.errores = {};
            this.errorGeneral = "";
            try {
                const { data, status } = await axios.post(`/api/marketing/empresas/${this.empresa.id}/usuarios`, {
                    user_id: this.elegida.id,
                    rol: this.rol,
                });
                this.guardando = false;
                this.$emit("asignado", data?.data || null, { reactivado: status === 200 });
                this.$emit("update:modelValue", false);
            } catch (e) {
                const d = e?.response?.data || {};
                if (e?.response?.status === 422 && d.errors) {
                    const porCampo = {};
                    Object.entries(d.errors).forEach(([k, v]) => (porCampo[k] = Array.isArray(v) ? v[0] : String(v)));
                    this.errores = porCampo;
                    if (!porCampo.user_id && !porCampo.rol) this.errorGeneral = Object.values(porCampo)[0];
                } else {
                    this.errorGeneral = mensajeDeError(e, "No se pudo agregar a la persona.");
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
.mkt-dlg__titulos { min-width: 0; }
.mkt-dlg__titulo { margin: 0; font-size: 18px; line-height: 28px; font-weight: 600; color: var(--mkt-texto); }
.mkt-dlg__subtitulo {
    margin: 0;
    font-size: 13px;
    color: var(--mkt-texto-suave);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mkt-dlg__cuerpo { display: grid; gap: 16px; padding: 24px; }
.mkt-dlg__aviso { color: var(--mkt-peligro); background: color-mix(in srgb, var(--mkt-peligro) 10%, transparent); }
.mkt-dlg__resultados { min-height: 24px; }
.mkt-dlg__lista {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 264px;
    overflow-y: auto;
    border: 1px solid var(--mkt-borde);
    border-radius: var(--mkt-radio);
}
.mkt-dlg__lista li + li { border-top: 1px solid var(--mkt-borde); }
.mkt-dlg__resultado {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 8px 16px;
    border: 0;
    background: transparent;
    color: inherit;
    text-align: left;
    cursor: pointer;
    font: inherit;
}
.mkt-dlg__resultado:hover,
.mkt-dlg__resultado:focus-visible {
    background: color-mix(in srgb, var(--mkt-primario) 8%, transparent);
    outline: none;
}
.mkt-dlg__elegida {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 1px solid color-mix(in srgb, var(--mkt-primario) 40%, transparent);
    border-radius: var(--mkt-radio);
    background: color-mix(in srgb, var(--mkt-primario) 6%, transparent);
}
.mkt-dlg__avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    border-radius: 50%;
    font-size: 13px;
    font-weight: 600;
    color: var(--mkt-primario);
    background: color-mix(in srgb, var(--mkt-primario) 14%, transparent);
}
.mkt-dlg__persona { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
.mkt-dlg__persona-nombre { font-weight: 600; font-size: 14px; color: var(--mkt-texto); overflow-wrap: anywhere; }
.mkt-dlg__persona-dato { font-size: 13px; color: var(--mkt-texto-suave); overflow-wrap: anywhere; }
.mkt-dlg__nota { margin: 0; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-dlg__nota--error { color: var(--mkt-peligro); }
/* Opciones del q-select: se pintan fuera de la raíz → arreglo de Bootstrap `.row > *` aquí. */
.mkt-dlg__opcion { --bs-gutter-x: 0; --bs-gutter-y: 0; }
.mkt-dlg__opcion > * { width: auto; max-width: none; margin-top: 0; }
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
    .mkt-dlg__pie :deep(.q-btn) { flex: 1 1 auto; }
}
</style>
