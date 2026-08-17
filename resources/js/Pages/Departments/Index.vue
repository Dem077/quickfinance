<script setup>
import CrudIndex from '../../Components/CrudIndex.vue';
defineProps({ records: Object, filters: Object, can: Object });
</script>
<template>
    <CrudIndex
        title="Departments"
        route-prefix="app.departments"
        :columns="[
            { key: 'name', label: 'Name' },
            { key: 'hod_name', label: 'HOD' },
            { key: 'petty_cash_float_amount', label: 'Petty cash float' },
        ]"
        :records="records"
        :filters="filters"
        :can="can"
    />
</template>
