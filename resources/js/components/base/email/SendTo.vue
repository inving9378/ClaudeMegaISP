<template>
    <div>
        <div class="input-group col-sm-12 col-md-6">
            <div class="input-group-prepend">
                <span
                    class="input-group-text bg-primary text-white"
                    :id="`${field}1`"
                    >{{ "To:" }}</span
                >
            </div>
            <input
                type="text"
                :name="field"
                :aria-describedby="`${field}1`"
                :class="{ 'form-control': true }"
                v-model="val"
            />
            <div class="input-group-append">
                <a
                    class="
                        btn btn-outline-primary
                        waves-effect waves-light
                        mr-1
                    "
                    data-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    Reenvío
                    <span class="caret"></span>
                </a>

                <div class="dropdown d-inline-block">
                    <div class="dropdown-menu dropdown-menu-right">
                        <!-- item-->
                        <a class="dropdown-item" href="">
                            <span class="align-middle">Cc</span>
                        </a>
                        <a class="dropdown-item" href="">
                            <span class="align-middle">Bcc</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, watch } from "vue";

export default {
    name: "SendTo",
    props: {
        field: String,
        modelValue: String,
    },
    setup(props, { emit }) {
        const val = ref(props.modelValue);

        watch(val, () => {
            emit("update-field", { value: val, field: props.field });
        });

        return {
            val,
        };
    },
};
</script>

<style scoped></style>
