<template>
    <div class="modal-body m-0">
        <template v-for="val in fieldsJson">
            <ComponentFormDefault
                v-if="val.include"
                :id="id"
                :json="val"
                :errors="dataForm.data.errors"
                :key="val"
                v-model="dataForm.data[val.field]"
                @update-field="updateThisField"
                @clear-error="clearError"
            />
        </template>

        <div class="text-end">
            <button type="button" class="btn btn-outline-primary waves-effect waves-light" @click="calculate">Calcular
            </button>
        </div>

        <div class="mt-2 p-5" data-simplebar style="max-height: 352px;" v-if="calculateInfo.ip_address_with_network_size">
            <ul class="list-unstyled activity-wid mb-0">

                <li class="activity-list activity-border">
                    <div class="activity-icon avatar-md">
                        <span class="avatar-title bg-soft-warning text-warning rounded-circle">
                            <i class="mdi mdi-ip-network font-size-24"></i>
                        </span>
                    </div>
                    <div class="timeline-list-item">
                        <div class="d-flex" style="min-height: 40px;">
                            <div class="flex-grow-1 overflow-hidden me-4 align-self-center">
                                <h5 class="font-size-14 mb-1">Dirección IP:</h5>
                                <p class="text-truncate text-muted font-size-13">hexadecimal - {{ calculateInfo.ip_address.hex }}</p>
                            </div>
                            <div class="flex-shrink-0 text-end me-3 align-self-center">
                                <h6 class="mb-1">{{ calculateInfo.ip_address.quads }}</h6>
                                <div class="font-size-13">Tamaño de red - {{ calculateInfo.network_size }}</div>
                            </div>
                        </div>
                    </div>
                </li>

                <li class="activity-list activity-border">
                    <div class="activity-icon avatar-md">
                        <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                            <i class="mdi mdi-family-tree font-size-24"></i>
                        </span>
                    </div>
                    <div class="timeline-list-item">
                        <div class="d-flex" style="min-height: 40px;">
                            <div class="flex-grow-1 overflow-hidden me-4 align-self-center">
                                <h5 class="font-size-14 mb-1">Clase:</h5>
                                <p class="text-truncate text-muted font-size-13">{{ ipClass[calculateInfo.network_size] }}</p>
                            </div>
                            <div class="flex-shrink-0 text-end me-3 align-self-center">
                                <h6 class="mb-1">Red:</h6>
                                <div class="font-size-13">{{ calculateInfo.network_portion.quads }}</div>
                            </div>
                        </div>
                    </div>
                </li>

                <li class="activity-list activity-border">
                    <div class="activity-icon avatar-md">
                        <span class="avatar-title bg-soft-warning text-warning rounded-circle">
                            <i class="mdi mdi-account-network font-size-24"></i>
                        </span>
                    </div>
                    <div class="timeline-list-item">
                        <div class="d-flex" style="min-height: 40px;">
                            <div class="flex-grow-1 overflow-hidden me-4 align-self-center">
                                <h5 class="font-size-14 mb-1">Rango IP:</h5>
                                <p class="text-truncate text-muted font-size-13">cantidad de ip - {{ calculateInfo.number_of_ip_addresses }}</p>
                            </div>
                            <div class="flex-shrink-0 text-end me-3 align-self-center">
                                <div class="d-flex"><h6 class="mb-1">Primer IP:</h6><span class="ms-1">{{ calculateInfo.min_host }}</span></div>
                                <div class="d-flex"><h6 class="mb-1">Ultimo IP:</h6><span class="ms-1">{{ calculateInfo.max_host }}</span></div>
                            </div>
                        </div>
                    </div>
                </li>

                <li class="activity-list">
                    <div class="activity-icon avatar-md">
                        <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                            <i class="mdi mdi-drama-masks font-size-24"></i>
                        </span>
                    </div>
                    <div class="timeline-list-item">
                        <div class="d-flex" style="min-height: 40px;">
                            <div class="flex-grow-1 overflow-hidden me-4 align-self-center">
                                <h5 class="font-size-14 mb-1">Mascara de subred:</h5>
                                <p class="text-truncate text-muted font-size-13">hexadecimal - {{ calculateInfo.subnet_mask.hex }}</p>
                            </div>
                            <div class="flex-shrink-0 text-end me-3 align-self-center">
                                <h6 class="mb-1">{{ calculateInfo.subnet_mask.quads }}</h6>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
    <div class="modal-footer">
        <a
            class="btn btn-primary mr-3"
            href="javascript:void(0)"
            @click="useThisRed"
        >
            Utilice esta red
        </a>
        <a
            class="btn btn-secondary"
            href="javascript:void(0)"
            @click="closeModal"
        >
            Cerrar
        </a>
    </div>
</template>

<script>
import {onMounted, reactive, ref, watch} from "vue";
import {requestFieldsByModule} from "../../../helpers/Request";
import Form from "../../../helpers/Form";
import ComponentFormDefault from "../../ComponentFormDefault";

export default {
    name: "IpCalculator",
    components: {ComponentFormDefault},
    emits: ["close-modal", "use-this-red"],
    props: {
        id: {
            type: String,
            default: null,
        }
    },
    setup(props, {emit}) {
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const calculateInfo = ref([]);
        const ipClass = {
            '8': 'Clase A', '16': 'Clase B', '24': 'Clase C', '32': 'Clase C',
            '9': 'Clase A', '17': 'Clase B', '25': 'Clase C',
            '10': 'Clase A', '18': 'Clase B', '26': 'Clase C',
            '11': 'Clase A', '19': 'Clase B', '27': 'Clase C',
            '12': 'Clase A', '20': 'Clase B', '28': 'Clase C',
            '13': 'Clase A', '21': 'Clase B', '29': 'Clase C',
            '14': 'Clase A', '22': 'Clase B', '30': 'Clase C',
            '15': 'Clase A', '23': 'Clase B', '31': 'Clase C',
        };

        onMounted(() => {
            initComponent();
        });

        const initComponent = async () => {
            await getfieldsJson("Ipv4Calculator");
        }

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);
        };

        const updateThisField = ({field, value}) => {
            dataForm.data[field] = value;
        };

        const clearError = ({field}) => {
            dataForm.data.errors.clear(field);
        };

        const closeModal = () => {
            emit('close-modal');
        }

        const useThisRed = () => {
            emit('use-this-red', dataForm.data);
        }

        const calculate = () => {
            dataForm.data
                .submit("post", "/red/ipv4/calculator", "calculator")
                .then((response) => {
                    calculateInfo.value = response
                });
        }

        return {
            fieldsJson,
            dataForm,
            updateThisField,
            clearError,
            closeModal,
            calculate,
            calculateInfo,
            ipClass,
            useThisRed
        }
    }
}
</script>

<style scoped>

</style>
