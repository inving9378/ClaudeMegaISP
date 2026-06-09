<template>
    <form
        method="POST"
        @submit.prevent="onSubmit"
        @change="dataForm.data.errors.clear($event.target.name)"
        @keydown="dataForm.data.errors.clear($event.target.name)"
    >
        <div class="modal-body m-0 row">
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

            <!-- Equipos asignados al proyecto (solo en modo edición) -->
            <div v-if="id" class="col-12 mt-3">
                <label class="form-label fw-semibold">Equipos asignados</label>
                <div v-if="loadingTeams" class="text-muted small">
                    <i class="fa fa-spinner fa-spin me-1"></i> Cargando equipos...
                </div>
                <div v-else>
                    <select
                        v-model="teamIds"
                        class="form-select"
                        multiple
                        size="5"
                    >
                        <option v-for="t in allTeams" :key="t.id" :value="t.id">
                            {{ t.name }}
                        </option>
                    </select>
                    <small class="text-muted">
                        Mantén Ctrl (o Cmd) para seleccionar varios. Si dejas vacío,
                        cualquier usuario podrá ser asignado a tareas de este proyecto.
                    </small>
                </div>
            </div>
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
                :disabled="dataForm.data.errors.any() || saving"
            >
                <span v-if="saving">
                    <i class="fa fa-spinner fa-spin me-1"></i> Guardando...
                </span>
                <span v-else>Guardar</span>
            </button>
        </div>
    </form>
</template>

<script>
import { onMounted, ref, watch } from "vue";
import {
    getfieldsJson,
    getfieldsEdited,
    updateThisField,
    clearError,
    fieldsJson,
    dataForm,
} from "../../../../hook/crudHook";
import ComponentFormDefault from "../../../ComponentFormDefault";

export default {
    name: "ProjectCrud",
    props: {
        action: String,
    },
    components: {
        ComponentFormDefault,
    },
    setup(props, { emit }) {
        const id = ref(null);
        const teamIds = ref([]);
        const allTeams = ref([]);
        const loadingTeams = ref(false);
        const saving = ref(false);

        onMounted(() => {
            initComponent(props.action);
            loadAllTeams();
        });

        watch(
            () => props.action,
            (action) => {
                initComponent(action);
            }
        );

        const initComponent = async (action) => {
            let idItem = getIdByAction(action);
            if (action == "/scheduling/project/add") {
                id.value = null;
                teamIds.value = [];
                await getfieldsJson("Project");
            } else {
                id.value = idItem;
                await getfieldsEdited("Project", idItem);
                await loadProjectTeams(idItem);
            }
        };

        const getIdByAction = (action) => {
            return _.trimStart(action, "/scheduling/project/update/");
        };

        const loadAllTeams = async () => {
            try {
                const { data } = await axios.get("/get-options-select", {
                    params: {},
                });
                // Usar el endpoint de teams directamente
                const res = await axios.post("/get-options-select", {
                    model: "App\\Models\\Team",
                    id: "id",
                    text: "name",
                });
                allTeams.value = Object.entries(res.data).map(([id, name]) => ({
                    id: parseInt(id),
                    name,
                }));
            } catch (e) {
                console.error("ProjectCrud: error cargando teams", e);
            }
        };

        const loadProjectTeams = async (projectId) => {
            if (!projectId) return;
            loadingTeams.value = true;
            try {
                const { data } = await axios.get(
                    `/scheduling/project/${projectId}/teams`
                );
                teamIds.value = data.map((t) => t.id);
            } catch (e) {
                console.error("ProjectCrud: error cargando teams del proyecto", e);
            } finally {
                loadingTeams.value = false;
            }
        };

        const syncTeams = async (projectId) => {
            try {
                await axios.post(`/scheduling/project/${projectId}/teams`, {
                    team_ids: teamIds.value,
                });
            } catch (e) {
                console.error("ProjectCrud: error guardando teams", e);
                toastr.warning("Proyecto guardado pero hubo un error al guardar los equipos.");
            }
        };

        const closeModal = () => {
            emit("close-modal");
        };

        const onSubmit = async () => {
            saving.value = true;
            try {
                const response = await dataForm.data.submit(
                    "post",
                    `${props.action}`,
                    props.action
                );
                // Sincronizar teams si estamos en edición (id conocido)
                if (id.value) {
                    await syncTeams(id.value);
                }
                emit("close-modal");
            } finally {
                saving.value = false;
            }
        };

        return {
            fieldsJson,
            dataForm,
            onSubmit,
            clearError,
            updateThisField,
            closeModal,
            id,
            teamIds,
            allTeams,
            loadingTeams,
            saving,
        };
    },
};
</script>

<style scoped></style>
