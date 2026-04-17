<template>
    <div class="row p-2">
        <div class="col-md-3">
            <div
                class="nav flex-column nav-pills"
                role="tablist"
                id="v-pills-tab"
                aria-orientation="vertical"
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
                    :class="['tab-pane fade', { 'show active': tab.active }]"
                    :id="`v-pills-${tab.ref}`"
                    role="tabpanel"
                >
                    <h4 class="text-center">{{ tab.title }}</h4>

                    <div class="accordion" :id="`accordion-${tab.ref}`">
                        <div
                            v-for="(accordion, index) in accordions[tab.ref]"
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
                                    <div
                                        class="form-check form-switch form-switch-md mx-3 mb-3"
                                        v-for="perm in fieldsJson[
                                            tab.ref
                                        ].filter(
                                            (p) =>
                                                p.field === accordion.filter ||
                                                p.depend === accordion.filter
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { fieldsJson as importedFieldsJson } from "./helper/constants";
import { getPermissionsForRole } from "./helper/request";

const props = defineProps({
    roleId: Number,
});

const tabs = ref([
    { ref: "plan", active: true, title: "Planes" },
    { ref: "crm", active: false, title: "Clientes potenciales" },
    { ref: "client", active: false, title: "Clientes" },
    { ref: "seller", active: false, title: "Vendedores" },
    { ref: "ticket", active: false, title: "Tickets" },
    { ref: "finance", active: false, title: "Finanzas" },
    { ref: "maps", active: false, title: "Mapas" },
    { ref: "network", active: false, title: "Gestión de red" },
    { ref: "administration", active: false, title: "Administración" },
    { ref: "configuration", active: false, title: "Configuración" },
]);

const accordions = ref({
    plan: [
        /* { title: "Planes", filter: "plan_view_plan" }, */
        {
            title: "Listado de planes de internet",
            filter: "plan_view_internet",
        },
        { title: "Listado de planes de voz", filter: "plan_view_voz" },
        {
            title: "Listado de planes personalizados",
            filter: "plan_view_custom",
        },
        { title: "Listado de planes paquetes", filter: "plan_view_package" },
    ],
    crm: [{ title: "Clientes potenciales", filter: "crm_view_crm" }],
    client: [{ title: "Clientes", filter: "client_view_client" }],
    seller: [
        /* { title: "Vendedores", filter: "vendors" }, */
        { title: "Dashboard", filter: "vendors_dashboard" },
        {
            title: "Listado de vendedores",
            filter: "vendors",
        },
    ],
    ticket: [
        /* { title: "Ticket", filter: "ticket_view_ticket" }, */
        {
            title: "Dashboard",
            filter: "ticket_view_dashboard",
        },
        {
            title: "Listado de tickets nuevos/abiertos",
            filter: "ticket_view_abierto",
        },
        {
            title: "Listado de tickets cerrados",
            filter: "ticket_view_cerrado",
        },
        {
            title: "Listado de tickets reciclados",
            filter: "ticket_view_reciclado",
        },
    ],
    finance: [
        {
            title: "Listado de transacciones",
            filter: "finanzas_view_transacciones",
        },
        {
            title: "Listado de finanzas",
            filter: "finanzas_view_facturas",
        },
        {
            title: "Listado de finanzas",
            filter: "finanzas_view_pagos",
        },
    ],
    maps: [
        {
            title: "Listado de mapas",
            filter: "maps_view_list",
        },
    ],
    network: [
        /* {
            title: "Gestión de red",
            filter: "router_view_router",
        }, */
        {
            title: "Enrutadores",
            filter: "router_view_router",
        },
        {
            title: "Redes Ipv4",
            filter: "ipv4_view_ipv4",
        },
    ],
    administration: [
        {
            title: "Administración",
            filter: "administration_view_administration",
        },
    ],
    configuration: [
        {
            title: "Configuración",
            filter: "config_view_config",
        },
    ],
});

const fieldsJson = ref(importedFieldsJson);
const permissions = ref([]);

onMounted(async () => {
    await getPermissions();
});

const getPermissions = async () => {
    try {
        const response = await getPermissionsForRole(5);
        permissions.value = response.permissions;

        permissions.value.forEach((permission) => {
            for (const tab in fieldsJson.value) {
                fieldsJson.value[tab].forEach((field) => {
                    if (field.field === permission) {
                        field.value = true;
                    }
                });
            }
        });
    } catch (error) {
        console.log(error);
    }
};
</script>

<style scoped>
.form-check-input {
    margin-right: 0.5rem;
}
</style>
