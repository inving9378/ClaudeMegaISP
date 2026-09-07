<template>
    <div class="empalmes-panel">
        <div v-if="loading" class="text-caption text-grey">Cargando…</div>

        <template v-else>
            <table v-if="empalmes && empalmes.length" class="empalmes-panel__table">
                <thead>
                    <tr>
                        <th>Hilo A</th>
                        <th>Extremo B</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="empalme in empalmes" :key="empalme.id">
                        <td>{{ hiloALabel(empalme) }}</td>
                        <td>{{ extremoBLabel(empalme) }}</td>
                        <td>{{ TIPO_LABELS[empalme.tipo] || empalme.tipo }}</td>
                        <td>{{ empalme.fecha }}</td>
                        <td>
                            <q-btn
                                icon="delete"
                                flat
                                round
                                dense
                                size="sm"
                                color="negative"
                                @click="confirmarEliminar(empalme)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="text-caption text-grey q-mb-sm">
                Sin uniones de hilos registradas en este elemento.
            </div>

            <q-btn
                no-caps
                flat
                dense
                icon="add"
                color="primary"
                label="Nueva unión"
                @click="abrirDialogo"
            />
        </template>

        <q-dialog v-model="dialogoAbierto" persistent>
            <q-card style="width: 420px; max-width: 90vw">
                <q-card-section class="q-pa-none">
                    <q-item>
                        <q-item-section><h6>Nueva unión de hilos</h6></q-item-section>
                        <q-item-section avatar>
                            <q-btn icon="close" flat round dense @click="cerrarDialogo" />
                        </q-item-section>
                    </q-item>
                </q-card-section>

                <q-separator />

                <q-card-section>
                    <div class="text-caption text-grey q-mb-sm">
                        Cable A (lado izquierdo)
                    </div>
                    <q-input
                        v-model.number="form.cable_a_id"
                        type="number"
                        dense
                        outlined
                        label="ID del cable A"
                    />

                    <q-option-group
                        v-model="modo"
                        :options="MODO_OPCIONES"
                        color="primary"
                        inline
                        dense
                        class="q-mt-sm"
                    />

                    <template v-if="modo === 'hilo'">
                        <div class="text-caption text-grey q-mt-sm">
                            Cable B (lado derecho)
                        </div>
                        <q-input
                            v-model.number="form.cable_b_id"
                            type="number"
                            dense
                            outlined
                            label="ID del cable B"
                        />
                    </template>
                    <template v-else>
                        <div class="text-caption text-grey q-mt-sm">
                            Splitter (lado derecho)
                        </div>
                        <q-input
                            v-model.number="form.splitter_id"
                            type="number"
                            dense
                            outlined
                            label="ID del splitter"
                        />
                    </template>

                    <q-btn
                        no-caps
                        dense
                        color="secondary"
                        label="Buscar hilos disponibles"
                        class="q-mt-sm"
                        :loading="buscando"
                        @click="buscarDisponibles"
                    />

                    <template v-if="disponibles">
                        <q-select
                            v-model="form.hilo_a_id"
                            :options="opcionesHilosA"
                            emit-value
                            map-options
                            dense
                            outlined
                            label="Hilo A"
                            class="q-mt-sm"
                        />
                        <q-select
                            v-if="modo === 'hilo'"
                            v-model="form.extremo_b_id"
                            :options="opcionesHilosB"
                            emit-value
                            map-options
                            dense
                            outlined
                            label="Hilo B"
                            class="q-mt-sm"
                        />
                        <q-select
                            v-else
                            v-model="form.extremo_b_id"
                            :options="opcionesPuertosB"
                            emit-value
                            map-options
                            dense
                            outlined
                            label="Puerto del splitter"
                            class="q-mt-sm"
                        />
                    </template>

                    <q-select
                        v-model="form.tipo"
                        :options="TIPO_OPCIONES"
                        emit-value
                        map-options
                        dense
                        outlined
                        label="Tipo de empalme"
                        class="q-mt-sm"
                    />
                    <q-input
                        v-model="form.fecha"
                        type="date"
                        dense
                        outlined
                        label="Fecha"
                        class="q-mt-sm"
                    />
                    <q-input
                        v-model.number="form.perdida_db"
                        type="number"
                        step="0.01"
                        dense
                        outlined
                        label="Pérdida dB (vacío = por catálogo)"
                        class="q-mt-sm"
                    />
                </q-card-section>

                <q-separator />
                <q-card-actions align="right">
                    <q-btn no-caps flat color="primary" label="Cancelar" @click="cerrarDialogo" />
                    <q-btn
                        no-caps
                        color="primary"
                        label="Unir"
                        :loading="guardando"
                        :disable="!puedeGuardar"
                        @click="guardar"
                    />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import Swal from "sweetalert2";
import { message } from "../../../../../helpers/toastMsg";
import {
    getEmpalmesExistentes,
    getHilosDisponibles,
    crearEmpalme,
    eliminarEmpalme,
} from "../../helper/empalmes-request";

defineOptions({
    name: "EmpalmesPanel",
});

const props = defineProps({
    elementoContenedorType: {
        type: String,
        required: true,
    },
    elementoContenedorId: {
        type: [Number, String],
        required: true,
    },
});

const MAPA_RED_HILO = "App\\Modules\\Addons\\MapaRed\\Models\\MapaRedHilo";
const MAPA_RED_PUERTO = "App\\Modules\\Addons\\MapaRed\\Models\\MapaRedPuerto";
const MAPA_RED_SPLITTER = "App\\Modules\\Addons\\MapaRed\\Models\\MapaRedSplitter";

const TIPO_LABELS = {
    fusion: "Fusión",
    mecanico: "Mecánico",
    conectorizado: "Conectorizado",
};

const TIPO_OPCIONES = [
    { label: "Fusión", value: "fusion" },
    { label: "Mecánico", value: "mecanico" },
    { label: "Conectorizado", value: "conectorizado" },
];

const MODO_OPCIONES = [
    { label: "Otro hilo", value: "hilo" },
    { label: "Puerto de splitter", value: "puerto" },
];

const empalmes = ref(null);
const loading = ref(false);

const cargarExistentes = async () => {
    loading.value = true;
    empalmes.value = await getEmpalmesExistentes(
        props.elementoContenedorType,
        props.elementoContenedorId
    );
    loading.value = false;
};

onMounted(cargarExistentes);

const hiloALabel = (empalme) =>
    empalme.hilo_a
        ? `Cable ${empalme.hilo_a.cable_id} · buffer ${empalme.hilo_a.buffer} · hilo ${empalme.hilo_a.numero}`
        : `Hilo #${empalme.hilo_a_id}`;

const extremoBLabel = (empalme) => {
    if (!empalme.extremo_b) return `#${empalme.extremo_b_id}`;
    if (empalme.extremo_b_type === MAPA_RED_HILO) {
        return `Cable ${empalme.extremo_b.cable_id} · buffer ${empalme.extremo_b.buffer} · hilo ${empalme.extremo_b.numero}`;
    }
    return `Puerto ${empalme.extremo_b.numero ?? empalme.extremo_b.id}`;
};

const confirmarEliminar = (empalme) => {
    Swal.fire({
        title: "¿Eliminar esta unión?",
        text: "El empalme quedará marcado como eliminado.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const respuesta = await eliminarEmpalme(empalme.id);
        if (respuesta.ok) {
            message("Unión eliminada correctamente.");
            cargarExistentes();
        } else {
            message(respuesta.message, "error");
        }
    });
};

const dialogoAbierto = ref(false);
const modo = ref("hilo");
const disponibles = ref(null);
const buscando = ref(false);
const guardando = ref(false);

const formInicial = () => ({
    cable_a_id: null,
    cable_b_id: null,
    splitter_id: null,
    hilo_a_id: null,
    extremo_b_id: null,
    tipo: "fusion",
    fecha: new Date().toISOString().slice(0, 10),
    perdida_db: null,
});

const form = ref(formInicial());

const abrirDialogo = () => {
    form.value = formInicial();
    modo.value = "hilo";
    disponibles.value = null;
    dialogoAbierto.value = true;
};

const cerrarDialogo = () => {
    dialogoAbierto.value = false;
};

const buscarDisponibles = async () => {
    if (!form.value.cable_a_id) {
        message("Captura el ID del cable A.", "error");
        return;
    }

    const params = { cable_a_id: form.value.cable_a_id };

    if (modo.value === "hilo") {
        if (!form.value.cable_b_id) {
            message("Captura el ID del cable B.", "error");
            return;
        }
        params.cable_b_id = form.value.cable_b_id;
    } else {
        if (!form.value.splitter_id) {
            message("Captura el ID del splitter.", "error");
            return;
        }
        params.puertable_type = MAPA_RED_SPLITTER;
        params.puertable_id = form.value.splitter_id;
    }

    buscando.value = true;
    form.value.hilo_a_id = null;
    form.value.extremo_b_id = null;
    disponibles.value = await getHilosDisponibles(params);
    buscando.value = false;
};

const opcionesHilosA = computed(() =>
    (disponibles.value?.hilos_a || []).map((hilo) => ({
        label: `Buffer ${hilo.buffer} · hilo ${hilo.numero}`,
        value: hilo.id,
    }))
);

const opcionesHilosB = computed(() =>
    (disponibles.value?.hilos_b || []).map((hilo) => ({
        label: `Buffer ${hilo.buffer} · hilo ${hilo.numero}`,
        value: hilo.id,
    }))
);

const opcionesPuertosB = computed(() =>
    (disponibles.value?.puertos_b || []).map((puerto) => ({
        label: `Puerto ${puerto.numero} (${puerto.rol})`,
        value: puerto.id,
    }))
);

const puedeGuardar = computed(
    () => !!form.value.hilo_a_id && !!form.value.extremo_b_id && !!form.value.tipo && !!form.value.fecha
);

const guardar = async () => {
    guardando.value = true;
    const payload = {
        hilo_a_id: form.value.hilo_a_id,
        extremo_b_type: modo.value === "hilo" ? MAPA_RED_HILO : MAPA_RED_PUERTO,
        extremo_b_id: form.value.extremo_b_id,
        elemento_contenedor_type: props.elementoContenedorType,
        elemento_contenedor_id: props.elementoContenedorId,
        tipo: form.value.tipo,
        fecha: form.value.fecha,
        perdida_db: form.value.perdida_db || null,
    };

    const respuesta = await crearEmpalme(payload);
    guardando.value = false;

    if (respuesta.ok) {
        message("Unión creada correctamente.");
        dialogoAbierto.value = false;
        cargarExistentes();
    } else {
        message(respuesta.message, "error");
    }
};
</script>

<style scoped>
.empalmes-panel {
    font-size: 12.5px;
}

.empalmes-panel__table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
}

.empalmes-panel__table th,
.empalmes-panel__table td {
    text-align: left;
    padding: 3px 4px;
    border-bottom: 1px solid rgba(128, 128, 128, 0.2);
    font-size: 12px;
}
</style>
