<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import type { AxiosError } from 'axios';
import type { ApiError } from '@/types/api';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const error = ref('');

const isLoading = computed(() => authStore.loading);

async function handleSubmit(): Promise<void> {
    error.value = '';

    try {
        await authStore.login({
            email: email.value,
            password: password.value,
        });

        const redirect = route.query.redirect as string | undefined;
        // Only allow relative paths to prevent open redirect
        const safeRedirect = redirect && redirect.startsWith('/') && !redirect.startsWith('//')
            ? redirect
            : '/';
        router.push(safeRedirect);
    } catch (err) {
        const axiosError = err as AxiosError<ApiError>;
        error.value = axiosError.response?.data?.message ?? 'Login failed. Please try again.';
    }
}
</script>

<template>
    <div class="bg-surface-800 rounded-lg p-8 shadow-xl">
        <div class="text-center mb-8">
            <img src="/logo.png" alt="Muzakily" class="w-20 h-20 mx-auto mb-4 rounded-2xl" />
            <h1 class="text-3xl font-bold text-white">Muzakily</h1>
            <p class="text-surface-400 mt-2">Sign in to your account</p>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
            <div v-if="error" class="p-3 bg-red-500/10 border border-red-500/20 rounded-lg">
                <p class="text-sm text-red-400">{{ error }}</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-surface-300 mb-2">
                    Email
                </label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    required
                    autocomplete="email"
                    class="w-full px-4 py-3 bg-surface-700 border border-surface-600 rounded-lg text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    placeholder="you@example.com"
                />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-surface-300 mb-2">
                    Password
                </label>
                <input
                    id="password"
                    v-model="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full px-4 py-3 bg-surface-700 border border-surface-600 rounded-lg text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    placeholder="Enter your password"
                />
            </div>

            <button
                type="submit"
                :disabled="isLoading"
                class="w-full py-3 px-4 bg-green-500 hover:bg-green-600 disabled:bg-green-500/50 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-colors"
            >
                <span v-if="isLoading">Signing in...</span>
                <span v-else>Sign in</span>
            </button>
        </form>
    </div>
</template>
