<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';

const authStore = useAuthStore();
const router = useRouter();

async function handleLogout(): Promise<void> {
    await authStore.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <header class="bg-surface-900 border-b border-surface-800 px-6 py-4 transition-colors duration-300">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-surface-50">Muzakily</h1>
            </div>

            <div class="flex items-center gap-4">
                <ThemeSwitcher />
                
                <span v-if="authStore.user" class="text-surface-300">
                    {{ authStore.user.name }}
                </span>
                <button
                    @click="handleLogout"
                    class="px-4 py-2 text-sm text-surface-300 hover:text-surface-50 hover:bg-surface-800 rounded-lg transition-colors"
                >
                    Logout
                </button>
            </div>
        </div>
    </header>
</template>
