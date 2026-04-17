<template>
    <div class="accordion-item">
        <div
            id="panelsStayOpen-collapseThree"
            class="accordion-collapse collapse show"
            :aria-labelledby="`panelsStayOpen-${id}`"
        >
            <div class="accordion-body">
                <div class="row">
                    <div class="col-sm-4">
                        <SelectComponentWithCheckbox
                            :property="{
                                field: 'status',
                                label: 'Estado',
                                class_col: '',
                                options: getOptionsStatus(),
                                module_id: `${id}`,
                            }"
                            @change="clearError('inventory_item_type_id')"
                            :modelValue="[]"
                            :errors="dataForm.data.errors"
                            @update-field="setFilter"
                        />
                    </div>
                    <div class="col-sm-4">
                        <InputVuePickerMultiple
                            :property="{
                                field: 'date_sent',
                                label: 'Fecha Enviado',
                                class_field: 'col-sm-12 col-md-9',
                                class_label:
                                    'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                                placeholder: 'Fecha Enviado',
                            }"
                            @update-field="setFilter"
                            :modelValue="date"
                            :errors="dataForm.data.errors"
                            @change="clearError('date_sent')"
                        >
                        </InputVuePickerMultiple>
                    </div>
                    <div class="col-sm-4">
                        <InputVuePickerMultiple
                            :property="{
                                field: 'date_created',
                                label: 'Fecha Creado',
                                class_field: 'col-sm-12 col-md-9',
                                class_label:
                                    'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                                placeholder: 'Fecha Creado',
                            }"
                            @update-field="setFilter"
                            :modelValue="date_created"
                            :errors="dataForm.data.errors"
                            @change="clearError('date_created')"
                        >
                        </InputVuePickerMultiple>
                    </div>
                </div>

                <Datatable
                    :module="url_base"
                    :model="module"
                    list=""
                ></Datatable>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref, watch, reactive } from "vue";
import Datatable from "../../../../base/shared/Datatable.vue";
import SelectComponentWithCheckbox from "../../../../../shared/SelectComponentWithCheckbox.vue";
import { filters } from "../../../../../helpers/filters";
import Form from "../../../../../helpers/Form";
import InputVuePickerMultiple from "../../../../../shared/InputVuePickerMultiple.vue";
import Swal from "sweetalert2";

export default {
    name: "ContentConfig",
    props: {
        module: String,
        title: String,
        url_base: String,
        id: String,
    },
    components: {
        Datatable,
        SelectComponentWithCheckbox,
        InputVuePickerMultiple,
    },
    emits: [""],
    setup(props, { emit }) {
        const dataForm = reactive({
            data: new Form({}),
        });
        onMounted(() => {
            initComponent();
        });

        const date = ref("");
        const date_created = ref("");

        const initComponent = async () => {
            $(document).on("click", ".send_message", function () {
                let id = $(this).parent().attr("data-id");
                sendMessageIfAcepted(id);
            });
        };

        const setFilter = (obj) => {
            filters.value = {
                ...filters.value,
                [obj.field]: obj.value._value, // Asigna dinámicamente el valor al campo especificado
            };
        };

        const getOptionsStatus = () => {
            return {
                sent: "Enviado",
                pending: "Pendiente",
                failed: "Error",
            };
        };

        const sendMessageIfAcepted = (id) => {
            Swal.fire({
                title: "Enviar Mensaje",
                text: "Estas seguro de enviar el mensaje?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Enviar",
                cancelButtonText: "Cancelar",
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = new FormData();
                    form.append("id", id);
                    form.append("status", "sent");
                    console.log(form);
                    axios
                        .post(`/${props.url_base}/send_message`, form)
                        .then((response) => {
                            if (response.data.status == "success") {
                                Swal.fire(
                                    "Mensaje Enviado",
                                    "El mensaje ha sido enviado con exito",
                                    "success"
                                );
                            }
                        })
                        .catch((error) => {
                            console.log(error);
                        });
                }
            });
        };

        return {
            setFilter,
            filters,
            dataForm,
            getOptionsStatus,
            date,
            date_created,
        };
    },
};
</script>

<style scoped></style>
