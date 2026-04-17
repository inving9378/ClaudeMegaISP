<template>
    <div
        class="modal fade"
        id="modalbundleservice"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        aria-labelledby="staticBackdropLabel"
        tabindex="-1"
        aria-hidden="true"
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
                        <SelectComponent
                            v-if="
                                fieldsJson['init'] &&
                                fieldsJson['init']['bundle_id']
                            "
                            :key="fieldsJson['init']['bundle_id']"
                            :property="fieldsJson['init']['bundle_id']"
                            :errors="dataForm.data.errors"
                            @click="clearError({ field: 'bundle_id' })"
                            v-model="dataForm.data['bundle_id']"
                            @update-field="updateThisField"
                        />
                        <br />

                        <div class="card p-3" v-show="show">
                            <h4 class="card-title text-start">
                                Opciones del Servicio de Paquete
                            </h4>
                            <br />

                            <template
                                v-for="val in fieldsJson[
                                    'bundle_service_option'
                                ]"
                            >
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
                        </div>

                        <div class="card p-3" v-show="show">
                            <h4 class="card-title text-start">
                                Informacion de Contrato
                            </h4>
                            <br />
                            <template
                                v-for="val in fieldsJson[
                                    'contract_information'
                                ]"
                            >
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
                        </div>

                        <div class="card p-3" v-show="show">
                            <h4 class="card-title text-start">
                                Servicios de Internet
                            </h4>
                            <br />
                            <div v-for="service in internetService">
                                <div
                                    class="accordion"
                                    :id="`accordionExampleInternetService${service.id}`"
                                >
                                    <div class="accordion-item">
                                        <h2
                                            class="accordion-header"
                                            :id="`headingInternetService${service.id}`"
                                        >
                                            <button
                                                class="accordion-button fw-medium text-capitalize"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapseInternetService${service.id}`"
                                                aria-expanded="true"
                                                :aria-controls="`#collapseInternetService${service.id}`"
                                            >
                                                {{ service.title }}
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapseInternetService${service.id}`"
                                            class="accordion-collapse collapse show"
                                            :aria-labelledby="`headingInternetService${service.id}`"
                                            :data-bs-parent="`#accordionExampleInternetService${service.id}`"
                                        >
                                            <div class="accordion-body">
                                                <div class="text-muted">
                                                    <template
                                                        v-for="val in fieldsJson[
                                                            `internet_service_${service.id}`
                                                        ]"
                                                    >
                                                        <ComponentFormDefault
                                                            v-if="val.include"
                                                            :id="idClient"
                                                            :json="val"
                                                            :errors="
                                                                dataForm.data
                                                                    .errors
                                                            "
                                                            :key="val"
                                                            v-model="
                                                                dataForm.data[
                                                                    val.field
                                                                ]
                                                            "
                                                            @update-field="
                                                                updateThisField
                                                            "
                                                            @clear-error="
                                                                clearError
                                                            "
                                                        />
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <br />
                            </div>
                        </div>

                        <div class="card p-3" v-show="show">
                            <h4 class="card-title text-start">
                                Servicios de Voz
                            </h4>
                            <br />
                            <div v-for="service in vozService">
                                <div
                                    class="accordion"
                                    :id="`accordionExampleVozService${service.id}`"
                                >
                                    <div class="accordion-item">
                                        <h2
                                            class="accordion-header"
                                            :id="`headingVozService${service.id}`"
                                        >
                                            <button
                                                class="accordion-button fw-medium text-capitalize"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapseVozService${service.id}`"
                                                aria-expanded="true"
                                                :aria-controls="`#collapseVozService${service.id}`"
                                            >
                                                {{ service.title }}
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapseVozService${service.id}`"
                                            class="accordion-collapse collapse show"
                                            :aria-labelledby="`headingVozService${service.id}`"
                                            :data-bs-parent="`#accordionExampleVozService${service.id}`"
                                        >
                                            <div class="accordion-body">
                                                <div class="text-muted">
                                                    <template
                                                        v-for="val in fieldsJson[
                                                            `voz_service_${service.id}`
                                                        ]"
                                                    >
                                                        <ComponentFormDefault
                                                            v-if="val.include"
                                                            :id="idClient"
                                                            :json="val"
                                                            :errors="
                                                                dataForm.data
                                                                    .errors
                                                            "
                                                            :key="val"
                                                            v-model="
                                                                dataForm.data[
                                                                    val.field
                                                                ]
                                                            "
                                                            @update-field="
                                                                updateThisField
                                                            "
                                                            @clear-error="
                                                                clearError
                                                            "
                                                        />
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <br />
                            </div>
                        </div>

                        <div class="card p-3" v-show="show">
                            <h4 class="card-title text-start">
                                Servicios Personalizados
                            </h4>
                            <br />
                            <div v-for="service in customService">
                                <div
                                    class="accordion"
                                    :id="`accordionExampleCustomService${service.id}`"
                                >
                                    <div class="accordion-item">
                                        <h2
                                            class="accordion-header"
                                            :id="`headingCustomService${service.id}`"
                                        >
                                            <button
                                                class="accordion-button fw-medium text-capitalize"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapseCustomService${service.id}`"
                                                aria-expanded="true"
                                                :aria-controls="`#collapseCustomService${service.id}`"
                                            >
                                                {{ service.title }}
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapseCustomService${service.id}`"
                                            class="accordion-collapse collapse show"
                                            :aria-labelledby="`headingCustomService${service.id}`"
                                            :data-bs-parent="`#accordionExampleCustomService${service.id}`"
                                        >
                                            <div class="accordion-body">
                                                <div class="text-muted">
                                                    <div
                                                        v-for="val in fieldsJson[
                                                            `custom_service_${service.id}`
                                                        ]"
                                                    >
                                                        <ComponentFormDefault
                                                            v-if="val.include"
                                                            :id="idClient"
                                                            :json="val"
                                                            :idModel="idModel"
                                                            :errors="
                                                                dataForm.data
                                                                    .errors
                                                            "
                                                            :key="val"
                                                            v-model="
                                                                dataForm.data[
                                                                    val.field
                                                                ]
                                                            "
                                                            @update-field="
                                                                updateThisField
                                                            "
                                                            @clear-error="
                                                                clearError
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <br />
                            </div>
                        </div>

                        <div class="form-group text-center">
                            <a
                                class="btn btn-secondary me-3"
                                href="javascript:void(0)"
                                @click="closeModal"
                                >Cerrar</a
                            >

                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="dataForm.data.errors.any()"
                            >
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, ref, watch, onMounted } from "vue";

import Form from "../../../../../helpers/Form";
import { requestFieldsByModule } from "../../../../../helpers/Request";
import ComponentFormDefault from "../../../../ComponentFormDefault";
import SelectComponent from "../../../../../shared/SelectComponent";
import {
    getPlansForByBundleId,
    requestEditedBundlePlan,
} from "../../helpers/helper";
import { changeBalance } from "../../info/comun_variable";

export default {
    name: "ClientCrudBundleService",
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
        render: Number,
    },
    components: {
        ComponentFormDefault,
        SelectComponent,
    },
    emits: ["cleanModal"],
    setup(props, { emit }) {
        const disabledComponentInEdit = ref(false);
        const show = ref(false);
        const title = ref("Crear Servicio de Bundle");
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const idModel = ref(null);

        const internetService = ref({});
        const vozService = ref({});
        const customService = ref({});
        const oldIdBundle = ref(null);

        onMounted(() => {
            initComponent(props.action);
        });

        watch(
            () => props.action,
            (action, actionBefore) => {
                initComponent(action);
            }
        );

        watch(
            () => props.render,
            () => {
                initComponent(props.action);
            }
        );

        const initComponent = async (action) => {
            action == `crear/${props.idClient}`
                ? await getfieldsJson("ClientBundleService")
                : await getfieldsEditedBundleService(
                      "ClientBundleService",
                      props.action
                  );
        };

        const getfieldsEditedBundleService = async (model, action) => {
            let id = action.substr(7);
            idModel.value = id;
            let result = await requestEditedBundlePlan(id);

            internetService.value = result["planes_internet"];
            vozService.value = result["planes_voz"];
            customService.value = result["planes_custom"];
            fieldsJson.value = result["fields"];

            dataForm.data = new Form(fieldsJson.value);

            oldIdBundle.value = dataForm.data["bundle_id"];

            let val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["bundle_service_option"] = _.mapKeys(
                val["bundle_service_option"],
                (v) => v.field
            );
            val["contract_information"] = _.mapKeys(
                val["contract_information"],
                (v) => v.field
            );

            _.forEach(internetService.value, (service) => {
                val[`internet_service_${service.id}`] = _.mapKeys(
                    val[`internet_service_${service.id}`],
                    (v) => v.field
                );
            });

            _.forEach(vozService.value, (service) => {
                val[`voz_service_${service.id}`] = _.mapKeys(
                    val[`voz_service_${service.id}`],
                    (v) => v.field
                );
            });

            _.forEach(customService.value, (service) => {
                val[`custom_service${service.id}`] = _.mapKeys(
                    val[`custom_service${service.id}`],
                    (v) => v.field
                );
            });

            fieldsJson.value = val;
            show.value = true;
            title.value = "Editar Servicio de Bundle";
            disabledComponentInEdit.value = true;
        };

        const getfieldsJson = async (model) => {
            fieldsJson.value = await requestFieldsByModule(model);
            dataForm.data = new Form(fieldsJson.value);

            let val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["bundle_service_option"] = _.mapKeys(
                val["bundle_service_option"],
                (v) => v.field
            );
            val["contract_information"] = _.mapKeys(
                val["contract_information"],
                (v) => v.field
            );
            val["internet_service"] = _.mapKeys(
                val["internet_service"],
                (v) => v.field
            );
            val["voz_service"] = _.mapKeys(val["voz_service"], (v) => v.field);
            val["custom_service"] = _.mapKeys(
                val["custom_service"],
                (v) => v.field
            );
            fieldsJson.value = val;
            show.value = false;
            title.value = "Crear Servicio de Bundle";
            disabledComponentInEdit.value = false;
        };

        const updateThisField = ({ field, value }) => {
            (field == "bundle_id" &&
                dataForm.data["bundle_id"] != oldIdBundle.value) ||
            (field == "bundle_id" &&
                `${props.action}` == `crear/${props.idClient}`)
                ? requestPlanForBundle(value)
                : (dataForm.data[field] = value);
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        const requestPlanForBundle = async (id) => {
            let result = await getPlansForByBundleId(id.value, props.idClient);
            internetService.value = result["planes_internet"];
            vozService.value = result["planes_voz"];
            customService.value = result["planes_custom"];
            fieldsJson.value = result["fields"];

            dataForm.data = new Form(fieldsJson.value);

            let val = _.groupBy(fieldsJson.value, "partition");
            val["init"] = _.mapKeys(val["init"], (v) => v.field);
            val["bundle_service_option"] = _.mapKeys(
                val["bundle_service_option"],
                (v) => v.field
            );
            val["contract_information"] = _.mapKeys(
                val["contract_information"],
                (v) => v.field
            );
            _.forEach(internetService.value, (service) => {
                val[`internet_service_${service.id}`] = _.mapKeys(
                    val[`internet_service_${service.id}`],
                    (v) => v.field
                );
            });
            _.forEach(vozService.value, (service) => {
                val[`voz_service_${service.id}`] = _.mapKeys(
                    val[`voz_service_${service.id}`],
                    (v) => v.field
                );
            });
            _.forEach(customService.value, (service) => {
                val[`custom_service${service.id}`] = _.mapKeys(
                    val[`custom_service${service.id}`],
                    (v) => v.field
                );
            });

            fieldsJson.value = val;
            show.value = true;
        };

        const verifyIfIpv4IsEqual = (data) => {
            let arrayWithNameFieldAndIpv4 = [];
            const internetIpv4Keys = Object.keys(data).filter(
                (key) =>
                    /^plan_internet_ipv4_\d+$/.test(key) && data[key] !== null
            );
            const internetIpv4Values = internetIpv4Keys.map((key) => {
                let number = parseInt(key.match(/\d+$/)[0]);
                let internetServiceTitle =
                    internetService.value.find((obj) => obj.id === number)
                        .title ??
                    internetService.value.find((obj) => obj.id === number)
                        .service_name;
                const newKey = `${internetServiceTitle}`; // Generar la nueva clave Con el titulo del Servicio
                return {
                    [newKey]: data[key],
                };
            });

            const customIpv4Keys = Object.keys(data).filter(
                (key) =>
                    /^plan_custom_ipv4_\d+$/.test(key) && data[key] !== null
            );
            const customIpv4Values = customIpv4Keys.map((key) => {
                const number = key.match(/\d+$/)[0]; // Extraer el número final de la clave
                const newKey = data[`plan_custom_service_name_${number}`]; // Generar la nueva clave con el titulo del Servicio
                return {
                    [newKey]: data[key], // Asignar el valor a la nueva clave
                };
            });

            // Agregar los valores al array
            arrayWithNameFieldAndIpv4.push(
                ...internetIpv4Values,
                ...customIpv4Values
            );
            return ifValueIsEqualReturnAlertWithError(
                arrayWithNameFieldAndIpv4
            );
        };

        const ifValueIsEqualReturnAlertWithError = (array) => {
            for (let i = 0; i < array.length; i++) {
                const fieldName = Object.keys(array[i])[0];
                const fieldValue = array[i][fieldName];

                for (let j = i + 1; j < array.length; j++) {
                    const otherFieldName = Object.keys(array[j])[0];
                    const otherFieldValue = array[j][otherFieldName];

                    if (fieldValue === otherFieldValue) {
                        const errorMessage = `Existen Ip Estatica Iguales en los Planes: ${fieldName} y ${otherFieldName}`;
                        alert(errorMessage);
                        return true;
                    }
                }
            }

            return false;
        };

        const onSubmit = async () => {
            let errorIp = verifyIfIpv4IsEqual(dataForm.data);
            if (!errorIp) {
                dataForm.data
                    .submit(
                        "post",
                        `/cliente/clientbundleservice/${props.action}`,
                        props.action
                    )
                    .then((response) => {
                        changeBalance.value = true;
                        toastr.success(
                            `Cliente ${
                                props.idClient ? "actualizado" : "creado"
                            } satisfactoriamente`,
                            "Cliente con Servicio de Bundle"
                        );
                        redirectToCurrentURL();
                        emit("cleanModal");
                    });
            } else {
                toastr.error(
                    "Se encontraron errores en los datos. Hay IP iguales"
                );
            }
        };

        const redirectToCurrentURL = () => {
            let url = window.location.href;
            window.location.href = url + "#Servicios";
        };

        const closeModal = () => {
            emit("cleanModal");
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            updateThisField,
            clearError,
            show,
            closeModal,
            title,
            disabledComponentInEdit,
            internetService,
            vozService,
            customService,
            idModel,
        };
    },
};
</script>

<style scoped></style>
