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
    <div class="max-w-2xl">
        <h1 class="text-3xl font-bold text-white mb-6">Settings</h1>

        <section class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Account</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Name</label>
                    <p class="text-white">{{ authStore.user?.name ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                    <p class="text-white">{{ authStore.user?.email ?? '-' }}</p>
                </div>
            </div>
        </section>

        <section class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Playback</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white font-medium">Audio Quality</p>
                        <p class="text-gray-400 text-sm">Stream in the highest quality available</p>
                    </div>
                    <select class="bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                        <option value="auto">Auto</option>
                        <option value="high">High</option>
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white font-medium">Crossfade</p>
                        <p class="text-gray-400 text-sm">Smooth transition between songs</p>
                    </div>
                    <select class="bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                        <option value="0">Off</option>
                        <option value="3">3 seconds</option>
                        <option value="5">5 seconds</option>
                        <option value="10">10 seconds</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Session</h2>
            <button
                @click="handleLogout"
                class="px-6 py-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg transition-colors"
            >
                Sign Out
            </button>
        </section>
    </div>
</template>
