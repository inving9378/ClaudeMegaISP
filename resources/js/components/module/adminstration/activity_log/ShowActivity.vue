<template>
    <div
        class="modal fade"
        id="modalShowActivityLog"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        aria-labelledby="staticBackdropLabel"
    >
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>

                <div class="modal-body">
                    <div class="row" v-if="data">
                        <div class="col-lg-6">
                            <span
                                ><b>Fecha De Creación: </b
                                >{{ data.created_at }}</span
                            >
                            <br />
                            <span><b>Modelo: </b>{{ data.subject_type }}</span>
                        </div>
                        <div class="col-lg-6">
                            <span
                                ><b>Descripción: </b
                                >{{ data.description }}</span
                            >
                            <br />
                            <span><b>Usuario: </b>{{ data.user_name }}</span>
                        </div>

                        <div class="col-lg-12 row mt-3">
                            <div class="col-lg-6">
                                <span><h1>Atributos Antiguos</h1></span>
                                <div
                                    v-for="attribute in oldAttributes"
                                    :key="Object.keys(attribute)[0]"
                                >
                                    <p
                                        v-for="(value, key) in attribute"
                                        :key="key"
                                        :class="{
                                            'highlight-difference':
                                                hasDifference(key, value),
                                        }"
                                    >
                                        <b>{{ key }}:</b> {{ value }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <span><h1>Atributos Nuevos</h1></span>
                                <div
                                    v-for="attribute in newAttributes"
                                    :key="Object.keys(attribute)[0]"
                                >
                                    <p
                                        v-for="(value, key) in attribute"
                                        :key="key"
                                        :class="{
                                            'highlight-difference':
                                                hasDifference(key, value),
                                        }"
                                    >
                                        <b>{{ key }}:</b> {{ value }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref, watch } from "vue";

export default {
    name: "ShowActivity",
    props: {},
    components: {},
    setup(props, { emit }) {
        const title = ref("Revisar Actividad");
        const data = ref({});
        const properties = ref();
        const oldAttributes = ref([]);
        const newAttributes = ref([]);

        onMounted(() => {
            $(document).on("click", `#show_activity_log`, function (e) {
                $(`#modalShowActivityLog`).modal("show");
                let dataValue = JSON.parse($(this).attr("data-data"));
                data.value = Object.assign({}, dataValue);
                let propertiesValue = JSON.parse(dataValue.properties);

                let dataOld = propertiesValue.old;
                let dataNew = propertiesValue.attributes;
                if (dataOld) {
                    oldAttributes.value = Object.entries(dataOld).map(
                        ([key, value]) => {
                            return { [key]: value };
                        }
                    );
                }

                if (dataNew) {
                    newAttributes.value = Object.entries(dataNew).map(
                        ([key, value]) => {
                            return { [key]: value };
                        }
                    );
                }
            });
        });

        function hasDifference(key, value) {
            const newAttribute = newAttributes.value.find((attr) => attr[key]);
            return newAttribute && newAttribute[key] !== value;
        }

        return {
            title,
            data,
            properties,
            oldAttributes,
            newAttributes,
            hasDifference
        };
    },
};
</script>

<style scoped></style>
