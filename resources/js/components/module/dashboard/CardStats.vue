<template>
    <div class="col-sm-12 col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fa fa-user fa-fw"></i> {{ tableName }}
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a
                        class="list-group-item"
                        v-for="(val, item_index) in dataStat"
                        :key="item_index"
                    >{{
                            item_index
                        }}<span class="pull-right">{{ val.total }}</span></a
                    >
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

<style scoped>
</style>
