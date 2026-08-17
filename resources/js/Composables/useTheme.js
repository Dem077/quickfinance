import { computed, ref, watch } from 'vue';

const STORAGE_KEY = 'finance-theme';

function canUseDom() {
    return typeof window !== 'undefined' && typeof document !== 'undefined';
}

function getStoredTheme() {
    if (!canUseDom()) {
        return 'light';
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    return stored === 'light' || stored === 'dark' ? stored : 'light';
}

function applyTheme(theme) {
    if (!canUseDom()) {
        return;
    }

    const root = document.documentElement;
    root.classList.toggle('dark', theme === 'dark');
    root.style.colorScheme = theme;

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', theme === 'dark' ? '#070b12' : '#f1f5f9');
    }
}

const theme = ref(getStoredTheme());

export function useTheme() {
    watch(
        theme,
        (value) => {
            if (canUseDom()) {
                localStorage.setItem(STORAGE_KEY, value);
            }
            applyTheme(value);
        },
        { immediate: true },
    );

    const isDark = computed(() => theme.value === 'dark');

    function toggleTheme() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    return {
        theme,
        isDark,
        toggleTheme,
    };
}
