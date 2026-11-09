<template>
    <div class="tc-wrap" :class="{ 'tc-dark': darkMode }">
        <Breadcrumb :list="breadcrumbList" />
        <q-card flat class="tc-card">
            <q-card-section
                class="d-flex tc-cardhead"
                style="justify-content: space-between; align-items: center"
            >
                <div class="tc-h1">Listado de vendedores</div>

                <a
                    href="/administracion/user/crear?role=vendedor"
                    class="tc-btn tc-btn-ok ms-auto"
                >
                    <i class="bi bi-plus-lg me-1"></i> Agregar Vendedor
                </a>
            </q-card-section>
            <q-table
                v-table-resizable="visibleColumns"
                :rows="data"
                :columns="visibleColumns"
                :filter="filter"
                :dark="darkMode"
                :rows-per-page-label="'Elementos por página'"
                :rows-per-page-options="rowPerPageOptions"
                v-model:pagination="pagination"
                binary-state-sort
                :loading="loading"
                no-data-label="No hay elementos para mostrar"
            >
                <template v-slot:top="props">
                    <div class="d-flex flex-wrap align-items-center gap-2 w-100">
                    <!-- Filtro de Estado (en el servidor). La estrella lo guarda como
                         predeterminado de ESTE usuario; otra vez, lo restablece. -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <label for="vendedores-filtro-estado" class="col-form-label me-1">Estado</label>
                        <select
                            id="vendedores-filtro-estado"
                            class="form-select form-select-sm"
                            style="width: 170px"
                            v-model="estado"
                        >
                            <option
                                v-for="opcion in OPCIONES_ESTADO"
                                :key="opcion.value"
                                :value="opcion.value"
                            >
                                {{ opcion.label }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="btn btn-sm btn-link px-1"
                            :title="textoEstrella"
                            :aria-label="textoEstrella"
                            @click="alternarPredeterminado"
                        >
                            <i
                                :class="[
                                    'mdi',
                                    esPredeterminado
                                        ? 'mdi-star text-warning'
                                        : 'mdi-star-outline text-muted',
                                ]"
                                style="font-size: 18px"
                            ></i>
                        </button>
                        <span
                            v-if="estado !== 'todos'"
                            class="badge bg-primary d-inline-flex align-items-center"
                            style="font-size: 12px"
                        >
                            Filtrando: {{ etiquetaEstado(estado) }}
                            <button
                                type="button"
                                class="btn-close btn-close-white ms-2"
                                style="font-size: 8px"
                                aria-label="Quitar el filtro de Estado"
                                title="Quitar el filtro de Estado"
                                @click="estado = 'todos'"
                            ></button>
                        </span>
                    </div>
                    <div class="d-flex align-items-center ms-auto">
                        <button
                            type="button"
                            class="tc-btn tc-btn-seg"
                            @click="showModal = true"
                            title="Mostrar/ocultar columnas"
                        >
                            <i class="bi bi-layout-three-columns"></i>
                        </button>
                        <q-btn
                            flat
                            round
                            dense
                            :icon="
                                props.inFullscreen
                                    ? 'fullscreen_exit'
                                    : 'fullscreen'
                            "
                            @click="props.toggleFullscreen"
                            class="q-ml-md"
                        />

                        <q-input
                            borderless
                            dense
                            v-model="filter"
                            placeholder="Buscar"
                            class="mb-0"
                            style="margin-left: 16px; border: 1px solid"
                            :dark="darkMode"
                        >
                        </q-input>
                    </div>
                    </div>
                </template>
                <template v-slot:body-cell-name="props">
                    <q-td :props="props">
                        <a
                            :href="
                                '/vendedores/' +
                                props.row.seller_id +
                                '/seguimiento-vendedor/' +
                                props.row.id
                            "
                            >{{ props.row.name }}</a
                        >
                    </q-td>
                </template>
                <template v-slot:body-cell-type="props">
                    <q-td :props="props">
                        <span :class="'badge-' + props.row.type">{{
                            props.row.type
                        }}</span>
                    </q-td>
                </template>
                <template v-slot:body-cell-status_seller="props">
                    <q-td :props="props">
                        <span :class="'badge-' + props.row.status_seller">{{
                            props.row.status_seller
                        }}</span>
                    </q-td>
                </template>
                <template v-slot:body-cell-balance="props">
                    <q-td :props="props">
                        $
                        <b>{{ props.row.balance }}</b>
                    </q-td>
                </template>
            </q-table>
        </q-card>

        <!-- Modal -->
        <modal
            :show="showModal"
            :size="'xs'"
            @update:show="showModal = $event"
            title="Mostrar columnas/Ocultar columnas"
        >
            <template #body>
                <div class="my-3">
                    <p>
                        Para mostrar los campos de la tabla, seleccione la
                        casilla de verificación correspondiente.
                    </p>
                </div>
                <div
                    class="form-check form-switch form-switch-md"
                    v-for="(column, index) in columns"
                    :key="index"
                >
                    <input
                        class="form-check-input"
                        type="checkbox"
                        v-model="column.visible"
                    />
                    <label class="form-check-label">{{ column.label }}</label>
                </div>
            </template>
            <template #footer>
                <button class="tc-btn tc-btn-ok" @click="saveColumnsTable">
                    Guardar
                </button>
            </template>
        </modal>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import Swal from "sweetalert2";
import { getAll } from "./helper/request.js";
import { usePreferenciasVista } from "../../../composables/usePreferenciasVista.js";
import { useTableUrlState } from "../../../hook/useTableUrlState";
import Modal from "../../../shared/ModalSimple.vue";
import Breadcrumb from "../../base/shared/Breadcrumb.vue";
import { darkMode } from "../../../hook/appConfig.js";
import { useDataTable } from "../../../composables/useDataTable.js";

const props = defineProps({
    id: Number,
    seller_id: Number,
});

const columns = ref([
    {
        name: "seller_id",
        required: true,
        label: "ID",
        align: "left",
        field: "seller_id",
        sortable: true,
        visible: true,
    },
    {
        name: "type",
        required: true,
        label: "Tipo",
        align: "left",
        field: "type",
        sortable: true,
        visible: true,
    },
    {
        name: "name",
        required: true,
        label: "Nombre",
        align: "left",
        field: "name",
        sortable: true,
        visible: true,
    },
    {
        name: "father_last_name",
        align: "left",
        label: "Apellido paterno",
        field: "father_last_name",
        sortable: true,
        visible: true,
    },
    {
        name: "mother_last_name",
        align: "left",
        label: "Apellido materno",
        field: "mother_last_name",
        sortable: true,
        visible: true,
    },
    {
        name: "address",
        align: "left",
        label: "Dirección",
        field: "address",
        sortable: true,
        visible: true,
    },
    {
        name: "city_municipality",
        align: "start",
        label: "Municipio",
        field: "city_municipality",
        sortable: true,
        visible: true,
    },
    {
        name: "state_country",
        align: "left",
        label: "Estado",
        field: "state_country",
        sortable: true,
        visible: true,
    },
    {
        name: "phone",
        align: "left",
        label: "Teléfono",
        field: "phone",
        sortable: true,
        visible: true,
    },
    {
        name: "balance",
        align: "right",
        label: "Saldo del vendedor",
        field: "balance",
        sortable: true,
        visible: true,
    },
    {
        name: "rfc",
        align: "center",
        label: "RFC",
        field: "rfc",
        sortable: true,
        visible: true,
    },
    {
        name: "status_seller",
        align: "left",
        label: "Status",
        field: "status_seller",
        sortable: true,
        visible: true,
    },
]);

const data = ref([]);
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const loading = ref(false);
const filter = ref("");
const showModal = ref(false);
const tableIdentifier = ref("vendedores");
const { getColumns, saveColumns } = useDataTable();

// La lista se pagina en el navegador: sin rowsNumber (con él Quasar la trataba como paginada en el
// servidor y el pie decía «1-10 de 10» con cualquier número de filas).
const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 50,
});

const breadcrumbList = ref([
    { title: "Dashboard", a: "/vendedores/dashboard" },
    { title: "Vendedores", a: "/vendedores" },
]);

// Filtro de Estado (#1199). Sin predeterminado guardado por el usuario: Activos. «Inactivos»
// incluye a quienes se les quitó el check de vendedor: conservan prospectos, ventas y comisiones.
const ESTADO_INICIAL = "activo";
const OPCIONES_ESTADO = [
    { label: "Todos", value: "todos" },
    { label: "Activos", value: "activo" },
    { label: "Inactivos", value: "inactivo" },
    { label: "Bloqueados", value: "bloqueado" },
];
const esOpcion = (valor) => OPCIONES_ESTADO.some((opcion) => opcion.value === valor);
const etiquetaEstado = (valor) =>
    OPCIONES_ESTADO.find((opcion) => opcion.value === valor)?.label ?? valor;

const estado = ref(ESTADO_INICIAL);
// Lo que el usuario dejó como predeterminado (null = no ha elegido).
const predeterminado = ref(null);
const preferencias = usePreferenciasVista("vendedores");

// Al volver de la ficha de un vendedor se conserva el filtro de esa entrada del historial
// (history.state, no la URL); manda sobre el predeterminado.
const tablaUrl = useTableUrlState({ parametros: [] });
const estadoDelHistorial = (() => {
    const { privado } = tablaUrl.leer();
    return esOpcion(privado.estado) ? privado.estado : null;
})();
watch(estado, () => tablaUrl.guardar({}, { estado: estado.value }));

// Hasta tener el predeterminado del usuario no se pide la lista (la primera ya sale filtrada).
let filtroListo = false;
watch(
    estado,
    () => {
        if (filtroListo) {
            pagination.value.page = 1;
            getSellers();
        }
    },
    { flush: "sync" }
);

onMounted(async () => {
    getColumnsTable();
    try {
        const guardadas = await preferencias.cargar();
        if (esOpcion(guardadas.estado)) predeterminado.value = guardadas.estado;
    } catch (error) {
        // Sin preferencias se trabaja con el valor inicial.
        window.console.error("No se pudieron leer las preferencias del listado:", error);
    }
    estado.value = estadoDelHistorial ?? predeterminado.value ?? ESTADO_INICIAL;
    filtroListo = true;
    getSellers();
});

const esPredeterminado = computed(
    () => predeterminado.value !== null && predeterminado.value === estado.value
);
const textoEstrella = computed(() =>
    esPredeterminado.value
        ? `Es tu predeterminado. Clic para restablecer (al entrar verás «${etiquetaEstado(ESTADO_INICIAL)}»)`
        : "Usar como predeterminado"
);

const avisar = (texto, icon = "success") =>
    Swal.fire({ toast: true, position: "top-end", timer: 3000, showConfirmButton: false, icon, title: texto });

const alternarPredeterminado = async () => {
    try {
        if (esPredeterminado.value) {
            await preferencias.restablecer("estado");
            predeterminado.value = null;
            avisar(`Estado: al entrar verás «${etiquetaEstado(ESTADO_INICIAL)}».`);
        } else {
            const valor = estado.value;
            await preferencias.guardar("estado", valor);
            predeterminado.value = valor;
            avisar(`Estado: al entrar verás «${etiquetaEstado(valor)}».`);
        }
    } catch (error) {
        avisar("No se pudo guardar el predeterminado.", "error");
    }
};

// Si llegan dos respuestas cruzadas (cambios rápidos de filtro), solo vale la última petición.
let ultimaPeticion = 0;

const getSellers = async () => {
    const peticion = ++ultimaPeticion;
    try {
        loading.value = true;
        const filas = await getAll({ estado: estado.value });
        if (peticion !== ultimaPeticion) return;
        data.value = filas;
        loading.value = false;
    } catch (error) {
        if (peticion === ultimaPeticion) loading.value = false;
        console.log(error);
    }
};

const getColumnsTable = async () => {
    try {
        const response = await getColumns(tableIdentifier.value);
        const storedColumns = response;

        if (storedColumns && storedColumns.length > 0) {
            columns.value.forEach((column) => {
                const storedColumn = storedColumns.find(
                    (col) => col.name === column.name
                );
                if (storedColumn) {
                    column.visible = storedColumn.visible;
                }
            });
        }
    } catch (error) {
        console.log(error);
    }
};

const saveColumnsTable = async () => {
    try {
        const columnsData = columns.value.map((col) => ({
            name: col.name,
            visible: col.visible,
        }));

        await saveColumns(tableIdentifier.value, columnsData);
        showModal.value = false;
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);
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
    max-width: 1160px;
    margin: 0 auto;
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

/* Tarjeta contenedora */
.tc-card {
    background: var(--tc-surface);
    border: 1px solid var(--tc-line);
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    color: var(--tc-ink);
    overflow: hidden;
}
.tc-cardhead {
    border-bottom: 1px solid var(--tc-line);
}
.tc-h1 {
    font-size: 18px;
    font-weight: 700;
    color: var(--tc-ink);
    margin: 0;
}

/* Botones estilo Torre */
.tc-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
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
.tc-btn-ok {
    background: var(--tc-accent);
    border-color: var(--tc-accent);
    color: #fff;
}
.tc-btn-ok:hover {
    filter: brightness(1.08);
    color: #fff;
}
.tc-btn-seg {
    color: var(--tc-info);
    border-color: var(--tc-info);
    background: transparent;
}

/* Que la q-table se funda con la tarjeta (surface/tokens de la Torre) */
.tc-card :deep(.q-table__container),
.tc-card :deep(.q-table__top),
.tc-card :deep(.q-table__bottom) {
    background: transparent;
    color: var(--tc-ink);
}
.tc-card :deep(.q-table thead th) {
    color: var(--tc-muted);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.03em;
}

/* Badges de tipo/estado con la paleta semántica de la Torre */
.badge-Interno,
.badge-Externo,
.badge-Distribuidor,
.badge-Activo,
.badge-Inactivo,
.badge-Bloqueado {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.6;
    border: 1px solid;
}
.badge-Interno {
    color: var(--tc-info);
    border-color: var(--tc-info);
    background: rgba(37, 99, 235, 0.1);
}
.badge-Externo {
    color: var(--tc-accent);
    border-color: var(--tc-accent);
    background: rgba(13, 148, 136, 0.1);
}
.badge-Distribuidor {
    color: var(--tc-slate);
    border-color: var(--tc-slate);
    background: rgba(100, 116, 139, 0.12);
}
.badge-Activo {
    color: var(--tc-ok);
    border-color: var(--tc-ok);
    background: rgba(22, 163, 74, 0.1);
}
.badge-Inactivo {
    color: var(--tc-warn);
    border-color: var(--tc-warn);
    background: rgba(217, 119, 6, 0.12);
}
.badge-Bloqueado {
    color: var(--tc-bad);
    border-color: var(--tc-bad);
    background: rgba(220, 38, 38, 0.1);
}
</style>
