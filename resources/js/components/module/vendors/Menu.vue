<template>
    <div class="tc-wrap" :class="{ 'tc-dark': darkMode }">
        <div class="d-flex justify-content-between align-items-center">
            <Breadcrumb :list="breadcrumbList" />
            <div class="d-flex gap-2 mb-3">
                <button
                    @click="goToPreviousSeller"
                    class="tc-btn tc-btn-seg"
                    :disabled="isFirstSeller"
                    title="Vendedor anterior"
                >
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button
                    @click="goToNextSeller"
                    class="tc-btn tc-btn-seg"
                    :disabled="isLastSeller"
                    title="Vendedor siguiente"
                >
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- #9990601 — QUIÉN se está viendo. Sin esto, abrir el panel del vendedor equivocado
             (o de uno dado de baja) se ve idéntico a que el módulo esté roto: tablas en blanco y
             ninguna pista. Pasó el 2026-09-08 y costó media hora de diagnóstico. -->
        <div
            v-if="seller_nombre"
            class="tc-sellerbar"
            :class="{ 'tc-sellerbar--warn': !seller_activo }"
        >
            <i class="bi bi-person-badge"></i>
            <strong>{{ seller_nombre }}</strong>
            <span class="tc-muted-txt"
                >· vendedor #{{ seller_id }} · usuario #{{ user_id }}</span
            >
            <span v-if="!seller_activo" class="tc-badge-warn ms-auto">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Vendedor {{ seller_estado || "inactivo" }} — es normal que no
                tenga movimientos
            </span>
        </div>

        <!-- Pestañas estilo Torre (nav-tabs + iconos bi) -->
        <ul class="nav nav-tabs tc-tabs">
            <li class="nav-item">
                <a
                    class="nav-link"
                    :class="{ active: activeTab === '#navs-pills-justified-information' }"
                    href="#"
                    @click.prevent="setActiveTab('#navs-pills-justified-information')"
                >
                    <i class="bi bi-person me-1"></i> Información
                </a>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link"
                    :class="{ active: activeTab === '#navs-pills-justified-prospects' }"
                    href="#"
                    @click.prevent="setActiveTab('#navs-pills-justified-prospects')"
                >
                    <i class="bi bi-people me-1"></i> Prospectos
                </a>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link"
                    :class="{ active: activeTab === '#navs-pills-justified-sales' }"
                    href="#"
                    @click.prevent="setActiveTab('#navs-pills-justified-sales')"
                >
                    <i class="bi bi-wallet2 me-1"></i> Ventas
                </a>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link"
                    :class="{ active: activeTab === '#navs-pills-justified-statistics' }"
                    href="#"
                    @click.prevent="setActiveTab('#navs-pills-justified-statistics')"
                >
                    <i class="bi bi-bar-chart me-1"></i> Estadísticas
                </a>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link"
                    :class="{ active: activeTab === '#navs-pills-justified-billing' }"
                    href="#"
                    @click.prevent="setActiveTab('#navs-pills-justified-billing')"
                >
                    <i class="bi bi-cash-stack me-1"></i> Facturación
                </a>
            </li>
            <li
                class="nav-item"
                v-if="hasPermission.data.canView('seller_cuts') && is_counter"
            >
                <a
                    class="nav-link"
                    :class="{ active: activeTab === '#navs-pills-justified-cutting' }"
                    href="#"
                    @click.prevent="setActiveTab('#navs-pills-justified-cutting')"
                >
                    <i class="bi bi-calendar-check me-1"></i> Corte mostrador
                </a>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link"
                    :class="{ active: activeTab === '#navs-pills-justified-inventory-items' }"
                    href="#"
                    @click.prevent="setActiveTab('#navs-pills-justified-inventory-items')"
                >
                    <i class="bi bi-box-seam me-1"></i> Artículos
                </a>
            </li>
        </ul>

        <!-- Paneles (montaje perezoso del activo, como q-tab-panels) -->
        <div class="tc-panel">
            <div v-if="activeTab === '#navs-pills-justified-information'">
                <InformationSeller :id="seller_id" />
            </div>
            <div v-if="activeTab === '#navs-pills-justified-prospects'">
                <ListProspects :id="user_id" />
            </div>
            <div v-if="activeTab === '#navs-pills-justified-sales'">
                <ListSales :id="user_id" />
            </div>
            <div v-if="activeTab === '#navs-pills-justified-statistics'">
                <Dashboard :id="user_id" :mediums_of_sales="mediums_of_sales" />
            </div>
            <div v-if="activeTab === '#navs-pills-justified-billing'">
                <Billing :user_id="user_id" :seller_id="seller_id" />
            </div>
            <div
                v-if="
                    activeTab === '#navs-pills-justified-cutting' &&
                    hasPermission.data.canView('seller_cuts') &&
                    is_counter
                "
            >
                <cuts-component
                    :seller-id="seller_id"
                    :user-id="user_id"
                    :sucursal-id="sucursal_id"
                    :is-counter="is_counter"
                    :has-permission="hasPermission"
                />
            </div>
            <div v-if="activeTab === '#navs-pills-justified-inventory-items'">
                <InventoryItemSeller v-if="user_id" :user_id="user_id" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from "vue";
import Breadcrumb from "../../base/shared/Breadcrumb.vue";
import InformationSeller from "./information/InformationSeller.vue";
import ListProspects from "./prospects/ListProspects.vue";
import ListSales from "./sales/Sales.vue";
import Dashboard from "./statistics/Dashboard.vue";
import Billing from "./billing/index.vue";
import CutsComponent from "./billing/components/cuts/CutsComponent.vue";
import { getAll } from "./helper/request";
import InventoryItemSeller from "../sellers/inventory_items/index.vue";
import { activeTab, setActiveTab, setUserId } from "../sellers/comun_variables";
import { darkMode } from "../../../hook/appConfig";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";

defineOptions({
    name: "Menu",
});

const props = defineProps({
    user_id: Number,
    seller_id: Number,
    sucursal_id: Number,
    is_counter: Boolean,
    // #9990601 — identidad y estado del vendedor que se está viendo.
    seller_nombre: { type: String, default: "" },
    seller_estado: { type: String, default: "" },
    seller_activo: { type: Boolean, default: true },
    mediums_of_sales: {
        type: Array,
        default: [],
    },
});

const hasPermission = reactive({
    data: new Permission({}),
});

const sellers = ref([]);

const breadcrumbList = ref([
    { title: "Dashboard", a: "/vendedores/dashboard" },
    { title: "Vendedores", a: "/sellers/seller" },
    { title: "", active: true },
]);

const isFirstSeller = computed(() => {
    const currentIndex = sellers.value.findIndex(
        (seller) => seller.seller_id === props.seller_id
    );
    return currentIndex === 0;
});

const isLastSeller = computed(() => {
    const currentIndex = sellers.value.findIndex(
        (seller) => seller.seller_id === props.seller_id
    );
    return currentIndex === sellers.value.length - 1;
});

onMounted(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    const savedTab = localStorage.getItem("activeTab");
    if (savedTab) {
        activeTab.value =
            !props.is_counter && savedTab === "#navs-pills-justified-cutting"
                ? "#navs-pills-justified-information"
                : savedTab;
    } else {
        setActiveTab("#navs-pills-justified-information");
    }
    getSellers();
    if (props.user_id) {
        setUserId(props.user_id);
    }
});

const getSellers = async () => {
    try {
        sellers.value = await getAll();
        updateBreadcrumb();
    } catch (error) {
        console.log(error);
    }
};

const updateBreadcrumb = () => {
    const seller = sellers.value.find(
        (seller) => seller.seller_id === props.seller_id
    );
    if (seller) {
        breadcrumbList.value[2] = {
            title: `${seller.name} ${seller.father_last_name} ${seller.mother_last_name} - ${seller.seller_id}`,
            active: true,
        };
    }
};

const goToPreviousSeller = () => {
    const currentIndex = sellers.value.findIndex(
        (seller) => seller.seller_id === props.seller_id
    );
    if (currentIndex > 0) {
        const previousSeller = sellers.value[currentIndex - 1];
        window.location.href = `/vendedores/${previousSeller.seller_id}/seguimiento-vendedor/${previousSeller.id}`;
    }
};

const goToNextSeller = () => {
    const currentIndex = sellers.value.findIndex(
        (seller) => seller.seller_id === props.seller_id
    );
    if (currentIndex < sellers.value.length - 1) {
        const nextSeller = sellers.value[currentIndex + 1];
        window.location.href = `/vendedores/${nextSeller.seller_id}/seguimiento-vendedor/${nextSeller.id}`;
    }
};
</script>

<style scoped>
/* ── Sistema visual de la Torre (mismos tokens --tc-* que TorreControl) ── */
.tc-wrap {
    --tc-surface: #ffffff;
    --tc-ink: #111827;
    --tc-muted: #6b7280;
    --tc-line: #e5e7eb;
    --tc-bg2: #f8fafc;
    --tc-ok: #16a34a;
    --tc-info: #2563eb;
    --tc-warn: #d97706;
    --tc-bad: #dc2626;
    --tc-slate: #64748b;
    --tc-accent: #0d9488;
    color: var(--tc-ink);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica,
        Arial, sans-serif;
}
.tc-wrap.tc-dark {
    --tc-surface: #151d2e;
    --tc-ink: #e8edf6;
    --tc-muted: #9aa7bd;
    --tc-line: #2a3550;
    --tc-bg2: #1b2436;
    --tc-ok: #22c55e;
    --tc-info: #60a5fa;
    --tc-warn: #f59e0b;
    --tc-bad: #f87171;
    --tc-slate: #94a3b8;
    --tc-accent: #2dd4bf;
}

/* Botones estilo Torre */
.tc-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid var(--tc-line);
    background: var(--tc-surface);
    color: var(--tc-ink);
    cursor: pointer;
    text-decoration: none;
    transition: filter 0.15s, background 0.15s;
}
.tc-btn:hover {
    filter: brightness(0.97);
}
.tc-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.tc-btn-seg {
    color: var(--tc-info);
    border-color: var(--tc-info);
    background: transparent;
}

/* Pestañas estilo Torre */
.tc-tabs {
    border-bottom: 1px solid var(--tc-line);
    gap: 2px;
    flex-wrap: wrap;
}
.tc-tabs .nav-link {
    color: var(--tc-muted);
    border: 1px solid transparent;
    border-bottom: none;
    border-radius: 8px 8px 0 0;
    padding: 8px 14px;
    font-weight: 600;
    font-size: 13px;
    background: transparent;
    cursor: pointer;
}
.tc-tabs .nav-link:hover {
    color: var(--tc-ink);
    background: var(--tc-bg2);
}
.tc-tabs .nav-link.active {
    color: var(--tc-accent);
    background: var(--tc-surface);
    border-color: var(--tc-line);
    border-bottom-color: var(--tc-surface);
    margin-bottom: -1px;
}

/* Contenedor de paneles */
.tc-panel {
    padding-top: 14px;
}

/* Barra de identidad del vendedor */
.tc-sellerbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 10px;
    margin-bottom: 10px;
    background: var(--tc-bg2);
    border: 1px solid var(--tc-line);
    color: var(--tc-ink);
}
.tc-sellerbar--warn {
    background: rgba(217, 119, 6, 0.1);
    border-color: var(--tc-warn);
}
.tc-muted-txt {
    color: var(--tc-muted);
    font-size: 12px;
}
.tc-badge-warn {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    color: var(--tc-warn);
    border: 1px solid var(--tc-warn);
    background: rgba(217, 119, 6, 0.12);
}
</style>
