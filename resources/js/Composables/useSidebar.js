import { onMounted, ref, watch } from 'vue';

const STORAGE_KEY = 'finance-sidebar-collapsed';
const NAV_GROUP_STORAGE_PREFIX = 'finance-nav-group-';

function canUseLocalStorage() {
    return typeof window !== 'undefined' && typeof localStorage !== 'undefined';
}

const collapsed = ref(canUseLocalStorage() && localStorage.getItem(STORAGE_KEY) === '1');
const mobileOpen = ref(false);
const expandedNavGroups = ref({});

function readGroupExpanded(key) {
    if (!canUseLocalStorage()) {
        return true;
    }

    return localStorage.getItem(`${NAV_GROUP_STORAGE_PREFIX}${key}-expanded`) !== '0';
}

function persistGroupExpanded(key, value) {
    if (!canUseLocalStorage()) {
        return;
    }

    localStorage.setItem(`${NAV_GROUP_STORAGE_PREFIX}${key}-expanded`, value ? '1' : '0');
}

export function useSidebar() {
    onMounted(() => {
        if (!canUseLocalStorage()) {
            return;
        }

        collapsed.value = localStorage.getItem(STORAGE_KEY) === '1';
    });

    watch(collapsed, (value) => {
        if (!canUseLocalStorage()) {
            return;
        }

        localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    });

    function ensureNavGroup(key) {
        if (expandedNavGroups.value[key] === undefined) {
            expandedNavGroups.value[key] = readGroupExpanded(key);
        }
    }

    function setNavGroupExpanded(key, value) {
        expandedNavGroups.value[key] = value;
        persistGroupExpanded(key, value);
    }

    function toggleNavGroupExpanded(key) {
        ensureNavGroup(key);
        setNavGroupExpanded(key, !expandedNavGroups.value[key]);
    }

    function showNavGroupItems(key) {
        ensureNavGroup(key);
        return expandedNavGroups.value[key] || collapsed.value;
    }

    function toggleCollapsed() {
        collapsed.value = !collapsed.value;
    }

    function openMobile() {
        mobileOpen.value = true;
    }

    function closeMobile() {
        mobileOpen.value = false;
    }

    return {
        collapsed,
        mobileOpen,
        toggleCollapsed,
        setNavGroupExpanded,
        toggleNavGroupExpanded,
        showNavGroupItems,
        openMobile,
        closeMobile,
    };
}
