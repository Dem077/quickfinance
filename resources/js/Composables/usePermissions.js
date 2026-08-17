import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.auth?.permissions ?? []);
    const roles = computed(() => page.props.auth?.roles ?? []);

    const can = (permission) => {
        if (roles.value.includes('super_admin')) {
            return true;
        }

        return permissions.value.includes(permission);
    };

    const canAny = (list) => list.some((permission) => can(permission));

    const hasRole = (role) => roles.value.includes(role);

    return { permissions, roles, can, canAny, hasRole };
}
