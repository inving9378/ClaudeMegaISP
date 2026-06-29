<template>
    <modal
        :show="props.showModal"
        :size="'lg'"
        @update:show="updateShow"
        :title="'Modificar permisos de ' + props.entityName"
    >
        <template #body>
            <div class="d-flex flex-row-reverse">
                <button class="btn btn-primary" @click="addAll = !addAll">
                    {{ textButtonAll }}
                </button>
            </div>
            <div class="row p-2">
                <div class="col-md-3">
                    <div
                        class="nav flex-column nav-pills"
                        role="tablist"
                        id="v-pills-tab"
                        aria-orientation="vertical"
                        data-spa-skip
                    >
                        <a
                            v-for="tab in tabs"
                            :key="tab.ref"
                            :class="['nav-link mb-2', { active: tab.active }]"
                            data-bs-toggle="pill"
                            :href="`#v-pills-${tab.ref}`"
                            role="tab"
                        >
                            {{ tab.title }}
                        </a>
                    </div>
                </div>
                <div class="col-md-9">
                    <div
                        class="tab-content text-muted mt-4 mt-md-0"
                        id="v-pills-tabContent"
                    >
                        <div
                            v-for="tab in tabs"
                            :key="tab.ref"
                            :class="[
                                'tab-pane fade',
                                { 'show active': tab.active },
                            ]"
                            :id="`v-pills-${tab.ref}`"
                            role="tabpanel"
                        >
                            <h4 class="text-center">{{ tab.title }}</h4>

                            <div class="accordion" :id="`accordion-${tab.ref}`">
                                <div
                                    v-for="(accordion, index) in accordions[
                                        tab.ref
                                    ]"
                                    :key="index"
                                    class="accordion-item"
                                >
                                    <h2
                                        class="accordion-header"
                                        :id="`heading-${tab.ref}-${index}`"
                                    >
                                        <button
                                            class="accordion-button"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            :data-bs-target="`#collapse-${tab.ref}-${index}`"
                                            aria-expanded="true"
                                            :aria-controls="`collapse-${tab.ref}-${index}`"
                                        >
                                            {{ accordion.title }}
                                        </button>
                                    </h2>
                                    <div
                                        :id="`collapse-${tab.ref}-${index}`"
                                        class="accordion-collapse collapse show"
                                        :aria-labelledby="`heading-${tab.ref}-${index}`"
                                        :data-bs-parent="`#accordion-${tab.ref}`"
                                    >
                                        <div class="accordion-body">
                                            <template
                                                v-if="tab.ref === 'promotions'"
                                            >
                                                <div
                                                    class="form-check form-switch form-switch-md mx-3 mb-3"
                                                    v-for="perm in allPromotions.filter(
                                                        (p) =>
                                                            p.code ===
                                                            accordion.filter
                                                    )"
                                                    :key="perm.id"
                                                >
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        :id="`flexSwitchCheckDefault-${perm.id}`"
                                                        v-model="perm.value"
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        :for="`flexSwitchCheckDefault-${perm.id}`"
                                                    >
                                                        {{
                                                            perm.promotionable
                                                                .name
                                                        }}
                                                    </label>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div
                                                    class="form-check form-switch form-switch-md mx-3 mb-3"
                                                    v-for="perm in fieldsJson[
                                                        tab.ref
                                                    ].filter(
                                                        (p) =>
                                                            p.field ===
                                                                accordion.filter ||
                                                            p.depend ===
                                                                accordion.filter
                                                    )"
                                                    :key="perm.field"
                                                >
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        :id="`flexSwitchCheckDefault-${perm.field}`"
                                                        v-model="perm.value"
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        :for="`flexSwitchCheckDefault-${perm.field}`"
                                                    >
                                                        {{ perm.label }}
                                                    </label>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="updatePermissions">
                Guardar
            </button>
        </template>
    </modal>
</template>

<script setup>
/**
 * Modal compartido de asignación de permisos (#114).
 *
 * Dedup de PermissionUser.vue + PermissionRole.vue. Parametrizado por
 * entityType ('user' | 'role'). El BACKEND queda intacto: cada tipo usa su
 * propio endpoint load/save (user=permisos directos; role=cascade multi-rol),
 * con payloads idénticos a los modales originales. Las promociones son solo
 * para 'user'.
 */
import { watch, ref, computed, onMounted } from "vue";
import {
    fieldsJson as importedFieldsJson,
    accordions,
    extraModuleTabs,
    buildUncategorizedTab,
} from "./rol/helper/constants.js";
import {
    getPermissionsCatalog,
    getPermissionsForRole,
    updatePermissionByRole,
} from "./rol/helper/request";
import {
    getPermissionsForUser,
    updatePermissionByUser,
} from "./user/helper/request";
import Swal from "sweetalert2";
import Modal from "../../../shared/ModalSimple.vue";

const props = defineProps({
    entityType: {
        type: String,
        required: true, // 'user' | 'role'
    },
    entityId: {
        type: Number,
        default: 0,
    },
    entityName: {
        type: String,
        required: true,
    },
    showModal: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(["update:showModal"]);

// Las promociones existen solo para usuarios (idéntico a PermissionUser original).
const hasPromotions = computed(() => props.entityType === "user");

const baseTabs = [
    { ref: "dashboard", active: true, title: "Dashboard" },
    { ref: "plan", active: false, title: "Planes" },
    { ref: "crm", active: false, title: "Clientes potenciales" },
    { ref: "client", active: false, title: "Clientes" },
    { ref: "seller", active: false, title: "Vendedores" },
    { ref: "ticket", active: false, title: "Tickets" },
    { ref: "finance", active: false, title: "Finanzas" },
    { ref: "maps", active: false, title: "Mapas" },
    { ref: "olts", active: false, title: "OLTs" },
    { ref: "scheduling", active: false, title: "Actividades Programadas" },
    { ref: "network", active: false, title: "Gestión de red" },
    { ref: "inventory", active: false, title: "Inventario" },
    { ref: "administration", active: false, title: "Administración" },
    { ref: "configuration", active: false, title: "Configuración" },
    { ref: "message", active: false, title: "Mensajes" },
];

// Orden idéntico a los originales: user inserta "promotions" entre message y releases.
const tabs = ref([
    ...baseTabs,
    ...(hasPromotions.value
        ? [{ ref: "promotions", active: false, title: "Promociones" }]
        : []),
    { ref: "releases", active: false, title: "Actualizaciones" },
    ...extraModuleTabs.map((t) => ({ ...t, active: false })),
]);

const fieldsJson = ref(importedFieldsJson);
const permissions = ref([]);
const allPromotions = ref([]);
const avaiablesPromotions = ref([]);

watch(
    () => props.entityId,
    (newId, oldId) => {
        if (newId && newId !== oldId) {
            getPermissions();
        }
    },
    { immediate: false }
);

const getPermissions = async () => {
    try {
        if (props.entityType === "user") {
            const response = await getPermissionsForUser(props.entityId);
            permissions.value = response.permissions;
            allPromotions.value = response.all_promotions;
            avaiablesPromotions.value = response.avaiables_promotions;
        } else {
            const response = await getPermissionsForRole(props.entityId);
            permissions.value = response.permissions;
        }
        applyPermissions();
    } catch (error) {
        console.log(error);
    }
};

/**
 * Recorre los campos una sola vez (versión robusta del PermissionUser original):
 * fija value=true/false según el Set de permisos. Para roles el resultado es
 * idéntico (checked sii el permiso está en la lista). Promociones solo en user.
 */
const applyPermissions = () => {
    const permSet = new Set(permissions.value);
    for (const tab in fieldsJson.value) {
        fieldsJson.value[tab].forEach((field) => {
            field.value = permSet.has(field.field);
        });
    }
    if (hasPromotions.value) {
        const avaiables = avaiablesPromotions.value.map((p) => p.id);
        allPromotions.value.forEach((p) => {
            p.value = avaiables.includes(p.id);
        });
    }
};

// Pestaña dinámica "Otros": expone cualquier permiso de BD no curado (item #71)
const loadCatalog = async () => {
    const { permissions: catalog } = await getPermissionsCatalog();
    const { fields, accordions: accs } = buildUncategorizedTab(catalog);
    if (!fields.length) return;
    fieldsJson.value.otros = fields;
    accordions.value.otros = accs;
    if (!tabs.value.some((t) => t.ref === "otros")) {
        tabs.value.push({
            ref: "otros",
            active: false,
            title: "Otros / Sin categorizar",
        });
    }
    if (permissions.value.length) applyPermissions();
};

onMounted(loadCatalog);

const preparePermissionsData = () => {
    let permissionsData = [];
    for (const tabKey in fieldsJson.value) {
        fieldsJson.value[tabKey].forEach((field) => {
            if (field.value) {
                permissionsData.push(field.field);
            }
        });
    }
    return permissionsData;
};

const textButtonAll = ref("Agregar Todos");
const addAll = ref(false);

watch(addAll, (n) => {
    textButtonAll.value = n ? "Quitar Todos" : "Agregar Todos";
    for (const tabKey in fieldsJson.value) {
        fieldsJson.value[tabKey].forEach((field) => {
            field.value = n;
        });
    }
    if (hasPromotions.value) {
        allPromotions.value.forEach((p) => {
            p.value = n;
        });
    }
});

const updatePermissions = async () => {
    const permissionsToUpdate = preparePermissionsData();

    let response;
    if (props.entityType === "user") {
        response = await updatePermissionByUser(props.entityId, {
            permissions: permissionsToUpdate,
            promotions: allPromotions.value
                .filter((p) => p.value)
                .map((p) => p.id),
        });
    } else {
        response = await updatePermissionByRole(props.entityId, {
            permissions: permissionsToUpdate,
        });
    }

    if (response.status == 200) {
        Swal.fire("¡Actualizado!", response.message, "success");
        emit("update:showModal", false);
    } else {
        Swal.fire(
            "¡Error!",
            "Hubo un error al actulizar los permisos",
            "error"
        );
    }
};

const updateShow = (newValue) => {
    emit("update:showModal", newValue);
};
</script>

<style scoped>
.form-check-input {
    margin-right: 0.5rem;
}
</style>
