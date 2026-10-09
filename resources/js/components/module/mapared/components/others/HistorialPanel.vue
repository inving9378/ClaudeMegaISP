<template>
    <div class="historial-panel">
        <div v-if="loading && !registros.length" class="text-caption text-grey">
            Cargando…
        </div>

        <template v-else>
            <div v-if="registros.length" class="historial-panel__lista">
                <div
                    v-for="registro in registros"
                    :key="registro.id"
                    class="historial-panel__item"
                >
                    <q-badge :color="colorAccion(registro.accion)" class="historial-panel__badge">
                        {{ textoAccion(registro.accion) }}
                    </q-badge>
                    <div class="historial-panel__detalle">
                        <div class="text-caption">{{ diffResumen(registro) }}</div>
                        <div class="text-caption text-grey">
                            {{ formatearFecha(registro.created_at) }} ·
                            {{ nombreUsuario(registro.usuario) }}
                        </div>
                    </div>
                </div>

                <q-btn
                    v-if="hayMasPaginas"
                    flat
                    dense
                    no-caps
                    size="sm"
                    color="primary"
                    label="Cargar más"
                    :loading="loading"
                    class="q-mt-xs"
                    @click="cargarMas"
                />
            </div>
            <div v-else class="text-caption text-grey">
                Sin cambios registrados todavía para este elemento.
            </div>
        </template>
    </div>
</template>

<script setup>
// MR-23 fase 4d-C (item roadmap #9990561) — sección "Historial" en la ficha del elemento,
// mismo patrón que FotosPanel.vue (props tipo+id, carga en onMounted, estado vacío/loading).
import { ref, onMounted } from "vue";
import { getHistorialLayer } from "../../helper/historial-request";

defineOptions({
    name: "HistorialPanel",
});

const props = defineProps({
    id: {
        type: [Number, String],
        required: true,
    },
});

const registros = ref([]);
const loading = ref(false);
const paginaActual = ref(1);
const hayMasPaginas = ref(false);

const ACCION_LABEL = {
    crear: "Creado",
    editar: "Editado",
    eliminar: "Eliminado",
    mover: "Movido",
};

const ACCION_COLOR = {
    crear: "positive",
    editar: "primary",
    eliminar: "negative",
    mover: "warning",
};

const textoAccion = (accion) => ACCION_LABEL[accion] || accion;
const colorAccion = (accion) => ACCION_COLOR[accion] || "grey";

const nombreUsuario = (usuario) => {
    if (!usuario) return "Sistema";
    return [usuario.name, usuario.father_last_name, usuario.mother_last_name]
        .filter(Boolean)
        .join(" ");
};

const formatearFecha = (fecha) => {
    if (!fecha) return "";
    try {
        return new Date(fecha).toLocaleString("es-MX", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    } catch (e) {
        return fecha;
    }
};

const diffResumen = (registro) => {
    if (registro.accion === "crear") return "Elemento creado";
    if (registro.accion === "eliminar") return "Elemento eliminado";
    if (registro.campo) {
        const anterior = registro.valor_anterior ?? "(vacío)";
        const nuevo = registro.valor_nuevo ?? "(vacío)";
        return `${registro.campo}: ${anterior} → ${nuevo}`;
    }
    return textoAccion(registro.accion);
};

const cargar = async (pagina = 1) => {
    loading.value = true;
    const res = await getHistorialLayer(props.id, pagina);
    loading.value = false;

    if (!res.ok) return;

    const payload = res.data;
    // Laravel paginate(): { data: [...], current_page, last_page, ... }
    const nuevos = payload?.data ?? [];
    registros.value = pagina === 1 ? nuevos : [...registros.value, ...nuevos];
    paginaActual.value = payload?.current_page ?? pagina;
    hayMasPaginas.value = (payload?.current_page ?? 1) < (payload?.last_page ?? 1);
};

const cargarMas = () => cargar(paginaActual.value + 1);

onMounted(() => cargar(1));
</script>

<style scoped>
.historial-panel__item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px solid rgba(128, 128, 128, 0.15);
}

.historial-panel__item:last-child {
    border-bottom: none;
}

.historial-panel__badge {
    margin-top: 2px;
    flex-shrink: 0;
}

.historial-panel__detalle {
    min-width: 0;
    word-break: break-word;
}
</style>
