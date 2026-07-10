<template>
    <modal
        :show="props.showModal"
        :size="'lg'"
        @update:show="updateShow"
        :title="'Modificar permisos de ' + props.entityName"
    >
        <template #body>
            <!-- Reforma de permisos B3: badge informativo + botón agregar/quitar todos -->
            <div
                class="d-flex justify-content-between align-items-center flex-wrap mb-2"
            >
                <span class="badge bg-info text-dark">
                    <i class="fas fa-user-shield me-1"></i>Asignación solo por rol
                </span>
                <button
                    class="btn btn-primary btn-sm"
                    @click="addAll = !addAll"
                >
                    {{ textButtonAll }}
                </button>
            </div>

            <div class="row g-3 perm-two-col">
                <!-- ===================== COLUMNA: PANEL ADMINISTRADOR ===================== -->
                <div class="col-12 col-lg-8 perm-col">
                    <h5 class="perm-col-title">
                        <i class="fas fa-desktop me-2"></i>Panel administrador
                    </h5>
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
                                    :class="[
                                        'nav-link mb-2',
                                        { active: tab.active },
                                    ]"
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

                                    <div
                                        class="accordion"
                                        :id="`accordion-${tab.ref}`"
                                    >
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
                                                        v-if="
                                                            tab.ref ===
                                                            'promotions'
                                                        "
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
                                                                    perm
                                                                        .promotionable
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
                                                                    (p.field ===
                                                                        accordion.filter ||
                                                                        p.depend ===
                                                                            accordion.filter) &&
                                                                    contextOf(
                                                                        p.field
                                                                    ) !==
                                                                        'portal'
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
                </div>

                <!-- ===================== COLUMNA: PORTAL COLABORADOR ===================== -->
                <div class="col-12 col-lg-4 perm-col perm-col-portal">
                    <h5 class="perm-col-title">
                        <i class="fas fa-users me-2"></i>Portal colaborador
                    </h5>
                    <p class="text-muted small mb-2">
                        Permisos de la app/portal del colaborador (Talento).
                    </p>
                    <div
                        v-if="!portalGroups.length"
                        class="text-muted small p-2"
                    >
                        Sin permisos de portal en el catálogo.
                    </div>
                    <div
                        v-for="grp in portalGroups"
                        :key="grp.title"
                        class="mb-3"
                    >
                        <div
                            class="fw-bold small text-uppercase text-muted mb-1"
                        >
                            {{ grp.title }}
                        </div>
                        <div
                            class="form-check form-switch form-switch-md mx-2 mb-2"
                            v-for="perm in grp.perms"
                            :key="perm.field"
                        >
                            <input
                                class="form-check-input"
                                type="checkbox"
                                :id="`portal-${perm.field}`"
                                v-model="perm.value"
                            />
                            <label
                                class="form-check-label"
                                :for="`portal-${perm.field}`"
                            >
                                {{ perm.label }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <template #footer>
            <div class="me-auto small text-muted perm-counter">
                <strong>{{ panelCount }}</strong> admin ·
                <strong>{{ portalCount }}</strong> colaborador
            </div>
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
 *
 * Reforma de permisos B3: la vista se divide en DOS COLUMNAS por `context`
 * (Panel administrador | Portal colaborador). La lógica de carga/guardado NO
 * cambia — solo la presentación: los checkboxes de ambas columnas se enlazan a
 * los MISMOS objetos de fieldsJson, así que preparePermissionsData los recoge
 * igual. El split se hace por el `context` que devuelve el catálogo.
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

// Reforma B3: mapa nombre_permiso => 'panel' | 'portal' (del catálogo).
const contextByName = ref({});
const contextOf = (name) =>
    contextByName.value[name] === "portal" ? "portal" : "panel";

// Columna derecha: permisos de context 'portal', agrupados por prefijo de módulo.
const portalGroups = computed(() => {
    const groups = {};
    for (const tab in fieldsJson.value) {
        fieldsJson.value[tab].forEach((f) => {
            if (contextOf(f.field) !== "portal") return;
            const prefix = f.field.includes(".")
                ? f.field.split(".")[0]
                : f.field.split("_")[0];
            (groups[prefix] = groups[prefix] || []).push(f);
        });
    }
    return Object.keys(groups)
        .sort()
        .map((k) => ({ title: k, perms: groups[k] }));
});

// Contadores del footer ("X admin · Y colaborador"): permisos ACTIVOS por contexto.
const countChecked = (ctx) => {
    let n = 0;
    for (const tab in fieldsJson.value) {
        fieldsJson.value[tab].forEach((f) => {
            if (f.value && contextOf(f.field) === ctx) n++;
        });
    }
    return n;
};
const panelCount = computed(() => countChecked("panel"));
const portalCount = computed(() => countChecked("portal"));

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
    const { permissions: catalog, contexts } = await getPermissionsCatalog();
    contextByName.value = contexts || {};
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
.perm-col-title {
    font-weight: 600;
    padding-bottom: 0.4rem;
    margin-bottom: 0.6rem;
    border-bottom: 2px solid var(--bs-border-color, #e5e7eb);
}
.perm-col-portal {
    border-left: 1px solid var(--bs-border-color, #e5e7eb);
}
/* En pantallas angostas (< lg) las columnas se apilan: Panel arriba, Portal abajo,
   y el separador pasa a ser superior en vez de lateral. */
@media (max-width: 991.98px) {
    .perm-col-portal {
        border-left: none;
        border-top: 1px solid var(--bs-border-color, #e5e7eb);
        padding-top: 0.75rem;
        margin-top: 0.5rem;
    }
}
.perm-counter strong {
    color: var(--bs-body-color, inherit);
}
</style>
