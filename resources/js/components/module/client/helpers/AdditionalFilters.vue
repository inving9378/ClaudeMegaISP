<template>
    <label for="select_filter" :class="`col-form-label text-md-end`">
        Filtros Adicionales
    </label>
    <div class="gap-2" style="width: 200px">
        <select
            :class="{ 'form-control': true }"
            name="select_filter"
            id="select_filter"
            v-model="val"
            style="width: 100%"
            multiple
        >
            <option
                v-for="option in options.val"
                :value="option.value"
                :text="option.label"
            ></option>
        </select>
    </div>
</template>

<script>
import toastr from "toastr";
import { onMounted, reactive, ref, watch } from "vue";
import ModalCentrado from "../../../../shared/ModalCentrado.vue";
import { showLoading, hideLoading } from "../../../../helpers/loading";
import {
    convertToBoostrapSelect,
    selectTransform,
} from "../../../../helpers/Transform";

export default {
    name: "AdditionalFilters",
    props: {},
    components: { ModalCentrado },
    emits: ["setFilter"],
    setup(props, { emit }) {
        const val = ref([]);

        const options = reactive({
            val: [
                {
                    label: "Con Servicios de Internet",
                    value: "internet_service",
                },
            ],
        });

        onMounted(async () => {
            $(document).ready(function () {
                convertToBoostrapSelect("select_filter", val, options.val);
            });
        });

        watch(val, () => {
            let v = proccessVal(val.value);
            emit("setFilter", v);
        });

        const proccessVal = (val) => {
            const objecReturn = ref(null);
            let relations = {
                internet_service: {
                    internet_service: "all",
                },
            };
            val.forEach((element) => {
                objecReturn.value = relations[element];
            });

            return objecReturn;
        };

        const showModalFilters = () => {
            $(`#modaleAdditionalFilters`).modal("show");
        };
        return {
            options,
            showModalFilters,
            val,
        };
    },
};
</script>

<style scoped></style>
