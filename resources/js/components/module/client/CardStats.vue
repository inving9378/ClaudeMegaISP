<template>
    <div class="col-sm-12 col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fa fa-user fa-fw"></i> {{ tableName }}
            </div>
            <div class="card-body">
                <div class="list-group">
                    <template v-for="(val, item_index) in dataStat">
                        <a class="list-group-item" :href="val.link !== '#' ? `/cliente/${val.link}` : null"
                            >{{ item_index
                            }}<span class="pull-right" :class="{ 'text-lightblue': val.link !== '#' }">{{ val.total }}</span></a
                        >
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "CardStats",
    props: {
        dataStat: Object,
        tableName: String,
    },
};
</script>

<style scoped></style>
