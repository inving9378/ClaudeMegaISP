<template>
    <div class="col-sm-12 reassign-container">
        <InputText
            :property="{
                field: 'option1',
                label: 'Opcion 1',
            }"
            :errors="dataForm.data.errors"
            @click="dataForm.data.errors.clear(`option1`)"
            @update-field="updateThisField"
            :modelValue="option1"
            :indexToGuests="1"
            :key="option1"
            @show-button-add="showAddButtonInItemsValue"
        />
    </div>

    <div class="" v-for="(item, index) in items" :key="index">
        <InputText
            :property="{
                field: `option${index + 2}`,
                label: `Option ${index + 2}`,
            }"
            :errors="dataForm.data.errors"
            @click="dataForm.data.errors.clear(`option${index + 2}`)"
            @update-field="updateThisField"
            :modelValue="item.option"
            :showDeleteButton="true"
            :indexToGuests="index + 2"
            @delete-button="removeItem"
            @show-button-add="showAddButtonInItemsValue"
            :key="index + 2"
        />
        <button
            type="button"
            @click="removeItem(index + 2)"
            class="btn btn-primary f-right"
        >
            Eliminar
        </button>
        <button
            type="button"
            v-on:click="addItems(index + 2)"
            class="btn btn-primary float-left"
            v-if="showAddButtonInItems && index === items.length - 1"
        >
            Agregar
        </button>
    </div>

    <div
        class="col-sm-12 reassign-container"
        v-if="showAddButtonInItems && items.length == 0"
    >
        <button
            type="button"
            v-on:click="addItems(1)"
            class="btn btn-primary float-left"
        >
            Agregar
        </button>
    </div>
</template>
<script>
import { onMounted, ref, watch, reactive } from "vue";
import { selectTransform } from "../../../../helpers/Transform";
import { filters } from "../../../../helpers/filters";
import InputText from "./InputText.vue";
import {
    updateThisField,
    clearError,
    dataForm,
} from "../../../../hook/crudHook";

export default {
    name: "OptionsSelect",
    props: {
        dataForm: Object,
    },
    components: {
        InputText,
    },
    emits: ["module-id", "updateField"],
    setup(props, { emit }) {
        const option1 = ref();
        const updateThisField = ({ field, value }) => {
            props.dataForm.data[field] = value;
        };
        const showAddButtonInItems = ref(false);
        const showAddButtonInItemsValue = (val) => {
            if (props.dataForm.data[`option${val}`]) {
                showAddButtonInItems.value = true;
            } else {
                showAddButtonInItems.value = false;
            }
        };

        const items = ref([]);
        const addItems = (index) => {
            if (props.dataForm.data[`option${index + 1}`]) {
                showAddButtonInItems.value = true;
            } else {
                showAddButtonInItems.value = false;
            }
            items.value.push({});
        };
        const removeItem = (index) => {
            $(`#option${index}`).remove();
            delete props.dataForm.data[`option${index}`];
            items.value.splice(index - 2, 1);
            if (props.dataForm.data[`option${index - 1}`]) {
                showAddButtonInItems.value = true;
            } else {
                showAddButtonInItems.value = false;
            }
        };

        return {
            updateThisField,
            option1,
            items,
            addItems,
            removeItem,
            showAddButtonInItems,
            showAddButtonInItemsValue,
        };
    },
};
</script>

<style scoped></style>
