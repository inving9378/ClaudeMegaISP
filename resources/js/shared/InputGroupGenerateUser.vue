<template>
    <div
        :class="`${
            property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'
        } row mb-2 ${errors.has(property.field) && 'has-danger'}`"
    >
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

            <input
                type="text"
                :id="property.field"
                :name="property.field"
                :placeholder="property.placeholder"
                :aria-label="property.placeholder"
                :aria-describedby="`${property.field}1`"
                :class="'form-control col-sm-12 col-md-9 ms-1'"
                v-model="val"              
                autocomplete="off"                
            />
            <div class="input-group-text" v-if="errors.has(property.field)">
                <span
                    class="cursor-pointer"
                    :id="`${property.field}1`"
                    @click="generateUserExist"
                    ><i class="fa fa-random"></i>
                </span>
            </div>
        </div>
        <span class="col-sm-12 col-md-3"></span>
        <div
            v-if="errors.has(property.field)"
            class="col-sm-9 pristine-error text-help"
        >
            {{ errors.get(property.field) }}
        </div>
    </div>
</template>

<script>
import { ref, watch, onMounted } from "vue";
import {
    requestGenerateUser,
    requestGenerateUserExist,
} from "../helpers/Request";

export default {
    name: "InputGroupGenerateUser",
    props: {
        errors: {
            type: Object,
            default: {},
        },
        id: String,
        property: Object,
        modelValue: null,
    },
    setup(props, { emit }) {
        const val = ref(null);
        const property = props.property;
        onMounted(async () => {
            let user = await requestGenerateUser(props.id);
            val.value = user;
        });

        const generateUserExist = async () => {
            val.value = await requestGenerateUserExist();
            emit("clear-error", "user");
        };

        watch(val, () => {
            emit("update-field", { value: val, field: props.property.field });
        });

        return {
            val,
            generateUserExist,
        };
    },
};
</script>

<style scoped></style>
