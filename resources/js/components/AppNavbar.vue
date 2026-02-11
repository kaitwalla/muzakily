<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useLibraryStore } from '@/stores/library';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';

const router = useRouter();
const authStore = useAuthStore();
const libraryStore = useLibraryStore();

const isOpen = ref(false);

const userInitial = computed(() => {
    return authStore.user?.name?.charAt(0).toUpperCase() ?? '?';
});

function toggleDropdown(): void {
    isOpen.value = !isOpen.value;
}

function closeDropdown(): void {
    isOpen.value = false;
}

function navigateToSettings(): void {
    closeDropdown();
    router.push({ name: 'settings' });
}

async function handleScanLibrary(): Promise<void> {
    if (libraryStore.isScanning) return;
    try {
        await libraryStore.triggerScan();
    } catch {
        // Error handled in store
    }
}

async function handleLogout(): Promise<void> {
    closeDropdown();
    await authStore.logout();
    router.push({ name: 'login' });
}

function handleClickOutside(event: MouseEvent): void {
    const target = event.target as HTMLElement;
    if (!target.closest('.user-dropdown')) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    // Fetch initial scan status if admin
    if (authStore.isAdmin) {
        libraryStore.fetchStatus();
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    libraryStore.stopPolling();
});
</script>

<template>
    <header class="bg-surface-900 border-b border-surface-800 px-6 py-4 transition-colors duration-300">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-surface-50">Muzakily</h1>
            </div>

            <div class="flex items-center gap-4">
                <ThemeSwitcher />

                <!-- User Dropdown -->
                <div v-if="authStore.user" class="relative user-dropdown">
                    <button
                        @click.stop="toggleDropdown"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 text-surface-200 transition-colors"
                    >
                        <!-- Avatar or Initial -->
                        <div class="w-7 h-7 rounded-full overflow-hidden bg-surface-600 flex items-center justify-center">
                            <img
                                v-if="authStore.user.avatar_url"
                                :src="authStore.user.avatar_url"
                                :alt="authStore.user.name"
                                class="w-full h-full object-cover"
                            />
                            <span v-else class="text-sm font-medium text-surface-300">
                                {{ userInitial }}
                            </span>
                        </div>
                        <span class="hidden sm:block">{{ authStore.user.name }}</span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="w-4 h-4 transition-transform duration-200"
                            :class="{ 'rotate-180': isOpen }"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                        v-if="isOpen"
                        class="absolute right-0 mt-2 w-48 bg-surface-800 border border-surface-700 rounded-lg shadow-xl z-50 overflow-hidden"
                    >
                        <div class="py-1">
                            <!-- Settings -->
                            <button
                                @click="navigateToSettings"
                                class="w-full text-left px-4 py-2 text-sm text-surface-300 hover:bg-surface-700 hover:text-white transition-colors flex items-center gap-2"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-4 h-4"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                Settings
                            </button>

                            <!-- Scan Library (Admin only) -->
                            <button
                                v-if="authStore.isAdmin"
                                @click="handleScanLibrary"
                                :disabled="libraryStore.isScanning"
                                class="w-full text-left px-4 py-2 text-sm text-surface-300 hover:bg-surface-700 hover:text-white transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg
                                    v-if="libraryStore.isScanning"
                                    class="w-4 h-4 animate-spin"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-4 h-4"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
                                    />
                                </svg>
                                {{ libraryStore.isScanning ? 'Scanning...' : 'Scan Library' }}
                            </button>

                            <!-- Divider -->
                            <div class="border-t border-surface-700 my-1"></div>

                            <!-- Sign Out -->
                            <button
                                @click="handleLogout"
                                class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-surface-700 hover:text-red-300 transition-colors flex items-center gap-2"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-4 h-4"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"
                                    />
                                </svg>
                                Sign Out
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
