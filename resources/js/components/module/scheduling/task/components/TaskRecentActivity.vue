<template>
    <div class="col-12">
        <div class="card">
            <div class="card-body px-0">
                <div class="px-3" data-simplebar style="max-height: 352px">
                    <ul class="list-unstyled activity-wid mb-0">
                        <template v-if="data" v-for="(item, key) in data">
                            <li
                                v-if="
                                    (item.description == 'created' &&
                                        item.properties.attributes.created_by !=
                                            0 &&
                                        item.properties.attributes.created_by !=
                                            undefined) ||
                                    (item.description == 'updated' &&
                                        item.properties.attributes.updated_by !=
                                            0 &&
                                        item.properties.attributes.updated_by !=
                                            undefined)
                                "
                                :class="`activity-list ${
                                    data.length - 1 != key
                                        ? 'activity-border'
                                        : ''
                                }`"
                            >
                                <div class="activity-icon avatar-md">
                                    <span
                                        class="avatar-title bg-soft-warning text-warning rounded-circle"
                                    >
                                        <i
                                            class="bx bx-bitcoin font-size-24"
                                        ></i>
                                    </span>
                                </div>
                                <div class="timeline-list-item">
                                    <div class="d-flex">
                                        <div
                                            class="flex-grow-1 overflow-hidden me-4"
                                        >
                                            <h5 class="font-size-14 mb-1">
                                                {{
                                                    item.description ==
                                                    "updated"
                                                        ? "Actualizado por " +
                                                          item.user_name
                                                        : "Creado por " +
                                                          item.user_name
                                                }}
                                            </h5>
                                            <h5 class="font-size-14 mb-1">
                                                {{
                                                    item.description ==
                                                    "updated"
                                                        ? formatDateWithTime(item.updated_at)
                                                        : formatDateWithTime(item.created_at)
                                                }}
                                            </h5>
                                        </div>
                                        <div
                                            class="flex-shrink-0 text-end me-3"
                                        >
                                            <h6 class="mb-1">
                                                <i
                                                    class="fas fa-eye cursor-pointer"
                                                    @click="showInfo(item)"
                                                ></i>
                                            </h6>
                                        </div>

                                        <div
                                            class="flex-shrink-0 text-end"
                                        ></div>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
            <!-- end card body -->
        </div>
        <!-- end card -->
    </div>

    <div
        class="modal fade modal-center modal-activity"
        tabindex="-1"
        role="dialog"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Informacion</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <div class="modal-body modal-xl">
                    <div class="col-lg-12 row mt-3">
                        <div class="col-lg-12">
                            <span>
                                <b>Fecha De Creación: </b>
                                {{ textInformation.created_at }}
                            </span>
                        </div>
                        <div class="col-lg-12 mt-2">
                            <span>
                                <b>Usuario: </b>
                                {{ textInformation.user }}
                            </span>
                        </div>
                        <div class="col-lg-12 mt-2 mb-2">
                            <span>
                                <b>Descripción: </b>
                                {{ textInformation.text }}
                            </span>
                        </div>

                        <div class="col-lg-6">
                            <span><h3>Atributos Antiguos</h3></span>
                            <div
                                v-for="attribute in oldAttributes"
                                :key="Object.keys(attribute)[0]"
                            >
                                <p
                                    v-for="(value, key) in attribute"
                                    :key="key"
                                    :class="{
                                        'highlight-difference': hasDifference(
                                            key,
                                            value
                                        ),
                                    }"
                                >
                                    <b>{{ key }}:</b> {{ value }}
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <span><h3>Atributos Nuevos</h3></span>
                            <div
                                v-for="attribute in newAttributes"
                                :key="Object.keys(attribute)[0]"
                            >
                                <p
                                    v-for="(value, key) in attribute"
                                    :key="key"
                                    :class="{
                                        'highlight-difference': hasDifference(
                                            key,
                                            value
                                        ),
                                    }"
                                >
                                    <b>{{ key }}:</b> {{ value }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
</template>

<script>
import { onMounted, ref } from "vue";
import { formatDateWithTime } from "../../../../../helpers/formatDateService";

export default {
    name: "TaskRecentActivity",
    props: {
        data: Object,
    },
    setup(props, { emit }) {
        const showInfo = (item) => {
            showInformation(item);
            $(".modal-center.modal-activity").modal("show");
        };

        const textInformation = ref("");
        const oldAttributes = ref([]);
        const newAttributes = ref([]);
        const showInformation = (item) => {
            textInformation.value = item;
            let dataOld = item.properties.old;
            let dataNew = item.properties.attributes;
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
        };

        function hasDifference(key, value) {
            const newAttribute = newAttributes.value.find((attr) => attr[key]);
            return newAttribute && newAttribute[key] !== value;
        }

        return {
            showInfo,
            textInformation,
            oldAttributes,
            newAttributes,
            showInformation,
            hasDifference,
            formatDateWithTime
        };
    },
};
</script>

<style scoped></style>
