import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User, LoginRequest, RegisterRequest, UpdateProfileRequest, UserPreferences } from '@/types/auth';
import * as authApi from '@/api/auth';
import { getAuthToken } from '@/api/client';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const loading = ref(false);
    const initialized = ref(false);

    const isAuthenticated = computed(() => !!user.value);

    let initializePromise: Promise<void> | null = null;

    async function initialize(): Promise<void> {
        if (initialized.value) return;

        // Return existing promise to prevent concurrent calls
        if (initializePromise) return initializePromise;

        initializePromise = (async () => {
            const token = getAuthToken();
            if (token) {
                try {
                    loading.value = true;
                    user.value = await authApi.getCurrentUser();
                } catch {
                    user.value = null;
                } finally {
                    loading.value = false;
                }
            }
            initialized.value = true;
        })();

        return initializePromise;
    }

    async function login(credentials: LoginRequest): Promise<void> {
        loading.value = true;
        try {
            const response = await authApi.login(credentials);
            user.value = response.user;
        } finally {
            loading.value = false;
        }
    }

    async function register(data: RegisterRequest): Promise<void> {
        loading.value = true;
        try {
            const response = await authApi.register(data);
            user.value = response.user;
        } finally {
            loading.value = false;
        }
    }

    async function logout(): Promise<void> {
        loading.value = true;
        try {
            await authApi.logout();
            user.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function updateProfile(data: UpdateProfileRequest): Promise<void> {
        loading.value = true;
        try {
            user.value = await authApi.updateProfile(data);
        } finally {
            loading.value = false;
        }
    }

    async function updatePreferences(preferences: Partial<UserPreferences>): Promise<void> {
        await updateProfile({ preferences });
    }

    return {
        user,
        loading,
        initialized,
        isAuthenticated,
        initialize,
        login,
        register,
        logout,
        updateProfile,
        updatePreferences,
    };
});
