<template>
    <q-table
        v-table-resizable="columns"
        :columns="columns"
        :rows="rows"
        :loading="loading"
        :dark="darkMode"
        wrap-cells
        row-key="id"
        loading-label="Obteniendo registros, por favor espere..."
        no-data-label="No existen registros disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="registros por página"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[20, 30, 50, 100]"
    >
        <template v-slot:top>
            <div class="row no-padding">
                <div class="col self-end text-right no-padding">
                    <q-btn
                        color="green"
                        class="q-mr-sm"
                        icon="add"
                        @click="() => onHandleClick(null, true)"
                        v-if="hasPermission.data.canView('client_edit_client')"
                        ><q-tooltip>Adicionar</q-tooltip></q-btn
                    >
                    <q-btn
                        color="primary"
                        class="q-mr-sm"
                        icon="history"
                        :loading="loading"
                        @click="onRequest"
                        ><q-tooltip>Recargar</q-tooltip></q-btn
                    >
                </div>
            </div>
        </template>
        <template v-slot:body-cell-status="props">
            <q-td class="text-center">
                <q-badge
                    label="Activa"
                    color="green"
                    v-if="props.row.status === 'active'"
                />
                <q-badge
                    label="Cancelada"
                    color="warning"
                    v-else-if="props.row.status === 'canceled'"
                />
                <q-badge label="Cerrada" color="negative" v-else />
            </q-td>
        </template>
        <template
            v-slot:body-cell-actions="props"
            v-if="hasPermission.data.canView('client_edit_client')"
        >
            <q-td class="text-center q-gutter-sm">
                <q-btn
                    icon="edit"
                    flat
                    size="sm"
                    round
                    dense
                    color="primary"
                    :disable="['canceled', 'closed'].includes(props.row.status)"
                    @click="() => onHandleClick(props.row, false)"
                >
                    <q-tooltip v-if="props.row.status === 'active'"
                        >Editar</q-tooltip
                    >
                </q-btn>
                <q-btn
                    icon="cancel"
                    flat
                    size="sm"
                    round
                    dense
                    color="negative"
                    :disable="['canceled', 'closed'].includes(props.row.status)"
                    @click="() => cancel(props.row.id)"
                >
                    <q-tooltip v-if="props.row.status === 'active'"
                        >Cancelar</q-tooltip
                    >
                </q-btn>
            </q-td>
        </template>
    </q-table>

    <q-dialog v-model="showDialog" persistent allow-focus-outside>
        <q-card style="width: 600px; max-width: 700vw">
            <dialog-header-component
                title="Especifique la oferta"
                @close="showDialog = false"
            />
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <q-form ref="form" greedy>
                    <div class="row q-my-sm">
                        <label class="col-12 col-sm-4 text-right col-form-label"
                            >Descarga actual</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                dense
                                outlined
                                readonly
                                v-model="formData.download_profile"
                                :dark="darkMode"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label class="col-12 col-sm-4 text-right col-form-label"
                            >Subida actual</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                dense
                                outlined
                                readonly
                                v-model="formData.upload_profile"
                                :dark="darkMode"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="current_download_profile"
                            >Promoción</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="promotion"
                                option-label="name"
                                option-value="id"
                                :required="true"
                                :model-value="formData.promotion"
                                :options="promotions"
                                @change="onChangePromotion"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="current_download_profile"
                            >Velocidad de descarga</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                dense
                                outlined
                                readonly
                                v-model="formData.current_download_profile"
                                :dark="darkMode"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="current_upload_profile"
                            >Velocidad de subida</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                dense
                                outlined
                                readonly
                                v-model="formData.current_upload_profile"
                                :dark="darkMode"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="end_at"
                            >Hasta</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <VueDatePicker
                                id="end_at"
                                v-model="formData.end_at"
                                position="right"
                                locale="es"
                                :teleport="true"
                                week-start="0"
                                :format="customFormat"
                                :enableTimePicker="false"
                                :dark="darkMode"
                                :disabled-dates="
                                    (d) => disableLastDates(d, true)
                                "
                                :readonly="!definedByUser"
                            >
                            </VueDatePicker>
                        </div>
                    </div>
                </q-form>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Guardar"
                    no-caps
                    color="primary"
                    class="q-mr-sm"
                    @click="save"
                />
                <q-btn
                    label="Cancelar"
                    no-caps
                    color="grey-7"
                    @click="showDialog = false"
                />
            </q-card-actions>
            <q-inner-loading
                :showing="saving"
                label="Procesando, por favor espere..."
                label-class="text-primary"
                label-style="font-size: 1.1em"
                :dark="darkMode"
            />
        </q-card>
    </q-dialog>
</template>
<script setup>
import { computed, onBeforeMount, onMounted, reactive, ref } from "vue";
import { darkMode } from "../../../hook/appConfig";
import { getNomenclatures } from "../olts/helper/request";
import {
    getPromotions,
    addPromotion,
    editPromotion,
    cancelPromotion,
} from "./helpers/helper";
import SelectFormComponent from "../olts/components/form/SelectFormComponent.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import { errorValidation, message } from "../../../helpers/toastMsg";
import { useDatePicker } from "../../../composables/useDatePicker";
import Permission from "../../../helpers/Permission";
import { allViewHasPermission } from "../../../helpers/Request";
import moment from "moment";
import { getAvaiablesPromotions } from "../adminstration/user/helper/request";
import DialogHeaderComponent from "../../../shared/DialogHeaderComponent.vue";

defineOptions({ name: "PromotionsComponent" });

const props = defineProps({
    clientId: Number | String,
});
const saving = ref(false);
const loading = ref(false);

const { customFormat, disableLastDates } = useDatePicker();

const columns = ref([
    {
        name: "before_download_profile",
        field: "before_download_profile",
        label: "Descarga (Pasado)",
        align: "left",
        sortable: true,
    },
    {
        name: "current_download_profile",
        field: "current_download_profile",
        label: "Descarga (Nuevo)",
        align: "left",
        sortable: true,
    },
    {
        name: "before_upload_profile",
        field: "before_upload_profile",
        label: "Subida (Pasado)",
        align: "left",
        sortable: true,
    },
    {
        name: "current_upload_profile",
        field: "current_upload_profile",
        label: "Subida (Nuevo)",
        align: "left",
        sortable: true,
    },
    {
        name: "created_at",
        field: "created_at",
        label: "Desde",
        align: "left",
        sortable: true,
        format: (val) => {
            return new moment(val).format("DD/MM/YYYY");
        },
        sort: (a, b, rowA, rowB) =>
            new Date(rowA.created_at) - new Date(rowB.created_at),
    },
    {
        name: "end_at",
        field: "end_at",
        label: "Hasta",
        align: "left",
        sortable: true,
        format: (val) => {
            return new moment(val).format("DD/MM/YYYY");
        },
        sort: (a, b, rowA, rowB) =>
            new Date(rowA.end_at) - new Date(rowB.end_at),
    },
    {
        name: "status",
        field: "status",
        label: "Estado",
        align: "center",
        sortable: true,
    },
]);
const rows = ref([]);
let current = null;
const showDialog = ref(false);
const speedProfiles = ref([]);
const form = ref(null);
const store = false;
const profile = ref(null);
const formData = ref({
    client_id: null,
    current_download_profile: null,
    current_upload_profile: null,
    end_at: null,
    download_profile: null,
    upload_profile: null,
});
const promotions = ref([]);
const definedByUser = ref(false);

const hasPermission = reactive({
    data: new Permission({}),
});

onBeforeMount(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    if (hasPermission.data.canView("client_edit_client")) {
        columns.value.push({
            name: "actions",
            field: "actions",
            label: "",
            align: "center",
            sortable: false,
        });
    }
});

onMounted(() => {
    onRequest();
    loadNomenclatures();
    loadPromotions();
});

const hasActive = computed(() => {
    return rows.value.find((r) => r.status === "active") ?? null;
});

const loadNomenclatures = async () => {
    if (speedProfiles.value.length === 0) {
        const result = await getNomenclatures(["speed_profiles"]);
        if (result) {
            speedProfiles.value = result.speed_profiles;
        }
    }
};

const loadPromotions = async () => {
    const result = await getAvaiablesPromotions("data_plan_promotion");
    if (result) {
        promotions.value = result.promotions.map((p) => p.promotionable);
    }
};

const onChangePromotion = (name, val) => {
    formData.value[name] = val;
    let promotion = promotions.value.find((p) => p.id === val);
    if (promotion) {
        formData.value.current_download_profile = promotion.download ?? null;
        formData.value.current_upload_profile = promotion.upload ?? null;
        if (promotion.defined_by_user) {
            definedByUser.value = true;
            formData.value.end_at = null;
        } else {
            formData.value.end_at = moment().add(
                promotion.duration,
                promotion.type_duration
            );
            definedByUser.value = false;
        }
    } else {
        formData.value.current_download_profile = null;
        formData.value.current_upload_profile = null;
        formData.value.end_at = null;
    }
};

const setDefaultData = (data) => {
    if (data) {
        formData.value = {
            client_id: props.clientId,
            current_download_profile: data?.current_download_profile ?? null,
            current_upload_profile: data?.current_upload_profile ?? null,
            end_at: data?.end_at ?? null,
            download_profile: data?.current_download_profile ?? null,
            upload_profile: data?.current_upload_profile ?? null,
        };
    } else {
        formData.value = {
            client_id: props.clientId,
            current_download_profile: null,
            current_upload_profile: null,
            end_at: data?.end_at ?? null,
            download_profile: profile.value?.download_speed ?? null,
            upload_profile: profile.value?.upload_speed ?? null,
        };
    }
};

const onHandleClick = (data = null) => {
    if (!data && hasActive.value) {
        message(
            "No se puede agregar una promoción mientras haya una activa",
            "info"
        );
        return;
    }
    setDefaultData(data);
    showDialog.value = true;
    current = data;
};

const onRequest = async () => {
    loading.value = true;
    const result = await getPromotions(props.clientId, {
        columns: columns.value.map((c) => c.name),
    });
    if (result) {
        rows.value = result.objects;
        profile.value = result.profile;
    }
    loading.value = false;
};

const save = () => {
    form.value.validate().then(async (success) => {
        if (success) {
            if (formData.value.end_at) {
                if (current) {
                    update();
                } else {
                    create();
                }
            } else {
                message("Debe especificar la fecha", "error");
            }
        } else {
            errorValidation();
        }
    });
};

const create = async () => {
    saving.value = true;
    const result = await addPromotion(formData.value);
    saving.value = false;
    if (result.success) {
        message(result.message, "success");
        showDialog.value = false;
        onRequest();
    } else {
        message(
            result.message ??
                result.error ??
                "Error al procesar esta solicitud",
            "error"
        );
    }
};

const update = async () => {
    saving.value = true;
    const result = await editPromotion(current.id, formData.value);
    saving.value = false;
    if (result.success) {
        message(result.message, "success");
        showDialog.value = false;
        onRequest();
    } else {
        message(
            result.message ??
                result.error ??
                "Error al procesar esta solicitud",
            "error"
        );
    }
};

const cancel = async (id) => {
    loading.value = true;
    const result = await cancelPromotion(id);
    loading.value = false;
    if (result.success) {
        message(result.message, "success");
        onRequest();
    } else {
        message(
            result.message ??
                result.error ??
                "Error al procesar esta solicitud",
            "error"
        );
    }
};
</script>
<style>
.dp__input_readonly {
    border-style: dashed !important;
}
</style>
