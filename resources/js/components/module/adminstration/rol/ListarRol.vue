<template>
    <div class="q-pa-md">
        <q-card>
            <q-card-section
                class="d-flex"
                style="justify-content: space-between; align-items: center;"
            >
                <div class="text-h6">Listado de Roles</div>
                <div class="d-flex gap-2">
                    <button
                        v-if="hasPermission.data.canView('role_permission_role')"
                        class="btn btn-outline-warning btn-sm waves-effect waves-light"
                        @click="openSyncModal"
                        title="Sincronizar permisos .view faltantes a todos los roles y todos los permisos a super-administrator + DESARROLLADOR"
                    >
                        <i class="fas fa-sync-alt me-1"></i> Sincronizar permisos
                    </button>
                    <button
                        v-if="hasPermission.data.canView('role_add_role')"
                        class="btn btn-success waves-effect waves-light"
                        @click="openModal()"
                    >
                        Agregar rol
                    </button>
                </div>
            </q-card-section>
            <q-table
                v-table-resizable
                row-key="id"
                v-model:pagination="pagination"
                ref="tableRef"
                no-data-label="No hay elementos para mostrar"
                :rows="roles"
                :columns="columns"
                :loading="loading"
                :dark="darkMode"
                :rows-per-page-label="'Elementos por página'"
                :rows-per-page-options="rowPerPageOptions"
            >
                <template v-slot:top="props">
                    <div
                        class="d-flex justify-content-end align-items-center gap-3"
                    >
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
                            v-model="filter"
                            style="margin-left: 16px; border: 1px solid"
                            :dark="darkMode"
                        />
                    </div>
                </template>
                <template v-slot:body-cell-action="props">
                    <div class="d-flex justify-content-center">
                        <span
                            v-if="
                                hasPermission.data.canView(
                                    'role_permission_role'
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
                                'disabled-text': isSuperAdmin(props.row.name),
                            }"
                        >
                            <i class="fas fa-lock"></i>
                        </span>
                        <span
                            v-if="hasPermission.data.canView('role_edit_role')"
                            class="text-primary me-2"
                            role="button"
                            @click="openModal(props.row.id)"
                            :class="{
                                'disabled-text': isSuperAdmin(props.row.name),
                            }"
                        >
                            <i class="fas fa-edit"></i>
                        </span>
                        <span
                            v-if="
                                hasPermission.data.canView('role_delete_role')
                            "
                            class="text-primary"
                            role="button"
                            @click="remove(props.row.id)"
                            :class="{
                                'disabled-text': isSuperAdmin(props.row.name),
                            }"
                        >
                            <i class="fas fa-trash"></i>
                        </span>
                    </div>
                </template>
            </q-table>
        </q-card>
        <!-- ---------------------------------------------------------------------- -->
        <modal
            :show="showModal"
            :size="'xs'"
            @update:show="showModal = $event"
            :title="modalTitle"
        >
            <template #body>
                <div class="form-group">
                    <label for="name" class="form-label">Nombre del rol</label>
                    <input
                        type="text"
                        class="form-control"
                        placeholder="Nombre del rol"
                        v-model="role.name"
                    />
                </div>
            </template>
            <template #footer>
                <button class="btn btn-primary" @click="saveRole">
                    {{ buttonTitle }}
                </button>
            </template>
        </modal>
        <!-- ---------------------------------------------------------------------- -->

        <!-- ---------------------------------------------------------------------- -->
        <PermissionRole
            :roleId="idRole"
            :roleName="roleName"
            v-model:showModal="showModalPermissions"
        />
        <!-- ---------------------------------------------------------------------- -->

        <!-- Modal sincronizar permisos (solo super-administrator) -->
        <modal
            :show="showSyncModal"
            :size="'sm'"
            @update:show="showSyncModal = $event"
            title="Sincronizar permisos a roles base"
        >
            <template #body>
                <div v-if="syncLoading" class="text-center py-3">
                    <div class="spinner-border text-warning" role="status"></div>
                    <p class="mt-2 text-muted small">Sincronizando…</p>
                </div>
                <div v-else-if="syncResult">
                    <div class="alert mb-3" :class="syncResult.total > 0 ? 'alert-success' : 'alert-info'">
                        <i class="fas fa-check-circle me-1"></i> {{ syncResult.message }}
                    </div>
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-light"><tr><th>Rol / Categoría</th><th class="text-end">Asignados</th></tr></thead>
                        <tbody>
                            <tr><td>super-administrator</td><td class="text-end">{{ syncResult.report['super-administrator'] ?? 0 }}</td></tr>
                            <tr><td>DESARROLLADOR</td><td class="text-end">{{ syncResult.report['DESARROLLADOR'] ?? 0 }}</td></tr>
                            <tr><td>Otros roles (solo .view)</td><td class="text-end">{{ syncResult.report['others_view'] ?? 0 }}</td></tr>
                            <tr class="fw-bold"><td>Total</td><td class="text-end">{{ syncResult.total }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="small text-muted">
                    <p>Esta acción:</p>
                    <ul>
                        <li>Asigna <strong>todos los permisos</strong> faltantes a <code>super-administrator</code> y <code>DESARROLLADOR</code>.</li>
                        <li>Asigna los permisos <code>.view</code> faltantes a <strong>todos los demás roles</strong>.</li>
                        <li>Es <strong>idempotente</strong> — correrla varias veces no produce duplicados.</li>
                    </ul>
                </div>
            </template>
            <template #footer>
                <button v-if="!syncResult" class="btn btn-warning" :disabled="syncLoading" @click="runSync">
                    <i class="fas fa-sync-alt me-1"></i> Ejecutar sincronización
                </button>
                <button class="btn btn-secondary ms-2" @click="closeSyncModal">Cerrar</button>
            </template>
        </modal>
        <!-- ---------------------------------------------------------------------- -->
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import {
    getAll,
    getById,
    create,
    update,
    deleteRol,
    syncRolesToBasePermissions,
} from "./helper/request.js";
import Swal from "sweetalert2";
import Modal from "../../../../shared/ModalSimple.vue";
import PermissionRole from "./PermissionRole.vue";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";
import { darkMode } from "../../../../hook/appConfig.js";

const hasPermission = reactive({
    data: new Permission({}),
});

const columns = ref([
    {
        name: "name",
        required: true,
        label: "Nombre del Rol",
        align: "start",
        field: "name",
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

const roles = ref([]);
const loading = ref(false);
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const showModal = ref(false);
const modalTitle = ref("Agregar rol");
const buttonTitle = ref("Agregar");
const role = reactive({
    id: null,
    name: "",
});

const showModalPermissions = ref(false);
const filter = ref("");

// ── Sincronización de permisos (solo super-administrator) ──────────────────
// El botón se muestra a quien puede gestionar permisos de rol.
// El endpoint valida hasRole('super-administrator') y rechaza con 403 si no aplica.
const showSyncModal = ref(false);
const syncLoading  = ref(false);
const syncResult   = ref(null);

const openSyncModal  = () => { syncResult.value = null; showSyncModal.value = true; };
const closeSyncModal = () => { showSyncModal.value = false; syncResult.value = null; };

const runSync = async () => {
    syncLoading.value = true;
    try {
        syncResult.value = await syncRolesToBasePermissions();
    } catch (e) {
        Swal.fire('Error', 'No se pudo completar la sincronización.', 'error');
        showSyncModal.value = false;
    } finally {
        syncLoading.value = false;
    }
};

const pagination = ref({
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 0,
});
const idRole = ref(0);
const roleName = ref("");

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    getRoles();
});

const getRoles = async () => {
    try {
        loading.value = true;
        roles.value = await getAll();
        loading.value = false;
    } catch (error) {
        console.log(error);
        loading.value = false;
    }
};

const openModal = async (idRole = null) => {
    showModal.value = true;
    if (idRole) {
        modalTitle.value = "Editar rol";
        buttonTitle.value = "Actualizar";
        try {
            const response = await getById(idRole);
            const { id, name } = response;
            role.id = id;
            role.name = name;
        } catch (error) {
            console.log(error);
        }
    } else {
        modalTitle.value = "Agregar rol";
        buttonTitle.value = "Agregar";
        role.id = null;
        role.name = "";

    }
};

const openModalPermissions = async (roleId, name) => {
    idRole.value = roleId;
    roleName.value = name;
    showModalPermissions.value = true;
};

const saveRole = async () => {
    if (role.id) {
        await updateRole();
    } else {
        await createRole();
    }
};

const createRole = async () => {
    try {
        const response = await create(role);
        showModal.value = false;
        getRoles();
        Swal.fire("¡Creado!", response.message, "success");
    } catch (error) {
        console.log(error);
        showModal.value = true;
        Swal.fire(
            "¡Error!",
            "Hubo un error en el registro, verifica que el rol no esté registrado",
            "error"
        );
    }
};

const updateRole = async () => {
    try {
        const response = await update(role.id, role);
        showModal.value = false;
        getRoles();
        Swal.fire("¡Actualizado!", response.message, "success");
    } catch (error) {
        console.log(error);
        Swal.fire("¡Error!", "Hubo un error al actualizar el rol", "error");
        showModal.value = true;
    }
};

const remove = async (roleId) => {
    Swal.fire({
        title: "¿Estás seguro de eliminar el rol?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "¡Sí, eliminar!",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await deleteRol(roleId);
                Swal.fire("¡Eliminado!", response.message, "success");
                getRoles();
            } catch (error) {
                console.log(error.response.data.message);
                Swal.fire(
                    "¡Error!",
                    "Hubo un error al eliminar el rol",
                    "error"
                );
            }
        }
    });
};

const isSuperAdmin = (roleName) => {
    return /super\s*-?\s*administra(tor|dor)/i.test(roleName);
};
</script>

<style scoped>
.disabled-text {
    opacity: 0.5;
    pointer-events: none;
}
</style>
