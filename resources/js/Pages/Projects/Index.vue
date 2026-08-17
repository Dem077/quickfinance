<script setup>
import CrudIndex from '../../Components/CrudIndex.vue';
defineProps({ records: Object, filters: Object, can: Object });
</script>
<template>
    <CrudIndex
        title="Projects"
        route-prefix="app.projects"
        :columns="[{ key: 'name', label: 'Name' }]"
        :records="records"
        :filters="filters"
        :can="can"
    />
</template>
