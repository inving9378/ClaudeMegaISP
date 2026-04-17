<template>
    <Select2Component
        :property="{
            field: `${property.field}`,
            label:  `${property.label ? property.label:'Router'}`,
            placeholder: 'Selecciona un router',
            search: {
                model: 'App\\Models\\Router',
                text: 'title',
                id: 'id',
            },
            module_id:property.module_id,
            class_col:property.class_col
        }"
        :key="modelValue"
        :errors="errors"
        :modelValue="modelValue"
        @update:modelValue="modelValue = $event"
        @change="clearError('router_id')"
        @update-field="updateThisField"
        :idModel="idModel"
    />

    <Select2Component
        v-if="routerId"
        :property="{
            field: setNameField('ipv4_assignment', `${property.field}`),
            label: 'Método de Asignación de IP',
            placeholder: 'Selecciona un Método de Asignación de IP',
            options: optionsIpv4Assignment,
            module_id:property.module_id,
            class_col:property.class_col
        }"
        :errors="errors"
        @change="
            clearError(setNameField('ipv4_assignment', `${property.field}`))
        "
        @update-field="updateThisField"
        :modelValue="ipv4Assignment"
        :key="routerId.value"
    />

    <Select2Component
        v-if="showDeppendIpStatic"
        :property="{
            field: setNameField('ipv4', `${property.field}`),
            label: 'Ip',
            placeholder: 'Selecciona un ip',
            search: searchIpv4,
            module_id:property.module_id,
            class_col:property.class_col
        }"
        :errors="errors"
        @change="clearError(setNameField('ipv4', `${property.field}`))"
        @update-field="updateThisField"
        :modelValue="ipv4"
        :idModel="idModel"
        :key="routerId.value"
    />

    <InputText
        v-if="showDeppendIpStatic"
        :property="{
            field: setNameField('additional_ipv4', `${property.field}`),
            label: 'Red adicional IPv4',
            placeholder: 'Introducir Red',
            module_id:property.module_id,
            class_col:property.class_col
        }"
        :errors="errors"
        @click="
            clearError(setNameField('additional_ipv4', `${property.field}`))
        "
        @update-field="updateThisField"
        :modelValue="additionalIpv4"
        :idModel="idModel"
        :key="routerId.value"
    />

    <Select2Component
        v-if="showDeppendIpPool"
        :property="{
            field: setNameField('ipv4_pool', `${property.field}`),
            label: 'Ip',
            placeholder: 'Selecciona un ip Pool',
            search: searchIpv4Pool,
            module_id:property.module_id,
            class_col:property.class_col
        }"
        :errors="errors"
        @change="clearError(setNameField('ipv4_pool', `${property.field}`))"
        @update-field="updateThisField"
        :modelValue="ipv4Pool"
        :idModel="idModel"
        :key="routerId.value"
    />
</template>

<script>
import { onMounted, ref, watch } from "vue";
import Select2Component from "./Select2Component.vue";
import InputText from "./InputText.vue";

export default {
    name: "RouterNetwork",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        property: Object,
        modelValue: String | Number,
        idModel: String | Number
    },
    components: {
        Select2Component,
        InputText,
    },
    emits: ["update-field", "change", "clear-error"],
    setup(props, { emit }) {
        const routerId = ref();

        const showDeppendIpStatic = ref(false);
        const showDeppendIpPool = ref(false);

        const ipv4Assignment = ref();
        const optionsIpv4Assignment = {
            "IP Estatica": "IP Estatica",
            "Pool IP": "Pool IP",
        };

        const ipv4 = ref();
        const optionsIpv4 = ref([]);

        const ipv4Pool = ref();
        const optionsIpv4Pool = ref([]);

        const additionalIpv4 = ref("");

        ///filters
        const searchIpv4 = ref({
            model: "App\\Models\\NetworkIp",
            text: "ip",
            id: "id",
            filter: [
                {
                    field_relation: "network",
                    field: "type_of_use",
                    value: "Estatico",
                },
                {
                    field_relation: "network",
                    field: "router_id",
                    value: routerId.value,
                },
                {
                    field: "used",
                    value: 0,
                }
            ],
        });

        const searchIpv4Pool = ref({
            model: "App\\Models\\Network",
            id: "id",
            text: "network",
            filter: [
                { field: "type_of_use", value: "Pool" },
                { field: "router_id", value: routerId.value },
                { field_relation: "network_ip", field: "used", value: 0 },

                // only when edited orWhere user by current Client
            ],
        });

        //////////////////////

        const edit = ref(false);

        onMounted(async () => {
            if (props.property.value) {
                routerId.value = props.property.value;
                edit.value = true;
            }
        });

        const updateThisField = (json) => {
            if (json.field === `${props.property.field}`) {
                routerId.value = json.value;
            }
            if (
                json.field ===
                setNameField("ipv4_assignment", `${props.property.field}`)
            ) {
                setTypeIpAssigned(json.value);
            }

            emit("update-field", json);
        };

        const clearError = (field) => {
            emit("clear-error", { field });
        };

        const setTypeIpAssigned = (v) => {
            if (v.value === "IP Estatica") {
                showDeppendIpPool.value = false;
                showDeppendIpStatic.value = true;
            }
            if (v.value === "Pool IP") {
                showDeppendIpStatic.value = false;
                showDeppendIpPool.value = true;
            }
        };

        const setNameField = (name, field) => {
            return field.replace("router_id", name);
        };

        watch(ipv4 , () => {
            if (ipv4.value) {
                ipv4Pool.value = null;
            }
        });

        watch(ipv4Pool , () => {
            if (ipv4Pool.value) {
                ipv4.value = null;
            }
        });

        watch(routerId, () => {
            if (edit.value == true) {
                searchIpv4.value.filter[1].value = routerId.value;
                searchIpv4Pool.value.filter[1].value = routerId.value;
                if (routerId.value == props.property.value) {

                    let res = _.filter(props.property.inputs_depend, v => {
                        let field = v.field;
                        return field.includes('ipv4_assignment');
                    });
                    if (res.length) {
                        ipv4Assignment.value = props.property.inputs_depend[res[0].field].value;
                    }

                    if (ipv4Assignment.value === "IP Estatica") {
                        showDeppendIpPool.value = false;
                        showDeppendIpStatic.value = true;

                        let res = _.filter(props.property.inputs_depend, v => {
                            let field = v.field;
                            return field.includes('ipv4') && !field.includes('ipv4_assignment');
                        });

                        if (res.length) {
                            ipv4.value = props.property.inputs_depend[res[0].field].value;
                        }

                        let conditionIncludeIvp4Selected = {
                            or_where: "id",
                            value: ipv4.value,
                        };

                        searchIpv4.value.filter.push(conditionIncludeIvp4Selected);
                    }

                    if (ipv4Assignment.value === "Pool IP") {
                        showDeppendIpStatic.value = false;
                        showDeppendIpPool.value = true;

                        let res = _.filter(props.property.inputs_depend, v => {
                            let field = v.field;
                            return field.includes('ipv4_pool');
                        });
                        if (res.length) {
                            ipv4Pool.value = props.property.inputs_depend[res[0].field].value;
                        }
                    }
                    edit.value = false;
                } else {
                    ipv4Assignment.value = null;
                    showDeppendIpPool.value = false;
                    showDeppendIpStatic.value = false;
                    searchIpv4.value.filter.splice(3, 1);
                }
            } else {
                searchIpv4.value.filter[1].value = routerId.value;
                searchIpv4Pool.value.filter[1].value = routerId.value;
            }
        });

        return {
            routerId,

            showDeppendIpStatic,
            showDeppendIpPool,

            ipv4Assignment,
            optionsIpv4Assignment,

            ipv4,
            optionsIpv4,

            ipv4Pool,
            optionsIpv4Pool,

            additionalIpv4,

            updateThisField,
            clearError,
            setNameField,
            setTypeIpAssigned,
            searchIpv4,
            searchIpv4Pool,
        };
    },
};
</script>

<style scoped></style>
