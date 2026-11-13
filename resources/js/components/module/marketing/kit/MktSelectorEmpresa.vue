<template>
    <div class="mkt-selector">
        <!-- Cargando -->
        <q-skeleton v-if="estado === 'cargando'" type="QChip" width="160px" height="36px" />

        <!-- Error -->
        <div v-else-if="estado === 'error'" class="mkt-selector__error" role="alert">
            <q-icon name="error_outline" size="18px" aria-hidden="true" />
            <span>{{ mensajeError }}</span>
            <q-btn flat dense no-caps size="sm" color="primary" label="Reintentar" @click="cargar" />
        </div>

        <!-- Una sola empresa: solo se muestra -->
        <div v-else-if="empresas.length <= 1" class="mkt-selector__fija" :title="`Empresa activa: ${activa?.nombre || ''}`">
            <span class="mkt-selector__muestra" :style="{ background: colorDe(activa) }" aria-hidden="true"></span>
            <span class="mkt-selector__nombre">{{ activa?.nombre }}</span>
            <mkt-insignia-estado v-if="activa?.rol" :texto="etiquetaRol(activa.rol)" :tono="tonoRol(activa.rol)" />
        </div>

        <!-- Varias empresas: se elige -->
        <q-select
            v-else
            :model-value="activa?.id"
            :options="empresas"
            option-value="id"
            option-label="nombre"
            emit-value
            map-options
            outlined
            dense
            options-dense
            :loading="cambiando"
            :disable="cambiando"
            class="mkt-selector__select"
            aria-label="Empresa activa"
            @update:model-value="cambiar"
        >
            <template #prepend>
                <span class="mkt-selector__muestra" :style="{ background: colorDe(activa) }" aria-hidden="true"></span>
            </template>
            <template #selected>
                <span class="mkt-selector__nombre">{{ activa?.nombre }}</span>
            </template>
            <template #option="scope">
                <q-item v-bind="scope.itemProps" class="mkt-selector__opcion">
                    <q-item-section avatar class="mkt-selector__opcion-avatar">
                        <span class="mkt-selector__muestra" :style="{ background: colorDe(scope.opt) }" aria-hidden="true"></span>
                    </q-item-section>
                    <q-item-section>
                        <q-item-label>{{ scope.opt.nombre }}</q-item-label>
                        <q-item-label caption>{{ etiquetaRol(scope.opt.rol) }}</q-item-label>
                    </q-item-section>
                </q-item>
            </template>
        </q-select>
    </div>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import MktInsigniaEstado from "./MktInsigniaEstado.vue";
import { etiquetaRol, tonoRol, mensajeDeError } from "./formato";
import { esColorHex, MARCA_MEGANET } from "./tokens";

/**
 * Selector de empresa activa (contrato 2.6 rutas 1 y 2).
 * Emite `cargado(contexto)` con la respuesta completa de GET /api/marketing/contexto
 * y `error(error)` si no se pudo leer.
 */
export default {
    name: "MktSelectorEmpresa",
    components: { MktInsigniaEstado },
    emits: ["cargado", "error"],
    data() {
        return {
            estado: "cargando",
            contexto: null,
            cambiando: false,
            mensajeError: "",
        };
    },
    computed: {
        empresas() {
            return this.contexto?.empresas || [];
        },
        activa() {
            return this.contexto?.empresa_activa || null;
        },
    },
    mounted() {
        this.cargar();
    },
    methods: {
        etiquetaRol,
        tonoRol,
        colorDe(empresa) {
            return esColorHex(empresa?.color_primario) ? empresa.color_primario : MARCA_MEGANET.primario;
        },
        async cargar() {
            this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/contexto");
                this.contexto = data;
                this.estado = "listo";
                this.$emit("cargado", data);
            } catch (e) {
                this.mensajeError = e?.response?.data?.error === "sin_empresa"
                    ? "Sin empresa asignada"
                    : "No se pudo cargar la empresa";
                this.estado = "error";
                this.$emit("error", e);
            }
        },
        async cambiar(companyId) {
            if (this.cambiando || companyId === this.activa?.id) return;
            this.cambiando = true;
            try {
                await axios.post("/api/marketing/contexto/empresa-activa", { company_id: companyId });
                // La empresa activa cambia todo lo que se ve: se recarga la pantalla completa.
                window.location.reload();
            } catch (e) {
                message(mensajeDeError(e, "No se pudo cambiar de empresa."), "error");
                this.cambiando = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-selector { display: inline-flex; align-items: center; min-width: 0; max-width: 100%; }
.mkt-selector__fija {
    display: inline-flex;
    align-items: center;
    gap: var(--mkt-esp-1, 8px);
    min-height: 36px;
    padding: 4px 12px;
    border: 1px solid var(--mkt-borde, #e9e9ef);
    border-radius: 999px;
    background: var(--mkt-fondo-tarjeta, #fff);
    max-width: 100%;
}
.mkt-selector__muestra {
    display: inline-block;
    width: 12px;
    height: 12px;
    flex: 0 0 12px;
    border-radius: 50%;
    box-shadow: 0 0 0 2px color-mix(in srgb, currentColor 12%, transparent);
}
.mkt-selector__nombre {
    font-weight: 600;
    font-size: 14px;
    color: var(--mkt-texto, #343a40);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mkt-selector__select { min-width: 220px; max-width: 320px; }
.mkt-selector__error {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--mkt-peligro, #fd625e);
}
/* Las opciones del q-select se pintan fuera de la raíz (menú flotante): arreglo de Bootstrap `.row > *` aquí. */
.mkt-selector__opcion { --bs-gutter-x: 0; --bs-gutter-y: 0; }
.mkt-selector__opcion > * { width: auto; max-width: none; margin-top: 0; }
.mkt-selector__opcion > .mkt-selector__opcion-avatar { min-width: 28px; padding-right: 8px; }
@media (max-width: 599px) {
    .mkt-selector,
    .mkt-selector__select { width: 100%; max-width: 100%; min-width: 0; }
}
</style>
