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
                @request="getAllUsers"
                style="max-height: 70vh"
            >
                <template v-slot:top="props">
                    <div
                        class="d-flex justify-content-end align-items-center gap-3"
                    >
                        <button
                            type="button"
                            class="btn btn-outline-info"
                            @click="showModal = true"
                        >
                            ...
                        </button>
                        <button
                            class="btn btn-outline-secondary"
                            @click="reloadTable"
                        >
                            <i class="fas fa-sync"></i>
                        </button>
                        <q-btn
                            flat
                            round
                            dense
                            :icon="
                                props.inFullscreen
                                    ? 'fullscreen_exit'
                                    : 'fullscreen'
                            "
                            @click="props.toggleFullscreen"
                            class="q-ml-md"
                        />
                        <q-input
                            borderless
                            dense
                            placeholder="Buscar"
                            class="mb-0"
                            style="margin-left: 16px; border: 1px solid"
                            v-model="filter"
                            :dark="darkMode"
                        />
                    </div>
                </template>

                <template v-slot:body="props">
                    <q-tr
                        :props="props"
                        :class="props.row.active ? 'Activo' : 'Bloqueado'"
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
                                {{ col.value }}
                            </slot>
                            <slot
                                :name="'body-cell-' + col.name"
                                v-bind="props"
                                v-else
                            >
                                <div class="d-flex justify-content-center">
                                    <span
                                        v-if="
                                            hasPermission.data.canView(
                                                'user_permission_user'
                                            )
                                        "
                                        class="text-primary me-2"
                                        role="button"
                                        @click="
                                            openModalPermissions(
                                                props.row.id,
                                                props.row.name
                                            )
                                        "
                                        :class="{
                                            'disabled-text': isSuperAdmin(
                                                props.row.id
                                            ),
                                        }"
                                    >
                                        <i class="fas fa-lock"></i>
                                    </span>
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
                                                props.row.active
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
                                                props.row.active
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

        <!-- ---------------------------------------------------------------------- -->
        <PermissionUser
            :userId="idUser"
            :userName="userName"
            v-model:showModal="showModalPermissions"
        />
        <!-- ---------------------------------------------------------------------- -->
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from "vue";
import Swal from "sweetalert2";
import Modal from "../../../../shared/ModalSimple.vue";
import { getAll, deleteUser, activeOrInactive } from "./helper/request.js";
import PermissionUser from "./PermissionUser.vue";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";
import { darkMode } from "../../../../hook/appConfig.js";
import { useDataTable } from "../../../../composables/useDataTable.js";
import { pro } from "@he-tree/vue";

const hasPermission = reactive({
    data: new Permission({}),
});

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
        name: "active",
        align: "start",
        label: "Activo",
        field: "active",
        sortable: true,
        visible: true,
        format: (val) => {
            return val ? "Si" : "No";
        },
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
});

const idUser = ref(null);
const userName = ref("");
const showModalPermissions = ref(false);
onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    getColumnsTable();
    tableRef.value.requestServerInteraction();
});

const openModalPermissions = async (userId, name) => {
    idUser.value = userId;
    userName.value = name;
    showModalPermissions.value = true;
};

const isSuperAdmin = (id) => {
    return id == 1;
};

const getAllUsers = async ({ pagination: { page, rowsPerPage } }) => {
    loading.value = true;

    try {
        const { data, total } = await getAll(page, rowsPerPage, filter.value);

        users.value.splice(0, users.value.length, ...data);

        pagination.value.page = page;
        pagination.value.rowsPerPage = rowsPerPage;
        pagination.value.rowsNumber = total;
    } catch (error) {
        console.error("Error in onRequest:", error);
    } finally {
        loading.value = false;
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
    0: {
        title: "Confirmar activación",
        message: "¿Está seguro de que desea activar este usuario?",
        confirm: "Sí, Activar",
        success: "Usuario activado correctamente",
    },
    1: {
        title: "Confirmar desactivación",
        message: "¿Está seguro de que desea desactivar este usuario?",
        confirm: "Sí, Desactivar",
        success: "Usuario desactivado correctamente",
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
