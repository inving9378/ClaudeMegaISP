<template>
    <!-- Solo aparece cuando hay pendientes: su presencia = hay trabajo. -->
    <div class="dropdown d-inline-block" v-if="count > 0">
        <button
            type="button"
            class="btn header-item noti-icon position-relative"
            @click="goToQueue"
            :title="`Pagos por conciliar: ${count} pendiente(s)`"
        >
            <i class="mdi mdi-cash-multiple icon-lg" style="font-size: 20px;"></i>
            <span class="badge bg-danger rounded-pill">{{ count }}</span>
        </button>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";

defineOptions({ name: "ConciliacionBell" });

const props = defineProps({
    // Endpoint de conteo (gateado por conciliacion.manage server-side).
    endpoint:    { type: String, required: true },
    // A dónde va el clic (la cola). Full-load (la cola usa data-spa-skip).
    queueUrl:    { type: String, required: true },
    // Intervalo de polling en segundos (configurable). Mínimo 15.
    pollSeconds: { type: Number, default: 45 },
});

const count = ref(0);
let timer = null;

async function fetchCount() {
    try {
        const { data } = await window.axios.get(props.endpoint);
        count.value = Number(data.count ?? 0);
    } catch (e) {
        // Silencioso: la campana nunca debe romper la topbar.
    }
}

function goToQueue() {
    window.location.href = props.queueUrl;
}

onMounted(() => {
    fetchCount(); // primer conteo inmediato
    // Vive en el topbar Vue app, que monta UNA vez y spa-nav no remonta →
    // el intervalo no se duplica al navegar entre pantallas.
    timer = setInterval(fetchCount, Math.max(15, props.pollSeconds) * 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer); // defensivo (el topbar rara vez se desmonta)
});
</script>
