<script setup>
import CrudIndex from '../../Components/CrudIndex.vue';
defineProps({ records: Object, filters: Object, can: Object });
</script>
<template>
    <CrudIndex
        title="Vendors"
        route-prefix="app.vendors"
        :columns="[
            { key: 'name', label: 'Name' },
            { key: 'bank', label: 'Bank' },
            { key: 'account_no', label: 'Account' },
            { key: 'mobile', label: 'Mobile' },
            { key: 'gst_no', label: 'GST' },
        ]"
        :records="records"
        :filters="filters"
        :can="can"
    />
</template>
