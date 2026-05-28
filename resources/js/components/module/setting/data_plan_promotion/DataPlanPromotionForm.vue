<template>
    <q-dialog
        v-model="showDialog"
        persistent
        @before-show="setData"
        @show="onShow"
        @hide="onHide"
    >
        <q-card class="scroll" style="width: 480px">
            <dialog-header-component
                :icon="data ? 'edit' : 'add'"
                :title="`${data ? 'Editar' : 'Adicionar'} promoción`"
                closable
                @close="showDialog = false"
            />

            <q-card-section>
                <q-form greedy ref="formRef">
                    <div class="row">
                        <div class="col-md-3 text-right">
                            <label for="name" class="q-mt-sm">Nombre</label>
                        </div>
                        <div class="col-md-9">
                            <q-input
                                name="name"
                                v-model="formData.name"
                                dense
                                outlined
                                :error="hasError('name')"
                                :error-message="getError('name')?.join(', ')"
                                :rules="[(val) => !!val || 'Requerido']"
                                @update:model-value="() => removeError('name')"
                            />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 text-right">
                            <label for="name" class="q-mt-sm">Subida</label>
                        </div>
                        <div class="col-md-9">
                            <q-select
                                name="upload"
                                v-model="formData.upload"
                                :options="
                                    profiles.filter(
                                        (p) => p.direction === 'upload'
                                    )
                                "
                                option-value="name"
                                option-label="name"
                                dense
                                outlined
                                option-dense
                                emit-value
                                map-options
                                :error="hasError('upload')"
                                :error-message="getError('upload')?.join(', ')"
                                :rules="[(val) => !!val || 'Requerido']"
                                @update:model-value="
                                    () => removeError('upload')
                                "
                            />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 text-right">
                            <label for="name" class="q-mt-sm">Bajada</label>
                        </div>
                        <div class="col-md-9">
                            <q-select
                                name="download"
                                v-model="formData.download"
                                :options="
                                    profiles.filter(
                                        (p) => p.direction === 'download'
                                    )
                                "
                                option-value="name"
                                option-label="name"
                                dense
                                outlined
                                option-dense
                                emit-value
                                map-options
                                :error="hasError('download')"
                                :error-message="
                                    getError('download')?.join(', ')
                                "
                                :rules="[(val) => !!val || 'Requerido']"
                                @update:model-value="
                                    () => removeError('download')
                                "
                            />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 text-right">
                            <label for="name" class="q-mt-sm">Caducidad</label>
                        </div>
                        <template v-if="!formData.defined_by_user">
                            <div class="col-md-4">
                                <q-input
                                    name="duration"
                                    v-model.number="formData.duration"
                                    dense
                                    outlined
                                    :error="hasError('duration')"
                                    :error-message="
                                        getError('duration')?.join(', ')
                                    "
                                    :rules="[(val) => !!val || 'Requerido']"
                                    @update:model-value="
                                        () => removeError('duration')
                                    "
                                />
                            </div>
                            <div class="col-md-5">
                                <q-select
                                    name="download"
                                    v-model="formData.type_duration"
                                    :options="[
                                        { label: 'Días', value: 'day' },
                                        { label: 'Meses', value: 'month' },
                                    ]"
                                    dense
                                    outlined
                                    option-dense
                                    emit-value
                                    map-options
                                    :error="hasError('type_duration')"
                                    :error-message="
                                        getError('type_duration')?.join(', ')
                                    "
                                    :rules="[(val) => !!val || 'Requerido']"
                                    @update:model-value="
                                        () => removeError('type_duration')
                                    "
                                />
                            </div>
                        </template>
                        <div class="col-md-9 self-center" v-else>
                            <input
                                type="checkbox"
                                v-model="formData.defined_by_user"
                                id="defined_by_user"
                                style="width: 20px; height: 20px"
                            />
                            <label
                                for="defined_by_user"
                                class="q-ml-sm cursor-pointer absolute"
                                >Definido por el usuario</label
                            >
                        </div>
                    </div>
                    <div class="row" v-if="!formData.defined_by_user">
                        <div class="col-md-3 text-right"></div>
                        <div class="col-md-9">
                            <input
                                type="checkbox"
                                v-model="formData.defined_by_user"
                                id="defined_by_user"
                                style="width: 20px; height: 20px"
                            />
                            <label
                                for="defined_by_user"
                                class="q-ml-sm cursor-pointer absolute"
                                >Definido por el usuario</label
                            >
                        </div>
                    </div>
                </q-form>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    flat
                    no-caps
                    label="Cancelar"
                    color="negative"
                    @click="showDialog = false"
                />
                <q-btn
                    flat
                    no-caps
                    label="Guardar"
                    color="primary"
                    @click="onSave"
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
import { ref, watch } from "vue";
import DialogHeaderComponent from "../../../../shared/DialogHeaderComponent.vue";
import { useForm } from "../../../../composables/useForm";
import axios from "axios";
import { errorValidation, message } from "../../../../helpers/toastMsg";
import { darkMode } from "../../../../hook/appConfig";

const props = defineProps({
    data: Object,
    show: Boolean,
    profiles: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["hide", "created", "updated"]);

const showDialog = ref(false);
const { removeError, setErrors, hasError, hasErrors, getError, clearErrors } =
    useForm();

const saving = ref(false);
const formRef = ref(null);

const formData = ref({
    name: null,
    download: null,
    upload: null,
    duration: null,
    type_duration: null,
    defined_by_user: false,
});

watch(
    () => props.show,
    (n) => {
        showDialog.value = n;
    }
);

watch(
    () => formData.value.defined_by_user,
    () => {
        formData.value.duration = 0;
        formData.value.type_duration = null;
    }
);

const onUpdateField = (attrs) => {
    const { name, value } = attrs;
    form[name] = value;
};

const setData = () => {
    formData.value = {
        name: props.data?.name ?? null,
        download: props.data?.download ?? null,
        upload: props.data?.upload ?? null,
        duration: props.data?.duration ?? null,
        type_duration: props.data?.type_duration ?? null,
        defined_by_user: props.data?.defined_by_user ?? false,
    };
};

const onShow = async () => {};

const onHide = () => {
    clearErrors();
    emits("hide");
};

const onSave = async () => {
    formRef.value.validate().then((success) => {
        if (success) {
            if (props.data?.id) {
                update();
            } else {
                store();
            }
        } else {
            errorValidation();
        }
    });
};

const store = async () => {
    saving.value = true;
    axios
        .post("/configuracion/data-plan-promotions/store", formData.value)
        .then((res) => {
            emits("created", res.data);
            message("Promoción agregada correctamente", "success");
            showDialog.value = false;
        })
        .catch((res) => {
            const data = res.response.data;
            if (data.errors) {
                setErrors(data.errors);
                errorValidation();
            } else {
                message(res?.response?.data?.message ?? res.message, "error");
            }
        })
        .finally(() => {
            saving.value = false;
        });
};

const update = async () => {
    saving.value = true;
    axios
        .put(
            `/configuracion/data-plan-promotions/update/${props.data.id}`,
            formData.value
        )
        .then((res) => {
            message("Promoción modificada correctamente", "success");
            emits("updated", res.data);
            showDialog.value = false;
        })
        .catch((res) => {
            const data = res.response.data;
            if (data.errors) {
                setErrors(data.errors);
                errorValidation();
            } else {
                message(res.response.message, "error");
            }
        })
        .finally(() => {
            saving.value = false;
        });
};
</script>
