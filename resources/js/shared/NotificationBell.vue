<template>
    <div class="dropdown d-inline-block">
        <button type="button" class="btn header-item noti-icon position-relative"
            id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
            aria-expanded="false">
            <i data-feather="bell" class="icon-lg"></i>
            <span v-if="count > 0"
                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger hdr-badge hdr-badge-corner">
                {{ count }}
            </span>
        </button>
        <div v-if="count > 0" class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
            aria-labelledby="page-header-notifications-dropdown">
            <notification-topbar :notifications="notificationsJson"></notification-topbar>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";

defineOptions({ name: "NotificationBell" });

// #9990615 — auto-refresco por polling, sin recargar la página.
const INTERVALO_MS = 30000;
const ENDPOINT = "/notifications/count";

const props = defineProps({
    // Lista inicial ya renderizada por el servidor (JSON string), para no esperar
    // al primer poll y no duplicar la carga que ya hizo el controlador.
    initial: { type: String, default: "[]" },
});

const notifications = ref([]);
const count = computed(() => notifications.value.length);
const notificationsJson = computed(() => JSON.stringify(notifications.value));

let timer = null;

const parseInitial = () => {
    try {
        const parsed = JSON.parse(props.initial);
        notifications.value = Array.isArray(parsed) ? parsed : Object.values(parsed || {});
    } catch (e) {
        notifications.value = [];
    }
};

async function poll() {
    try {
        const { data } = await window.axios.get(ENDPOINT);
        notifications.value = data.notifications || [];
    } catch (e) {
        // Silencioso: la campana nunca debe romper el header.
    }
}

function iniciarPolling() {
    if (timer) return;
    timer = setInterval(poll, INTERVALO_MS);
}

function detenerPolling() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

function onVisibilityChange() {
    if (document.hidden) {
        detenerPolling();
    } else {
        poll();
        iniciarPolling();
    }
}

onMounted(() => {
    parseInitial();
    iniciarPolling();
    document.addEventListener("visibilitychange", onVisibilityChange);
});

onUnmounted(() => {
    detenerPolling();
    document.removeEventListener("visibilitychange", onVisibilityChange);
});
</script>
