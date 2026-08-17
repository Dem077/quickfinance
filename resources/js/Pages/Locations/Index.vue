<script setup>
import CrudIndex from '../../Components/CrudIndex.vue';

defineProps({
    records: Object,
    filters: Object,
    can: Object,
});
</script>

<template>
    <CrudIndex
        title="Locations"
        route-prefix="app.locations"
        :columns="[{ key: 'name', label: 'Name' }]"
        :records="records"
        :filters="filters"
        :can="can"
    />
</template>
