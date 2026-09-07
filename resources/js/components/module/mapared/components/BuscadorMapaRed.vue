<template>
    <div class="buscador-mapa-red">
        <q-input
            v-model="texto"
            dense
            outlined
            bg-color="white"
            clearable
            placeholder="Buscar cliente, nodo, serie ONT..."
            @update:model-value="onInput"
            @clear="limpiar"
        >
            <template v-slot:prepend>
                <q-icon name="search" />
            </template>
            <template v-slot:append v-if="loading">
                <q-spinner size="20px" color="primary" />
            </template>
        </q-input>

        <q-card v-if="mostrarDropdown" class="buscador-mapa-red__dropdown">
            <q-list dense>
                <template v-if="loading">
                    <q-item>
                        <q-item-section class="text-caption text-grey">
                            Buscando…
                        </q-item-section>
                    </q-item>
                </template>
                <template v-else-if="!hayResultados">
                    <q-item>
                        <q-item-section class="text-caption text-grey">
                            Sin resultados para "{{ texto }}".
                        </q-item-section>
                    </q-item>
                </template>
                <template v-else>
                    <template v-for="grupo in GRUPOS" :key="grupo.clave">
                        <template v-if="resultados[grupo.clave]?.length">
                            <q-item-label header class="text-weight-bold">
                                {{ grupo.label }}
                            </q-item-label>
                            <q-item
                                v-for="item in resultados[grupo.clave]"
                                :key="`${grupo.clave}-${item.id}`"
                                clickable
                                v-ripple
                                @click="seleccionar(item, grupo.clave)"
                            >
                                <q-item-section>
                                    <q-item-label>{{ item.label }}</q-item-label>
                                    <q-item-label caption>
                                        {{ item.tipo || grupo.label }}
                                    </q-item-label>
                                </q-item-section>
                            </q-item>
                        </template>
                    </template>
                </template>
            </q-list>
        </q-card>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { debounce } from "lodash";
import { buscarEnMapa } from "../helper/buscar-request";

defineOptions({
    name: "BuscadorMapaRed",
});

const emit = defineEmits(["select"]);

// Orden fijo decidido (q2, item #9990535): Clientes -> Nodos -> Enlaces/otros.
// "onts" viene de mapared_enlaces_servicio (serie ONT) y se muestra como "Enlaces/otros".
const GRUPOS = [
    { clave: "clientes", label: "Clientes" },
    { clave: "nodos", label: "Nodos" },
    { clave: "onts", label: "Enlaces/otros" },
];

const texto = ref("");
const loading = ref(false);
const buscoAlMenosUnaVez = ref(false);
const resultados = ref({ nodos: [], clientes: [], onts: [] });

const hayResultados = computed(() =>
    GRUPOS.some((g) => (resultados.value[g.clave] || []).length > 0)
);

const mostrarDropdown = computed(
    () => (texto.value || "").trim().length >= 3 && buscoAlMenosUnaVez.value
);

const ejecutarBusqueda = async (q) => {
    loading.value = true;
    const respuesta = await buscarEnMapa(q);
    if (respuesta.ok) {
        resultados.value = {
            nodos: respuesta.data?.nodos || [],
            clientes: respuesta.data?.clientes || [],
            onts: respuesta.data?.onts || [],
        };
    } else {
        resultados.value = { nodos: [], clientes: [], onts: [] };
    }
    buscoAlMenosUnaVez.value = true;
    loading.value = false;
};

const buscarConDebounce = debounce(ejecutarBusqueda, 300);

const onInput = (valor) => {
    const q = (valor || "").trim();
    if (q.length < 3) {
        buscoAlMenosUnaVez.value = false;
        resultados.value = { nodos: [], clientes: [], onts: [] };
        return;
    }
    buscarConDebounce(q);
};

const limpiar = () => {
    texto.value = "";
    buscoAlMenosUnaVez.value = false;
    resultados.value = { nodos: [], clientes: [], onts: [] };
};

const seleccionar = (item, grupoClave) => {
    emit("select", {
        id: item.id,
        tipo: item.tipo || grupoClave,
        label: item.label,
        lat: item.lat,
        lng: item.lng,
    });
};
</script>

<style scoped>
.buscador-mapa-red {
    position: relative;
    width: 320px;
    max-width: 100%;
}

.buscador-mapa-red__dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 360px;
    overflow-y: auto;
    z-index: 5;
}
</style>
