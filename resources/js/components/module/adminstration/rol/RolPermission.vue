<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
    >
        <div class="row p-2" v-if="tabs">
            <div class="col-md-3">
                <div
                    class="nav flex-column nav-pills"
                    id="v-pills-tab"
                    role="tablist"
                    aria-orientation="vertical"
                >
                    <a
                        v-for="tab in tabs"
                        :class="`nav-link mb-2 ${tab.active}`"
                        data-bs-toggle="pill"
                        :href="`#v-pills-${tab.ref}`"
                        role="tab"
                        :aria-controls="`v-pills-${tab.ref}`"
                    >
                        {{ tab.title }}
                    </a>
                </div>
            </div>
            <!-- end col -->
            <div class="col-md-9">
                <div
                    class="tab-content text-muted mt-4 mt-md-0"
                    id="v-pills-tabContent"
                >
                    <div
                        v-for="tab in tabs"
                        :class="`tab-pane fade ${
                            tab.active == 'active' ? 'show active' : ''
                        } row`"
                        :id="`v-pills-${tab.ref}`"
                        role="tabpanel"
                        :aria-labelledby="`v-pills-${tab.ref}-tab`"
                    >
                        <template v-for="val in fieldsJson[tab.ref]">
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
                    </div>
                </div>
            </div>
            <!--  end col -->
        </div>
        <div class="modal-footer">
            <a
                class="btn btn-secondary mr-3"
                href="javascript:void(0)"
                @click="closeModal"
            >
                Cerrar
            </a>

            <button
                class="btn btn-primary"
                type="submit"
                :disabled="dataForm.data.errors.any()"
            >
                Guardar
            </button>
        </div>
    </form>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";
import ComponentFormDefault from "./local-components/ComponentFormDefault";
import { requestPermissionForRole } from "./helper/request";
import Form from "../../../../helpers/Form";

export default {
    name: "RolPermission",
    components: { ComponentFormDefault },
    props: {
        rol: String,
    },
    setup(props, { emit }) {
        const fieldsJson = ref({});
        const dataForm = reactive({
            data: new Form({}),
        });
        const tabs = ref([]);

        const setTabs = () => {
            tabs.value = [
                {
                    ref: "dashboard",
                    active: "active",
                    title: "Dashboard",
                },
                {
                    ref: "plan",
                    active: "",
                    title: "Planes",
                },
                {
                    ref: "crm",
                    active: "",
                    title: "CRM",
                },
                {
                    ref: "client",
                    active: "",
                    title: "Clientes",
                },
                {
                    ref: "ticket",
                    active: "",
                    title: "Tickets",
                },
                {
                    ref: "map",
                    active: "",
                    title: "Mapas",
                },
                {
                    ref: "olts",
                    active: "",
                    title: "OLTs",
                },
                {
                    ref: "finance",
                    active: "",
                    title: "Finanzas",
                },
                {
                    ref: "router",
                    active: "",
                    title: "Router",
                },
                {
                    ref: "user",
                    active: "",
                    title: "Administración",
                },
                {
                    ref: "setting",
                    active: "",
                    title: "Configuración",
                },
                {
                    ref: "message",
                    active: "",
                    title: "Mensajes",
                },
            ];
        };

        onMounted(() => {
            setTabs();
            initComponent(props.rol);
        });

        watch(
            () => props.rol,
            (rol, rolBefore) => {
                initComponent(rol);
            }
        );

        const initComponent = async (rol) => {
            fieldsJson.value = await requestPermissionForRole(rol);
            dataForm.data = new Form(fieldsJson.value);

            let val;
            val = _.groupBy(fieldsJson.value, "partition");
            _.forEach(tabs.value, (v) => {
                val[v.ref] = _.mapKeys(val[v.ref], (v) => v.field);
            });
            fieldsJson.value = val;
        };

        const closeModal = () => {
            emit("close-modal", "permissionrole");
        };

        const onSubmit = () => {
            dataForm.data
                .submit(
                    "post",
                    `/administracion/rol/update/${props.rol}`,
                    "edit"
                )
                .then((response) => {
                    toastr.success("Permisos actualizados correctamente");
                    emit("close-modal", "permissionrole");
                });
        };

        const updateThisField = ({ field, value }) => {
            dataForm.data[field] = value;
        };

        const clearError = ({ field }) => {
            dataForm.data.errors.clear(field);
        };

        return {
            updateThisField,
            clearError,
            fieldsJson,
            dataForm,
            tabs,
            onSubmit,
            closeModal,
        };
    },
};
</script>

<style scoped></style>
