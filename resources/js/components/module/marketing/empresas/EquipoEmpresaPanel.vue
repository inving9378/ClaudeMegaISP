<template>
    <mkt-tarjeta-panel
        :titulo="`Equipo de ${empresa.nombre}`"
        :subtitulo="subtitulo"
    >
        <template v-if="puedeEditar && estado !== 'error'" #acciones>
            <q-btn unelevated no-caps color="primary" icon="person_add" label="Agregar persona" @click="dialogo = true" />
        </template>

        <q-banner v-if="avisoFila" dense rounded class="mkt-equipo__aviso" role="alert">
            <template #avatar><q-icon name="error_outline" /></template>
            {{ avisoFila }}
            <template #action>
                <q-btn flat dense no-caps label="Entendido" @click="avisoFila = ''" />
            </template>
        </q-banner>

        <mkt-estado-vacio
            v-if="estado === 'error'"
            icono="cloud_off"
            titulo="No se pudo cargar el equipo"
            :texto="mensajeError"
        >
            <template #accion>
                <q-btn outline no-caps color="primary" icon="refresh" label="Reintentar" @click="cargar" />
            </template>
        </mkt-estado-vacio>

        <mkt-tabla-ejecutiva
            v-else
            :columnas="columnas"
            :filas="miembros"
            fila-key="user_id"
            :cargando="estado === 'cargando'"
            vacio-icono="group_add"
            vacio-titulo="Esta empresa aún no tiene equipo"
            vacio-texto="Agrega a las personas que trabajarán en ella y elige su rol: Administrador, Marketing o Ventas."
        >
            <template v-if="puedeEditar" #vacio-accion>
                <q-btn unelevated no-caps color="primary" icon="person_add" label="Agregar persona" @click="dialogo = true" />
            </template>

            <template #body-cell-nombre="props">
                <q-td :props="props" :class="{ 'mkt-equipo__inactiva': !props.row.activo }">
                    <div class="mkt-equipo__persona">
                        <span class="mkt-equipo__nombre">{{ props.row.nombre }}</span>
                        <span v-if="props.row.user_id === authUserId" class="mkt-equipo__tu">Tú</span>
                    </div>
                    <div class="mkt-equipo__dato">{{ props.row.login_user }}</div>
                </q-td>
            </template>

            <template #body-cell-email="props">
                <q-td :props="props" :class="{ 'mkt-equipo__inactiva': !props.row.activo }">
                    <span class="mkt-equipo__dato">{{ props.row.email || "—" }}</span>
                </q-td>
            </template>

            <template #body-cell-rol="props">
                <q-td :props="props">
                    <q-select
                        v-if="puedeEditar"
                        :model-value="props.row.rol"
                        :options="roles"
                        emit-value
                        map-options
                        borderless
                        dense
                        options-dense
                        :disable="!props.row.activo || !!guardando[props.row.user_id]"
                        :loading="guardando[props.row.user_id] === 'rol'"
                        :aria-label="`Rol de ${props.row.nombre}`"
                        class="mkt-equipo__rol"
                        @update:model-value="(r) => actualizar(props.row, { rol: r }, 'rol')"
                    >
                        <template #selected>
                            <mkt-insignia-estado :texto="etiquetaRol(props.row.rol)" :tono="tonoRol(props.row.rol)" />
                        </template>
                        <template #option="scope">
                            <q-item v-bind="scope.itemProps" class="mkt-equipo__opcion">
                                <q-item-section>
                                    <q-item-label>{{ scope.opt.label }}</q-item-label>
                                    <q-item-label caption>{{ scope.opt.descripcion }}</q-item-label>
                                </q-item-section>
                            </q-item>
                        </template>
                    </q-select>
                    <mkt-insignia-estado v-else :texto="etiquetaRol(props.row.rol)" :tono="tonoRol(props.row.rol)" />
                </q-td>
            </template>

            <template #body-cell-activo="props">
                <q-td :props="props">
                    <div class="mkt-equipo__activo">
                        <q-toggle
                            v-if="puedeEditar"
                            :model-value="props.row.activo"
                            color="positive"
                            dense
                            :disable="!!guardando[props.row.user_id]"
                            :aria-label="`${props.row.activo ? 'Quitar' : 'Reactivar'} a ${props.row.nombre}`"
                            @update:model-value="(v) => actualizar(props.row, { activo: v }, 'activo')"
                        />
                        <mkt-insignia-estado
                            :texto="props.row.activo ? 'Activo' : 'Inactivo'"
                            :tono="props.row.activo ? 'exito' : 'neutro'"
                        />
                        <q-spinner v-if="guardando[props.row.user_id] === 'activo'" size="16px" color="primary" />
                    </div>
                </q-td>
            </template>

            <template #body-cell-asignado_at="props">
                <q-td :props="props" :class="{ 'mkt-equipo__inactiva': !props.row.activo }">
                    <span class="mkt-equipo__dato">{{ formatoFecha(props.row.asignado_at) }}</span>
                </q-td>
            </template>
        </mkt-tabla-ejecutiva>

        <asignar-usuario-dialog v-model="dialogo" :empresa="empresa" @asignado="alAsignar" />
    </mkt-tarjeta-panel>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import {
    MktTarjetaPanel,
    MktTablaEjecutiva,
    MktEstadoVacio,
    MktInsigniaEstado,
    ROLES_EMPRESA,
    etiquetaRol,
    tonoRol,
    formatoFecha,
    mensajeDeError,
} from "../kit";
import AsignarUsuarioDialog from "./AsignarUsuarioDialog.vue";

export default {
    name: "EquipoEmpresaPanel",
    components: { MktTarjetaPanel, MktTablaEjecutiva, MktEstadoVacio, MktInsigniaEstado, AsignarUsuarioDialog },
    props: {
        empresa: { type: Object, required: true },
        authUserId: { type: Number, default: null },
        puedeEditar: { type: Boolean, default: true },
    },
    emits: ["cambio"],
    data() {
        return {
            estado: "cargando", // cargando | listo | error
            miembros: [],
            mensajeError: "",
            avisoFila: "",
            guardando: {}, // user_id → 'rol' | 'activo'
            dialogo: false,
            roles: ROLES_EMPRESA,
            columnas: [
                { name: "nombre", label: "Persona", field: "nombre", align: "left", sortable: true },
                { name: "email", label: "Correo", field: "email", align: "left", ocultarEnMovil: true },
                { name: "rol", label: "Rol", field: "rol", align: "left" },
                { name: "activo", label: "Estado", field: "activo", align: "left" },
                { name: "asignado_at", label: "Desde", field: "asignado_at", align: "left", sortable: true, ocultarEnMovil: true },
            ],
        };
    },
    computed: {
        subtitulo() {
            if (this.estado !== "listo") return "Quién trabaja en esta empresa y con qué rol";
            const activos = this.miembros.filter((m) => m.activo).length;
            const admins = this.miembros.filter((m) => m.activo && m.rol === "admin").length;
            return `${activos} ${activos === 1 ? "persona activa" : "personas activas"} · ${admins} ${admins === 1 ? "administrador" : "administradores"}`;
        },
    },
    watch: {
        "empresa.id"() {
            this.avisoFila = "";
            this.cargar();
        },
    },
    mounted() {
        this.cargar();
    },
    methods: {
        etiquetaRol,
        tonoRol,
        formatoFecha,
        async cargar() {
            const id = this.empresa.id;
            this.estado = "cargando";
            try {
                const { data } = await axios.get(`/api/marketing/empresas/${id}/usuarios`);
                if (id !== this.empresa.id) return; // se cambió de empresa mientras cargaba
                this.miembros = Array.isArray(data?.data) ? data.data : [];
                this.estado = "listo";
            } catch (e) {
                if (id !== this.empresa.id) return;
                this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
                this.estado = "error";
            }
        },
        async actualizar(fila, cambios, tipo) {
            if (this.guardando[fila.user_id]) return;
            this.avisoFila = "";
            this.guardando = { ...this.guardando, [fila.user_id]: tipo };
            try {
                const { data } = await axios.put(
                    `/api/marketing/empresas/${this.empresa.id}/usuarios/${fila.user_id}`,
                    cambios
                );
                const nueva = data?.data ? data.data : { ...fila, ...cambios };
                this.miembros = this.miembros.map((m) => (m.user_id === fila.user_id ? { ...m, ...nueva } : m));
                message(
                    tipo === "rol"
                        ? `${fila.nombre} ahora es ${etiquetaRol(nueva.rol)}.`
                        : `${fila.nombre} ${nueva.activo ? "vuelve a estar activo" : "ya no está en el equipo"}.`
                );
                this.$emit("cambio");
            } catch (e) {
                // La fila no cambia (el control usa :model-value, no v-model): se muestra el porqué.
                this.avisoFila = mensajeDeError(e, "No se pudo guardar el cambio.");
            } finally {
                const { [fila.user_id]: _omit, ...resto } = this.guardando;
                this.guardando = resto;
            }
        },
        alAsignar(miembro, { reactivado } = {}) {
            message(
                miembro?.nombre
                    ? `${miembro.nombre} ${reactivado ? "se reactivó" : "se agregó"} como ${etiquetaRol(miembro.rol)}.`
                    : "Persona agregada."
            );
            this.cargar();
            this.$emit("cambio");
        },
    },
};
</script>

<style scoped>
.mkt-equipo__aviso {
    margin-bottom: 16px;
    color: var(--mkt-peligro, #fd625e);
    background: color-mix(in srgb, var(--mkt-peligro, #fd625e) 10%, transparent);
}
.mkt-equipo__persona { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.mkt-equipo__nombre { font-weight: 600; color: var(--mkt-texto, #343a40); }
.mkt-equipo__tu {
    font-size: 11px;
    font-weight: 600;
    padding: 0 6px;
    border-radius: 4px;
    color: var(--mkt-marca, #0066cc);
    border: 1px solid currentColor;
    line-height: 16px;
}
.mkt-equipo__dato { font-size: 13px; color: var(--mkt-texto-suave, #74788d); }
.mkt-equipo__rol { min-width: 150px; }
.mkt-equipo__activo { display: flex; align-items: center; gap: 8px; }
.mkt-equipo__opcion { --bs-gutter-x: 0; --bs-gutter-y: 0; }
.mkt-equipo__opcion > * { width: auto; max-width: none; margin-top: 0; }
.mkt-equipo__inactiva { opacity: 0.55; }
</style>
