<template>
    <div class="q-pa-md" :class="darkMode ? 'bg-grey-10 text-white' : 'bg-white'">
        <q-tabs
            v-model="tab"
            dense
            class="q-mb-md"
            :class="darkMode ? 'text-white' : 'text-grey-8'"
            active-color="primary"
            indicator-color="primary"
            align="left"
        >
            <q-tab name="cables" label="Tipos de cable" />
            <q-tab name="conectores" label="Tipos de conector" />
            <q-tab name="cajas" label="Tipos de caja" />
            <q-tab name="splitters" label="Tipos de splitter" />
        </q-tabs>

        <q-tab-panels v-model="tab" animated :class="darkMode ? 'bg-grey-10 text-white' : 'bg-white'">
            <q-tab-panel v-for="recurso in Object.keys(catalogos)" :key="recurso" :name="recurso">
                <div class="row items-center justify-between q-mb-sm">
                    <div class="text-subtitle1">{{ catalogos[recurso].titulo }}</div>
                    <q-btn
                        v-if="puedeGestionar"
                        color="primary"
                        icon="add"
                        label="Nuevo"
                        no-caps
                        @click="abrirFormulario(recurso)"
                    />
                </div>

                <q-table
                    :rows="catalogos[recurso].filas"
                    :columns="catalogos[recurso].columnas"
                    row-key="id"
                    :loading="catalogos[recurso].cargando"
                    :pagination="{ rowsPerPage: 15 }"
                    flat
                    bordered
                    :dark="darkMode"
                >
                    <template v-slot:body-cell-acciones="props">
                        <q-td :props="props" class="text-right">
                            <q-btn
                                v-if="puedeGestionar"
                                flat
                                dense
                                round
                                icon="edit"
                                @click="abrirFormulario(recurso, props.row)"
                            />
                            <q-btn
                                v-if="puedeGestionar"
                                flat
                                dense
                                round
                                color="negative"
                                icon="delete"
                                @click="confirmarEliminar(recurso, props.row)"
                            />
                        </q-td>
                    </template>
                </q-table>
            </q-tab-panel>
        </q-tab-panels>

        <!-- Modal de alta/edición, un formulario por catálogo -->
        <q-dialog v-model="mostrarForm" persistent>
            <q-card style="min-width: 420px" :dark="darkMode">
                <q-card-section>
                    <div class="text-h6">
                        {{ formId ? "Editar" : "Nuevo" }} — {{ catalogos[formRecurso]?.titulo }}
                    </div>
                </q-card-section>

                <q-card-section class="q-gutter-sm">
                    <q-input v-model="form.nombre" label="Nombre *" dense outlined />

                    <template v-if="formRecurso === 'cables'">
                        <q-input v-model="form.fabricante" label="Fabricante" dense outlined />
                        <q-input v-model.number="form.numero_hilos" type="number" label="Número de hilos *" dense outlined />
                        <q-input v-model.number="form.hilos_por_buffer" type="number" label="Hilos por buffer" dense outlined />
                        <q-select
                            v-model="form.esquema_color"
                            :options="['eia_tia_598', 'abnt_nbr_14771']"
                            label="Esquema de color *"
                            dense
                            outlined
                        />
                        <div class="text-caption q-mt-sm">Atenuación (dB/km) — editable, no es constante</div>
                        <q-input v-model.number="form.atenuacion_1310" type="number" step="0.01" label="Ventana 1310nm" dense outlined />
                        <q-input v-model.number="form.atenuacion_1490" type="number" step="0.01" label="Ventana 1490nm" dense outlined />
                        <q-input v-model.number="form.atenuacion_1550" type="number" step="0.01" label="Ventana 1550nm" dense outlined />
                        <q-input v-model.number="form.precio" type="number" step="0.01" label="Precio" dense outlined />
                        <q-input v-model="form.unidad_medida" label="Unidad de medida" dense outlined />
                    </template>

                    <template v-else-if="formRecurso === 'conectores'">
                        <q-input v-model.number="form.perdida_db" type="number" step="0.01" label="Pérdida (dB)" dense outlined />
                    </template>

                    <template v-else-if="formRecurso === 'cajas'">
                        <q-input v-model.number="form.capacidad_puertos" type="number" label="Capacidad de puertos" dense outlined />
                        <q-input v-model.number="form.capacidad_fusiones" type="number" label="Capacidad de fusiones" dense outlined />
                        <q-input v-model="form.ip_rating" label="Clasificación IP" dense outlined />
                        <q-input v-model="form.ik_rating" label="Clasificación IK" dense outlined />
                    </template>

                    <template v-else-if="formRecurso === 'splitters'">
                        <q-input v-model="form.fabricante" label="Fabricante" dense outlined />
                        <q-toggle v-model="form.balanceado" label="Balanceado" />
                        <q-input v-model="form.ratio" label="Ratio (ej. 1:8)" dense outlined />
                        <q-input v-model.number="form.numero_puertos" type="number" label="Número de puertos *" dense outlined />
                        <q-select
                            v-model="form.tipo_conector_id"
                            :options="opcionesConector"
                            option-label="nombre"
                            option-value="id"
                            emit-value
                            map-options
                            clearable
                            label="Tipo de conector"
                            dense
                            outlined
                        />
                        <div class="text-caption q-mt-sm">Pérdidas (dB) — editables, no son constantes</div>
                        <q-input v-model.number="form.perdida_db" type="number" step="0.01" label="Pérdida balanceada" dense outlined />
                        <q-input v-model.number="form.perdida_paso_db" type="number" step="0.01" label="Pérdida de paso (desbalanceado)" dense outlined />
                        <q-input v-model.number="form.perdida_derivacion_db" type="number" step="0.01" label="Pérdida de derivación (desbalanceado)" dense outlined />
                        <q-input v-model.number="form.precio" type="number" step="0.01" label="Precio" dense outlined />
                    </template>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" @click="mostrarForm = false" />
                    <q-btn color="primary" label="Guardar" :loading="guardando" @click="guardar" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script setup>
// MR-08 Fase 3 (item roadmap #9990534) — UI de administración de los 4 catálogos del
// Mapa de Red (cables/conectores/cajas/splitters), consumiendo el CRUD real de la Fase 2
// (#9990536/#9990538, CatalogosController).
//
// Divergencia documentada respecto al spec original del item (que asumía 16 permisos
// mapa_red.catalogo.{tipo}.{accion} y soft-delete): la Fase 2 ya se construyó con el
// Alcance textual más específico de MR-08 (decisión registrada en el log de #9990504) —
// 2 permisos (mapa_red_catalogo_view/manage) y hard-delete con guard de referencias
// (422 si hay un cable/splitter físico usando el tipo). Esta UI consume ese contrato real,
// no el genérico. El gate de navegación (mapa_red_catalogo_view) se aplica en el Blade.
import { ref, reactive, computed, onMounted } from "vue";
import { useQuasar } from "../../../../../public/plugins/quasar/js/quasar.umd.prod";
import { darkMode } from "../../../hook/appConfig";
import {
    listarCatalogo,
    guardarCatalogo,
    eliminarCatalogo,
} from "./helper/catalogos-admin-request.js";

const props = defineProps({
    puedeGestionar: { type: Boolean, default: false },
});

const $q = useQuasar();
const tab = ref("cables");

const columnasBase = [
    { name: "nombre", label: "Nombre", field: "nombre", align: "left", sortable: true },
];

const catalogos = reactive({
    cables: {
        titulo: "Tipos de cable",
        cargando: false,
        filas: [],
        columnas: [
            ...columnasBase,
            { name: "numero_hilos", label: "Hilos", field: "numero_hilos", align: "right" },
            { name: "esquema_color", label: "Esquema de color", field: "esquema_color", align: "left" },
            { name: "precio", label: "Precio", field: "precio", align: "right" },
            { name: "acciones", label: "", field: "acciones", align: "right" },
        ],
    },
    conectores: {
        titulo: "Tipos de conector",
        cargando: false,
        filas: [],
        columnas: [
            ...columnasBase,
            { name: "perdida_db", label: "Pérdida (dB)", field: "perdida_db", align: "right" },
            { name: "acciones", label: "", field: "acciones", align: "right" },
        ],
    },
    cajas: {
        titulo: "Tipos de caja",
        cargando: false,
        filas: [],
        columnas: [
            ...columnasBase,
            { name: "capacidad_puertos", label: "Puertos", field: "capacidad_puertos", align: "right" },
            { name: "capacidad_fusiones", label: "Fusiones", field: "capacidad_fusiones", align: "right" },
            { name: "acciones", label: "", field: "acciones", align: "right" },
        ],
    },
    splitters: {
        titulo: "Tipos de splitter",
        cargando: false,
        filas: [],
        columnas: [
            ...columnasBase,
            { name: "ratio", label: "Ratio", field: "ratio", align: "left" },
            { name: "perdida_db", label: "Pérdida (dB)", field: "perdida_db", align: "right" },
            { name: "acciones", label: "", field: "acciones", align: "right" },
        ],
    },
});

const opcionesConector = computed(() => catalogos.conectores.filas);

const mostrarForm = ref(false);
const formRecurso = ref(null);
const formId = ref(null);
const guardando = ref(false);
const form = reactive({
    nombre: null,
    fabricante: null,
    numero_hilos: null,
    hilos_por_buffer: null,
    esquema_color: "eia_tia_598",
    atenuacion_1310: null,
    atenuacion_1490: null,
    atenuacion_1550: null,
    precio: null,
    unidad_medida: null,
    perdida_db: null,
    capacidad_puertos: null,
    capacidad_fusiones: null,
    ip_rating: null,
    ik_rating: null,
    balanceado: true,
    ratio: null,
    numero_puertos: null,
    tipo_conector_id: null,
    perdida_paso_db: null,
    perdida_derivacion_db: null,
});

const cargar = async (recurso) => {
    catalogos[recurso].cargando = true;
    const res = await listarCatalogo(recurso);
    catalogos[recurso].cargando = false;
    if (res.ok) {
        catalogos[recurso].filas = res.data;
    } else {
        $q.notify({ type: "negative", message: res.message });
    }
};

const cargarTodos = () => Object.keys(catalogos).forEach(cargar);

const resetForm = () => {
    Object.keys(form).forEach((k) => (form[k] = k === "esquema_color" ? "eia_tia_598" : k === "balanceado" ? true : null));
};

const abrirFormulario = (recurso, fila = null) => {
    resetForm();
    formRecurso.value = recurso;
    formId.value = fila?.id ?? null;

    if (fila) {
        Object.assign(form, fila);
        if (recurso === "cables" && fila.atenuacion_db_km) {
            const at = typeof fila.atenuacion_db_km === "string" ? JSON.parse(fila.atenuacion_db_km) : fila.atenuacion_db_km;
            form.atenuacion_1310 = at["1310"] ?? null;
            form.atenuacion_1490 = at["1490"] ?? null;
            form.atenuacion_1550 = at["1550"] ?? null;
        }
    }

    mostrarForm.value = true;
};

const payloadPorRecurso = (recurso) => {
    if (recurso === "cables") {
        return {
            nombre: form.nombre,
            fabricante: form.fabricante,
            numero_hilos: form.numero_hilos,
            hilos_por_buffer: form.hilos_por_buffer,
            esquema_color: form.esquema_color,
            atenuacion_db_km: {
                1310: form.atenuacion_1310,
                1490: form.atenuacion_1490,
                1550: form.atenuacion_1550,
            },
            precio: form.precio,
            unidad_medida: form.unidad_medida,
        };
    }
    if (recurso === "conectores") {
        return { nombre: form.nombre, perdida_db: form.perdida_db };
    }
    if (recurso === "cajas") {
        return {
            nombre: form.nombre,
            capacidad_puertos: form.capacidad_puertos,
            capacidad_fusiones: form.capacidad_fusiones,
            ip_rating: form.ip_rating,
            ik_rating: form.ik_rating,
        };
    }
    // splitters
    return {
        nombre: form.nombre,
        fabricante: form.fabricante,
        balanceado: !!form.balanceado,
        ratio: form.ratio,
        numero_puertos: form.numero_puertos,
        perdida_db: form.perdida_db,
        precio: form.precio,
        tipo_conector_id: form.tipo_conector_id,
        perdida_paso_db: form.perdida_paso_db,
        perdida_derivacion_db: form.perdida_derivacion_db,
    };
};

const guardar = async () => {
    guardando.value = true;
    const res = await guardarCatalogo(formRecurso.value, payloadPorRecurso(formRecurso.value), formId.value);
    guardando.value = false;

    if (!res.ok) {
        $q.notify({ type: "negative", message: res.message });
        return;
    }

    $q.notify({ type: "positive", message: "Guardado correctamente." });
    mostrarForm.value = false;
    cargar(formRecurso.value);
};

const confirmarEliminar = (recurso, fila) => {
    $q.dialog({
        title: "Eliminar",
        message: `¿Eliminar "${fila.nombre}" del catálogo? Esta acción no se puede deshacer.`,
        cancel: true,
        persistent: true,
    }).onOk(async () => {
        const res = await eliminarCatalogo(recurso, fila.id);
        if (!res.ok) {
            $q.notify({ type: "negative", message: res.message });
            return;
        }
        $q.notify({ type: "positive", message: "Eliminado." });
        cargar(recurso);
    });
};

onMounted(cargarTodos);
</script>

<style scoped>
/* Quasar nombra su propio wrapper interno de <q-tabs> con la clase "row" (su convención
   de utilidades flex, no la de Bootstrap) — Bootstrap's .row > * { width: 100% } choca con
   eso y estira cada pestaña a casi el ancho completo del contenedor. Fix acotado a este
   componente (no se toca bootstrap.min.css global): forzar el ancho por contenido. */
:deep(.q-tabs .q-tab) {
    width: auto !important;
    flex: 0 0 auto !important;
}
</style>
