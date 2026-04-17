<template>
    <div
        v-if="endNumber - 1 == 0 || endNumber - 1 == 255"
        class="p-4 text-center border mt-3 mb-3 ms-2 me-2"
        style="background: #f5f5f5; width: 99%"
    >
        {{ builNetwork(val.ip) }}
    </div>
    <div class="col-lg-3">
        <div :class="`spl-card`">
            <div
                class="spl-card-left"
                :class="getClassCardLeftFilterByPing(val)"
            >
                <div
                    class="spl-card-primary"
                    :class="getClassCardLeftFilterByPing(val)"
                >
                    <div class="spl-center-container spl-card-padding">
                        <div class="spl-center-content">
                            <div v-if="val.client_id">
                                <a :href="`/cliente/editar/${val.client_id}`">{{val.client_id}}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="spl-card-right"
                :class="getClassCardRightFilterByUsed(val)"
            >
                <div class="spl-center-container spl-card-text-center">
                    <div
                        class="spl-center-content"
                        title="10.205.2.1 [Customer]  GATEWAY"
                    >
                        <div
                            :class="`${
                                val.used_by != '---'
                                    ? 'spl-item-in-success'
                                    : 'spl-spl-item-in-gray-gray'
                            }`"
                        >
                            <i
                                :class="`${val.icon_for_host_category} fa-2x`"
                            ></i>
                            <div class="spl-card-item-center">
                                .{{ endNumber }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "IpCard",
    props: {
        endNumber: String,
        val: Object,
    },
    setup() {
        const getClassCardRightFilterByUsed = (val) => {
            return `${
                val.used_by != "--" ? "spl-card-success" : "spl-card-gray"
            }`;
        };

        const getClassCardLeftFilterByPing = (val) => {
            if (val.ping == "Desconocido") {
                return "spl-legend-primary";
            }

            if (val.ping == "OK") {
                return "spl-legend-success";
            }

            if (val.ping == "TimeOut") {
                return "spl-legend-warning";
            }

            if (val.ping == "Error") {
                return "spl-legend-danger";
            }
        };

        const builNetwork = (ip) => {
            return _.replace(`${ip}last`, ".1last", ".0");
        };
        return {
            builNetwork,
            getClassCardRightFilterByUsed,
            getClassCardLeftFilterByPing,
        };
    },
};
</script>

<style scoped></style>
