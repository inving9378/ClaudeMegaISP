<template>
    <div
        class="modal fade"
        id="modalpayment"
        data-backdrop="static"
        data-keyboard="false"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="m-auto">{{ title }}</h6>
                </div>
                <div class="modal-body m-0">
                    <form
                        method="POST"
                        @submit.prevent="onSubmit"
                        @change="dataForm.data.errors.clear($event.target.name)"
                        @keydown="
                            dataForm.data.errors.clear($event.target.name)
                        "
                    >
                        <div
                            class="alert alert-info alert-dismissible fade show mb-2 text-center"
                            role="alert"
                            v-if="activePromisePayment == 1"
                        >
                            <strong>Promesas de Pago Activas</strong>
                        </div>

                        <div
                            class="alert alert-info alert-dismissible fade show mb-2"
                            role="alert"
                            v-if="costAllService"
                        >
                            <strong>Costo de todos los Servicios: </strong>
                            <span
                            >{{ costAllService }}
                                <span v-if="promotions"
                                >(Con promoción incluida)</span
                                ></span
                            >
                            <template v-if="promotions">
                                <br/>
                                <b class="customer-name-wrapper me-1">
                                    Servicios con Promociones Activas:
                                </b>
                                <span class="customer-billing-balance-title">
                                    <template
                                        v-for="(
                                            [url, index], i
                                        ) in Object.entries(promotions)"
                                        :key="index"
                                    >
                                        <a :href="url" target="_blank">{{
                                                index
                                            }}</a>
                                        <span
                                            v-if="
                                                i <
                                                Object.entries(promotions)
                                                    .length -
                                                    1
                                            "
                                        >,
                                        </span>
                                        <span v-else>.</span>
                                    </template>
                                </span>
                            </template>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                                aria-label="Close"
                            ></button>
                        </div>

                        <div
                            class="alert alert-info alert-dismissible fade show mb-2"
                            role="alert"
                        >
                            <strong>Vencimiento del Servicio</strong> -
                            {{ activeServiceExpiration }}
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                                aria-label="Close"
                            ></button>
                        </div>

                        <!--
                            FASE PAGOS 2b — Selector de método controlado.
                            El campo dinámico payment_method_id (SelectComponent + Choices.js)
                            no reaccionaba al cambiar de método; aquí se pinta un <select>
                            controlado nativo de Vue (el nativo se oculta en getfieldsJson/
                            getfieldsEdited con include=false, pero sigue viajando en el POST
                            porque escribimos dataForm.data.payment_method_id).
                        -->
                        <div class="col-12 row mb-2">
                            <label
                                class="col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center"
                            >
                                Método de pago
                            </label>
                            <div class="col-sm-12 col-md-9">
                                <select
                                    class="form-control"
                                    v-model="selectedMethodId"
                                    @change="onMethodChange"
                                >
                                    <option
                                        v-for="m in paymentMethods"
                                        :key="m.id"
                                        :value="m.id"
                                    >
                                        {{ m.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!--
                            FASE PAGOS 2b — Campos propios del método (declarativos).
                            Se pintan/ocultan según el método activo (activeMethodFields)
                            y se limpian al cambiar de método (onMethodChange).
                        -->
                        <template
                            v-for="f in activeMethodFields"
                            :key="f.key"
                        >
                            <div class="col-12 row mb-2">
                                <label
                                    class="col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center"
                                >
                                    {{ f.label }}
                                </label>
                                <div class="col-sm-12 col-md-9">
                                    <input
                                        v-if="f.type === 'text'"
                                        type="text"
                                        class="form-control"
                                        v-model.trim="dataForm.data[f.key]"
                                        :placeholder="f.label"
                                    />
                                    <select
                                        v-else-if="f.type === 'tecnico'"
                                        class="form-control"
                                        v-model="dataForm.data.tecnico_id"
                                    >
                                        <option :value="null">
                                            Seleccione un técnico...
                                        </option>
                                        <option
                                            v-for="t in tecnicos"
                                            :key="t.id"
                                            :value="t.id"
                                        >
                                            {{ t.name }}
                                        </option>
                                    </select>
                                    <input
                                        v-else-if="f.type === 'file'"
                                        type="file"
                                        name="file"
                                        accept="image/*"
                                        class="form-control"
                                        @change="onMethodFileChange"
                                    />
                                </div>
                            </div>
                        </template>

                        <template v-for="val in fieldsJson">
                            <ComponentFormDefault
                                v-if="val.include"
                                :id="idClient"
                                :json="val"
                                :errors="dataForm.data.errors"
                                :key="val"
                                v-model="dataForm.data[val.field]"
                                @update-field="updateThisField"
                                @clear-error="clearError"
                            />
                        </template>

                        <div class="col-12 row mb-2">
                            <label
                                class="col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center"
                            >
                                Periodo(s)
                            </label>

                            <div class="col-sm-12 col-md-9">
                                <div
                                    v-for="(period, index) in selectedPeriods"
                                    :key="index"
                                    class="d-flex align-items-center mb-2"
                                >
                                    <input
                                        type="text"
                                        class="form-control"
                                        :value="period"
                                        disabled
                                    />
                                </div>
                                <small v-if="!selectedPeriods.length" class="text-muted">
                                    Ingrese un monto que cubra al menos un periodo.
                                </small>
                            </div>
                        </div>
                        <div v-show="showImage" class="image resize">
                            <img
                                style="width: -webkit-fill-available"
                                :src="`${getShowImage}`"
                                :data-zoom="`${getShowImage}`"
                                @dblclick="changeDrift"
                            />
                            <p></p>
                        </div>

                        <div class="form-group text-center">
                            <a
                                class="btn btn-secondary me-3"
                                href="javascript:void(0)"
                                @click="closeModal"
                            >
                                Cerrar
                            </a>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="
                                    dataForm.data.errors.any() || disabledButton
                                "
                            >
                                Aplicar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {reactive, ref, watch, onMounted, onBeforeMount, computed} from "vue";
import {
    getActiveServiceExpiration,
    getCostAllService,
    getIsClientPromisePayment,
    getPromotions,
    getAvailablePeriodsByClient,
    getTecnicos,
} from "../helpers/request";
import ComponentFormDefault from "../../../../ComponentFormDefault";
import {
    requestEditedFieldsById,
    requestFieldsByModule,
} from "../../../../../helpers/Request";
import Form from "../../../../../helpers/Form";
import Drift from "drift-zoom";
import {changeBalance} from "../../info/comun_variable";
import {debounce} from "lodash";
import {hideLoading, showLoading} from "../../../../../helpers/loading";

export default {
    name: "ClientCrudPayment",
    props: {
        module: {
            type: String,
            default: null,
        },
        idClient: {
            type: String,
            default: null,
        },
        action: String,
    },
    components: {ComponentFormDefault},
    setup(props, {emit}) {
        const title = ref("Crear Pago");
        const fieldsJson = ref({});

        // FASE PAGOS 2b — métodos seleccionables en esta pantalla manual.
        // OpenPay (8) y Tarjeta OpenPay (9) NO se incluyen: llegan solo por
        // webhook y capturarlos aquí duplicaría el pago.
        const BASE_METHODS = [
            { id: 1, label: "Efectivo en Caja" },
            { id: 2, label: "Transferencia Bancaria" },
            { id: 5, label: "Oxxo" },
            { id: 7, label: "Pago a técnico" },
        ];
        const selectedMethodId = ref(1);
        // En edición, un pago viejo puede tener un método fuera de los 4 (p.ej.
        // PayPal/OpenPay). No lo perdemos: lo agregamos como opción transitoria.
        const paymentMethods = computed(() => {
            const list = [...BASE_METHODS];
            if (
                selectedMethodId.value != null &&
                !list.some((m) => m.id == selectedMethodId.value)
            ) {
                list.unshift({
                    id: selectedMethodId.value,
                    label: `Método actual (#${selectedMethodId.value})`,
                });
            }
            return list;
        });

        // FASE PAGOS 2b — Config DECLARATIVA de campos por método.
        // Estructura reusable: el mismo motor lo usará la IA para poblar los
        // campos propios de cada método. Para agregar/ajustar un método solo se
        // toca este objeto (no hay condicionales dispersos por el template).
        //   type: 'text'    → input de texto  (dataForm.data[key])
        //         'tecnico' → selector de técnico (dataForm.data.tecnico_id)
        //         'file'    → comprobante/foto (dataForm.data.file)
        const PAYMENT_METHOD_FIELDS = {
            // 1 Efectivo en Caja: sin campos extra.
            1: [],
            // 2 Transferencia Bancaria.
            2: [
                { key: "clave_rastreo", label: "Clave de rastreo (SPEI)", type: "text", required: true },
                { key: "titular", label: "Titular (ordenante)", type: "text", required: true },
                { key: "banco_origen", label: "Banco origen", type: "text", required: true },
                { key: "file", label: "Comprobante (foto)", type: "file", required: true },
            ],
            // 5 Oxxo (el monto es lo recibido, NO incluye comisión Oxxo).
            5: [
                { key: "referencia_oxxo", label: "Referencia / folio de venta", type: "text", required: true },
                { key: "file", label: "Comprobante (foto)", type: "file", required: true },
            ],
            // 7 Pago a técnico.
            7: [
                { key: "tecnico_id", label: "Técnico", type: "tecnico", required: true },
                { key: "file", label: "Foto (opcional)", type: "file", required: false },
            ],
        };
        // Llaves de DATOS que no existen en field_modules: se inyectan en
        // fieldsJson (include:false) para que Form.uploadFile las serialice.
        const EXTRA_FIELD_KEYS = [
            "clave_rastreo",
            "titular",
            "banco_origen",
            "referencia_oxxo",
            "tecnico_id",
        ];
        // Todas las llaves gestionadas por método (incluye el file existente).
        const ALL_METHOD_KEYS = [...EXTRA_FIELD_KEYS, "file"];

        const tecnicos = ref([]);
        const activeMethodFields = computed(
            () => PAYMENT_METHOD_FIELDS[selectedMethodId.value] || []
        );

        const dataForm = reactive({
            data: new Form({}),
        });
        const costAllService = ref(0);
        const activeServiceExpiration = ref(0);
        const showImage = ref(false);
        const getShowImage = ref("");
        const drift = ref();
        const driftDisbled = ref(true);
        const disabledButton = ref(false);
        const activePromisePayment = ref(true);

        const promotions = ref(null);

        const showButtonPeriod = ref(false);
        const loading = ref(false);

        const PERIODS = ref([]);

        const costPerPeriod = computed(() => {
            return Number(costAllService.value) || 0;
        });

        const getCurrentPeriod = () => {
            const now = new Date();
            return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
        };
        const selectedPeriods = ref([getCurrentPeriod()]);

        const nextPeriod = (period) => {
            if (!period) return null;
            const [year, month] = period.split("-").map(Number);
            const date = new Date(year, month - 1);
            date.setMonth(date.getMonth() + 1);

            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
        };

        const isConsecutive = (previous, current) => {
            return nextPeriod(previous) === current;
        };

        onMounted(() => {
            drift.value = new Drift($("div.image > img")[0], {
                paneContainer: $("div.image > p")[0],
            });
            drift.value.disable();
            initComponent(props.action);
        });

        const changeDrift = () => {
            driftDisbled.value = !driftDisbled.value;
            driftDisbled.value ? drift.value.disable() : drift.value.enable();
        };

        watch(
            () => props.action,
            (action, actionBefore) => {
                initComponent(action);
            }
        );

        const ifDisabledEnabledPaymentPromiseFieldSetToNullCourtDateFields = (
            enabled_payment_promise
        ) => {
            if (enabled_payment_promise === false) {
                let elementsToRemoveFromFormData = [
                    "first_court_date",
                    "second_court_date",
                    "third_court_date",
                ];
                elementsToRemoveFromFormData.forEach((element) => {
                    dataForm.data[element] = null;
                });
            }
        };

        watch(dataForm, () => {
            ifDisabledEnabledPaymentPromiseFieldSetToNullCourtDateFields(
                dataForm.data.enabled_payment_promise
            );

            if (dataForm.data.file) {
                if (
                    dataForm.data.file.type == "png" ||
                    dataForm.data.file.type == "jpg" ||
                    dataForm.data.file.type == "jpeg"
                ) {
                    showImage.value = true;
                    getShowImage.value = dataForm.data.file.path;
                } else if ($('input[name="file"]').length) {
                    let type = $('input[name="file"]')[0].files[0].type;
                    if (
                        type.includes("jpg") ||
                        type.includes("png") ||
                        type.includes("jpeg")
                    ) {
                        showImage.value = true;
                        getShowImage.value = URL.createObjectURL(
                            $('input[name="file"]')[0].files[0]
                        );
                    } else {
                        showImage.value = false;
                    }
                }
            } else {
                showImage.value = false;
            }

            if (amountIsGreaterThanCostAllServiceActive()) {
                showButtonPeriod.value = true;
            } else {
                showButtonPeriod.value = false;
            }
        });

        const amountIsGreaterThanCostAllServiceActive = () => {
            return dataForm.data.amount >= costAllService.value;
        };

        const initComponent = async (action) => {
            activeServiceExpiration.value = await getActiveServiceExpiration(
                props.idClient
            );

            costAllService.value = await getCostAllService(props.idClient);
            activePromisePayment.value = await getIsClientPromisePayment(
                props.idClient
            );
            promotions.value = await getPromotions(props.idClient);

            if (!tecnicos.value.length) {
                tecnicos.value = await getTecnicos();
            }

            action == `crear/${props.idClient}`
                ? await getfieldsJson("ClientPayment")
                : await getfieldsEdited("ClientPayment", props.action);

            await loadAvailablePeriods();

            if (activePromisePayment.value == 1) {
                fieldsJson.value.enabled_payment_promise.include = false;
            }
        };

        // Prepara fieldsJson antes de construir el Form:
        //  - Oculta del loop dinámico el payment_method_id nativo (Choices.js,
        //    roto) y el file (lo pintamos controlado por método). Siguen en
        //    originalData → viajan en el POST.
        //  - Inyecta las llaves de datos por método (include:false) para que
        //    Form.uploadFile las serialice aunque no existan en field_modules.
        const prepareDynamicFields = () => {
            if (fieldsJson.value.payment_method_id) {
                fieldsJson.value.payment_method_id.include = false;
            }
            if (fieldsJson.value.file) {
                fieldsJson.value.file.include = false;
            }
            EXTRA_FIELD_KEYS.forEach((k) => {
                if (!fieldsJson.value[k]) {
                    fieldsJson.value[k] = {
                        field: k,
                        type: "input-string",
                        include: false,
                        label: k,
                    };
                }
            });
        };

        const getfieldsEdited = async (model, action) => {
            let id = action.substr(7);
            fieldsJson.value = await requestEditedFieldsById(model, id);
            prepareDynamicFields();
            dataForm.data = new Form(fieldsJson.value);
            selectedMethodId.value = Number(dataForm.data.payment_method_id) || 1;
            onMethodChange();
            title.value = "Editar Gasto";
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            prepareDynamicFields();
            dataForm.data = new Form(fieldsJson.value);
            selectedMethodId.value = 1;
            onMethodChange();
            title.value = "Crear Gasto";
        };

        // Al cambiar de método: fija el método en el Form y limpia los campos
        // propios del método anterior (los que no pertenecen al método activo).
        const onMethodChange = () => {
            dataForm.data.payment_method_id = selectedMethodId.value;
            const active = activeMethodFields.value.map((f) => f.key);
            ALL_METHOD_KEYS.forEach((k) => {
                if (!active.includes(k)) dataForm.data[k] = null;
            });
        };

        // Guarda el archivo (comprobante/foto) en la llave 'file' que ya lee el
        // backend (clientCreatePayment → $request->file).
        const onMethodFileChange = (event) => {
            dataForm.data.file = event.target.files[0] || null;
        };

        const onSubmit = () => {

            if (dataForm.data.amount == 0){
                alert("Debe ingresar un monto mayor a 0");
                return;
            }

            if (dataForm.data.amount < costAllService.value) {
                if (!confirm("El monto ingresado es menor al costo de todos los servicios, ¿Desea continuar?")) {
                    return;
                }
            }

            disabledButton.value = true;
            dataForm.data
                .uploadFile(
                    `/cliente/billing/payment/${props.action}`,
                    title.value == "Crear Gasto" ? "reset" : "editar"
                )

                .then((response) => {
                    changeBalance.value = true;

                    toastr.success("Pago");
                    initComponent(props.action);
                    emit("cleanModal");
                })
                .catch((error) => {
                    const msg =
                        error?.error ||
                        error?.message ||
                        error?.response?.data?.message ||
                        "Ocurrió un error inesperado.";
                    toastr.error(msg);
                })
                .finally(() => {
                    // Reactiva el botón pase lo que pase para que el operador
                    // pueda reintentar si el AJAX falló (antes quedaba colgado).
                    disabledButton.value = false;
                });
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        const updateThisField = ({field, value}) => {
            dataForm.data[field] = value;
            disabledButton.value = false;
        };

        const clearError = ({field}) => {
            dataForm.data.errors.clear(field);
        };

        watch(loading, () => {
            if (loading.value) {
                showLoading();
            } else {
                hideLoading();
            }
        });

        watch(
            selectedPeriods,
            (newVal) => {
                updateThisField({
                    field: "payment_period",
                    value: newVal
                });
            },
            { deep: true }
        );

        const loadAvailablePeriods = async () => {
            const periods = await getAvailablePeriodsByClient(props.idClient);

            PERIODS.value = periods.map(p => ({
                value: p.period,
                pending_balance: p.pending_balance
            }));

            recalculatePeriods();
        };

        const recalculatePeriods = () => {
            selectedPeriods.value = [];
            if (costPerPeriod.value <= 0) {
              //retornar el period actual
              selectedPeriods.value.push(PERIODS.value[0].value);
              return;
            }
            let remainingAmount = Number(dataForm.data.amount) || 0;
            // 1️⃣ consumir periodos reales del backend
            for (let i = 0; i < PERIODS.value.length; i++) {
                const period = PERIODS.value[i];
                const cost = Number(period.pending_balance);

                if (remainingAmount <= 0) break;

                selectedPeriods.value.push(period.value);
                remainingAmount -= cost;
            }

            // 2️⃣ si queda dinero → generar periodos futuros
            let lastPeriod =
                selectedPeriods.value[selectedPeriods.value.length - 1];

            while (remainingAmount >= costPerPeriod.value) {
                lastPeriod = nextPeriod(lastPeriod);
                selectedPeriods.value.push(lastPeriod);
                remainingAmount -= costPerPeriod.value;
            }

            updateThisField({
                field: "payment_period",
                value: selectedPeriods.value
            });
        };

        watch(
            () => dataForm.data.amount,
            () => {
                recalculatePeriods();
            }
        );

        watch(
            PERIODS,
            () => {
                recalculatePeriods();
            }
        );

        return {
            fieldsJson,
            dataForm,
            selectedMethodId,
            paymentMethods,
            onMethodChange,
            activeMethodFields,
            tecnicos,
            onMethodFileChange,
            onSubmit,
            updateThisField,
            clearError,
            closeModal,
            title,
            activeServiceExpiration,
            showImage,
            getShowImage,
            changeDrift,
            disabledButton,
            costAllService,
            activePromisePayment,
            promotions,
            showButtonPeriod,
            PERIODS,
            selectedPeriods,
        };
    },
};
</script>

<style scoped></style>
