<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();

async function handleLogout(): Promise<void> {
    await authStore.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <header class="bg-gray-800 border-b border-gray-700 px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-white">Muzakily</h1>
            </div>

            <div class="flex items-center gap-4">
                <span v-if="authStore.user" class="text-gray-300">
                    {{ authStore.user.name }}
                </span>
                <button
                    @click="handleLogout"
                    class="px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                >
                    Logout
                </button>
            </div>
        </div>
    </header>
</template>
