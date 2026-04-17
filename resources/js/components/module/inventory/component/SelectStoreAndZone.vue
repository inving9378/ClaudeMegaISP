<template>
    <div class="col-md-12">
        <div class="form-group">
            <div class="mb-3">
                <label for="state_country" class="form-label">Almacen</label>
                <select
                    class="form-select"
                    v-model="inventoryStore"
                    @change="getInventoryStore"
                >
                    <option disabled value="">Selecciona el Almacen</option>
                    <option
                        v-for="inventoryStore in inventoryStores"
                        :key="inventoryStore.id"
                        :value="inventoryStore.id"
                    >
                        {{ inventoryStore.name }}
                    </option>
                </select>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            <div class="mb-3">
                <label for="city_municipality" class="form-label">Zona</label>
                <select class="form-select" v-model="storeZone">
                    <option disabled value="">Selecciona la Zona</option>
                    <option
                        v-for="storeZone in storeZones"
                        :key="storeZone.id"
                        :value="storeZone.id"
                    >
                        {{ storeZone.name }}
                    </option>
                </select>
            </div>
        </div>
    </div>
</template>

<script>
import { watch, ref, reactive, onMounted } from "vue";
import { updateThisField } from "../../../../hook/crudHook";

export default {
    name: "SelectStoreAndZone",
    props: {
        store_zone_id: String | Number,
        inventory_store_id: String | Number,
    },
    emits: [""],
    components: {},
    setup(props, { emit }) {
        const inventoryStores = ref([]);
        const inventoryStore = ref(null);
        const storeZones = ref([]);
        const storeZone = ref(null);

        const getStores = async () => {
            let data = [];
            await axios["get"](`/inventory/inventory_store/get-all`).then(
                (response) => {
                    data = response.data;
                }
            );
            return data;
        };

        const getStoreZones = async (id) => {
            let data = {};
            await axios["get"](
                `/inventory/store_zone/get-store-zones-by-store/${id}`
            ).then((response) => {
                data = response.data;
                data.sort((a, b) => {
                    const regex = /^([A-Za-z]+)-(\d+)$/;

                    const [, letterA, numberA] = a.name.match(regex) || [];
                    const [, letterB, numberB] = b.name.match(regex) || [];

                    // Si las letras son iguales, ordena por número
                    if (letterA === letterB) {
                        return parseInt(numberA, 10) - parseInt(numberB, 10);
                    }

                    // Si no, ordena por letra alfabéticamente
                    return letterA.localeCompare(letterB);
                });
            });
            return data;
        };
        onMounted(async () => {
            inventoryStores.value = await getStores();
            inventoryStore.value = props.inventory_store_id;
            if (props.store_zone_id) {
                storeZone.value = props.store_zone_id;
            }
        });

        const getInventoryStore = async () => {
            const selectedState = inventoryStores.value.find(
                (s) => s.id === inventoryStore.value
            );
            storeZones.value = await getStoreZones(selectedState.id);
        };

        watch(inventoryStore, (value, oldValue) => {
            updateThisField({
                field: "inventory_store_id",
                value: inventoryStore.value,
            });

            if (value != oldValue) {
                updateThisField({
                    field: "store_zone_id",
                    value: null,
                });
            }
        });

        watch(storeZone, () => {
            updateThisField({
                field: "store_zone_id",
                value: storeZone.value,
            });
        });

        return {
            inventoryStores,
            storeZones,
            inventoryStore,
            storeZone,
            getInventoryStore,
            getStoreZones,
        };
    },
};
</script>

<style scoped></style>
