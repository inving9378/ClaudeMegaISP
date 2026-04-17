<template>
    <q-card class="my-card q-ml-xs" flat bordered>
        <q-item>
            <q-item-section>
                <q-item-label>Observaciones</q-item-label>
            </q-item-section>
            <q-item-section avatar v-if="editorReady">
                <q-btn
                    color="primary"
                    class="q-mr-sm"
                    label="Añadir"
                    no-caps
                    @click="
                        () => {
                            currentObject = null;
                            showForm = true;
                        }
                    "
                    v-if="hasAdd && !box.closed"
                />
            </q-item-section>
        </q-item>

        <q-separator />

        <q-card-section style="padding: 10px">
            <q-scroll-area style="height: 200px">
                <q-list dense separator v-if="shortedList.length > 0">
                    <q-item
                        v-for="o in shortedList"
                        :key="`observation-${o.id}`"
                    >
                        <q-item-section>
                            <q-item-label :lines="o.lines">
                                <span v-html="o.comment"></span>
                            </q-item-label>
                        </q-item-section>
                        <q-item-section avatar style="padding: 0">
                            <q-btn
                                :icon="o.lines === 2 ? 'add' : 'remove'"
                                flat
                                round
                                dense
                                size="12px"
                                @click="o.lines = o.lines === 2 ? 10000 : 2"
                            />
                        </q-item-section>
                        <q-item-section
                            avatar
                            style="padding: 0"
                            v-if="editorReady && !box.closed"
                        >
                            <q-btn
                                icon="edit"
                                flat
                                round
                                dense
                                color="primary"
                                size="12px"
                                @click="
                                    () => {
                                        currentObject = o;
                                        showForm = true;
                                    }
                                "
                                v-if="hasEdit"
                            />
                        </q-item-section>
                        <q-item-section
                            avatar
                            style="padding: 0"
                            v-if="!box.closed"
                        >
                            <q-btn
                                icon="delete"
                                flat
                                round
                                dense
                                color="danger"
                                size="12px"
                                :loading="o.loading"
                                @click="destroy(o)"
                                v-if="hasDelete"
                            />
                        </q-item-section>
                    </q-item>
                </q-list>
                <p
                    class="text-center"
                    v-else-if="!loading && rows.length === 0"
                >
                    No existen observaciones
                </p>
                <q-inner-loading
                    :showing="loading"
                    label="Obteniendo datos, por favor espere..."
                    label-class="text-primary"
                    label-style="font-size: 1.1em"
                />
            </q-scroll-area>
        </q-card-section>
    </q-card>

    <form-observation-component
        :box-id="box.id"
        :object="currentObject"
        :show="showForm"
        @hide="showForm = false"
        @created="(data) => rows.push(data)"
        @updated="onUpdateRow"
        v-if="!closing && !box.closed"
    />
</template>

<script setup>
import { onMounted, ref, computed, watch } from "vue";
import FormObservationComponent from "./FormObservationComponent.vue";
import {
    listObservations,
    destroyObservation,
} from "../../helper/cutObservations";
import { error500, message } from "../../../../../../helpers/toastMsg";
import { dom } from "../../../../../../../../public/plugins/quasar/js/quasar.umd.prod";
import Swal from "sweetalert2";

const props = defineProps({
    object: Object,
    box: Object,
    hasPermission: Object,
    closing: Boolean,
});

const rows = ref([]);
const editorReady = ref(false);
const loading = ref(false);
const showForm = ref(false);
const currentObject = ref(null);
const { ready } = dom;

onMounted(() => {
    list();
});

watch(
    () => props.box,
    () => {
        list();
    },
    {
        deep: true,
    }
);

const hasAdd = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_add_comments") ?? false
    );
});

const hasEdit = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_edit_comments") ?? false
    );
});

const hasDelete = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_delete_comments") ??
        false
    );
});

ready(function () {
    editorReady.value = true;
});

const shortedList = computed(() => {
    return rows.value.sort((a, b) => b.id - a.id);
});

const list = async () => {
    loading.value = true;
    let data = await listObservations(props.box.id);
    loading.value = false;
    if (data !== null) {
        rows.value = data;
    } else {
        rows.value = [];
    }
};

const onUpdateRow = (r) => {
    const row = rows.value.find((rr) => rr.id === r.id);
    if (row) {
        Object.assign(row, r);
    }
};

const destroy = async (object) => {
    Swal.fire({
        title: "Confirmación!",
        text: "Seguro que desea eliminar esta observación?",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Si",
        cancelButtonText: "No",
    }).then(async (result) => {
        if (result.isConfirmed) {
            object.loading = true;
            const result = await destroyObservation(object.id);
            if (result) {
                message("Observación eliminada correctamente");
                rows.value = rows.value.filter((r) => r.id !== object.id);
            } else {
                error500();
                object.loading = true;
            }
        }
    });
};
</script>
