<template>
    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2`"
    >
        <label
            :for="property.field"
            :class="`col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center`"
        >
            {{ property.label }}
        </label>
        <div class="col-sm-12 col-md-8 d-flex align-items-center">
            <button
                type="button"
                class="btn btn-outline-info"
                @click="showModal"
            >
                Mostrar
            </button>
        </div>
    </div>
    <div class="modal fade bs-example-modal-center"
         :id="`${property.field}InModal`"
         data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="staticBackdropLabel"
         tabindex="-1" style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Center modal</h5>
                    <button type="button" class="btn-close" @click="closeModal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <label
                            :for="property.field"
                            :class="`d-sm-block d-md-none col-sm-12 col-form-label pe-2`"
                        >
                            {{ property.label }}
                        </label>

                        <div class="input-group">
                            <label
                                :for="property.field"
                                :class="`d-md-block d-none col-md-3 col-form-label text-md-end pe-2 `"
                            >
                                {{ property.label }}
                            </label>
                            <div class="ms-1 input-group-text">
                                <span
                                    class="text-black cursor-pointer"
                                    :id="`${property.field}1`"
                                    @click="viewPassword"
                                >
                                    <i class="fa fa-eye"></i>
                                </span>
                            </div>
                            <input
                                :type="typeInput"
                                :id="property.field"
                                :name="property.field"
                                :placeholder="property.placeholder"
                                :aria-label="property.placeholder"
                                :aria-describedby="`${property.field}1`"
                                :class="'form-control col-sm-12 col-md-9'"
                                v-model="val"
                                :disabled="property.disabled"
                                autocomplete="off"
                            />
                            <div class="input-group-text">
                                <span
                                    class="text-black cursor-pointer"
                                    :id="`${property.field}1`"
                                    @click="generateRandomPassword"
                                >
                                    refresh
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-light waves-effect"
                        @click="closeModal"
                    >
                        Cerrar
                    </button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
</template>

<script>
import {onMounted, ref, watch} from "vue";
import {requestRandomPassword} from "../helpers/Request";
import Modal from "../helpers/modal";

export default {
    name: "InputPasswordInModal",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String,
    },
    emits: ['updateField'],
    setup(props, {emit}) {
        const val = ref(props.modelValue);
        const typeInput = ref("passwordModal");
        const modal = ref();

        onMounted(() => {
            modal.value = new Modal(`${props.property.field}InModal`);
        });

        const showModal = () => {
            typeInput.value = "password";
            modal.value.show();
        };


        const closeModal = () => {
            modal.value.hide();
        };

        const viewPassword = () => {
            typeInput.value = "text";
        };
        const generateRandomPassword = async () => {
            val.value = await requestRandomPassword();
        };

        watch(val, () => {
            emit("update-field", {value: val, field: props.property.field});
        });

        return {
            val,
            typeInput,
            showModal,
            viewPassword,
            generateRandomPassword,
            closeModal
        };
    },
};
</script>

<style scoped></style>
