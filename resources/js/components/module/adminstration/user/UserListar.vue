<template>
    <div class="q-pa-md">
        <q-card>
            <q-card-section
                class="d-flex"
                style="justify-content: space-between"
            >
                <div class="text-h6">Listado de Administradores</div>
                <a
                    v-if="hasPermission.data.canView('user_add_user')"
                    href="/administracion/user/crear"
                    class="btn btn-success waves-effect waves-light"
                >
                    Agregar Registro
                </a>
            </q-card-section>
            <q-table
                v-table-resizable="visibleColumns"
                row-key="id"
                v-model:pagination="pagination"
                ref="tableRef"
                no-data-label="No hay elementos para mostrar"
                :rows="users"
                :columns="visibleColumns"
                :loading="loading"
                :rows-per-page-label="'Elementos por página'"
                :rows-per-page-options="rowPerPageOptions"
                :filter="filter"
                :dark="darkMode"
                binary-state-sort
                @request="getAllUsers"
            >
                <template v-slot:top="props">
                    <div class="d-flex flex-wrap align-items-center gap-2 w-100">
                        <!-- Filtros (en el servidor). La estrella guarda el valor como
                             predeterminado de ESTE usuario; otra vez, lo restablece. -->
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div
                                v-for="campo in CAMPOS_FILTRO"
                                :key="campo.nombre"
                                class="d-flex align-items-center"
                            >
                                <label
                                    :for="`admin-filtro-${campo.nombre}`"
                                    class="col-form-label me-1"
                                    >{{ campo.etiqueta }}</label
                                >
                                <select
                                    :id="`admin-filtro-${campo.nombre}`"
                                    class="form-select form-select-sm"
                                    style="width: 170px"
                                    v-model="filtros[campo.nombre]"
                                >
                                    <option
                                        v-for="opcion in campo.opciones"
                                        :key="opcion.value"
                                        :value="opcion.value"
                                    >
                                        {{ opcion.label }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-link px-1"
                                    :title="textoEstrella(campo)"
                                    :aria-label="textoEstrella(campo)"
                                    @click="alternarPredeterminado(campo)"
                                >
                                    <i
                                        :class="[
                                            'mdi',
                                            esPredeterminado(campo.nombre)
                                                ? 'mdi-star text-warning'
                                                : 'mdi-star-outline text-muted',
                                        ]"
                                        style="font-size: 18px"
                                    ></i>
                                </button>
                            </div>
                            <span
                                v-for="campo in CAMPOS_FILTRO.filter(
                                    (c) => filtros[c.nombre] !== 'todos'
                                )"
                                :key="`chip-${campo.nombre}`"
                                class="badge bg-primary d-inline-flex align-items-center"
                                style="font-size: 12px"
                            >
                                Filtrando: {{ campo.chip }}{{ etiquetaDe(campo, filtros[campo.nombre]) }}
                                <button
                                    type="button"
                                    class="btn-close btn-close-white ms-2"
                                    style="font-size: 8px"
                                    :aria-label="`Quitar el filtro de ${campo.etiqueta}`"
                                    :title="`Quitar el filtro de ${campo.etiqueta}`"
                                    @click="filtros[campo.nombre] = 'todos'"
                                ></button>
                            </span>
                        </div>

                        <div class="d-flex align-items-center ms-auto">
                            <!-- Mismo menú que el listado de Clientes -->
                            <q-btn flat round dense icon="mdi-dots-vertical" class="dt-actions-toggle"
                                :dark="darkMode" aria-label="Acciones de la tabla" style="margin-right: 4px">
                                <q-menu anchor="bottom left" self="top left" :dark="darkMode"
                                    transition-show="jump-down" transition-hide="jump-up">
                                    <q-list style="min-width: 220px">
                                        <q-item clickable v-close-popup @click="showModal = true">
                                            <q-item-section avatar><q-icon name="mdi-view-column" style="color: #85B7EB" /></q-item-section>
                                            <q-item-section>Ver columnas</q-item-section>
                                        </q-item>
                                        <q-item clickable v-close-popup @click="reloadTable">
                                            <q-item-section avatar><q-icon name="mdi-refresh" style="color: #34D399" /></q-item-section>
                                            <q-item-section>Actualizar</q-item-section>
                                        </q-item>
                                        <q-item clickable v-close-popup @click="alternarColoresEstado">
                                            <q-item-section avatar><q-icon name="mdi-palette" style="color: #ED93B1" /></q-item-section>
                                            <q-item-section>Alternar colores de estado</q-item-section>
                                        </q-item>
                                        <q-separator />
                                        <q-item-label header class="q-py-xs">Estilo de estado</q-item-label>
                                        <q-item clickable v-close-popup @click="setRowStatusStyle('underline')">
                                            <q-item-section avatar>
                                                <q-icon :name="rowStatusStyle === 'underline' ? 'mdi-check' : 'mdi-format-underline'"
                                                    :style="{ color: rowStatusStyle === 'underline' ? '#34D399' : '#85B7EB' }" />
                                            </q-item-section>
                                            <q-item-section>Subrayado</q-item-section>
                                        </q-item>
                                        <q-item clickable v-close-popup @click="setRowStatusStyle('filled')">
                                            <q-item-section avatar>
                                                <q-icon :name="rowStatusStyle === 'filled' ? 'mdi-check' : 'mdi-format-color-fill'"
                                                    :style="{ color: rowStatusStyle === 'filled' ? '#34D399' : '#ED93B1' }" />
                                            </q-item-section>
                                            <q-item-section>Relleno</q-item-section>
                                        </q-item>
                                        <q-separator />
                                        <q-item clickable v-close-popup @click="props.toggleFullscreen">
                                            <q-item-section avatar><q-icon name="mdi-fullscreen" style="color: #9B7EDE" /></q-item-section>
                                            <q-item-section>{{ props.inFullscreen ? 'Salir de pantalla completa' : 'Expandir pantalla' }}</q-item-section>
                                        </q-item>
                                        <q-item clickable v-close-popup @click="exportar">
                                            <q-item-section avatar><q-icon name="mdi-download" style="color: #EF9F27" /></q-item-section>
                                            <q-item-section>Exportar</q-item-section>
                                        </q-item>
                                    </q-list>
                                </q-menu>
                            </q-btn>
                            <q-input
                                class="dt-search mb-0"
                                dense
                                outlined
                                debounce="400"
                                placeholder="Buscar"
                                style="margin-left: 8px; margin-right: 10px"
                                v-model="filter"
                                :dark="darkMode"
                            />
                        </div>
                    </div>
                </template>

                <template v-slot:body="props">
                    <q-tr
                        :props="props"
                        :class="coloresEstado ? adminStatusMeta(props.row.estado).rowClass : ''"
                    >
                        <q-td
                            v-for="col in props.cols"
                            :key="col.name"
                            :props="props"
                        >
                            <slot
                                :name="'body-cell-' + col.name"
                                v-bind="props"
                                v-if="col.name != 'action'"
                            >
                                <template v-if="col.name === 'estado'">
                                    <q-badge
                                        :color="adminStatusMeta(props.row.estado).color"
                                        :label="adminStatusMeta(props.row.estado).label"
                                    />
                                </template>
                                <template v-else>{{ col.value }}</template>
                            </slot>
                            <slot
                                :name="'body-cell-' + col.name"
                                v-bind="props"
                                v-else
                            >
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <!-- Talento: badge "es colaborador" + enlace a ficha -->
                                    <a
                                        v-if="props.row.talento_colaborador_id"
                                        :href="'/talento/colaborador/' + props.row.talento_colaborador_id"
                                        class="badge bg-success text-white text-decoration-none me-1"
                                        style="font-size:10px;"
                                        title="Ver ficha de colaborador en Talento"
                                    >
                                        <i class="fa fa-id-badge me-1"></i>Talento
                                    </a>
                                    <!-- Reforma de permisos B1.3: eliminado el candado de
                                         permisos individuales por usuario. El rol es la
                                         única fuente de verdad; la asignación vive en la
                                         pantalla de Roles. -->
                                    <span
                                        class="text-primary me-2"
                                        role="button"
                                        v-if="
                                            hasPermission.data.canView(
                                                'user_edit_user'
                                            )
                                        "
                                    >
                                        <a
                                            :href="
                                                '/administracion/user/' +
                                                props.row.id +
                                                '/editar'
                                            "
                                            class="text-primary"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </span>

                                    <!-- Botón bloquear / desbloquear -->
                                    <span
                                        v-if="
                                            hasPermission.data.canView('user_edit_user') &&
                                            props.row.estado !== 'inactivo'
                                        "
                                        :title="props.row.estado === 'bloqueado' ? 'Desbloquear' : 'Bloquear'"
                                        :class="[
                                            'me-1',
                                            props.row.estado === 'bloqueado' ? 'text-warning' : 'text-secondary',
                                            isSuperAdmin(props.row.id) ? 'disabled-text' : '',
                                        ]"
                                        role="button"
                                        @click="!isSuperAdmin(props.row.id) && bloquearRow(props.row)"
                                    >
                                        <i :class="['fas', props.row.estado === 'bloqueado' ? 'fa-unlock' : 'fa-lock']"></i>
                                    </span>

                                    <!-- Botón activar / desactivar -->
                                    <span
                                        v-if="
                                            hasPermission.data.canView(
                                                'user_delete_user'
                                            )
                                        "
                                        class="text-primary"
                                        role="button"
                                        @click="
                                            deleteRow(
                                                props.row.id,
                                                props.row.estado
                                            )
                                        "
                                        :class="{
                                            'disabled-text': isSuperAdmin(
                                                props.row.id
                                            ),
                                        }"
                                    >
                                        <i
                                            :class="[
                                                'fas',
                                                props.row.estado === 'activo'
                                                    ? 'fa-power-off'
                                                    : 'fa-check-circle',
                                            ]"
                                        ></i>
                                    </span>
                                </div>
                            </slot>
                        </q-td>
                    </q-tr>
                </template>
            </q-table>
        </q-card>
        <modal
            :show="showModal"
            :size="'xs'"
            @update:show="showModal = $event"
            title="Mostrar columnas/Ocultar columnas"
        >
            <template #body>
                <div class="my-3">
                    <p>
                        Para mostrar los campos de la tabla, seleccione la
                        casilla de verificación correspondiente.
                    </p>
                </div>
                <div
                    class="form-check form-switch form-switch-md"
                    v-for="(column, index) in columns"
                    :key="index"
                >
                    <input
                        class="form-check-input"
                        type="checkbox"
                        v-model="column.visible"
                    />
                    <label class="form-check-label">{{ column.label }}</label>
                </div>
            </template>
            <template #footer>
                <button class="btn btn-primary" @click="saveColumnsTable">
                    Guardar
                </button>
            </template>
        </modal>

    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive, watch } from "vue";
import Swal from "sweetalert2";
import Modal from "../../../../shared/ModalSimple.vue";
import { getAll, deleteUser, activeOrInactive, bloquearUser } from "./helper/request.js";
import { adminStatusMeta } from "../../../../helpers/adminStatus.js";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";
import { darkMode, rowStatusStyle, setRowStatusStyle } from "../../../../hook/appConfig.js";
import { useDataTable } from "../../../../composables/useDataTable.js";
import { usePreferenciasVista } from "../../../../composables/usePreferenciasVista.js";
import { useTableUrlState } from "../../../../hook/useTableUrlState";
import { exportarFilas } from "../../../../helpers/ExportTablaExcel";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import { pro } from "@he-tree/vue";

const props = defineProps({
    // Roles para el filtro (JSON desde la vista, sin el rol de clientes).
    rolesFiltro: {
        type: String,
        default: "[]",
    },
});

const hasPermission = reactive({
    data: new Permission({}),
});

const leerRoles = () => {
    try {
        const roles = JSON.parse(props.rolesFiltro);
        return Array.isArray(roles) ? roles : [];
    } catch (error) {
        return [];
    }
};

// Filtros del listado. Sin predeterminado guardado por el usuario: Activos y todos los roles.
const CAMPOS_FILTRO = [
    {
        nombre: "estado",
        etiqueta: "Estado",
        chip: "",
        inicial: "activo",
        opciones: [
            { label: "Todos", value: "todos" },
            { label: "Activos", value: "activo" },
            { label: "Inactivos", value: "inactivo" },
            { label: "Bloqueados", value: "bloqueado" },
        ],
    },
    {
        nombre: "rol",
        etiqueta: "Rol",
        chip: "rol ",
        inicial: "todos",
        opciones: [
            { label: "Todos", value: "todos" },
            ...leerRoles().map((rol) => ({ label: rol, value: rol })),
        ],
    },
];
const esOpcionDe = (campo, valor) => campo.opciones.some((opcion) => opcion.value === valor);
const etiquetaDe = (campo, valor) =>
    campo.opciones.find((opcion) => opcion.value === valor)?.label ?? valor;

const filtros = reactive({ estado: "activo", rol: "todos" });
// Lo que el usuario dejó como predeterminado (null = no ha elegido).
const predeterminados = reactive({ estado: null, rol: null });
const coloresEstado = ref(true);
const preferencias = usePreferenciasVista("administradores");

const columns = ref([
    {
        name: "id",
        align: "start",
        label: "ID",
        field: "id",
        sortable: true,
        visible: true,
    },
    {
        name: "name",
        required: true,
        label: "Nombre",
        align: "start",
        field: "name",
        sortable: true,
        visible: true,
    },
    {
        name: "father_last_name",
        align: "start",
        label: "Apellido paterno",
        field: "father_last_name",
        sortable: true,
        visible: true,
    },
    {
        name: "mother_last_name",
        align: "start",
        label: "Apellido materno",
        field: "mother_last_name",
        sortable: true,
        visible: true,
    },
    {
        name: "email",
        align: "start",
        label: "Correo Electronico",
        field: "email",
        sortable: true,
        visible: true,
    },
    {
        name: "login_user",
        align: "start",
        label: "Nombre de usuario",
        field: "login_user",
        sortable: true,
        visible: true,
    },
    {
        name: "address",
        align: "start",
        label: "Dirección",
        field: "address",
        sortable: true,
        visible: true,
    },
    {
        name: "city_municipality",
        align: "start",
        label: "Municipio",
        field: "city_municipality",
        sortable: true,
        visible: true,
    },
    {
        name: "colony",
        align: "start",
        label: "Colonia",
        field: "colony",
        sortable: true,
        visible: true,
    },
    {
        name: "state_country",
        align: "start",
        label: "Estado",
        field: "state_country",
        sortable: true,
        visible: true,
    },
    {
        name: "code_postal",
        align: "start",
        label: "Código Postal",
        field: "code_postal",
        sortable: true,
        visible: true,
    },
    {
        name: "rfc",
        align: "start",
        label: "RFC",
        field: "rfc",
        sortable: true,
        visible: true,
    },
    {
        name: "rol_name",
        align: "start",
        label: "Rol",
        field: "rol_name",
        sortable: true,
        visible: true,
    },
    {
        name: "sucursal_str",
        align: "start",
        label: "Sucursal",
        field: "sucursal_str",
        sortable: true,
        visible: true,
    },
    {
        name: "estado",
        align: "start",
        label: "Estado",
        field: "estado",
        sortable: true,
        visible: true,
    },
    {
        name: "action",
        label: "Acciones",
        align: "center",
        field: "action",
        sortable: false,
        visible: true,
    },
]);

const users = ref([]);
const loading = ref(false);
const filter = ref("");
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const showModal = ref(false);
const tableIdentifier = ref("administradores");
const { getColumns, saveColumns } = useDataTable();

const pagination = ref({
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 0,
    sortBy: null,
    descending: false,
});

// #1176 (patrón de #1162/#1175): conserva página, filas por página, orden, búsqueda y filtros al
// entrar a un registro y volver. Búsqueda y filtros van en history.state, nunca en la URL; los
// filtros de esa entrada mandan sobre el predeterminado del usuario. Se lee antes de montar.
const tablaUrl = useTableUrlState({ parametros: ["page", "rowsPerPage", "sortBy", "descending"] });
const filtrosDelHistorial = {};
{
    const { url, privado } = tablaUrl.leer();
    if (/^\d+$/.test(url.rowsPerPage ?? "") && rowPerPageOptions.value.includes(Number(url.rowsPerPage))) {
        pagination.value.rowsPerPage = Number(url.rowsPerPage);
    }
    if (/^[1-9]\d*$/.test(url.page ?? "") && pagination.value.rowsPerPage !== 0) {
        pagination.value.page = Number(url.page);
    }
    if (columns.value.some((col) => col.sortable && col.name === url.sortBy)) {
        pagination.value.sortBy = url.sortBy;
        pagination.value.descending = url.descending === "1";
    }
    if (typeof privado.search === "string") filter.value = privado.search;
    CAMPOS_FILTRO.forEach((campo) => {
        if (esOpcionDe(campo, privado[campo.nombre])) filtrosDelHistorial[campo.nombre] = privado[campo.nombre];
    });
    watch(
        [
            filter,
            () => filtros.estado,
            () => filtros.rol,
            () => pagination.value.page,
            () => pagination.value.rowsPerPage,
            () => pagination.value.sortBy,
            () => pagination.value.descending,
        ],
        () => {
            const { page, rowsPerPage, sortBy, descending } = pagination.value;
            tablaUrl.guardar(
                {
                    page: page !== 1 ? String(page) : null,
                    rowsPerPage: rowsPerPage !== 50 ? String(rowsPerPage) : null,
                    sortBy: sortBy || null,
                    descending: sortBy && descending ? "1" : null,
                },
                {
                    ...(filter.value !== "" ? { search: filter.value } : {}),
                    estado: filtros.estado,
                    rol: filtros.rol,
                }
            );
        }
    );
}

// Los filtros recargan desde la página 1. Hasta tener las preferencias del usuario no se pide nada:
// así la primera petición ya sale con los filtros que le tocan.
let filtrosListos = false;
watch(
    () => [filtros.estado, filtros.rol],
    () => {
        if (filtrosListos) {
            tableRef.value.requestServerInteraction({ pagination: { ...pagination.value, page: 1 } });
        }
    },
    { flush: "sync" }
);

const cargarPreferencias = async () => {
    try {
        const guardadas = await preferencias.cargar();
        CAMPOS_FILTRO.forEach((campo) => {
            if (esOpcionDe(campo, guardadas[campo.nombre])) predeterminados[campo.nombre] = guardadas[campo.nombre];
        });
        coloresEstado.value = guardadas.colores_estado !== false;
    } catch (error) {
        // Sin preferencias se trabaja con los valores iniciales.
        window.console.error("No se pudieron leer las preferencias del listado:", error);
    }
};

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    getColumnsTable();
    await cargarPreferencias();
    CAMPOS_FILTRO.forEach((campo) => {
        filtros[campo.nombre] =
            filtrosDelHistorial[campo.nombre] ?? predeterminados[campo.nombre] ?? campo.inicial;
    });
    filtrosListos = true;
    tableRef.value.requestServerInteraction();
});

const isSuperAdmin = (id) => {
    return id == 1;
};

// Filtros y orden tal como los espera el servidor ("todos" = sin filtro).
const parametrosServidor = ({ sortBy, descending }) => ({
    estado: filtros.estado !== "todos" ? filtros.estado : undefined,
    rol: filtros.rol !== "todos" ? filtros.rol : undefined,
    sortBy: sortBy || undefined,
    descending: sortBy ? (descending ? 1 : 0) : undefined,
});

// Si llegan dos respuestas cruzadas (cambios rápidos de filtro), solo vale la última petición.
let ultimaPeticion = 0;

const getAllUsers = async ({ pagination: { page, rowsPerPage, sortBy, descending } }) => {
    const peticion = ++ultimaPeticion;
    loading.value = true;

    try {
        const { data, total } = await getAll(
            page,
            rowsPerPage,
            filter.value,
            parametrosServidor({ sortBy, descending })
        );
        if (peticion !== ultimaPeticion) return;

        users.value.splice(0, users.value.length, ...data);

        pagination.value.page = page;
        pagination.value.rowsPerPage = rowsPerPage;
        pagination.value.sortBy = sortBy;
        pagination.value.descending = descending;
        pagination.value.rowsNumber = total;
    } catch (error) {
        console.error("Error in onRequest:", error);
    } finally {
        if (peticion === ultimaPeticion) loading.value = false;
    }
};

const esPredeterminado = (nombre) =>
    predeterminados[nombre] !== null && predeterminados[nombre] === filtros[nombre];

const textoEstrella = (campo) =>
    esPredeterminado(campo.nombre)
        ? `Es tu predeterminado. Clic para restablecer (al entrar verás «${etiquetaDe(campo, campo.inicial)}»)`
        : "Usar como predeterminado";

const avisar = (texto, icon = "success") =>
    Swal.fire({ toast: true, position: "top-end", timer: 3000, showConfirmButton: false, icon, title: texto });

const alternarPredeterminado = async (campo) => {
    try {
        if (esPredeterminado(campo.nombre)) {
            await preferencias.restablecer(campo.nombre);
            predeterminados[campo.nombre] = null;
            avisar(`${campo.etiqueta}: al entrar verás «${etiquetaDe(campo, campo.inicial)}».`);
        } else {
            const valor = filtros[campo.nombre];
            await preferencias.guardar(campo.nombre, valor);
            predeterminados[campo.nombre] = valor;
            avisar(`${campo.etiqueta}: al entrar verás «${etiquetaDe(campo, valor)}».`);
        }
    } catch (error) {
        avisar("No se pudo guardar el predeterminado.", "error");
    }
};

const alternarColoresEstado = async () => {
    coloresEstado.value = !coloresEstado.value;
    try {
        await preferencias.guardar("colores_estado", coloresEstado.value);
    } catch (error) {
        avisar("No se pudo guardar la preferencia de colores.", "error");
    }
};

// Exporta lo que se está viendo: mismos filtros, búsqueda, orden y columnas visibles, todas las
// páginas. El servidor exige el permiso del listado para traer todos los registros.
const exportar = async () => {
    showLoading();
    try {
        const { data } = await getAll(1, 0, filter.value, parametrosServidor(pagination.value));
        exportarFilas(
            columns.value,
            data,
            "Administradores",
            visibleColumns.value.map((col) => col.name)
        );
    } catch (error) {
        Swal.fire("No se exportó", error.response?.data?.message || "Error inesperado", "warning");
    } finally {
        hideLoading();
    }
};

const getColumnsTable = async () => {
    try {
        const response = await getColumns(tableIdentifier.value);
        const storedColumns = response;

        if (storedColumns && storedColumns.length > 0) {
            columns.value.forEach((column) => {
                const storedColumn = storedColumns.find(
                    (col) => col.name === column.name
                );
                if (storedColumn) {
                    column.visible = storedColumn.visible;
                }
            });
        }
    } catch (error) {
        console.log(error);
    }
};

const ACTION_TEXTS = {
    activo: {
        title: "Confirmar desactivación",
        message: "¿Está seguro de que desea desactivar este usuario?",
        confirm: "Sí, Desactivar",
        success: "Usuario desactivado correctamente",
    },
    inactivo: {
        title: "Confirmar activación",
        message: "¿Está seguro de que desea activar este usuario?",
        confirm: "Sí, Activar",
        success: "Usuario activado correctamente",
    },
    bloqueado: {
        title: "Confirmar activación",
        message: "El usuario está bloqueado. ¿Desea reactivarlo?",
        confirm: "Sí, Reactivar",
        success: "Usuario reactivado correctamente",
    },
};

const deleteRow = async (userId, action) => {
    try {
        const t = ACTION_TEXTS[action];

        const confirmed = await Swal.fire({
            title: t.title,
            text: t.message,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: t.confirm,
            cancelButtonText: "Cancelar",
        });

        if (confirmed.isConfirmed) {
            const response = await activeOrInactive(userId, action);

            Swal.fire({
                title: "Éxito",
                text: t.success,
                icon: "success",
            });

            getAllUsers({ pagination: pagination.value });
        }
    } catch (error) {
        Swal.fire({
            title: "Error",
            text: error.response?.data?.error || "Error inesperado",
            icon: "error",
        });
    }
};

const bloquearRow = async (row) => {
    try {
        if (row.estado !== 'bloqueado') {
            const confirmed = await Swal.fire({
                title: "Confirmar bloqueo",
                text: "Esto impedirá que su extensión haga llamadas salientes a la calle (seguirá recibiendo llamadas y comunicándose internamente). ¿Continuar?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sí, Bloquear",
                cancelButtonText: "Cancelar",
            });
            if (!confirmed.isConfirmed) return;
        }
        await bloquearUser(row.id);
        Swal.fire({ title: "Éxito", text: row.estado === 'bloqueado' ? "Usuario desbloqueado" : "Usuario bloqueado", icon: "success" });
        getAllUsers({ pagination: pagination.value });
    } catch (error) {
        Swal.fire({ title: "Error", text: error.response?.data?.error || "Error inesperado", icon: "error" });
    }
};

const saveColumnsTable = async () => {
    try {
        const columnsData = columns.value.map((col) => ({
            name: col.name,
            visible: col.visible,
        }));

        await saveColumns(tableIdentifier.value, columnsData);
        showModal.value = false;
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const tableRef = ref();

const reloadTable = () => {
    getAllUsers({ pagination: pagination.value });
};
</script>

<style scoped>
.form-check-input {
    margin-right: 0.5rem;
}
.disabled-text {
    opacity: 0.5;
    pointer-events: none;
}
</style>
